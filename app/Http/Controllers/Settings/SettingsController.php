<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Services\Payments\PaystackGateway;
use App\Services\Settings\Settings;
use App\Services\Topup\Providers\ProviderManager;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SettingsController extends Controller
{
    public function __construct(protected Settings $settings) {}

    // ── Payments (Paystack) ─────────────────────────────────────────────

    public function payments(PaystackGateway $paystack)
    {
        $c = $this->settings->paystack();

        return view('settings.payments', [
            'c' => $c,
            'enabled' => $paystack->enabled(),
            'mode' => $paystack->mode(),
            'source' => $this->settings->paystackSource(),
            'masked' => ['test' => $this->mask($c['test_secret_key'] ?? null), 'live' => $this->mask($c['live_secret_key'] ?? null)],
            'saved' => ['test' => $this->settings->has('paystack.test_secret_key'), 'live' => $this->settings->has('paystack.live_secret_key')],
            'last' => $this->settings->lastChange(Settings::PAYSTACK),
        ]);
    }

    public function savePayments(Request $request)
    {
        $d = $request->validate([
            'mode' => ['required', 'in:sandbox,live'],
            'test_public_key' => ['nullable', 'string', 'max:200', 'starts_with:pk_test_'],
            'live_public_key' => ['nullable', 'string', 'max:200', 'starts_with:pk_live_'],
            'test_secret_key' => ['nullable', 'string', 'max:200', 'starts_with:sk_test_'],
            'live_secret_key' => ['nullable', 'string', 'max:200', 'starts_with:sk_live_'],
        ], [
            '*.starts_with' => 'That doesn\'t look like the right key. It should start with :values.',
        ]);

        $values = [
            'paystack.active' => $request->boolean('active') ? '1' : '0',
            'paystack.mode' => $d['mode'],
            'paystack.test_public_key' => trim((string) ($d['test_public_key'] ?? '')),
            'paystack.live_public_key' => trim((string) ($d['live_public_key'] ?? '')),
        ];
        // A blank secret box keeps the saved key; "remove" clears it.
        foreach (['test', 'live'] as $m) {
            if ($request->boolean('clear_' . $m . '_secret')) $values['paystack.' . $m . '_secret_key'] = null;
            elseif (filled($d[$m . '_secret_key'] ?? null)) $values['paystack.' . $m . '_secret_key'] = trim($d[$m . '_secret_key']);
        }
        $this->settings->put($values, $request->user());

        $secret = app(PaystackGateway::class)->secret();
        $note = $d['mode'] === 'live' && $secret === '' ? ' Online payments stay off until a live secret key is saved.' : '';

        return back()->with('success', 'Payment settings saved.' . $note);
    }

    public function testPayments(PaystackGateway $paystack)
    {
        $problem = $paystack->check();

        return back()->with($problem ? 'error' : 'success', $problem
            ? 'Paystack didn\'t accept the ' . ($paystack->mode() === 'live' ? 'live' : 'test') . ' secret key: ' . $problem
            : 'Paystack accepted the ' . ($paystack->mode() === 'live' ? 'live' : 'test') . ' secret key.');
    }

    /** Forget every Paystack value saved here, so the .env values are used again. */
    public function useEnv(Request $request)
    {
        $this->settings->put(array_fill_keys(Settings::PAYSTACK, null), $request->user());

        return back()->with('success', 'Saved Paystack settings removed. The values in .env are used again.');
    }

    protected function mask(?string $key): ?string
    {
        if (!$key) return null;

        return substr($key, 0, 8) . str_repeat('•', 8) . substr($key, -4);
    }

    // ── VTU and SMS providers ───────────────────────────────────────────

    public function providers(ProviderManager $manager)
    {
        $cards = [];
        foreach (array_keys(Settings::PROVIDER_FIELDS) as $key) {
            $c = $this->settings->provider($key);
            $p = $manager->get($key);
            $cards[$key] = [
                'name'       => $p->name(),
                'c'          => $c,
                'configured' => $p->configured(),
                'source'     => $this->settings->providerSource($key),
                'masked'     => collect($c)->only(['api_key', 'secret_key', 'client_secret'])->map(fn ($v) => $this->mask($v))->all(),
                'saved'      => collect(['api_key', 'secret_key', 'client_secret'])->mapWithKeys(fn ($f) => [$f => $this->settings->has("provider.$key.$f")])->all(),
            ];
        }

        return view('settings.providers', ['cards' => $cards, 'last' => $this->settings->lastChange(array_merge(...array_map(
            fn ($k, $fields) => array_map(fn ($f) => "provider.$k.$f", $fields), array_keys(Settings::PROVIDER_FIELDS), Settings::PROVIDER_FIELDS)))]);
    }

    public function saveProvider(Request $request, string $provider)
    {
        abort_unless(isset(Settings::PROVIDER_FIELDS[$provider]), 404);

        $rules = [
            'vtpass' => ['mode' => ['required', 'in:sandbox,live'], 'api_key' => ['nullable', 'string', 'max:200'], 'public_key' => ['nullable', 'string', 'max:200'], 'secret_key' => ['nullable', 'string', 'max:200']],
            'termii' => ['api_key' => ['nullable', 'string', 'max:200'], 'sender_id' => ['nullable', 'string', 'max:11', 'regex:/^[A-Za-z0-9 .-]+$/'], 'channel' => ['required', 'in:generic,dnd'], 'base_url' => ['nullable', 'url', 'max:200']],
            'reloadly' => ['mode' => ['required', 'in:sandbox,live'], 'client_id' => ['nullable', 'string', 'max:200'], 'client_secret' => ['nullable', 'string', 'max:200']],
        ][$provider];
        $d = $request->validate($rules, ['sender_id.regex' => 'A sender ID is up to 11 letters and numbers.']);

        $values = [];
        foreach (Settings::PROVIDER_FIELDS[$provider] as $field) {
            $key = "provider.$provider.$field";
            if (in_array($key, Settings::SECRET, true)) {
                // A blank secret box keeps the saved key; "remove" clears it.
                if ($request->boolean('clear_' . $field)) $values[$key] = null;
                elseif (filled($d[$field] ?? null)) $values[$key] = trim($d[$field]);
            } else {
                $values[$key] = trim((string) ($d[$field] ?? '')) ?: null;
            }
        }
        $this->settings->put($values, $request->user());

        return back()->with('success', ucfirst($provider) . ' settings saved.');
    }

    /** Check the keys by reading our balance with the provider. */
    public function testProvider(ProviderManager $manager, string $provider)
    {
        abort_unless(isset(Settings::PROVIDER_FIELDS[$provider]), 404);
        $p = $manager->get($provider);
        if (!$p->configured()) return back()->with('error', $p->name() . ': enter the keys first.');
        $balance = $p->balance();

        return $balance === null
            ? back()->with('error', $p->name() . ' did not accept the keys (or could not be reached).')
            : back()->with('success', $p->name() . ' accepted the keys. Balance: ' . number_format($balance, 2) . '.');
    }

    // ── Website & app links ─────────────────────────────────────────────

    public function website()
    {
        $values = collect(Settings::SITE_FIELDS)->mapWithKeys(fn ($f) => [$f => config("afritopup.$f")])->all();

        return view('settings.website', [
            'pagetitle' => 'Website & app',
            'v'         => $values,
            'saved'     => collect(Settings::SITE_FIELDS)->filter(fn ($f) => $this->settings->has("site.$f"))->values()->all(),
            'last'      => $this->settings->lastChange(collect(Settings::SITE_FIELDS)->map(fn ($f) => "site.$f")->all()),
        ]);
    }

    public function saveWebsite(Request $request)
    {
        $url = ['nullable', 'url:https', 'max:500'];
        $data = $request->validate([
            'android'   => array_merge($url, ['regex:#^https://(play\.google\.com|play\.app\.goo\.gl)/#i']),
            'ios'       => array_merge($url, ['regex:#^https://(apps|itunes)\.apple\.com/#i']),
            'facebook'  => $url, 'x' => $url, 'instagram' => $url, 'linkedin' => $url, 'youtube' => $url, 'tiktok' => $url,
            'whatsapp'  => ['nullable', 'string', 'max:500'],
            'support_email' => 'nullable|email|max:190',
            'support_phone' => 'nullable|string|max:40',
            'address'   => 'nullable|string|max:255',
        ], [
            'android.regex' => 'Use the Google Play link, e.g. https://play.google.com/store/apps/details?id=com.afritopup.app',
            'ios.regex'     => 'Use the App Store link, e.g. https://apps.apple.com/app/afritopup/id1234567890',
            '*.url'         => 'Enter a full https:// link.',
        ]);

        // WhatsApp: a phone number becomes a wa.me link.
        $wa = trim((string) ($data['whatsapp'] ?? ''));
        if ($wa !== '' && !str_starts_with($wa, 'http')) {
            $digits = preg_replace('/\D/', '', $wa);
            if (str_starts_with($digits, '0')) $digits = '234' . substr($digits, 1);
            $wa = 'https://wa.me/' . $digits;
        }

        $map = [
            'app_links.android' => $data['android'] ?? null, 'app_links.ios' => $data['ios'] ?? null,
            'social_links.facebook' => $data['facebook'] ?? null, 'social_links.x' => $data['x'] ?? null,
            'social_links.instagram' => $data['instagram'] ?? null, 'social_links.linkedin' => $data['linkedin'] ?? null,
            'social_links.youtube' => $data['youtube'] ?? null, 'social_links.tiktok' => $data['tiktok'] ?? null,
            'social_links.whatsapp' => $wa ?: null,
            'support_email' => $data['support_email'] ?? null, 'support_phone' => $data['support_phone'] ?? null, 'address' => $data['address'] ?? null,
        ];
        $this->settings->put(collect($map)->mapWithKeys(fn ($v, $k) => ["site.$k" => $v])->all(), $request->user());
        \Illuminate\Support\Facades\Cache::forget('site.catalogue.NG');

        return back()->with('success', 'Website & app links saved — they are live on the website now.');
    }
}
