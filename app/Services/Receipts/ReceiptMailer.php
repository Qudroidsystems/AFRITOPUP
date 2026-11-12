<?php

namespace App\Services\Receipts;

use App\Mail\FundingReceiptMail;
use App\Mail\PurchaseReceiptMail;
use App\Models\Transaction;
use App\Models\WalletFunding;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Mail;

/**
 * E-mails customers a receipt (with a PDF copy) when a purchase completes,
 * fails or is refunded, and when their wallet is funded. Sent after the
 * response so it never slows a purchase; each status is e-mailed once.
 */
class ReceiptMailer
{
    public const STATUSES = [Transaction::SUCCESSFUL, Transaction::FAILED, Transaction::REFUNDED];

    /** Set while seeding demo data so test accounts aren't e-mailed. */
    public static bool $muted = false;

    /** On a web/API request: after the response (never slows a purchase). Elsewhere (queue, console): now. */
    protected function later(\Closure $send): void
    {
        if (app()->runningInConsole()) {
            rescue($send);
        } else {
            dispatch(fn () => rescue($send))->afterResponse();
        }
    }

    public function purchase(Transaction $txn): void
    {
        $user = $txn->user;
        if (self::$muted || !$user || !$user->wantsEmailReceipts() || !in_array($txn->status, self::STATUSES, true)) return;

        // Once per status (a refund after a failure is a separate e-mail).
        $sent = (array) ($txn->meta['receipts_emailed'] ?? []);
        if (in_array($txn->status, $sent, true)) return;
        // A failure that is refunded straight away: one e-mail (the refund) is enough.
        if ($txn->status === Transaction::FAILED && $txn->refunded_at) return;
        $txn->forceFill(['meta' => array_merge((array) $txn->meta, ['receipts_emailed' => array_merge($sent, [$txn->status])])])->saveQuietly();

        $id = $txn->id;
        $this->later(function () use ($id) {
            $t = Transaction::with('user', 'service', 'product')->find($id);
            if ($t) Mail::to($t->user->email, $t->user->name)->send(new PurchaseReceiptMail($t));
        });
    }

    public function funding(WalletFunding $funding): void
    {
        $user = $funding->user;
        if (self::$muted || !$user || !$user->wantsEmailReceipts() || $funding->status !== 'successful') return;
        if (str_starts_with((string) $funding->reference, 'DEMO-')) return;   // test balances from DemoSeeder

        $id = $funding->id;
        $this->later(function () use ($id) {
            $f = WalletFunding::with('user', 'wallet')->find($id);
            if ($f) Mail::to($f->user->email, $f->user->name)->send(new FundingReceiptMail($f));
        });
    }

    /** The receipt as a PDF (also used for "Download receipt" on the web and in the app). */
    public static function purchasePdf(Transaction $txn): string
    {
        return Pdf::loadView('pdf.receipt', ['t' => $txn->loadMissing('user', 'service', 'product')])->setPaper('a5')->output();
    }

    public static function fundingPdf(WalletFunding $f): string
    {
        return Pdf::loadView('pdf.funding-receipt', ['f' => $f->loadMissing('user', 'wallet')])->setPaper('a5')->output();
    }
}
