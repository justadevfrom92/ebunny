<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One entry in a ticket: from the customer, a reply, or a system line — sent through My Account, by
 * email, or a phone call (with direction and length). Messages and emails can have attachments.
 */
class SupportTicketMessage extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    /** How it came in or went out: label and icon. */
    public const VIA = ['portal' => ['My Account', '💬'], 'email' => ['Email', '✉'], 'phone' => ['Phone call', '📞']];

    public function attachments(): HasMany
    {
        return $this->hasMany(SupportTicketAttachment::class);
    }

    /** "7:05" for a phone call's length. */
    public function durationLabel(): string
    {
        return $this->duration ? intdiv($this->duration, 60).':'.str_pad((string) ($this->duration % 60), 2, '0', STR_PAD_LEFT) : '—';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
