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

#[Fillable(['name', 'email', 'password', 'role_id', 'active', 'last_login_at', 'employee_id', 'title', 'department', 'manager_id', 'phone_ext', 'location', 'hired_on', 'company', 'access_ends_on', 'extra_perms', 'denied_perms', 'access_note', 'notification_prefs'])]
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
            'notification_prefs' => 'array',
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

    /** People who report to this person (or, for a 3rd-party user's contact, the users they look after). */
    public function reports(): HasMany
    {
        return $this->hasMany(User::class, 'manager_id');
    }

    /** "3 years, 2 months" since hired_on. */
    public function tenure(): ?string
    {
        if (! $this->hired_on || $this->hired_on->isFuture()) {
            return null;
        }
        $d = $this->hired_on->diff(now());

        return trim(($d->y ? $d->y.' '.str('year')->plural($d->y).' ' : '').($d->m ? $d->m.' '.str('month')->plural($d->m) : ($d->y ? '' : 'under a month')));
    }

    /** Every app and right this person has. */
    public function perms(): array
    {
        $all = array_merge(array_keys(AdminApps::all()), array_keys(config('admin.permissions')));

        return array_values(array_filter($all, fn ($p) => $this->hasPerm($p)));
    }

    /**
     * Log in as: allowed for someone with Sheriff, for an active person other than themselves,
     * and only when the target has nothing the signed-in person lacks (no gaining access that way).
     * Returns null when allowed, or the reason it isn't.
     */
    public function cannotLogInAs(User $target): ?string
    {
        return match (true) {
            ! $this->hasPerm('sheriff') => 'Needs Sheriff.',
            $this->is($target) => 'This is you.',
            ! $target->active => 'Their sign-in is disabled.',
            (bool) array_diff($target->perms(), $this->perms()) => 'They have access you don\'t ('.collect(array_diff($target->perms(), $this->perms()))->map(fn ($p) => AdminApps::get($p)['name'] ?? ucfirst($p))->implode(', ').').',
            default => null,
        };
    }

    /**
     * True when this person may use an admin app (corral, lando, …) or a right (delete, refunds…):
     * their role grants it or it was added for them, and it wasn't taken away from them.
     */
    /** Whether this person wants a kind of notification (all kinds are on until they turn one off in Settings). */
    public function wantsNotification(string $kind): bool
    {
        return ($this->notification_prefs[$kind] ?? true) !== false;
    }

    public function adminNotifications(): HasMany
    {
        return $this->hasMany(AdminNotification::class)->latest('created_at')->latest('id');
    }

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
