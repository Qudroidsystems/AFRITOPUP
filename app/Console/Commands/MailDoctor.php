<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/**
 * php artisan mail:doctor [--to=you@example.com] [--selector=resend]
 *
 * Checks the things that decide whether AfriTopup's e-mails land in the inbox
 * or in spam: the mailer, the sender domain's SPF, DKIM and DMARC records,
 * MX, and that links point at the same domain. Optionally sends a test e-mail.
 */
class MailDoctor extends Command
{
    protected $signature = 'mail:doctor {--to= : Send a test confirmation e-mail to this address} {--selector=* : DKIM selector(s) your provider gave you}';

    protected $description = 'Check e-mail deliverability settings (SPF, DKIM, DMARC, mailer) and send a test e-mail';

    /** Selectors used by common transactional providers. */
    protected const SELECTORS = ['resend', 'pm', 'mail', 'brevo1', 'brevo2', 'mailjet', 'k1', 'mg', 'smtp', 's1', 's2', 'default', 'google', 'selector1', 'selector2', 'zoho', 'zmail', 'dkim', 'amazonses'];

    protected int $problems = 0;

    public function handle(): int
    {
        $mailer = config('mail.default');
        $from = (string) config('mail.from.address');
        $domain = strtolower(substr(strrchr($from, '@') ?: '', 1));
        $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);

        $this->line('');
        $this->info('  E-mail deliverability check');
        $this->line('  ───────────────────────────');

        // 1. Mailer
        $transport = config("mail.mailers.$mailer.transport", $mailer);
        if (in_array($transport, ['log', 'array'], true)) {
            $this->bad("Mailer is \"$mailer\" — e-mails are written to the log, not sent.", 'Set MAIL_MAILER=smtp (or resend / postmark / ses) in .env.');
        } elseif (in_array($transport, ['sendmail', 'mail'], true)) {
            $this->bad("Mailer is \"$transport\" (your web server sends directly). These usually go to spam.", 'Use a transactional provider over SMTP: Resend, Postmark, Brevo, Mailgun or Amazon SES.');
        } else {
            $host = config("mail.mailers.$mailer.host");
            $this->good("Mailer: $transport" . ($host ? " via $host" : ''));
        }

        // 2. Sender address
        if (!$domain) {
            $this->bad('MAIL_FROM_ADDRESS is empty.', 'Set e.g. MAIL_FROM_ADDRESS="no-reply@afritopup.com".');
            return $this->finish();
        }
        if (in_array($domain, ['gmail.com', 'yahoo.com', 'outlook.com', 'hotmail.com', 'icloud.com', 'ymail.com'], true)) {
            $this->bad("Sending as @$domain — Gmail/Yahoo reject or spam-folder mail \"from\" their domains sent by other servers (DMARC).", 'Send from your own domain, e.g. no-reply@afritopup.com.');
        } else {
            $this->good("From: " . config('mail.from.name') . " <$from>");
        }
        if (str_contains($domain, 'example') || $domain === 'localhost') {
            $this->bad("The sender domain \"$domain\" is a placeholder.", 'Use your real domain.');
            return $this->finish();
        }

