<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

/**
 * Something an admin user should know about: a report or export finished, a new support ticket, a customer
 * writing back on a ticket, a new website email, a file added, a campaign sent. It adds to the number on
 * their avatar until they open it. Each person picks the kinds they want in Settings.
 */
class AdminNotification extends Model
{
    public const UPDATED_AT = null;

    /** kind => [label in Settings, who gets it, icon] */
    public const KINDS = [
        'ticket_new' => ['New support tickets', 'Ranger team: a customer opened a ticket', '🎫'],
        'ticket_reply' => ['Support ticket chats', 'Your tickets: the customer wrote back (My Account or email)', '💬'],
        'email' => ['Emails', 'New Web Messages from the website contact form', '✉'],
        'report' => ['Reports done', 'Your Walker reports finished running', '📊'],
        'file' => ['Files finished', 'Your exports are ready, and new files in Walker folders', '📁'],
        'campaign' => ['Campaigns finished', 'Rodeo campaigns finished sending', '📣'],
    ];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['read_at' => 'datetime', 'created_at' => 'datetime'];
    }

    /** Links are kept as paths ("/admin/ranger/tickets/12"), so they work on any host the admin runs on. */
    protected function setUrlAttribute(?string $url): void
    {
        $this->attributes['url'] = $url === null ? null : (parse_url($url, PHP_URL_PATH) ?: '/').(($q = parse_url($url, PHP_URL_QUERY)) ? '?'.$q : '');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Send to each person who wants this kind (on unless they turned it off in Settings). */
    public static function notify(iterable|User $users, string $kind, string $title, ?string $body = null, ?string $url = null): int
    {
        $n = 0;
        foreach ($users instanceof User ? [$users] : $users as $u) {
            if ($u && $u->active && $u->wantsNotification($kind)) {
                self::create(['user_id' => $u->id, 'kind' => $kind, 'title' => mb_substr($title, 0, 200), 'body' => $body ? mb_substr($body, 0, 500) : null, 'url' => $url, 'created_at' => now()]);
                $n++;
            }
        }

        return $n;
    }

    /** Everyone whose role (or own rights) opens an app. */
    public static function appUsers(string $perm): Collection
    {
        return User::where('active', true)->with('role')->get()->filter(fn ($u) => $u->hasPerm($perm));
    }

    public function icon(): string
    {
        return self::KINDS[$this->kind][2] ?? '🔔';
    }
}
