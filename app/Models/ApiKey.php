<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * A reseller API key. Only its SHA-256 hash is stored; the key itself is
 * shown once, when it is created. Keys look like afk_live_<40 chars>.
 */
class ApiKey extends Model
{
    protected $fillable = ['user_id', 'name', 'prefix', 'key_hash', 'webhook_url', 'webhook_secret', 'allowed_ips', 'last_used_at', 'revoked_at'];

    protected $casts = ['allowed_ips' => 'array', 'last_used_at' => 'datetime', 'revoked_at' => 'datetime'];

    protected $hidden = ['key_hash', 'webhook_secret'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function isActive(): bool
    {
        return $this->revoked_at === null;
    }

    /** @return array{0: ApiKey, 1: string} the saved key and the plain key to show once */
    public static function issue(User $user, string $name, ?string $webhookUrl = null): array
    {
        $plain = 'afk_live_' . Str::random(40);
        $key = static::create([
            'user_id'        => $user->id,
            'name'           => $name,
            'prefix'         => substr($plain, 0, 14),
            'key_hash'       => hash('sha256', $plain),
            'webhook_url'    => $webhookUrl,
            'webhook_secret' => Str::random(40),
        ]);

        return [$key, $plain];
    }

    public static function findByPlain(string $plain): ?self
    {
        return static::where('key_hash', hash('sha256', $plain))->whereNull('revoked_at')->first();
    }

    public function allows(?string $ip): bool
    {
        $ips = array_filter((array) $this->allowed_ips);

        return !$ips || in_array($ip, $ips, true);
    }
}
