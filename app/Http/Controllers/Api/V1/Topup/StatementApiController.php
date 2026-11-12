<?php

namespace App\Http\Controllers\Api\V1\Topup;

use App\Http\Controllers\Api\V1\Concerns\RespondsWithApi;
use App\Http\Controllers\Controller;
use App\Mail\StatementMail;
use App\Models\Transaction;
use App\Services\Receipts\ReceiptMailer;
use App\Services\Statements\StatementService;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

/**
 * App: statements and receipts.
 *   GET  statement?from=&to=              summary + latest entries
 *   GET  statement/download?from=&to=&format=pdf|csv
 *   POST statement/email {from, to}
 *   GET  transactions/{reference}/receipt  PDF
 *   PUT  me/email-receipts {email_receipts: bool}
 */
class StatementApiController extends Controller
{
    use RespondsWithApi;

    public function __construct(protected StatementService $statements) {}

    public function summary(Request $request)
    {
        [$from, $to] = StatementService::range($request->query('from'), $request->query('to'));
        $s = $this->statements->build($request->user(), $from, $to);
        $m = fn ($v) => Money::format($v, $s['currency']);

        return $this->ok([
            'from' => $from->format('Y-m-d'), 'to' => $to->format('Y-m-d'), 'currency' => $s['currency'],
            'opening' => $s['opening'], 'credits' => $s['credits'], 'debits' => $s['debits'], 'closing' => $s['closing'],
            'opening_label' => $m($s['opening']), 'credits_label' => $m($s['credits']), 'debits_label' => $m($s['debits']), 'closing_label' => $m($s['closing']),
            'count' => $s['entries']->count(),
            'spend' => $s['spend']->map(fn ($r) => $r + ['total_label' => $m($r['total'])])->all(),
            'entries' => $s['entries']->reverse()->take(100)->values()->map(fn ($e) => [
                'id' => $e->id, 'type' => $e->type, 'amount' => (float) $e->amount, 'amount_label' => $m($e->amount),
                'balance_after' => (float) $e->balance_after, 'balance_label' => $m($e->balance_after),
                'description' => $e->description, 'reference' => $e->reference, 'created_at' => $e->created_at->toIso8601String(),
            ])->all(),
            'presets' => collect(StatementService::presets())->map(fn ($p, $k) => ['key' => $k, 'label' => $p[0], 'from' => $p[1]->format('Y-m-d'), 'to' => $p[2]->format('Y-m-d')])->values()->all(),
            'email_receipts' => $request->user()->wantsEmailReceipts(),
        ]);
    }

    public function download(Request $request)
    {
        [$from, $to] = StatementService::range($request->query('from'), $request->query('to'));
        $s = $this->statements->build($request->user(), $from, $to);
        if ($request->query('format') === 'csv') return $this->statements->csv($s);

        return response($this->statements->pdf($s), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="' . $this->statements->filename($s, 'pdf') . '"']);
    }

    public function email(Request $request)
    {
        [$from, $to] = StatementService::range($request->input('from'), $request->input('to'));
        $user = $request->user();
        $s = $this->statements->build($user, $from, $to);
        Mail::to($user->email, $user->name)->send(new StatementMail($s, $this->statements->pdf($s), $this->statements->filename($s, 'pdf')));

        return $this->ok(null, 'Your statement is on its way to ' . $user->email . '.');
    }

    public function receipt(Request $request, string $reference)
    {
        $t = Transaction::where('reference', $reference)->where('user_id', $request->user()->id)->firstOrFail();

        return response(ReceiptMailer::purchasePdf($t), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="Receipt-' . $t->reference . '.pdf"']);
    }

    public function receipts(Request $request)
    {
        $request->validate(['email_receipts' => 'required|boolean']);
        $request->user()->forceFill(['email_receipts' => $request->boolean('email_receipts')])->save();

        return $this->ok(['email_receipts' => $request->user()->wantsEmailReceipts()], $request->boolean('email_receipts') ? 'E-mail receipts are on.' : 'E-mail receipts are off.');
    }
}
