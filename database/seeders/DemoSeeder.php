<?php

namespace Database\Seeders;

use App\Models\PriceTier;
use App\Models\Service;
use App\Models\User;
use App\Models\WalletFunding;
use App\Services\Topup\TopupService;
use App\Services\Topup\WalletService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Test accounts with money in the wallet, for trying the web portal and the app.
 *
 *   php artisan db:seed --class=DemoSeeder
 *
 * Every account: password "password", transaction PIN "1234".
 *   customer@afritopup.test  ₦50,000   (referred by the agent)
 *   agent@afritopup.test     ₦250,000  + ₦3,500 referral earnings
 *   reseller@afritopup.test  ₦1,000,000 (with an API key)
 *
 * Web admin (staff) accounts, same password:
 *   admin@afritopup.test     Super Administrator (everything)
 *   support@afritopup.test   Support Officer
 *   finance@afritopup.test   Finance Officer
 *
 * Purchases run against the sandbox provider, so nothing real is bought.
 * Safe to run again: existing accounts are topped back up to the amounts
 * above, never doubled. Refuses to run in production.
 */
class DemoSeeder extends Seeder
{
    public const PASSWORD = 'password';
    public const PIN = '1234';

    public const ACCOUNTS = [
        // email, name, type, phone, wallet (NGN)
        ['customer@afritopup.test', 'Ada Customer', User::TYPE_CUSTOMER, '08030000001', 50000],
        ['agent@afritopup.test', 'Tunde Agent', User::TYPE_AGENT, '08030000002', 250000],
        ['reseller@afritopup.test', 'Bola Reseller', User::TYPE_RESELLER, '08030000003', 1000000],
    ];

    /** email, name, role, job title */
    public const STAFF = [
        ['admin@afritopup.test', 'Demo Admin', 'Super Administrator', 'Platform administrator'],
        ['support@afritopup.test', 'Demo Support', 'Support Officer', 'Customer support'],
        ['finance@afritopup.test', 'Demo Finance', 'Finance Officer', 'Finance & reconciliation'],
    ];

    public const ROLE_FOR = [User::TYPE_CUSTOMER => 'Customer', User::TYPE_AGENT => 'Agent', User::TYPE_RESELLER => 'Reseller'];

    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command?->error('DemoSeeder does not run in production.');

