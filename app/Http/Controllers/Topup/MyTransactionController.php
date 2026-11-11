<?php

namespace App\Http\Controllers\Topup;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use Illuminate\Http\Request;

/** A customer's own purchases and receipts. */
class MyTransactionController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:Buy services');
    }

    public function index(Request $request)
    {
        $q = $request->user()->transactions()->with('service', 'product')->latest('id')
            ->status($request->query('status'))
            ->when($request->query('category'), fn ($q, $c) => $q->where('category', $c))
            ->when($request->query('q'), fn ($q, $s) => $q->where(fn ($w) => $w->where('reference', 'like', "%$s%")->orWhere('recipient', 'like', "%$s%")));

        return view('topup.transactions.index', ['pagetitle' => 'My Transactions', 'transactions' => $q->paginate(20)->withQueryString()]);
    }

    public function show(Request $request, string $reference)
    {
        $t = Transaction::with('service', 'product')->where('reference', $reference)->firstOrFail();
        abort_unless($t->user_id === $request->user()->id || $request->user()->can('View transactions'), 404);

        return view('topup.transactions.show', ['pagetitle' => 'Receipt ' . $t->reference, 't' => $t]);
    }
}
