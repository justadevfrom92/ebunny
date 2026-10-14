<?php

namespace App\Models;

use App\Models\Concerns\RecordsHistory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Corral → CRM: someone who might become a customer, moving through the sales pipeline. */
class CrmLead extends Model
{
    use RecordsHistory;

    /** stage => [label, chance of winning (%) used for the weighted pipeline, pill tone] */
    public const STAGES = [
        'new' => ['New', 10, 'info'],
        'contacted' => ['Contacted', 20, 'info'],
        'qualified' => ['Qualified', 40, 'warn'],
        'proposal' => ['Proposal Sent', 70, 'warn'],
        'won' => ['Won', 100, 'ok'],
        'lost' => ['Lost', 0, 'bad'],
    ];

    public const OPEN = ['new', 'contacted', 'qualified', 'proposal'];

    public const KINDS = ['Residential', 'Small Business', 'Commercial', 'Multi-site'];

    public const SOURCES = ['Website', 'Referral', 'Broker', 'Partner', 'Event', 'Outbound', 'Renewal'];

    public const LOST_REASONS = ['Price', 'Stayed with current provider', 'Contract not up yet', 'No response', 'Credit check', 'Moved'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['est_value' => 'float', 'contract_ends_on' => 'date', 'next_follow_up_on' => 'date', 'stage_changed_at' => 'datetime', 'closed_at' => 'datetime'];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(CrmPartner::class, 'partner_id');
    }

    public function market(): BelongsTo
    {
        return $this->belongsTo(Market::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(CrmActivity::class, 'lead_id');
    }

    public function scopeOpen(Builder $q): Builder
    {
        return $q->whereIn('stage', self::OPEN);
    }

    public function isOpen(): bool
    {
        return in_array($this->stage, self::OPEN, true);
    }

    public function stageLabel(): string
    {
        return self::STAGES[$this->stage][0] ?? ucfirst($this->stage);
    }

    public function stageTone(): string
    {
        return self::STAGES[$this->stage][2] ?? '';
    }

    /** Yearly value times the stage's chance of winning. */
    public function weighted(): float
    {
        return round($this->est_value * (self::STAGES[$this->stage][1] ?? 0) / 100, 2);
    }

    public function displayName(): string
    {
        return $this->company ?: $this->name;
    }

    public function followUpOverdue(): bool
    {
        return $this->isOpen() && $this->next_follow_up_on && $this->next_follow_up_on->lt(today());
    }

    public function historyName(): array
    {
        return ['ItemLead_model', 'Admin Changes'];
    }

    public function historyLabel(): string
    {
        return 'Lead: '.$this->displayName();
    }
}
