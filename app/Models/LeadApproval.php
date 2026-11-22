<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A change on a Friends & Family account someone asked a Customer Service Lead to approve. */
class LeadApproval extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['input' => 'array', 'decided_at' => 'datetime'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function ledgerEntry(): BelongsTo
    {
        return $this->belongsTo(LedgerEntry::class);
    }

    public function scopePending($q)
    {
        return $q->where('status', 'pending');
    }

    /** "Billing - Add Bill Credit", or "Delete pending debit". */
    public function label(): string
    {
        if ($this->action === 'ledger-delete') {
            return 'Delete pending '.($this->ledgerEntry?->kind ?? 'item');
        }

        return (config('corral.actions.status')[$this->action] ?? config('corral.actions.service')[$this->action] ?? [$this->action])[0];
    }

    /** "$25.00 · Courtesy credit" from what was filled in. */
    public function details(): string
    {
        if ($this->action === 'ledger-delete') {
            return $this->ledgerEntry ? '$'.number_format((float) $this->ledgerEntry->amount, 2).' — '.$this->ledgerEntry->description : '';
        }

        return collect($this->input ?? [])->map(fn ($v, $k) => $k === 'amount' ? '$'.number_format((float) $v, 2) : (string) $v)->filter()->implode(' · ');
    }
}
