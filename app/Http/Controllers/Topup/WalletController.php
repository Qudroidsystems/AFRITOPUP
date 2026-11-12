<?php

namespace App\Http\Controllers\Topup;

use App\Exceptions\TopupException;
use App\Http\Controllers\Controller;
use App\Models\WalletFunding;
use App\Services\Topup\FundingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/** The customer's wallet: balance, statement, funding with Paystack, transaction PIN. */
class WalletController extends Controller
{
    public function __construct(protected FundingService $funding)
    {
        $this->middleware('permission:Buy services');
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $wallet = $user->wallet();

        return view('topup.wallet.index', [
            'pagetitle' => 'My Wallet',
            'wallet'    => $wallet,
            'entries'   => $wallet->entries()->latest('id')->paginate(20),
            'fundings'  => $user->fundings()->latest('id')->limit(5)->get(),
            'hasPin'    => $user->hasTransactionPin(),
            'cfg'       => config('afritopup.funding'),
            'account'   => $user->virtualAccounts()->where('is_active', true)->first(),
        ]);
    }

    /** Create the customer's own account number for bank-transfer funding. */
    public function virtualAccount(Request $request)
    {
        try {
            $this->funding->virtualAccount($request->user());
        } catch (TopupException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Your account number is ready. Transfers to it fund your wallet automatically.');
    }

    public function fund(Request $request)
    {
        $request->validate(['amount' => 'required|numeric|min:1']);
        try {
            $r = $this->funding->start($request->user(), $request->input('amount'), route('topup.wallet.callback'));
        } catch (TopupException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->away($r['url']);
    }

    /** Paystack sends the customer back here. The webhook may already have credited the wallet. */
    public function callback(Request $request)
    {
        $ref = (string) ($request->query('reference') ?: $request->query('trxref'));
        $f = WalletFunding::where('reference', $ref)->where('user_id', $request->user()->id)->first();
        if (!$f) return redirect()->route('topup.wallet')->with('error', 'We could not find that payment.');

        $f = $this->funding->confirm($f->reference);

        return redirect()->route('topup.wallet')->with(...match ($f->status) {
            'successful' => ['success', \App\Support\Money::format($f->amount, $f->currency) . ' has been added to your wallet.'],
            'pending'    => ['info', 'We are waiting for Paystack to confirm your payment. Your wallet will be credited automatically.'],
            default      => ['error', 'The payment was not completed.'],
        });
    }

    public function fee(Request $request)
    {
        return response()->json(['fee' => (float) $this->funding->fee((float) $request->query('amount', 0))]);
    }

    /** Set or change the 4-digit transaction PIN (needs the account password). */
    public function pin(Request $request)
    {
        $request->validate([
            'password' => 'required|string',
            'pin'      => 'required|digits:4|confirmed',
        ], ['pin.digits' => 'The PIN must be exactly 4 digits.', 'pin.confirmed' => 'The two PINs do not match.']);

        $user = $request->user();
        if (!Hash::check($request->input('password'), $user->password)) return back()->withErrors(['password' => 'Your password is not correct.']);
        if (in_array($request->input('pin'), ['0000', '1234', '1111', '4321'], true)) return back()->withErrors(['pin' => 'Choose a PIN that is harder to guess.']);

        $user->forceFill(['transaction_pin' => $request->input('pin')])->save();

        return back()->with('success', 'Your transaction PIN has been saved.');
    }
}
