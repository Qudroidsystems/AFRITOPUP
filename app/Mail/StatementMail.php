<?php

namespace App\Mail;

use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Str;

/** A wallet statement, as a PDF attachment. */
class StatementMail extends Mailable
{
    public function __construct(public array $summary, public string $pdf, public string $filename) {}

    public function envelope(): Envelope
    {
        $s = $this->summary;

        return new Envelope(subject: 'Your ' . config('afritopup.name') . ' statement: ' . $s['from']->format('j M Y') . ' – ' . $s['to']->format('j M Y'), tags: ['statement']);
    }

    public function content(): Content
    {
        $s = $this->summary;
        $m = fn ($v) => \App\Support\Money::format($v, $s['currency']);

        return new Content(view: 'emails.receipts.statement', text: 'emails.receipts.statement-text', with: [
            'subject' => $this->envelope()->subject,
            'preheader' => 'Closing balance ' . $m($s['closing']) . '. Your statement is attached as a PDF.',
            'firstName' => Str::before(trim($s['user']->name), ' ') ?: 'there',
            'lines' => [['Period', $s['from']->format('j M Y') . ' – ' . $s['to']->format('j M Y')], ['Opening balance', $m($s['opening'])], ['Money in', $m($s['credits'])], ['Money out', $m($s['debits'])], ['Closing balance', $m($s['closing'])]],
        ]);
    }

    public function attachments(): array
    {
        return [Attachment::fromData(fn () => $this->pdf, $this->filename)->withMime('application/pdf')];
    }
}
