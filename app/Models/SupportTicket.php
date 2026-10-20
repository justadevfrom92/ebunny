<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A support ticket a customer opened from My Account (Ranger works them). */
class SupportTicket extends Model
{
    public const STATUSES = ['new' => ['New', 'info'], 'open' => ['Open', 'warn'], 'waiting' => ['Waiting on Customer', ''], 'solved' => ['Solved', 'ok'], 'closed' => ['Closed', '']];

    public const PRIORITIES = ['low' => ['Low', ''], 'normal' => ['Normal', 'info'], 'high' => ['High', 'warn'], 'urgent' => ['Urgent', 'bad']];

    public const TOPICS = ['billing' => 'Billing & Payments', 'service' => 'Start, Stop or Move Service', 'plan' => 'My Plan', 'rewards' => 'Rangler Rewards', 'account' => 'My Account & Login', 'outage' => 'Power Outage', 'other' => 'Something Else'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['first_response_at' => 'datetime', 'solved_at' => 'datetime', 'rated_at' => 'datetime', 'last_activity_at' => 'datetime'];
    }

    public function number(): string
    {
        return 'RT-'.(10000 + $this->id);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(SupportTicketMessage::class)->oldest('created_at')->oldest('id');
    }

    public function watchers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'support_ticket_watchers')->withPivot('joined_at');
    }

    public function scopeActive(Builder $q): void
    {
        $q->whereIn('status', ['new', 'open', 'waiting']);
    }

    public function isDone(): bool
    {
        return in_array($this->status, ['solved', 'closed'], true);
    }

    /** Add a message and keep the ticket's times and status in step. */
    public function post(string $author, string $body, ?User $user = null): SupportTicketMessage
    {
        $m = $this->messages()->create(['author' => $author, 'body' => $body, 'user_id' => $user?->id, 'created_at' => now()]);
        $changes = ['last_activity_at' => now()];
        if ($author === 'agent') {
            $changes['first_response_at'] = $this->first_response_at ?? now();
            if ($this->status === 'new') {
                $changes['status'] = 'open';
            }
        }
        if ($author === 'customer' && in_array($this->status, ['waiting', 'solved'], true)) {
            $changes['status'] = 'open';   // the customer wrote back: it's ours again
        }
        $this->update($changes);

        return $m;
    }

    /**
     * Approval %: good ratings out of all ratings, for the tickets in $q. Null when nothing is rated.
     *
     * @return array{rated: int, good: int, pct: ?float}
     */
    public static function approval(Builder $q): array
    {
        $r = (clone $q)->whereNotNull('rating')->selectRaw("count(*) as rated, sum(case when rating = 'good' then 1 else 0 end) as good")->first();
        $rated = (int) $r->rated;

        return ['rated' => $rated, 'good' => (int) $r->good, 'pct' => $rated ? round(100 * $r->good / $rated, 1) : null];
    }
}
