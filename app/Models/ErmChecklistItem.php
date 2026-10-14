<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Bunkhouse: one step of someone's onboarding or offboarding. */
class ErmChecklistItem extends Model
{
    /** The steps a new checklist starts with: [task, team, days from the start (negative = before)] */
    public const TEMPLATES = [
        'onboarding' => [
            ['Send offer letter and welcome email', 'HR', -10], ['Background check cleared', 'HR', -7], ['Create admin sign-in and role', 'IT', -2],
            ['Laptop, headset and badge ready', 'IT', -1], ['First-day schedule and buddy assigned', 'Manager', 0], ['I-9 and tax forms', 'HR', 2],
            ['Benefits enrollment', 'HR', 14], ['Required training assigned', 'HR', 1], ['30-day check-in', 'Manager', 30], ['90-day review scheduled', 'HR', 85],
        ],
        'offboarding' => [
            ['Confirm last day in writing', 'HR', -14], ['Knowledge handoff and open work reassigned', 'Manager', -3], ['Exit interview', 'HR', -2],
            ['Final paycheck or last invoice', 'Finance', 0], ['Disable admin sign-in', 'IT', 0], ['Collect laptop and badge', 'IT', 1], ['Remove from shared inboxes and phone queues', 'IT', 1],
        ],
    ];

    public const EXTERNAL_OFFBOARDING = [
        ['Confirm contract end date with the company', 'HR', -14], ['Reassign open accounts and cases', 'Manager', -3], ['Disable admin sign-in on the access end date', 'IT', 0],
        ['Last invoice reconciled', 'Finance', 7], ['Vendor access review signed off', 'IT', 2],
    ];

    protected $table = 'erm_checklist_items';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['due_on' => 'date', 'done_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function doer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'done_by');
    }

    /** Create the standard steps for someone starting or leaving on $date. */
    public static function start(User $u, string $kind, CarbonInterface $date): void
    {
        $steps = $kind === 'offboarding' && $u->company ? self::EXTERNAL_OFFBOARDING : self::TEMPLATES[$kind];
        foreach ($steps as $i => [$task, $team, $days]) {
            self::create(['user_id' => $u->id, 'kind' => $kind, 'task' => $task, 'team' => $team, 'position' => $i, 'due_on' => $date->copy()->addDays($days)]);
        }
    }
}
