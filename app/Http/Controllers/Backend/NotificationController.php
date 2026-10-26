<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\AdminNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Your notifications (the number on your avatar): the full list, opening one (marks it read), mark all read;
 * and Settings: which kinds you get, and (when testing) switching to another sample user.
 */
class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        $f = $request->validate(['kind' => ['nullable', 'in:'.implode(',', array_keys(AdminNotification::KINDS))], 'unread' => ['nullable', 'boolean']]);
        $items = $request->user()->adminNotifications()->when($f['kind'] ?? null, fn ($q, $k) => $q->where('kind', $k))
            ->when($request->boolean('unread'), fn ($q) => $q->whereNull('read_at'))->paginate(25)->withQueryString();

        return view('backend.notifications.index', ['items' => $items, 'f' => $f, 'unread' => $request->user()->adminNotifications()->whereNull('read_at')->count()]);
    }

    public function open(Request $request, AdminNotification $notification): RedirectResponse
    {
        abort_unless($notification->user_id === $request->user()->id, 404);
        $notification->update(['read_at' => $notification->read_at ?? now()]);

        return $notification->url ? redirect($notification->url) : redirect()->route('admin.notifications.index');
    }

    public function readAll(Request $request): RedirectResponse
    {
        $request->user()->adminNotifications()->whereNull('read_at')->update(['read_at' => now()]);

        return back()->with('status', 'All notifications marked read');
    }

    public function settings(Request $request): View
    {
        return view('backend.notifications.settings', ['user' => $request->user(),
            'testUsers' => AuthController::testUsers()->reject(fn ($u) => $u->is($request->user()))]);
    }

    public function saveSettings(Request $request): RedirectResponse
    {
        $on = $request->validate(['kinds' => ['array'], 'kinds.*' => ['in:'.implode(',', array_keys(AdminNotification::KINDS))]])['kinds'] ?? [];
        $request->user()->update(['notification_prefs' => collect(AdminNotification::KINDS)->map(fn ($k, $kind) => in_array($kind, $on, true))->all()]);

        return back()->with('status', 'Notification settings saved');
    }
}
