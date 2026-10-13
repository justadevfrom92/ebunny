<?php

namespace App\Models;

use App\Models\Concerns\RecordsHistory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** An email or text sent to a group of customers (Rodeo → Campaigns). */
class Campaign extends Model
{
    use RecordsHistory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['audience' => 'array', 'sent_at' => 'datetime', 'scheduled_at' => 'datetime'];
    }

    /** What the campaign is for: [label, what counts as a conversion]. */
    public const GOALS = [
        'renewal' => ['Renewal', 'renewed or signed a new plan'],
        'product' => ['Product sign-up', 'added the product'],
        'winback' => ['Win back', 'came back with a new plan'],
        'rewards' => ['Rewards', 'redeemed stars'],
        'announcement' => ['Announcement', null],
    ];

    /** How many days after sending a conversion still counts. */
    public const WINDOW = 30;

    public function template(): BelongsTo
    {
        return $this->belongsTo(EmailTemplate::class, 'email_template_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ContactLog::class);
    }

    /** Customers the audience filters select. Marketing only goes to customers who opted in. */
    public function audienceQuery(bool $optedInOnly = true): Builder
    {
        $a = $this->audience ?? [];

        return Customer::query()->when($optedInOnly, fn ($q) => $q->where('marketing_opt_in', true))
            ->when($this->channel === 'SMS', fn ($q) => $q->where('phone_type', 'mobile'))
            ->when($this->channel === 'Email', fn ($q) => $q->whereNotNull('email'))
            ->when($a['statuses'] ?? null, fn ($q, $s) => $q->whereIn('status', $s))
            ->when($a['type'] ?? null, fn ($q, $t) => $q->where('type', $t))
            ->when($a['market_id'] ?? null, fn ($q, $m) => $q->where('market_id', $m))
            ->when($a['market_ids'] ?? null, fn ($q, $m) => $q->whereIn('market_id', $m))
            ->when($a['autopay'] ?? null, fn ($q, $v) => $q->where('autopay', $v === 'yes'))
            ->when($a['balance'] ?? null, fn ($q, $v) => $v === 'owes' ? $q->where('balance', '>', 0) : $q->where('balance', '<=', 0))
            ->when($a['product'] ?? null, fn ($q, $p) => $q->whereHas('products', fn ($x) => $x->where('product', $p)->whereNull('removed_at')))
            ->when($a['without_product'] ?? null, fn ($q, $p) => $q->whereDoesntHave('products', fn ($x) => $x->where('product', $p)->whereNull('removed_at')))
            ->when($a['contract_ends_within'] ?? null, fn ($q, $days) => $q->whereHas('planTerms', fn ($x) => $x->where('status', 'current')->whereBetween('contract_end', [today(), today()->addDays((int) $days)])));
    }

    /** Who matches but is left out, and why: not opted in, on the suppression list, contacted recently. */
    public function exclusions(): array
    {
        $matching = $this->audienceQuery(false);
        $optedIn = $this->audienceQuery();

        return [
            'not_opted_in' => (clone $matching)->count() - (clone $optedIn)->count(),
            'suppressed' => $this->channel === 'Email' ? (clone $optedIn)->whereIn(\DB::raw('lower(email)'), EmailSuppression::pluck('email')->map(fn ($e) => strtolower($e)))->count() : 0,
            'recent' => $this->cap_days ? (clone $optedIn)->whereHas('contactLogs', fn ($q) => $q->whereNotNull('campaign_id')->where('created_at', '>=', now()->subDays($this->cap_days)))->count() : 0,
        ];
    }

    /**
     * Sends to the audience: one message per customer (Salesforce for email, Twilio for texts
     * when set up in .env; otherwise logged). Skips suppressed addresses and anyone who got a
     * campaign within cap_days. Returns [sent, skipped].
     */
    public function send(bool $ready): array
    {
        $count = $skipped = 0;
        \DB::transaction(function () use ($ready, &$count, &$skipped) {
            $this->audienceQuery()->with('plan')->chunkById(200, function ($customers) use ($ready, &$count, &$skipped) {
                foreach ($customers as $c) {
                    $recent = $this->cap_days && $c->contactLogs()->whereNotNull('campaign_id')->where('created_at', '>=', now()->subDays($this->cap_days))->exists();
                    if ($recent || ($this->channel === 'Email' && EmailSuppression::has($c->email))) {
                        $skipped++;

                        continue;
                    }
                    ContactLog::create(['customer_id' => $c->id, 'campaign_id' => $this->id, 'channel' => $this->channel,
                        'template' => $this->channel === 'Email' ? $this->template?->name : 'Campaign: '.$this->name,
                        'body' => $this->channel === 'Email' ? strip_tags($this->template?->render($c) ?? '') : $this->textFor($c),
                        'phone' => $this->channel === 'SMS' ? $c->phone : null, 'status' => $ready ? 'queued' : 'not sent']);
                    $count++;
                }
            });
            $this->update(['status' => 'sent', 'sent_at' => now(), 'recipients' => $count, 'skipped' => $skipped]);
        });

        return [$count, $skipped];
    }

    /** The text message for one customer, with {{first_name}} and {{promo_code}} filled in. */
    public function textFor(Customer $c): string
    {
        return strtr((string) $this->message, ['{{first_name}}' => $c->first_name ?: 'there', '{{promo_code}}' => (string) $this->promo_code]);
    }

    /**
     * The funnel for a sent campaign: sent, delivered, opened, clicked (email), converted
     * (the goal reached within WINDOW days by a recipient).
     */
    public function results(): array
    {
        $m = $this->messages();
        $sent = (clone $m)->count();
        $delivered = (clone $m)->whereNotIn('status', ['failed', 'not sent', 'suppressed'])->whereNull('dropped_at')->count();
        $email = $this->channel === 'Email';

        return ['sent' => $sent, 'delivered' => $delivered,
            'opened' => $email ? (clone $m)->whereNotNull('opened_at')->count() : null,
            'clicked' => $email ? (clone $m)->whereNotNull('clicked_at')->count() : null,
            'converted' => $this->converted()->count()];
    }

    /** Recipients who reached the goal after the campaign was sent. */
    public function converted(): Builder
    {
        $ids = $this->messages()->pluck('customer_id');
        $from = $this->sent_at ?? now();
        $to = $from->copy()->addDays(self::WINDOW);
        $q = Customer::whereIn('id', $ids);

        return match ($this->goal) {
            'product' => $q->whereHas('products', fn ($x) => $x->where('product', $this->product)->whereBetween('created_at', [$from, $to])),
            'renewal', 'winback' => $q->whereHas('planTerms', fn ($x) => $x->whereBetween('ordered_at', [$from, $to])),
            'rewards' => $q->whereHas('starEntries', fn ($x) => $x->where('stars', '<', 0)->whereBetween('created_at', [$from, $to])),
            default => $q->whereRaw('0 = 1'),
        };
    }

    public function goalLabel(): string
    {
        return self::GOALS[$this->goal][0] ?? ucfirst((string) $this->goal);
    }
}
