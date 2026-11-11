<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

/**
 * One account per person: customers, agents, resellers and AfriTopup staff
 * all sign in here. `user_type` says what kind of account it is; roles and
 * permissions (spatie/laravel-permission) say what they may do; the price
 * tier says what discount they buy at.
 */
class User extends Authenticatable
{
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    public const TYPE_CUSTOMER = 'customer';
    public const TYPE_AGENT    = 'agent';
    public const TYPE_RESELLER = 'reseller';
    public const TYPE_STAFF    = 'staff';

    /** Folder on the "public" disk that holds profile photos. */
    public const AVATAR_DIR = 'avatars';

    protected $fillable = [
        'name',
        'email',
        'username',
        'phone_number',
        'user_type',
        'country_code',
        'price_tier_id',
        'business_name',
        'referred_by',
        'referral_code',
        'avatar',
        'gender',
        'date_of_birth',
        'job_title',
        'address',
        'bio',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'transaction_pin',
        'google_id',
        'apple_id',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at'    => 'datetime',
            'date_of_birth'        => 'date',
            'must_change_password' => 'boolean',
            'is_disabled'          => 'boolean',
            'last_login_at'        => 'datetime',
            'last_seen_at'         => 'datetime',
            'presence_until'       => 'datetime',
            'status_until'         => 'datetime',
            'password'             => 'hashed',
            'transaction_pin'      => 'hashed',
            'muted_notifications'  => 'array',
            'notify_digest'        => 'boolean',
            'notify_digest_at'     => 'datetime',
        ];
    }

    // ── Account type ───────────────────────────────────────────────────

    public function isStaff(): bool
    {
        return $this->user_type === self::TYPE_STAFF;
    }

    public function isCustomer(): bool
    {
        return $this->user_type === self::TYPE_CUSTOMER;
    }

    public function isAgent(): bool
    {
        return $this->user_type === self::TYPE_AGENT;
    }

    public function isReseller(): bool
    {
        return $this->user_type === self::TYPE_RESELLER;
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole(config('afritopup.super_admin_role', 'Super Administrator'));
    }

    public function isActive(): bool
    {
        return !$this->is_disabled;
    }

    public function getUserTypeLabelAttribute(): string
    {
        return config('afritopup.user_types.' . $this->user_type, ucfirst((string) $this->user_type));
    }

    // ── Wallet, purchases and pricing ─────────────────────────────────

    public function wallets(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Wallet::class);
    }

    /** The wallet in the user's currency (or the one given), created on first use. */
    public function wallet(?string $currency = null): Wallet
    {
        $currency = strtoupper($currency ?: $this->currency());

        return $this->wallets()->firstOrCreate(['currency' => $currency], ['balance' => 0]);
    }

    public function currency(): string
    {
        $country = $this->country_code ?: config('afritopup.default_country', 'NG');

        return (string) (Country::where('code', $country)->value('currency') ?: config('afritopup.default_currency', 'NGN'));
    }

    public function transactions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function fundings(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(WalletFunding::class);
    }

    public function apiKeys(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ApiKey::class);
    }

    public function senderIds(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(SmsSenderId::class);
    }

    public function priceTier(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(PriceTier::class);
    }

    public function country(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Country::class, 'country_code', 'code');
    }

    /** The tier this user buys at: their own, or the default one. */
    public function tier(): ?PriceTier
    {
        return $this->priceTier ?? PriceTier::default();
    }

    public function hasTransactionPin(): bool
    {
        return !empty($this->getRawOriginal('transaction_pin'));
    }

    public function checkTransactionPin(?string $pin): bool
    {
        return $this->hasTransactionPin() && $pin !== null && \Illuminate\Support\Facades\Hash::check($pin, $this->getRawOriginal('transaction_pin'));
    }

    // ── Names and photo ────────────────────────────────────────────────

    public function getFirstNameAttribute(): string
    {
        return explode(' ', trim((string) $this->name))[0] ?? '';
    }

    public function getLastNameAttribute(): string
    {
        $parts = preg_split('/\s+/', trim((string) $this->name));
        return count($parts) > 1 ? end($parts) : '';
    }

    public function getInitialsAttribute(): string
    {
        $parts = preg_split('/\s+/', trim((string) $this->name)) ?: [];
        $initials = collect($parts)->filter()->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->take(2)->implode('');
        return $initials !== '' ? $initials : 'U';
    }

    /** Public URL of the profile photo, or null when there is none. */
    public function getAvatarPathUrlAttribute(): ?string
    {
        if (!$this->avatar) return null;
        $path = self::AVATAR_DIR . '/' . basename($this->avatar);
        return Storage::disk('public')->exists($path) ? asset('storage/' . $path) : null;
    }

    /** Profile photo, or a generated initials picture. Always returns a URL. */
    public function getAvatarUrlAttribute(): string
    {
        return $this->avatar_path_url
            ?? 'https://ui-avatars.com/api/?name=' . urlencode((string) $this->name) . '&color=FFFFFF&format=png&background=' . ltrim((string) config('afritopup.primary_color', '#0B8457'), '#');
    }

    public function getFormattedDobAttribute(): ?string
    {
        return $this->date_of_birth?->format('d M Y');
    }

    // ── Scopes ─────────────────────────────────────────────────────────

    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('user_type', $type);
    }

    public function scopeStaff(Builder $query): Builder
    {
        return $query->where('user_type', self::TYPE_STAFF);
    }

    /** Everyone who buys: customers, agents and resellers. */
    public function scopeBuyers(Builder $query): Builder
    {
        return $query->where('user_type', '!=', self::TYPE_STAFF);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_disabled', false);
    }
}
