<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\TopupException;
use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Services\Topup\TopupService;
use Illuminate\Http\Request;

/** Every purchase on the platform: search, requery, refund, mark delivered, export. */
class TransactionController extends Controller
{
    public function __construct(protected TopupService $topup)
    {
        $this->middleware('permission:View transactions|Manage transactions');
        $this->middleware('permission:Manage transactions')->only(['requery', 'refund', 'deliver']);
    }

    protected function filtered(Request $request)
    {
        return Transaction::with('user:id,name,email', 'service:id,name,short_name,code,logo', 'product:id,name')
            ->status($request->query('status'))
            ->when($request->query('category'), fn ($q, $c) => $q->where('category', $c))
            ->when($request->query('provider'), fn ($q, $p) => $q->where('provider', $p))
            ->when($request->query('channel'), fn ($q, $c) => $q->where('channel', $c))
            ->when($request->query('from'), fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
            ->when($request->query('to'), fn ($q, $d) => $q->whereDate('created_at', '<=', $d))
            ->when($request->query('q'), function ($q, $s) {
                $q->where(fn ($w) => $w->where('reference', 'like', "%$s%")->orWhere('recipient', 'like', "%$s%")
                    ->orWhere('provider_reference', 'like', "%$s%")->orWhereHas('user', fn ($u) => $u->where('email', 'like', "%$s%")->orWhere('name', 'like', "%$s%")));
            });
    }

    public function index(Request $request)
    {
        $base = $this->filtered($request);
        $totals = (clone $base)->reorder()->selectRaw("COUNT(*) as n, SUM(CASE WHEN status = 'successful' THEN total ELSE 0 END) as sales, SUM(CASE WHEN status = 'successful' THEN total - COALESCE(cost, total) ELSE 0 END) as margin")->first();

        return view('admin.transactions.index', [
            'pagetitle'    => 'Transactions',
            'transactions' => $base->latest('id')->paginate(30)->withQueryString(),
            'totals'       => $totals,
            'stuck'        => Transaction::where('status', 'processing')->where('created_at', '<=', now()->subMinutes(15))->count(),
        ]);
    }

    public function show(Transaction $transaction)
    {
        $transaction->load('user', 'service', 'product', 'apiKey', 'wallet');
        $entries = \App\Models\WalletEntry::where('source_type', $transaction->getMorphClass())->where('source_id', $transaction->id)->orderBy('id')->get();

        return view('admin.transactions.show', ['pagetitle' => 'Transaction ' . $transaction->reference, 't' => $transaction, 'entries' => $entries]);
    }

    public function requery(Transaction $transaction)
    {
        $t = $this->topup->requery($transaction->load('service'));

        return back()->with('success', 'Checked with the provider: ' . $t->status_label . ($t->message ? ' — ' . $t->message : '') . '.');
    }

    public function refund(Request $request, Transaction $transaction)
    {
        $request->validate(['reason' => 'required|string|max:200']);
        try {
            $this->topup->refund($transaction->load('service', 'wallet'), $request->user(), $request->input('reason'));
        } catch (TopupException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Refunded to the customer\'s wallet.');
    }

    public function deliver(Request $request, Transaction $transaction)
    {
        $request->validate(['note' => 'nullable|string|max:200', 'token' => 'nullable|string|max:2000']);
        try {
            $this->topup->markDelivered($transaction, $request->user(), $request->input('note'), $request->input('token'));
        } catch (TopupException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Marked as delivered. The customer has been notified.');
    }

    public function export(Request $request)
    {
        $rows = $this->filtered($request)->latest('id')->limit(20000)->cursor();
        $name = 'transactions-' . now()->format('Ymd-His') . '.csv';

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Reference', 'Date', 'Customer', 'Email', 'Category', 'Service', 'Plan', 'Recipient', 'Amount', 'Discount', 'Fee', 'Total', 'Cost', 'Currency', 'Status', 'Provider', 'Provider ref', 'Channel']);
            foreach ($rows as $t) {
                fputcsv($out, [$t->reference, $t->created_at->format('Y-m-d H:i'), $t->user?->name, $t->user?->email, $t->category, $t->service?->name, $t->product?->name,
                    $t->recipient, $t->amount, $t->discount, $t->fee, $t->total, $t->cost, $t->currency, $t->status, $t->provider, $t->provider_reference, $t->channel]);
            }
            fclose($out);
        }, $name, ['Content-Type' => 'text/csv']);
    }
}
