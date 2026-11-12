<?php

use App\Mail\FundingReceiptMail;
use App\Mail\PurchaseReceiptMail;
use App\Mail\StatementMail;
use App\Models\Service;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Topup\TopupService;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->seed(DemoSeeder::class);
    $this->customer = User::where('email', 'customer@afritopup.test')->first();
    Mail::fake();
});

it('e-mails a receipt with a PDF copy when a purchase succeeds, once', function () {
    $t = app(TopupService::class)->purchase($this->customer, ['service_id' => Service::where('code', 'mtn')->value('id'), 'recipient' => '08031112222', 'amount' => 500], 'web');
    expect($t->status)->toBe(Transaction::SUCCESSFUL);

    Mail::assertSent(PurchaseReceiptMail::class, function ($m) use ($t) {
        $html = $m->render();
        return $m->txn->is($t) && $m->hasTo('customer@afritopup.test')
            && str_contains($html, $t->reference) && count($m->attachments()) === 1
            && str_contains($m->envelope()->subject, 'Receipt');
    });
    // The PDF really is a PDF.
    $pdf = \App\Services\Receipts\ReceiptMailer::purchasePdf($t);
    expect(substr($pdf, 0, 4))->toBe('%PDF');

    // Same status again: no second e-mail.
    app(\App\Services\Receipts\ReceiptMailer::class)->purchase($t->fresh());
    Mail::assertSent(PurchaseReceiptMail::class, 1);
});

it('respects the e-mail receipts switch, and resellers are off by default', function () {
    $this->actingAs($this->customer)->post(route('account.receipts'), ['email_receipts' => 0])->assertSessionHas('success');
    app(TopupService::class)->purchase($this->customer->fresh(), ['service_id' => Service::where('code', 'mtn')->value('id'), 'recipient' => '08031112222', 'amount' => 100], 'web');
    Mail::assertNotSent(PurchaseReceiptMail::class);

    expect(User::where('email', 'reseller@afritopup.test')->first()->wantsEmailReceipts())->toBeFalse()
        ->and(User::where('email', 'agent@afritopup.test')->first()->wantsEmailReceipts())->toBeTrue();
});

it('e-mails a receipt when the wallet is funded by transfer', function () {
    $account = \App\Models\VirtualAccount::create(['user_id' => $this->customer->id, 'provider' => 'paystack', 'account_number' => '9900112233', 'account_name' => 'AFT/Ada', 'bank_name' => 'Test Bank', 'currency' => 'NGN', 'is_active' => true]);
    event(new \App\Events\WalletFunded(\App\Models\WalletFunding::create([
        'reference' => 'TRF-ABC', 'user_id' => $this->customer->id, 'wallet_id' => $this->customer->wallet()->id, 'gateway' => 'transfer',
        'amount' => 5000, 'fee' => 50, 'charged' => 5050, 'currency' => 'NGN', 'status' => 'successful', 'paid_at' => now(),
    ])));
    Mail::assertSent(FundingReceiptMail::class, fn ($m) => $m->funding->reference === 'TRF-ABC' && str_contains($m->render(), '5,000'));
});

it('builds a statement that balances, as a page, PDF, CSV and e-mail', function () {
    $from = now()->subDays(2)->format('Y-m-d');
    $to = now()->format('Y-m-d');

    $page = $this->actingAs($this->customer)->get(route('account.statement', compact('from', 'to')))->assertOk()->assertSee('Closing balance');
    $s = app(\App\Services\Statements\StatementService::class)->build($this->customer, ...\App\Services\Statements\StatementService::range($from, $to));
    expect(round($s['opening'] + $s['credits'] - $s['debits'], 2))->toBe(round($s['closing'], 2))
        ->and($s['closing'])->toBe((float) $this->customer->wallet()->balance)
        ->and($s['entries']->count())->toBeGreaterThan(3);

    $pdf = $this->actingAs($this->customer)->get(route('account.statement.download', compact('from', 'to') + ['format' => 'pdf']))->assertOk();
    expect(substr($pdf->getContent(), 0, 4))->toBe('%PDF');
    $csv = $this->actingAs($this->customer)->get(route('account.statement.download', compact('from', 'to') + ['format' => 'csv']))->assertOk();
    expect($csv->streamedContent())->toContain('Opening balance')->toContain('Money in');

    $this->actingAs($this->customer)->post(route('account.statement.email'), compact('from', 'to'))->assertSessionHas('success');
    Mail::assertSent(StatementMail::class, fn ($m) => $m->hasTo('customer@afritopup.test') && count($m->attachments()) === 1);
});

it('gives the app statements and PDF receipts, only for the owner', function () {
    $token = $this->customer->createToken('t')->plainTextToken;
    $this->withToken($token)->getJson('/api/v1/statement')->assertOk()->assertJsonStructure(['data' => ['opening', 'closing', 'entries', 'presets', 'spend']]);
    $r = $this->withToken($token)->get('/api/v1/statement/download?format=pdf')->assertOk();
    expect($r->headers->get('content-type'))->toBe('application/pdf');
    $this->withToken($token)->postJson('/api/v1/statement/email', [])->assertOk();

    $mine = $this->customer->transactions()->first();
    $this->withToken($token)->get('/api/v1/transactions/' . $mine->reference . '/receipt')->assertOk();
    $theirs = User::where('email', 'agent@afritopup.test')->first()->transactions()->first();
    $this->withToken($token)->get('/api/v1/transactions/' . $theirs->reference . '/receipt')->assertNotFound();

    $this->withToken($token)->putJson('/api/v1/me/email-receipts', ['email_receipts' => false])->assertOk()->assertJsonPath('data.email_receipts', false);
});
