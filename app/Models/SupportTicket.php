<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/** A support ticket a customer opened from My Account (Ranger works them). */
class SupportTicket extends Model
{
    public const STATUSES = ['new' => ['New', 'info'], 'open' => ['Open', 'warn'], 'waiting' => ['Waiting on Customer', ''], 'solved' => ['Solved', 'ok'], 'closed' => ['Closed', '']];

    public const PRIORITIES = ['low' => ['Low', ''], 'normal' => ['Normal', 'info'], 'high' => ['High', 'warn'], 'urgent' => ['Urgent', 'bad']];

    /** Where the ticket came in: My Account, an email, or a phone call. */
    public const CHANNELS = ['My Account' => '💬', 'Email' => '✉', 'Phone' => '📞'];

    public const TOPICS = ['billing' => 'Billing & Payments', 'service' => 'Start, Stop or Move Service', 'plan' => 'My Plan', 'rewards' => 'Rangler Rewards', 'account' => 'My Account & Login', 'outage' => 'Power Outage', 'other' => 'Something Else'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['first_response_at' => 'datetime', 'solved_at' => 'datetime', 'rated_at' => 'datetime', 'last_activity_at' => 'datetime', 'manager_requested_at' => 'datetime'];
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

    /** Internal notes: real account Notes (they show on the Corral account too), linked to this ticket. */
    public function notes(): HasMany
    {
        return $this->hasMany(Note::class, 'support_ticket_id')->oldest()->oldest('id');
    }

    public function managerRequester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_requested_by');
    }

    /** Messages and internal notes together, oldest first: each item has ->kind (customer | agent | system | note). */
    public function thread(): Collection
    {
        $messages = $this->messages->map(fn ($m) => (object) ['kind' => $m->author, 'at' => $m->created_at, 'user' => $m->user, 'body' => $m->body, 'priority' => null, 'id' => 'm'.$m->id]);
        $notes = $this->notes->map(fn ($n) => (object) ['kind' => 'note', 'at' => $n->created_at, 'user' => $n->user, 'body' => $n->body, 'priority' => $n->priority, 'id' => 'n'.$n->id]);

        return $messages->concat($notes)->sortBy(fn ($x) => $x->at->timestamp.'.'.$x->id)->values();
    }

    /** An internal note: an account Note on the customer (category Ticket), linked to this ticket. */
    public function addNote(string $body, User $by, ?string $priority = null, string $category = 'Ticket', string $action = 'Internal Note'): void
    {
        if ($this->customer_id) {
            Note::create(['customer_id' => $this->customer_id, 'user_id' => $by->id, 'author' => $by->name, 'category' => $category, 'action' => $action,
                'disposition' => $this->number(), 'priority' => $priority ?? 'Low', 'body' => $body, 'support_ticket_id' => $this->id]);
        } else {
            $this->messages()->create(['author' => 'note', 'body' => $body, 'user_id' => $by->id, 'created_at' => now()]);   // no account to file it on
        }
        $this->update(['last_activity_at' => now()]);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(SupportTicketAttachment::class);
    }

    public function watchers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'support_ticket_watchers')->withPivot('joined_at');
    }

    /** Tickets a person handled: owned, replied to, or wrote a note on. */
    public function scopeHandledBy(Builder $q, int $userId): void
    {
        $q->where(fn ($w) => $w->where('assigned_to', $userId)
            ->orWhereIn('id', SupportTicketMessage::where('user_id', $userId)->whereIn('author', ['agent', 'note'])->select('support_ticket_id'))
            ->orWhereIn('id', Note::where('user_id', $userId)->whereNotNull('support_ticket_id')->select('support_ticket_id')));
    }

    /**
     * Search everything about a ticket: its number, subject and messages, notes, the customer
     * (name, email, phone, account) and the owner. $in limits it to some of: text, notes, people.
     */
    public function scopeSearch(Builder $q, string $term, array $in = []): void
    {
        $term = trim($term);
        $in = $in ?: ['text', 'notes', 'people'];
        $like = '%'.$term.'%';
        $digits = preg_replace('/\D/', '', $term);
        $phone = "replace(replace(replace(replace(replace(phone, '(', ''), ')', ''), '-', ''), ' ', ''), '.', '')";
        $q->where(function ($w) use ($term, $in, $like, $digits, $phone) {
            // RT-10023 or 10023 or 23: the ticket itself
            if (preg_match('/^(?:RT-?)?(\d+)$/i', $term, $m)) {
                $n = (int) $m[1];
                $w->orWhereIn('id', array_filter([$n, $n - 10000], fn ($i) => $i > 0));
            }
            if (in_array('text', $in, true)) {
                $w->orWhere('subject', 'like', $like)->orWhere('topic', 'like', $like)
                    ->orWhereIn('id', SupportTicketMessage::where('author', '!=', 'note')->where('body', 'like', $like)->select('support_ticket_id'));
            }
            if (in_array('notes', $in, true)) {
                $w->orWhereIn('id', SupportTicketMessage::where('author', 'note')->where('body', 'like', $like)->select('support_ticket_id'))
                    ->orWhereIn('id', Note::whereNotNull('support_ticket_id')->where('body', 'like', $like)->select('support_ticket_id'));
            }
            if (in_array('people', $in, true)) {
                $w->orWhere('name', 'like', $like)->orWhere('email', 'like', $like)
                    ->orWhereIn('customer_id', Customer::where(fn ($c) => $c->where('name', 'like', $like)->orWhere('email', 'like', $like)->orWhere('account', 'like', $like)
                        ->when(strlen($digits) >= 3, fn ($c) => $c->orWhereRaw("$phone like ?", ['%'.$digits.'%'])))->select('id'))
                    ->orWhereIn('assigned_to', User::where('name', 'like', $like)->select('id'));
            }
        });
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
    public function post(string $author, string $body, ?User $user = null, array $extra = []): SupportTicketMessage
    {
        $m = $this->messages()->create(['author' => $author, 'body' => $body, 'user_id' => $user?->id, 'created_at' => now()] + $extra);
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
