<?php

namespace Database\Seeders;

use App\Models\AdminNotification;
use App\Models\Campaign;
use App\Models\ContactMessage;
use App\Models\RangerAgent;
use App\Models\ReportRun;
use App\Models\ReportUpload;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Database\Seeder;

/** Sample notifications (fictional) so every admin user's avatar number, menu and Notifications page have something in them. */
class AdminNotificationSeeder extends Seeder
{
    public function run(): void
    {
        mt_srand(77);
        foreach (User::where('active', true)->get() as $u) {
            $items = [];
            if (RangerAgent::where('user_id', $u->id)->where('active', true)->exists()) {
                foreach (SupportTicket::latest('id')->limit(6)->get() as $t) {
                    $items[] = ['ticket_new', 'New ticket '.$t->number().': '.$t->subject, $t->name.' · '.(SupportTicket::TOPICS[$t->topic] ?? ''), route('ranger.tickets.show', $t), $t->created_at];
                }
                foreach (SupportTicket::active()->where('assigned_to', $u->id)->limit(3)->get() as $t) {
                    $items[] = ['ticket_reply', $t->name.' wrote back on '.$t->number(), 'Thanks, one more question about this…', route('ranger.tickets.show', $t), ($t->last_activity_at ?? $t->created_at)];
                }
            }
            if ($u->hasPerm('corral')) {
                foreach (ContactMessage::latest('id')->limit(3)->get() as $m) {
                    $items[] = ['email', 'New email from '.$m->first_name.' '.$m->last_name, $m->topic.': '.mb_substr((string) $m->message, 0, 120), route('corral.messages.show', $m), $m->created_at];
                }
            }
            foreach (ReportRun::where('user_id', $u->id)->where('status', 'done')->latest('finished_at')->limit(3)->get() as $r) {
                $items[] = [$r->app === 'walker' ? 'report' : 'file', ($r->app === 'walker' ? 'Report ready: ' : 'Export ready: ').$r->label(), number_format((int) $r->rows).' rows · '.$r->formatLabel(), route('admin.files.show', $r), $r->finished_at];
            }
            if ($u->hasPerm('walker')) {
                foreach (ReportUpload::latest('id')->limit(2)->get() as $up) {
                    $items[] = ['file', 'New file in '.$up->category.': '.$up->title, 'Added by '.($up->user?->name ?? 'someone'), route('walker.uploads.show', $up), $up->created_at];
                }
            }
            if ($u->hasPerm('rodeo')) {
                foreach (Campaign::where('status', 'sent')->latest('sent_at')->limit(2)->get() as $c) {
                    $items[] = ['campaign', 'Campaign sent: '.$c->name, number_format((int) $c->recipients).' sent, '.number_format((int) $c->skipped).' skipped', route('rodeo.campaigns.edit', $c), $c->sent_at];
                }
            }
            foreach ($items as $i => [$kind, $title, $body, $url, $at]) {
                $at = $at ?? now()->subHours($i + 1);
                AdminNotification::create(['user_id' => $u->id, 'kind' => $kind, 'title' => mb_substr($title, 0, 200), 'body' => $body, 'url' => $url,
                    'created_at' => $at, 'read_at' => $at->lt(now()->subDays(2)) || mt_rand(0, 2) === 0 ? $at->copy()->addHour() : null]);
            }
        }
    }
}
