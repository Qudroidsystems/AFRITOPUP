<?php

namespace App\Mail;

use App\Models\WalletFunding;
use App\Services\Receipts\ReceiptMailer;
use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Support\Str;

/** "Your wallet was funded" receipt. */
class FundingReceiptMail extends Mailable
{
    public function __construct(public WalletFunding $funding) {}

    public function envelope(): Envelope
    {
        $name = config('afritopup.name');
        $support = config('afritopup.support_email');

        return new Envelope(subject: 'Wallet funded: ' . \App\Support\Money::format($this->funding->amount, $this->funding->currency) . ' — ' . $name,
            replyTo: $support ? [new Address($support, $name . ' Support')] : [], tags: ['receipt', 'funding']);
    }

    public function headers(): Headers
    {
        return new Headers(text: ['X-Entity-Ref-ID' => $this->funding->reference]);
    }

    public function content(): Content
    {
        $f = $this->funding;

        return new Content(view: 'emails.receipts.funding', text: 'emails.receipts.funding-text', with: [
            'f' => $f,
            'firstName' => Str::before(trim((string) $f->user?->name), ' ') ?: 'there',
            'subject' => $this->envelope()->subject,
            'preheader' => 'Your new balance is ' . ($f->wallet?->formatted_balance ?? '') . '.',
            'url' => route('topup.wallet'),
        ]);
    }

    public function attachments(): array
    {
        return [Attachment::fromData(fn () => ReceiptMailer::fundingPdf($this->funding), 'Receipt-' . $this->funding->reference . '.pdf')->withMime('application/pdf')];
    }
}
