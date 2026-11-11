<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\TopupException;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletFunding;
use App\Services\Topup\FundingService;
use Illuminate\Http\Request;

/** Wallet fundings, every customer's balance, manual credits / debits and wallet holds. */
class WalletAdminController extends Controller
{
    public function __construct(protected FundingService $funding)
    {
        $this->middleware('permission:View wallets|Manage wallets');
        $this->middleware('permission:Manage wallets')->only(['adjust', 'toggleLock', 'recheck']);
        $this->middleware('permission:Update user')->only(['account']);
    }

    public function fundings(Request $request)
    {
        $q = WalletFunding::with('user:id,name,email', 'creator:id,name')->latest('id')
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->query('gateway'), fn ($q, $g) => $q->where('gateway', $g))
            ->when($request->query('q'), fn ($q, $s) => $q->where(fn ($w) => $w->where('reference', 'like', "%$s%")->orWhereHas('user', fn ($u) => $u->where('email', 'like', "%$s%")->orWhere('name', 'like', "%$s%"))));

        return view('admin.wallets.fundings', ['pagetitle' => 'Wallet Fundings', 'fundings' => $q->paginate(30)->withQueryString()]);
    }

    public function wallets(Request $request)
    {
        $q = Wallet::with('user:id,name,email,user_type')->orderByDesc('balance')
            ->when($request->query('q'), fn ($q, $s) => $q->whereHas('user', fn ($u) => $u->where('email', 'like', "%$s%")->orWhere('name', 'like', "%$s%")->orWhere('phone_number', 'like', "%$s%")));

        return view('admin.wallets.index', [
            'pagetitle' => 'Wallets',
            'wallets'   => $q->paginate(30)->withQueryString(),
            'float'     => Wallet::selectRaw('currency, SUM(balance) as total, COUNT(*) as n')->groupBy('currency')->get(),
        ]);
    }

    public function show(User $user)
    {
        $wallet = $user->wallet();

        return view('admin.wallets.show', [
            'pagetitle' => 'Wallet — ' . $user->name,
            'u'         => $user->load('priceTier'),
            'wallet'    => $wallet,
            'entries'   => $wallet->entries()->with('creator:id,name')->latest('id')->paginate(25),
            'tiers'     => \App\Models\PriceTier::orderBy('position')->get(),
        ]);
    }

    public function adjust(Request $request, User $user)
    {
        $d = $request->validate(['type' => 'required|in:credit,debit', 'amount' => 'required|numeric|min:1|max:10000000', 'note' => 'required|string|max:200']);
        $wallet = $user->wallet();
        try {
            $d['type'] === 'credit'
                ? $this->funding->manualCredit($wallet, $d['amount'], $d['note'], $request->user())
                : $this->funding->manualDebit($wallet, $d['amount'], $d['note'], $request->user());
        } catch (TopupException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', ucfirst($d['type']) . ' recorded. New balance: ' . $wallet->refresh()->formatted_balance . '.');
    }

    public function toggleLock(User $user)
    {
        $wallet = $user->wallet();
        $wallet->forceFill(['is_locked' => !$wallet->is_locked])->save();

        return back()->with('success', $wallet->is_locked ? 'Wallet put on hold: no purchases until it is released.' : 'Wallet released.');
    }

    /** Account type (customer / agent / reseller) and price tier. Resellers also get the Reseller role (API access). */
    public function account(Request $request, User $user)
    {
        $d = $request->validate([
            'user_type'     => ['required', \Illuminate\Validation\Rule::in(['customer', 'agent', 'reseller'])],
            'price_tier_id' => 'nullable|exists:price_tiers,id',
        ]);
        if ($user->isStaff()) return back()->with('error', 'Staff accounts are managed under User Management.');

        $user->forceFill(['user_type' => $d['user_type'], 'price_tier_id' => $d['price_tier_id'] ?? null])->save();
        $role = ['customer' => 'Customer', 'agent' => 'Agent', 'reseller' => 'Reseller'][$d['user_type']];
        try {
            $user->removeRole('Customer'); $user->removeRole('Agent'); $user->removeRole('Reseller');
            $user->assignRole($role);
        } catch (\Throwable $e) {
            // Roles not seeded.
        }

        return back()->with('success', $user->name . ' is now ' . strtolower($role) . ' on ' . ($user->fresh('priceTier')->priceTier?->name ?? 'default') . ' prices.');
    }

    /** Ask Paystack again about a pending card top-up. */
    public function recheck(WalletFunding $funding)
    {
        $f = $this->funding->confirm($funding->reference);

        return back()->with($f->status === 'successful' ? 'success' : 'info', 'Paystack says: ' . $f->status . '.');
    }
}
