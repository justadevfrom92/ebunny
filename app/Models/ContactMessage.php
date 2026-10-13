<?php

namespace App\Models;

use App\Models\Concerns\RecordsHistory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A message sent from the website's Contact Us form. */
class ContactMessage extends Model
{
    use RecordsHistory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['handled_at' => 'datetime', 'replied_at' => 'datetime'];
    }

    public const TOPICS = ['general' => 'General question', 'signup' => 'Sign up for service', 'billing' => 'Billing & payments', 'business' => 'Business service', 'rewards' => 'Rewards'];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    public function replier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'replied_by');
    }

    public function topicLabel(): string
    {
        return self::TOPICS[$this->topic] ?? ucfirst((string) $this->topic);
    }

    /** The account this message is probably about: same email, else same phone number. */
    public function match(): ?Customer
    {
        if ($this->customer_id) {
            return $this->customer;
        }
        $digits = preg_replace('/\D/', '', (string) $this->phone);

        return Customer::whereRaw('lower(email) = ?', [strtolower($this->email)])->first()
            ?? ($digits !== '' ? Customer::whereRaw("replace(replace(replace(replace(phone, '(', ''), ')', ''), '-', ''), ' ', '') like ?", ['%'.substr($digits, -10)])->first() : null);
    }

    public function scopeOpen(Builder $query): void
    {
        $query->whereNull('handled_at');
    }
}
