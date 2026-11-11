<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A purchase. Life cycle:
 *   pending     – created, wallet debited, not yet sent to the provider
 *   processing  – sent; the provider has not confirmed (re-checked by topup:requery)
 *   successful  – delivered
 *   failed      – the provider refused it; the wallet is refunded automatically
 *   refunded    – reversed by staff after it had been marked successful or stuck
 */
class Transaction extends Model
{
    public const PENDING = 'pending';
    public const PROCESSING = 'processing';
    public const SUCCESSFUL = 'successful';
    public const FAILED = 'failed';
    public const REFUNDED = 'refunded';

    public const STATUSES = [
        self::PENDING    => ['Pending', 'secondary'],
        self::PROCESSING => ['Processing', 'warning'],
        self::SUCCESSFUL => ['Successful', 'success'],
        self::FAILED     => ['Failed', 'danger'],
        self::REFUNDED   => ['Refunded', 'info'],
    ];

    protected $fillable = ['reference', 'user_id', 'wallet_id', 'category', 'service_id', 'product_id', 'country_code', 'recipient', 'customer_name',
        'phone', 'quantity', 'amount', 'discount', 'fee', 'total', 'cost', 'currency', 'status', 'provider', 'provider_reference', 'provider_response',
        'token', 'message', 'channel', 'api_key_id', 'client_reference', 'meta', 'attempts', 'last_checked_at', 'completed_at', 'refunded_at', 'refunded_by'];

    protected $casts = [
        'amount' => 'decimal:2', 'discount' => 'decimal:2', 'fee' => 'decimal:2', 'total' => 'decimal:2', 'cost' => 'decimal:2',
        'provider_response' => 'array', 'meta' => 'array', 'quantity' => 'integer', 'attempts' => 'integer',
        'last_checked_at' => 'datetime', 'completed_at' => 'datetime', 'refunded_at' => 'datetime',
    ];

    protected $hidden = ['provider_response'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function wallet()
    {
        return $this->belongsTo(Wallet::class);
    }

    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function apiKey()
    {
        return $this->belongsTo(ApiKey::class);
    }

    public function scopeStatus(Builder $q, ?string $status): Builder
    {
        return $status ? $q->where('status', $status) : $q;
    }

    public function isFinal(): bool
    {
        return in_array($this->status, [self::SUCCESSFUL, self::FAILED, self::REFUNDED], true);
    }

    public function isOpen(): bool
    {
        return in_array($this->status, [self::PENDING, self::PROCESSING], true);
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status][0] ?? ucfirst($this->status);
    }

    public function getStatusColorAttribute(): string
    {
        return self::STATUSES[$this->status][1] ?? 'secondary';
    }

    public function getCategoryLabelAttribute(): string
    {
        return config('afritopup.categories.' . $this->category . '.name', ucfirst($this->category));
    }

    /** "MTN Airtime ₦500 to 0803…" — one line for lists and notifications. */
    public function getSummaryAttribute(): string
    {
        $what = $this->product?->name ?? ($this->service?->display_name . ' ' . $this->category_label);

        return trim($what) . ' · ' . $this->recipient;
    }

    /** Public view of the transaction for the app and the reseller API. */
    public function present(): array
    {
        return [
            'reference'        => $this->reference,
            'client_reference' => $this->client_reference,
            'category'         => $this->category,
            'category_label'   => $this->category_label,
            'service'          => $this->service ? ['code' => $this->service->code, 'name' => $this->service->name, 'logo' => $this->service->logo_url] : null,
            'product'          => $this->product ? ['code' => $this->product->code, 'name' => $this->product->name] : null,
            'recipient'        => $this->recipient,
            'customer_name'    => $this->customer_name,
            'quantity'         => $this->quantity,
            'amount'           => (float) $this->amount,
            'discount'         => (float) $this->discount,
            'fee'              => (float) $this->fee,
            'total'            => (float) $this->total,
            'currency'         => $this->currency,
            'status'           => $this->status,
            'status_label'     => $this->status_label,
            'message'          => $this->message,
            'token'            => $this->token,
            'channel'          => $this->channel,
            'created_at'       => $this->created_at?->toIso8601String(),
            'completed_at'     => $this->completed_at?->toIso8601String(),
        ];
    }
}