        // 3. DNS
        if (!function_exists('dns_get_record') || !(@dns_get_record('gmail.com', DNS_MX))) {
            $this->warnLine('DNS lookups are not working from this machine — skipping SPF/DKIM/DMARC checks. Run this on your live server, or check at mxtoolbox.com.');
        } else {
            $txt = fn (string $name) => collect(@dns_get_record($name, DNS_TXT) ?: [])->map(fn ($r) => $r['txt'] ?? implode('', $r['entries'] ?? []))->all();

            $mx = @dns_get_record($domain, DNS_MX) ?: [];
            $mx ? $this->good("MX: " . collect($mx)->pluck('target')->take(2)->join(', ')) : $this->warnLine("No MX record for $domain — replies to your e-mails will bounce. Add one if you want to receive mail there.");

            $spf = collect($txt($domain))->first(fn ($t) => str_starts_with(strtolower($t), 'v=spf1'));
            if (!$spf) {
                $this->bad("No SPF record on $domain.", "Add a TXT record on $domain: v=spf1 include:<your provider> ~all  (your provider's dashboard shows the exact value).");
            } else {
                $this->good("SPF: $spf");
                if (str_contains($spf, '+all')) $this->bad('SPF ends in +all, which lets anyone send as you.', 'Change it to ~all or -all.');
                if (substr_count(strtolower($spf), 'v=spf1') > 1 || collect($txt($domain))->filter(fn ($t) => str_starts_with(strtolower($t), 'v=spf1'))->count() > 1) {
                    $this->bad('More than one SPF record — receivers then treat SPF as failed.', 'Merge them into a single v=spf1 record.');
                }
            }

            $selectors = array_filter((array) $this->option('selector')) ?: self::SELECTORS;
            $found = collect($selectors)->first(fn ($s) => collect($txt("$s._domainkey.$domain"))->contains(fn ($t) => str_contains(strtolower($t), 'p='))
                || (@dns_get_record("$s._domainkey.$domain", DNS_CNAME) ?: []));
            $found ? $this->good("DKIM: found selector \"$found\"")
                : $this->bad('No DKIM record found' . ($this->option('selector') ? '' : ' for common selectors') . '.', 'Your mail provider gives you DKIM records (TXT or CNAME) to add to DNS. Run again with --selector=<name> to check yours.');

            $dmarc = collect($txt("_dmarc.$domain"))->first(fn ($t) => str_starts_with(strtolower($t), 'v=dmarc1'));
            if (!$dmarc) {
                $this->bad("No DMARC record (_dmarc.$domain).", "Add a TXT record on _dmarc.$domain: v=DMARC1; p=none; rua=mailto:dmarc@$domain  — move to p=quarantine once reports look clean.");
            } else {
                $this->good("DMARC: $dmarc");
            }
        }

        // 4. Links
        if ($appHost && $appHost !== $domain && !str_ends_with($appHost, '.' . $domain) && !str_ends_with($domain, '.' . $appHost)) {
            $this->warnLine("Links in e-mails point to $appHost but you send from $domain. Matching domains look more trustworthy.");
        }
        if ($appHost === 'localhost' || str_starts_with((string) config('app.url'), 'http://')) {
            $this->warnLine('APP_URL is ' . config('app.url') . ' — on the live server use your https:// domain, or links in e-mails will not work.');
        }

        // 5. Test send
        if ($to = $this->option('to')) {
            try {
                $user = new \App\Models\User(['name' => 'Test User', 'email' => $to]);
                $user->id = 0;
                Mail::to($to)->send(new \App\Mail\AccountCodeMail($user, 'verify', '123456', url('/')));
                $this->good("Test e-mail sent to $to — check that it lands in the inbox (not spam), and in Gmail use ⋮ → \"Show original\": SPF, DKIM and DMARC should all say PASS.");
            } catch (\Throwable $e) {
                $this->bad('Sending failed: ' . $e->getMessage(), 'Check MAIL_HOST, MAIL_PORT, MAIL_USERNAME and MAIL_PASSWORD.');
            }
        } else {
            $this->line('  <fg=gray>Tip: add --to=you@gmail.com to send a test e-mail.</>');
        }

        return $this->finish();
    }

    protected function finish(): int
    {
        $this->line('');
        if ($this->problems) {
            $this->error("  $this->problems problem(s) found — fix them for reliable inbox delivery.");
            return self::FAILURE;
        }
        $this->info('  Looks good.');

        return self::SUCCESS;
    }

    protected function good(string $msg): void
    {
        $this->line("  <fg=green>✓</> $msg");
    }

    protected function warnLine(string $msg): void
    {
        $this->line("  <fg=yellow>!</> $msg");
    }

    protected function bad(string $msg, string $fix): void
    {
        $this->problems++;
        $this->line("  <fg=red>✗</> $msg");
        $this->line("    <fg=gray>→ $fix</>");
    }
}
