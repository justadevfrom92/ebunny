<?php

namespace App\Models;

use App\Models\Concerns\RecordsHistory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** An email or text sent to the customer. */
class ContactLog extends Model
{
    use RecordsHistory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['resolved_at' => 'datetime', 'sent_at' => 'datetime', 'dropped_at' => 'datetime', 'opened_at' => 'datetime', 'clicked_at' => 'datetime'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** [model name, log group] for History. */
    public function historyName(): array
    {
        return [$this->channel === 'SMS' ? 'ItemSms_model' : 'ItemEmailSalesforce_model', 'Emails'];
    }

    public function historyLabel(): string
    {
        return $this->channel.': '.$this->template;
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    /** Where the email got to: dropped, clicked, opened, sent, queued or not sent. */
    public function stage(): string
    {
        return match (true) {
            (bool) $this->dropped_at => 'dropped',
            (bool) $this->clicked_at => 'clicked',
            (bool) $this->opened_at => 'opened',
            (bool) $this->sent_at => 'sent',
            default => $this->status,
        };
    }

    /** The original item model an email is shown as (corral/data/item/{id}/{Model}), by its template. */
    public function itemModel(): ?string
    {
        if ($this->channel !== 'Email') {
            return null;
        }

        return collect(config('items.models'))->search(fn ($d) => ($d['template'] ?? null) === $this->template) ?: 'ItemEmailSalesforce_model';
    }

    /** This email's or text's own page in Corral. */
    public function corralUrl(): string
    {
        return $this->channel === 'Email' ? route('corral.items.show', [$this->id, $this->itemModel()]) : route('corral.sms.show', $this);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
