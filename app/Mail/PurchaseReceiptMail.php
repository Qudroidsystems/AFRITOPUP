<?php

namespace App\Mail;

use App\Models\Transaction;
use App\Services\Receipts\ReceiptMailer;
use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Support\Str;

/** Receipt for a purchase: successful, failed or refunded. HTML + text + PDF copy. */
class PurchaseReceiptMail extends Mailable
{
    public function __construct(public Transaction $txn) {}

    public function envelope(): Envelope
    {
        $t = $this->txn;
        $name = config('afritopup.name');
        $what = $t->product?->name ?? trim(($t->service?->display_name ?? '') . ' ' . $t->category_label);
        $subject = match ($t->status) {
            Transaction::SUCCESSFUL => "Receipt: {$what} for {$t->recipient}",
            Transaction::REFUNDED   => "Refunded: {$what} for {$t->recipient}",
            default                 => "Payment failed: {$what} for {$t->recipient}",
        };
        $support = config('afritopup.support_email');

        return new Envelope(subject: $subject . ' — ' . $name, replyTo: $support ? [new Address($support, $name . ' Support')] : [], tags: ['receipt', $t->status]);
    }

    public function headers(): Headers
    {
        return new Headers(messageId: $this->txn->reference . '.' . $this->txn->status . '@' . (parse_url(config('app.url'), PHP_URL_HOST) ?: 'afritopup'), text: ['X-Entity-Ref-ID' => $this->txn->reference . '-' . $this->txn->status]);
    }

    public function content(): Content
    {
        $t = $this->txn;
        $data = [
            't' => $t,
            'firstName' => Str::before(trim((string) $t->user?->name), ' ') ?: 'there',
            'what' => $t->product?->name ?? trim(($t->service?->display_name ?? '') . ' ' . $t->category_label),
            'subject' => $this->envelope()->subject,
            'preheader' => match ($t->status) {
                Transaction::SUCCESSFUL => ($t->token ? 'Your ' . ($t->category === 'electricity' ? 'token' : 'PIN') . ' is inside. ' : '') . \App\Support\Money::format($t->total, $t->currency) . ' paid.',
                Transaction::REFUNDED   => \App\Support\Money::format($t->total, $t->currency) . ' is back in your wallet.',
                default                 => 'This payment did not go through.',
            },
            'url' => route('topup.transactions.show', $t->reference),
        ];

        return new Content(view: 'emails.receipts.purchase', text: 'emails.receipts.purchase-text', with: $data);
    }

    public function attachments(): array
    {
        if ($this->txn->status !== Transaction::SUCCESSFUL) return [];

        return [Attachment::fromData(fn () => ReceiptMailer::purchasePdf($this->txn), 'Receipt-' . $this->txn->reference . '.pdf')->withMime('application/pdf')];
    }
}
