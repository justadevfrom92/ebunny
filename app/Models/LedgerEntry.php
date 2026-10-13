<?php

namespace App\Models;

use App\Models\Concerns\RecordsHistory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A pending credit or debit on an account. */
class LedgerEntry extends Model
{
    use RecordsHistory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['amount' => 'float', 'decided_at' => 'datetime'];
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** Who added it (empty for automatic entries such as rewards and promos). */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** [model name, log group] for History. */
    public function historyName(): array
    {
        return [$this->kind === 'credit' ? 'ItemCredit_model' : 'ItemDebit_model', 'Payments'];
    }

    public function historyLabel(): string
    {
        return ucfirst($this->kind).' $'.number_format($this->amount, 2).' — '.$this->description;
    }
}