            return;
        }

        $users = [];

        foreach (self::ACCOUNTS as [$email, $name, $type, $phone, $balance]) {
            $user = User::firstOrNew(['email' => $email]);
            $fresh = !$user->exists;
            $user->fill([
                'name' => $name, 'username' => Str::before($email, '@'), 'phone_number' => $phone, 'user_type' => $type,
                'country_code' => 'NG', 'business_name' => $type === User::TYPE_CUSTOMER ? null : explode(' ', $name)[0] . ' Ventures',
                'price_tier_id' => PriceTier::where('code', $type)->value('id'),
            ]);
            $user->forceFill([
                'password' => Hash::make(self::PASSWORD), 'transaction_pin' => self::PIN,
                'email_verified_at' => $user->email_verified_at ?? now(), 'must_change_password' => false, 'is_disabled' => false,
                'kyc_level' => $type === User::TYPE_CUSTOMER ? 1 : 2,
            ])->save();
            $user->referralCode();
            $user->syncRoles([self::ROLE_FOR[$type]]);   // "Buy services" etc. on the web portal
            $users[$type] = $user;

            $this->topUp($user, $balance);
            $this->command?->info(($fresh ? 'Created ' : 'Refreshed ') . $email);
        }

        foreach (self::STAFF as [$email, $name, $role, $title]) {
            $staff = User::firstOrNew(['email' => $email]);
            $staff->fill(['name' => $name, 'username' => Str::before($email, '@') . '-demo', 'user_type' => User::TYPE_STAFF,
                'job_title' => $title, 'country_code' => 'NG']);
            $staff->forceFill(['password' => Hash::make(self::PASSWORD), 'email_verified_at' => $staff->email_verified_at ?? now(),
                'must_change_password' => false, 'is_disabled' => false])->save();
            $staff->syncRoles([$role]);
            $this->command?->info("Staff {$email} ({$role})");
        }

        // The customer was referred by the agent.
        if (!$users[User::TYPE_CUSTOMER]->referred_by) {
            $users[User::TYPE_CUSTOMER]->forceFill(['referred_by' => $users[User::TYPE_AGENT]->id])->save();
        }

        // A little earnings balance for the agent, so "move to wallet" can be tried.
        $earnings = $users[User::TYPE_AGENT]->earningsWallet('NGN');
        if ((float) $earnings->balance < 3500) {
            app(WalletService::class)->credit($earnings, 3500 - (float) $earnings->balance, 'DEMO-EARN-' . strtoupper(Str::random(8)), 'Demo referral earnings');
        }

        // Reseller API key.
        if (!\App\Models\ApiKey::where('user_id', $users[User::TYPE_RESELLER]->id)->exists()) {
            [, $plain] = \App\Models\ApiKey::issue($users[User::TYPE_RESELLER], 'Demo key');
            $this->command?->warn("Reseller API key (shown once): {$plain}");
        }

        $this->history($users);

        // Back to the set balances after the demo purchases.
        foreach (self::ACCOUNTS as [$email, , $type, , $balance]) {
            $this->topUp($users[$type], $balance);
            $this->command?->line(sprintf('  %-26s wallet ₦%s', $email, number_format((float) $users[$type]->wallet('NGN')->balance, 2)));
        }

        $this->command?->newLine();
        $this->command?->info('Sign in with any account above. Password: ' . self::PASSWORD . ' · PIN: ' . self::PIN);
        $this->command?->info('Web admin: admin@afritopup.test / ' . self::PASSWORD);
    }

    /** Credit the NGN wallet up to $balance (a manual credit, recorded like an admin would). */
    protected function topUp(User $user, float $balance): void
    {
        $wallet = $user->wallet('NGN');
        $short = round($balance - (float) $wallet->balance, 2);
        if ($short <= 0) return;

        $funding = WalletFunding::create([
            'reference' => 'DEMO-' . strtoupper(Str::random(10)), 'user_id' => $user->id, 'wallet_id' => $wallet->id, 'gateway' => 'manual',
            'amount' => $short, 'fee' => 0, 'charged' => $short, 'currency' => 'NGN', 'status' => 'successful',
            'channel' => 'web', 'note' => 'Demo balance', 'paid_at' => now(),
        ]);
        app(WalletService::class)->credit($wallet, $short, $funding->reference, 'Demo balance', $funding);
    }

    /** Some purchases (sandbox) so the dashboard, history and receipts aren't empty. Only on the first run. */
    protected function history(array $users): void
    {
        $topup = app(TopupService::class);
        $code = fn (string $c) => Service::where('country_code', 'NG')->where('code', $c)->value('id');

        $orders = [
            User::TYPE_CUSTOMER => [
                ['service_id' => $code('mtn'), 'recipient' => '08031234567', 'amount' => 1000],
                ['service_id' => $code('mtn-data'), 'recipient' => '08031234567', 'product_code' => 'mtn-1gb-30d'],
                ['service_id' => $code('airtel'), 'recipient' => '08021234567', 'amount' => 500],
                ['service_id' => $code('dstv'), 'recipient' => '7012345678', 'product_code' => 'dstv-compact', 'phone' => '08031234567'],
                ['service_id' => $code('ikeja-electric'), 'recipient' => '45012345678', 'amount' => 5000, 'phone' => '08031234567'],
            ],
            User::TYPE_AGENT => [
                ['service_id' => $code('glo'), 'recipient' => '08051234567', 'amount' => 2000],
                ['service_id' => $code('mtn'), 'recipient' => '08061234567', 'amount' => 5000],
                ['service_id' => $code('9mobile'), 'recipient' => '08091234567', 'amount' => 1000],
                ['service_id' => $code('gotv'), 'recipient' => '2012345678', 'product_code' => null, 'phone' => '08031234567'],
            ],
            User::TYPE_RESELLER => [
                ['service_id' => $code('mtn'), 'recipient' => '08031112222', 'amount' => 10000],
                ['service_id' => $code('airtel-data'), 'recipient' => '08021112222', 'product_code' => null],
            ],
        ];

        foreach ($orders as $type => $list) {
            $user = $users[$type];
            if ($user->transactions()->exists()) continue;

            foreach ($list as $order) {
                if (!$order['service_id']) continue;
                // Pick the first plan where none was named.
                if (array_key_exists('product_code', $order) && !$order['product_code']) {
                    $order['product_code'] = Service::find($order['service_id'])?->products()->where('is_active', true)->orderBy('position')->value('code');
                    if (!$order['product_code']) continue;
                }
                try {
                    $topup->purchase($user, array_filter($order), 'web');
                } catch (\Throwable $e) {
                    $this->command?->warn("Skipped a demo purchase for {$user->email}: {$e->getMessage()}");
                }
            }
        }
    }
}
