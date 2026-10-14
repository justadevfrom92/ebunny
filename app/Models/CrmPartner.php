<?php

namespace App\Models;

use App\Models\Concerns\RecordsHistory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Corral → CRM: a broker or referral partner who sends us leads. */
class CrmPartner extends Model
{
    use RecordsHistory;

    public const KINDS = ['Broker', 'Referral Partner', 'Property Manager', 'Home Builder', 'Realtor'];

    public const STATUSES = ['Active', 'Paused', 'Ended'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['commission_rate' => 'float', 'agreement_ends_on' => 'date'];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function leads(): HasMany
    {
        return $this->hasMany(CrmLead::class, 'partner_id');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(CrmActivity::class, 'partner_id');
    }

    /** "$0.0040 / kWh" or "5% of first-year revenue". */
    public function commissionLabel(): string
    {
        return $this->commission_basis === 'kwh' ? '$'.number_format($this->commission_rate, 4).' / kWh' : rtrim(rtrim(number_format($this->commission_rate * 100, 2), '0'), '.').'% of first-year revenue';
    }

    /** What a won lead earns this partner in its first year. */
    public function commissionFor(CrmLead $lead): float
    {
        return round($this->commission_basis === 'kwh' ? $lead->annual_kwh * $this->commission_rate : $lead->est_value * $this->commission_rate, 2);
    }

    public function historyName(): array
    {
        return ['ItemPartner_model', 'Admin Changes'];
    }

    public function historyLabel(): string
    {
        return 'Partner: '.$this->name;
    }
}
