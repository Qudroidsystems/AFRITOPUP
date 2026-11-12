<?php

namespace App\Http\Controllers\Api\V1\Topup;

use App\Exceptions\TopupException;
use App\Http\Controllers\Api\V1\ApiController;
use App\Models\WalletFunding;
use App\Services\Topup\FundingService;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/** The app's wallet: balance and statement, Paystack funding, transaction PIN. */
class WalletApiController extends ApiController
{
    public function __construct(protected FundingService $funding)
    {
        $this->middleware('permission:Buy services');
    }

    public function show(Request $request)
    {
        $user = $request->user();
        $w = $user->wallet();

        return $this->ok([
            'balance'         => (float) $w->balance,
            'balance_label'   => $w->formatted_balance,
            'currency'        => $w->currency,
            'is_locked'       => $w->is_locked,
            'has_pin'         => $user->hasTransactionPin(),
            'funding'         => config('afritopup.funding'),
            'referral_code'   => $user->referral_code,
        ]);
    }

    public function statement(Request $request)
    {
        $w = $request->user()->wallet();
        $p = $w->entries()->latest('id')->paginate(min(50, (int) $request->query('per_page', 20)));

        return $this->paged($p, fn ($e) => [
            'id' => $e->id, 'type' => $e->type, 'amount' => (float) $e->amount, 'amount_label' => Money::format($e->amount, $w->currency),
            'balance_after' => (float) $e->balance_after, 'description' => $e->description, 'reference' => $e->reference,
            'created_at' => $e->created_at->toIso8601String(),
        ]);
    }

    /** Start a Paystack top-up. The app opens authorization_url in a web view and closes it on the app-return URL. */
    public function fund(Request $request)
    {
        $request->validate(['amount' => 'required|numeric|min:1']);
        try {
            $r = $this->funding->start($request->user(), $request->input('amount'), route('topup.fund.app-return'), 'app');
        } catch (TopupException $e) {
            return $this->fail($e->getMessage(), $e->status, $e->reason);
        }

        return $this->ok([
            'reference'         => $r['funding']->reference,
            'authorization_url' => $r['url'],
            'return_url'        => route('topup.fund.app-return'),
            'amount'            => (float) $r['funding']->amount,
            'fee'               => (float) $r['funding']->fee,
            'charged'           => (float) $r['funding']->charged,
        ], null, 201);
    }

    /** After the web view closes: ask Paystack and credit the wallet if paid. */
    public function fundStatus(Request $request, string $reference)
    {
        $f = WalletFunding::where('reference', $reference)->where('user_id', $request->user()->id)->firstOrFail();
        $f = $this->funding->confirm($f->reference);

        return $this->ok(['reference' => $f->reference, 'status' => $f->status, 'amount' => (float) $f->amount,
            'balance' => (float) $f->wallet->fresh()->balance, 'balance_label' => $f->wallet->fresh()->formatted_balance]);
    }

    /** The customer's own account number (null until they ask for one). */
    public function virtualAccount(Request $request)
    {
        $va = $request->user()->virtualAccounts()->where('is_active', true)->first();

        return $this->ok($va ? $va->present() + ['fee' => $this->feeLine()] : null);
    }

    public function createVirtualAccount(Request $request)
    {
        try {
            $va = $this->funding->virtualAccount($request->user());
        } catch (TopupException $e) {
            return $this->fail($e->getMessage(), $e->status, $e->reason);
        }

        return $this->ok($va->present() + ['fee' => $this->feeLine()], 'Your account number is ready.', 201);
    }

    protected function feeLine(): string
    {
        $c = (array) config('afritopup.funding');
        if ((float) ($c['transfer_fee_percent'] ?? 0) <= 0 && (float) ($c['transfer_fee_flat'] ?? 0) <= 0) return 'No fee';
        $pct = rtrim(rtrim(number_format((float) $c['transfer_fee_percent'], 2), '0'), '.');

        return $pct . '% per transfer' . ((float) ($c['transfer_fee_cap'] ?? 0) > 0 ? ', at most ' . Money::format($c['transfer_fee_cap']) : '');
    }

    public function fee(Request $request)
    {
        return $this->ok(['fee' => (float) $this->funding->fee((float) $request->query('amount', 0))]);
    }

    public function pin(Request $request)
    {
        $request->validate(['password' => 'required|string', 'pin' => 'required|digits:4']);
        $user = $request->user();
        if (!Hash::check($request->input('password'), $user->password)) return $this->fail('Your password is not correct.', 422, 'wrong_password', ['password' => ['Your password is not correct.']]);
        if (in_array($request->input('pin'), ['0000', '1234', '1111', '4321'], true)) return $this->fail('Choose a PIN that is harder to guess.', 422, 'weak_pin');
        $user->forceFill(['transaction_pin' => $request->input('pin')])->save();

        return $this->ok(['has_pin' => true], 'Transaction PIN saved.');
    }
}
