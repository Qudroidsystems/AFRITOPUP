<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Mail\StatementMail;
use App\Models\Transaction;
use App\Models\WalletFunding;
use App\Services\Receipts\ReceiptMailer;
use App\Services\Statements\StatementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

/** Website: wallet statements and PDF receipts for the signed-in customer. */
class StatementController extends Controller
{
    public function __construct(protected StatementService $statements) {}

    public function index(Request $request)
    {
        [$from, $to] = StatementService::range($request->query('from'), $request->query('to'));

        return view('account.statement', [
            'pagetitle' => 'Statement',
            's'         => $this->statements->build($request->user(), $from, $to),
            'presets'   => StatementService::presets(),
        ]);
    }

    public function download(Request $request)
    {
        [$from, $to] = StatementService::range($request->query('from'), $request->query('to'));
        $s = $this->statements->build($request->user(), $from, $to);

        if ($request->query('format') === 'csv') return $this->statements->csv($s);

        return response($this->statements->pdf($s), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $this->statements->filename($s, 'pdf') . '"',
        ]);
    }

    public function email(Request $request)
    {
        [$from, $to] = StatementService::range($request->input('from'), $request->input('to'));
        $user = $request->user();
        $s = $this->statements->build($user, $from, $to);
        Mail::to($user->email, $user->name)->send(new StatementMail($s, $this->statements->pdf($s), $this->statements->filename($s, 'pdf')));

        return back()->with('success', 'Your statement is on its way to ' . $user->email . '.');
    }

    /** PDF copy of a purchase receipt. */
    public function receipt(Request $request, string $reference)
    {
        $t = Transaction::where('reference', $reference)->firstOrFail();
        abort_unless($t->user_id === $request->user()->id || $request->user()->can('View transactions'), 404);

        return response(ReceiptMailer::purchasePdf($t), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="Receipt-' . $t->reference . '.pdf"']);
    }

    /** Turn e-mailed receipts on or off. */
    public function receiptsPreference(Request $request)
    {
        $on = $request->boolean('email_receipts');
        $request->user()->forceFill(['email_receipts' => $on])->save();

        return $request->expectsJson() ? response()->json(['email_receipts' => $on]) : back()->with('success', $on ? 'We will e-mail you a receipt for every payment.' : 'E-mail receipts turned off.');
    }
}
