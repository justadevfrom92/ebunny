<?php

namespace App\Models;

use App\Models\Concerns\RecordsHistory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Collection;

/** A customer account. Customers sign in to My Account with this model (guard "customer"). */
class Customer extends Authenticatable
{
    use RecordsHistory;

    protected $guarded = ['id'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'autopay' => 'boolean', 'paperless' => 'boolean', 'peak_perks' => 'boolean',
            'balance' => 'float', 'stars' => 'integer', 'start_date' => 'date',
            'requested_start' => 'date', 'actual_start' => 'date', 'due_date' => 'date', 'marketing_opt_in' => 'boolean',
            'authorized_users' => 'array', 'linked_accounts' => 'array',
            'password' => 'hashed', 'deposit_due' => 'float',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'account';
    }

    public function market(): BelongsTo
    {
        return $this->belongsTo(Market::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->latest('paid_on');
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class)->latest();
    }

    public function bills(): HasMany
    {
        return $this->hasMany(Bill::class)->latest('billed_on');
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(Note::class)->latest()->latest('id');
    }

    public function flags(): HasMany
    {
        return $this->hasMany(CustomerFlag::class)->whereNull('removed_at')->oldest();
    }

    public function products(): HasMany
    {
        return $this->hasMany(CustomerProduct::class)->whereNull('removed_at')->oldest();
    }

    public function ledger(): HasMany
    {
        return $this->hasMany(LedgerEntry::class)->latest();
    }

    public function paymentMethods(): HasMany
    {
        return $this->hasMany(PaymentMethod::class)->latest();
    }

    public function planTerms(): HasMany
    {
        return $this->hasMany(PlanTerm::class)->orderBy('ordered_at')->orderBy('id');
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(ServiceAddress::class)->orderBy('ordered_at');
    }

    public function ercotTransactions(): HasMany
    {
        return $this->hasMany(ErcotTransaction::class)->orderBy('created_at');
    }

    public function queueLogs(): HasMany
    {
        return $this->hasMany(QueueLog::class)->orderBy('entered_at')->orderBy('id');
    }

    public function files(): HasMany
    {
        return $this->hasMany(CustomerFile::class)->oldest();
    }

    public function contactLogs(): HasMany
    {
        return $this->hasMany(ContactLog::class)->latest();
    }

    public function starEntries(): HasMany
    {
        return $this->hasMany(StarEntry::class)->latest()->latest('id');
    }

    /**
     * Star history, newest first: stars earned and spent, plus monthly drawing entries (no stars;
     * the drawing is a free offer). Each row: at, reason, stars (null for an entry), drawing (bool).
     */
    public function starHistory(?int $limit = null): Collection
    {
        $stars = $this->starEntries()->when($limit, fn ($q) => $q->limit($limit))->get()
            ->map(fn ($s) => (object) ['at' => $s->created_at, 'reason' => $s->reason, 'stars' => $s->stars, 'drawing' => false]);
        $entries = HistoryItem::where('customer_id', $this->id)->where('model', 'ItemDrawingEntry_model')->latest()->when($limit, fn ($q) => $q->limit($limit))->get()
            ->map(fn ($h) => (object) ['at' => $h->created_at, 'reason' => 'Monthly drawing entry — '.$h->created_at->format('F Y'), 'stars' => null, 'drawing' => true]);

        return $stars->concat($entries)->sortByDesc(fn ($r) => $r->at->timestamp)->values()->when($limit, fn ($c) => $c->take($limit));
    }

    public function apiLogs(): HasMany
    {
        return $this->hasMany(ApiLog::class)->latest('created_at');
    }

    public function phonecalls(): HasMany
    {
        return $this->hasMany(Phonecall::class)->latest('started_at');
    }

    public function scheduledPayments(): HasMany
    {
        return $this->hasMany(ScheduledPayment::class)->orderBy('pay_at');
    }

    /**
     * The properties (service accounts) this My Account login can switch between: itself plus its
     * linked accounts. A business with several locations has one per location.
     */
    public function properties(): Collection
    {
        return collect([$this])->merge(self::whereIn('account', $this->linked_accounts ?? [])->where('id', '!=', $this->id)->orderBy('address')->get());
    }

    /** "123 Main St, Austin" — how a property is named in the switcher. */
    public function propertyLabel(): string
    {
        return trim(($this->address ?: 'Account '.$this->account).($this->unit ? ' #'.$this->unit : '').($this->city ? ', '.$this->city : ''));
    }

    public function hasProduct(string $name): bool
    {
        return $this->products()->where('product', $name)->exists();
    }

    /** Queues the account is in now (queue log rows not yet exited). */
    public function currentQueues()
    {
        return $this->queueLogs()->whereNull('exited_at')->get();
    }

    /** Recompute the stars balance from the ledger. */
    public function syncStars(): void
    {
        $this->forceFill(['stars' => (int) round($this->starEntries()->sum('stars'))])->saveQuietly();
    }

    /** Pill color for the status, from config('admin.customer_statuses'). */
    public function statusTone(): string
    {
        return config('admin.customer_statuses.'.$this->status, '');
    }
}
