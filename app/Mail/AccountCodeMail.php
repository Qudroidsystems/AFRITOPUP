<?php

namespace App\Mail;

use App\Models\User;
use App\Services\Auth\EmailCodes;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

/**
 * "Confirm your e-mail" and "Reset your password": a 6-digit code (for the app,
 * or typing in) plus a one-click link (for the web). HTML and plain text.
 */
class AccountCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public User $user, public string $purpose, public string $code, public ?string $url = null) {}

    public function envelope(): Envelope
    {
        $name = config('afritopup.name', 'AfriTopup');
        $support = config('afritopup.support_email');

        return new Envelope(
            subject: $this->purpose === EmailCodes::RESET ? "Reset your {$name} password" : "Confirm your e-mail for {$name}",
            replyTo: $support ? [new Address($support, $name . ' Support')] : [],
            tags: ['auth', $this->purpose],
        );
    }

    public function headers(): Headers
    {
        // A unique ref stops Gmail folding successive codes into one thread (and hiding the newest).
        return new Headers(text: ['X-Entity-Ref-ID' => (string) Str::uuid(), 'X-Auto-Response-Suppress' => 'OOF, AutoReply']);
    }

    public function content(): Content
    {
        $name = config('afritopup.name', 'AfriTopup');
        $reset = $this->purpose === EmailCodes::RESET;
        $data = [
            'subject'     => $this->envelope()->subject,
            'preheader'   => $reset ? "Your password reset code is {$this->code}." : "Your confirmation code is {$this->code}.",
            'firstName'   => Str::before(trim($this->user->name), ' ') ?: 'there',
            'heading'     => $reset ? 'Reset your password' : 'Confirm your e-mail address',
            'intro'       => $reset ? "We received a request to reset the password on your {$name} account. Enter this code in the app:" : "Thanks for signing up to {$name}. Enter this code to confirm it's really you:",
            'codeDisplay' => trim(chunk_split($this->code, 3, ' ')),
            'minutes'     => EmailCodes::TTL[$this->purpose],
            'url'         => $this->url,
            'buttonIntro' => $reset ? 'On the website? Use this button instead:' : 'Or confirm with one click:',
            'button'      => $reset ? 'Choose a new password' : 'Confirm my e-mail',
            'safety'      => $reset ? "Didn't ask for this? You can ignore this e-mail — your password won't change." : "Didn't create an account? You can safely ignore this e-mail.",
            'footerNote'  => $reset ? 'You are receiving this because a password reset was requested for your account.' : 'You are receiving this because this address was used to sign up.',
        ];

        return new Content(view: 'emails.auth.code', text: 'emails.auth.code-text', with: $data);
    }
}
