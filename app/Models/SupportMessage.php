<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class SupportMessage extends Model
{
    protected $fillable = ['conversation_id', 'user_id', 'from_staff', 'type', 'body', 'transaction_id', 'attachment_path', 'attachment_name', 'attachment_mime', 'attachment_size', 'read_at'];

    protected $casts = ['from_staff' => 'boolean', 'read_at' => 'datetime'];

    protected $hidden = ['attachment_path'];

    public function conversation()
    {
        return $this->belongsTo(SupportConversation::class, 'conversation_id');
    }

    public function sender()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }

    public function isImage(): bool
    {
        return $this->attachment_path && Str::startsWith((string) $this->attachment_mime, 'image/');
    }

    /** Shape shared by the website, the admin inbox and the app. $forStaff shows internal names. */
    public function present(bool $forStaff = false, string $fileRoute = 'support.attachment'): array
    {
        $txn = $this->transaction;

        return [
            'id'         => $this->id,
            'type'       => $this->type,
            'from_staff' => $this->from_staff,
            'mine'       => null,   // filled in per viewer
            'sender'     => $this->type === 'event' ? null : ($this->from_staff
                ? ($forStaff ? $this->sender?->name : (\Illuminate\Support\Str::before((string) $this->sender?->name, ' ') ?: 'Support') . ' · ' . config('afritopup.name') . ' Support')
                : $this->sender?->name),
            'body'       => $this->body,
            'attachment' => $this->attachment_path ? [
                'name'  => $this->attachment_name,
                'mime'  => $this->attachment_mime,
                'size'  => $this->attachment_size,
                'image' => $this->isImage(),
                'url'   => route($fileRoute, $this),
            ] : null,
            'transaction' => $txn ? [
                'reference' => $txn->reference,
                'title'     => trim(($txn->service?->display_name ?? '') . ' ' . ($txn->recipient ? '· ' . $txn->recipient : '')),
                'amount'    => \App\Support\Money::format($txn->total, $txn->currency),
                'status'    => $txn->status,
            ] : null,
            'read'       => (bool) $this->read_at,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
