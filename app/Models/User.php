<?php

namespace App\Models;

use App\Models\Concerns\RecordsHistory;
use App\Support\AdminApps;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'role_id', 'active', 'last_login_at', 'employee_id', 'title', 'department', 'manager_id', 'phone_ext', 'location', 'hired_on', 'company', 'access_ends_on', 'extra_perms', 'denied_perms', 'access_note'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, RecordsHistory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'active' => 'boolean',
            'hired_on' => 'date',
            'access_ends_on' => 'date',
            'extra_perms' => 'array',
            'denied_perms' => 'array',
        ];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function bookmarks(): BelongsToMany
    {
        return $this->belongsToMany(Customer::class, 'bookmarks')->withTimestamps();
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    /** Direct reports (Bunkhouse org chart). */
    public function reports(): HasMany
    {
        return $this->hasMany(User::class, 'manager_id');
    }

    public function checklistItems(): HasMany
    {
        return $this->hasMany(ErmChecklistItem::class);
    }

    public function isExternal(): bool
    {
        return filled($this->company);
    }

    /** "3 years, 2 months" since hired_on. */
    public function tenure(): ?string
    {
        if (! $this->hired_on) {
            return null;
        }
        $d = $this->hired_on->diff(now());

        return trim(($d->y ? $d->y.' '.str('year')->plural($d->y).' ' : '').($d->m ? $d->m.' '.str('month')->plural($d->m) : ($d->y ? '' : 'under a month')));
    }

    /**
     * True when this person may use an admin app (corral, lando, …) or a right (delete, refunds…):
     * their role grants it or it was added for them, and it wasn't taken away from them.
     */
    public function hasPerm(string $perm): bool
    {
        if (! $this->active || in_array($perm, $this->denied_perms ?? [], true)) {
            return false;
        }

        return in_array($perm, $this->role?->perms ?? [], true) || in_array($perm, $this->extra_perms ?? [], true);
    }

    /** How this person's access differs from their role: [perm => 'added' | 'removed']. */
    public function accessChanges(): array
    {
        return array_fill_keys($this->extra_perms ?? [], 'added') + array_fill_keys($this->denied_perms ?? [], 'removed');
    }

    /** The admin apps (built-in and custom) this user can open, keyed by app key. */
    public function apps(): array
    {
        return array_filter(AdminApps::all(), fn ($app, $key) => $this->hasPerm($key), ARRAY_FILTER_USE_BOTH);
    }

    public function initials(): string
    {
        return collect(explode(' ', $this->name))->map(fn ($w) => mb_substr($w, 0, 1))->take(2)->implode('');
    }
}
