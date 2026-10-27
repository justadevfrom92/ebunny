<?php

namespace App\Models;

use App\Support\Health;
use App\Support\HeartbeatControl;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One control used on a heartbeat check (restart, take down, clear…): who, when and what came back. */
class HeartbeatAction extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['ok' => 'boolean'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function checkName(): string
    {
        return Health::CHECKS[$this->check_key][0] ?? $this->check_key;
    }

    public function label(): string
    {
        return HeartbeatControl::actions($this->check_key)[$this->action][0] ?? ucfirst(str_replace('_', ' ', $this->action));
    }
}
