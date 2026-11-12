<?php

namespace App\Http\Controllers;

use App\Models\Service;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletFunding;
use App\Services\Topup\Providers\ProviderManager;
use App\Support\Modules;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Home. Buyers see their wallet, quick-buy tiles and recent purchases;
 * staff with "View dashboard" see the platform: sales, fundings, float,
 * failures and provider balances.
 */
class DashboardController extends Controller
{
    public function index(Request $request, ProviderManager $providers)
    {
        $user = $request->user();

        // Customers, agents and resellers get the customer-area home.
        if (!$user->isStaff()) return $this->customer($request);

        $overview = null;
        if ($user->can('View dashboard')) {
            $today = now()->startOfDay();
            $ok = Transaction::where('status', Transaction::SUCCESSFUL);

            $overview = [
                'salesToday'    => (clone $ok)->where('created_at', '>=', $today)->sum('total'),
                'countToday'    => (clone $ok)->where('created_at', '>=', $today)->count(),
                'salesMonth'    => (clone $ok)->where('created_at', '>=', now()->startOfMonth())->sum('total'),
                'marginMonth'   => (clone $ok)->where('created_at', '>=', now()->startOfMonth())->selectRaw('SUM(total - COALESCE(cost, total)) as m')->value('m') ?? 0,
                'fundedToday'   => WalletFunding::where('status', 'successful')->where('paid_at', '>=', $today)->sum('amount'),
                'float'         => Wallet::sum('balance'),
                'failedToday'   => Transaction::whereIn('status', [Transaction::FAILED])->where('created_at', '>=', $today)->count(),
                'processing'    => Transaction::where('status', Transaction::PROCESSING)->count(),
                'stuck'         => Transaction::where('status', Transaction::PROCESSING)->where('created_at', '<=', now()->subMinutes(15))->count(),
                'newCustomers'  => User::buyers()->where('created_at', '>=', $today)->count(),
                'buyers'        => User::buyers()->count(),
                'byCategory'    => Transaction::where('status', Transaction::SUCCESSFUL)->where('created_at', '>=', now()->subDays(30))
                    ->selectRaw('category, COUNT(*) as n, SUM(total) as total')->groupBy('category')->orderByDesc('total')->get(),
                'daily'         => Transaction::where('status', Transaction::SUCCESSFUL)->where('created_at', '>=', now()->subDays(13)->startOfDay())
                    ->get(['created_at', 'total'])->groupBy(fn ($t) => $t->created_at->format('Y-m-d'))->map->sum('total'),
                'recent'        => Transaction::with('user:id,name', 'service:id,name,short_name,color,category')->latest('id')->limit(8)->get(),
                'balances'      => Cache::remember('provider-balances', 300, fn () => collect(['vtpass', 'termii'])
                    ->mapWithKeys(fn ($k) => [$providers->get($k)->name() => $providers->get($k)->configured() ? rescue(fn () => $providers->get($k)->balance(), null, false) : null])->all()),
                'sandbox'       => Service::where('is_active', true)->where('provider', 'sandbox')->count(),
            ];
        }

        $buyer = null;
        if ($user->can('Buy services')) {
            $country = $user->country_code ?: config('afritopup.default_country', 'NG');
            $buyer = [
                'wallet'     => $user->wallet(),
                'categories' => collect(config('afritopup.categories'))->filter(fn ($c, $k) => Service::active()->in($country)->where('category', $k)->exists()),
                'recent'     => $user->transactions()->with('service', 'product')->latest('id')->limit(6)->get(),
                'spentMonth' => $user->transactions()->where('status', Transaction::SUCCESSFUL)->where('created_at', '>=', now()->startOfMonth())->sum('total'),
                'hasPin'     => $user->hasTransactionPin(),
            ];
        }

        return view('dashboard.index', [
            'pagetitle'     => 'Dashboard',
            'modules'       => Modules::all($user),
            'overview'      => $overview,
            'buyer'         => $buyer,
            'unread'        => Schema::hasTable('notifications') ? $user->unreadNotifications()->count() : 0,
        ]);
    }

    /** The customer area's home: wallet, quick pay, saved recipients, recent purchases, referrals. */
    protected function customer(Request $request)
    {
        $user = $request->user();
        $country = \App\Models\Country::byCode($user->country_code ?: config('afritopup.default_country', 'NG'));
        $currency = $country?->currency ?? 'NGN';
        $ok = $user->transactions()->where('status', Transaction::SUCCESSFUL);

        return view('account.dashboard', [
            'pagetitle'   => 'Home',
            'wallet'      => $user->wallet($currency),
            'earnings'    => $user->earningsWallet($currency),
            'account'     => $user->virtualAccounts()->where('is_active', true)->first(),
            'categories'  => collect(config('afritopup.categories'))->filter(fn ($c, $k) => Service::active()->in($country?->code ?? 'NG')->where('category', $k)->exists()),
            'recent'      => $user->transactions()->with('service')->latest('id')->limit(6)->get(),
            'saved'       => $user->beneficiaries()->with('service')->orderByDesc('last_used_at')->limit(6)->get(),
            'spentMonth'  => (clone $ok)->where('created_at', '>=', now()->startOfMonth())->sum('total'),
            'countMonth'  => (clone $ok)->where('created_at', '>=', now()->startOfMonth())->count(),
            'hasPin'      => $user->hasTransactionPin(),
            'referrals'   => $user->referrals()->count(),
            'code'        => $user->referralCode(),
            'currency'    => $currency,
            'country'     => $country,
        ]);
    }
}
