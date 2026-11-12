<?php

namespace App\Services\Settings;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;

/**
 * Portal-wide settings saved from the admin screens. A saved value wins over
 * the matching .env / config value; with nothing saved, config is used as before.
 *
 * Secret values (API keys) are encrypted with the app key, so changing APP_KEY
 * means entering them again.
 */
class Settings
{
    public const SECRET = ['paystack.test_secret_key', 'paystack.live_secret_key',
        'provider.vtpass.api_key', 'provider.vtpass.secret_key', 'provider.termii.api_key', 'provider.reloadly.client_secret'];

    protected const CACHE = 'portal-settings';

    /** Raw stored rows, key => value (secrets still encrypted). */
    protected function rows(): array
    {
        try {
            if (($rows = Cache::get(self::CACHE)) !== null) return $rows;
            if (!Schema::hasTable('settings')) return [];   // before migrate: don't remember that

            $rows = Setting::pluck('value', 'key')->all();
            Cache::forever(self::CACHE, $rows);

            return $rows;
        } catch (\Throwable $e) {
            return [];   // database not reachable
        }
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->rows()) && $this->rows()[$key] !== null && $this->rows()[$key] !== '';
    }

    public function get(string $key, mixed $default = null): mixed
    {
        if (!$this->has($key)) return $default;
        $value = $this->rows()[$key];
        if (in_array($key, self::SECRET, true)) {
            try {
                return Crypt::decryptString($value);
            } catch (DecryptException $e) {
                return $default;   // saved under a different APP_KEY
            }
        }

        return $value;
    }

    public function json(string $key): ?array
    {
        $v = $this->get($key);

        return is_string($v) ? json_decode($v, true) : null;
    }

    /** Save values; null removes the setting (falls back to config). */
    public function put(array $values, ?User $by = null): void
    {
        foreach ($values as $key => $value) {
            if ($value === null || $value === '') {
                Setting::where('key', $key)->delete();
                continue;
            }
            if (is_array($value)) $value = json_encode($value);
            $value = (string) $value;
            if (in_array($key, self::SECRET, true)) $value = Crypt::encryptString($value);
            Setting::updateOrCreate(['key' => $key], ['value' => $value, 'updated_by' => $by?->id]);
        }
        $this->flush();
    }

    public function flush(): void
    {
        Cache::forget(self::CACHE);
    }

    /** Who last changed any of these keys, and when. */
    public function lastChange(array $keys): ?Setting
    {
        try {
            return Setting::with('editor')->whereIn('key', $keys)->latest('updated_at')->first();
        } catch (\Throwable $e) {
            return null;
        }
    }

    // ── Website & app (Settings → Website & app) ────────────────────────
    /** config('afritopup.<field>') that admins can set; saved as "site.<field>". */
    public const SITE_FIELDS = [
        'app_links.android', 'app_links.ios',
        'social_links.facebook', 'social_links.x', 'social_links.instagram', 'social_links.linkedin',
        'social_links.youtube', 'social_links.tiktok', 'social_links.whatsapp',
        'support_email', 'support_phone', 'address',
    ];

    /** Put saved website settings over config('afritopup'), so every page, e-mail and the app see them. */
    public function applySiteToConfig(): void
    {
        try {
            foreach (self::SITE_FIELDS as $field) {
                if ($this->has("site.$field")) config(["afritopup.$field" => $this->get("site.$field")]);
            }
        } catch (\Throwable $e) {
            // Settings table not migrated yet.
        }
    }

    // ── Paystack ────────────────────────────────────────────────────────

    public const PAYSTACK = ['paystack.active', 'paystack.mode', 'paystack.test_public_key', 'paystack.test_secret_key', 'paystack.live_public_key', 'paystack.live_secret_key'];

    /** config('services.paystack') with saved values on top. */
    public function paystack(): array
    {
        $c = (array) config('services.paystack', []);
        foreach (self::PAYSTACK as $key) {
            if ($this->has($key)) $c[substr($key, 9)] = $this->get($key);
        }
        if ($this->has('paystack.active')) $c['active'] = $this->get('paystack.active') === '1';

        return $c;
    }

    /** Where the Paystack values come from, for the settings screen. */
    public function paystackSource(): string
    {
        foreach (self::PAYSTACK as $key) if ($this->has($key)) return 'portal';

        return 'env';
    }

    // ── VTU / SMS providers ─────────────────────────────────────────────

    /** Fields each provider has on Settings → Providers (the secret ones are in SECRET). */
    public const PROVIDER_FIELDS = [
        'vtpass' => ['mode', 'api_key', 'public_key', 'secret_key'],
        'termii' => ['api_key', 'sender_id', 'channel', 'base_url'],
        'reloadly' => ['mode', 'client_id', 'client_secret'],
    ];

    /** config('afritopup.providers.{key}') with saved values on top. */
    public function provider(string $key): array
    {
        $c = (array) config('afritopup.providers.' . $key, []);
        foreach (self::PROVIDER_FIELDS[$key] ?? [] as $field) {
            if ($this->has("provider.$key.$field")) $c[$field] = $this->get("provider.$key.$field");
        }

        return $c;
    }

    public function providerSource(string $key): string
    {
        foreach (self::PROVIDER_FIELDS[$key] ?? [] as $field) if ($this->has("provider.$key.$field")) return 'portal';

        return 'env';
    }
}
