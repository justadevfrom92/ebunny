<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\AdminNotification;
use App\Models\CrmActivity;
use App\Models\Customer;
use App\Models\HistoryItem;
use App\Models\LeadApproval;
use App\Models\ReportRun;
use App\Models\SavedReport;
use App\Models\SupportTicket;
use App\Models\User;
use App\Models\UserBlock;
use App\Models\UserSignIn;
use App\Support\AdminApps;
use App\Support\History;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/** Your own pages in the Account area: My Profile (the dashboard, with your access, sign-ins and activity) and Change Password. */
class AccountController extends Controller
{
    /** The Account dashboard: your details, what you can open, where you're signed in, your sign-ins and your activity. */
    public function profile(Request $request): View
    {
        $u = $request->user()->load(['role', 'manager']);
        $q = trim((string) $request->query('q'));
        $group = (string) $request->query('group');

        return view('backend.account.profile', [
            'u' => $u,
            'apps' => AdminApps::all(),
            'rights' => config('admin.permissions'),
            'changes' => $u->accessChanges(),
            'signIns' => self::signIns($request),
            'blocks' => UserBlock::where('user_id', $u->id)->latest()->get(),
            'items' => HistoryItem::with('customer')->where('user_id', $u->id)
                ->when($q !== '', fn ($w) => $w->where('summary', 'like', '%'.$q.'%'))
                ->when($group !== '', fn ($w) => $w->where('group', $group))
                ->latest('created_at')->latest('id')->paginate(10, ['*'], 'activity_page')->withQueryString()->fragment('activity'),
            'q' => $q, 'group' => $group,
            'groups' => HistoryItem::where('user_id', $u->id)->distinct()->orderBy('group')->pluck('group'),
            'saved' => SavedReport::where('user_id', $u->id)->latest()->get(),
        ]);
    }

    /** You can change your name and phone extension; Sheriff sets the rest. */
    public function saveProfile(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:120'], 'phone_ext' => ['nullable', 'string', 'max:20']]);
        $request->user()->update($data);

        return redirect()->route('admin.account.profile')->with('status', 'Profile saved.');
    }

    public function password(Request $request): View
    {
        return view('backend.account.password', ['loggedInAs' => $request->session()->has('impersonator_id')]);
    }

    public function savePassword(Request $request): RedirectResponse
    {
        // While using "Log in as" you're not this person, so you can't change their password
        abort_if($request->session()->has('impersonator_id'), 403);
        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', 'different:current_password', Password::min(8)->letters()->numbers()],
        ], ['current_password.current_password' => 'That is not your current password.', 'password.different' => 'Pick a password different from your current one.']);
        $user = $request->user();
        $user->forceFill(['password' => Hash::make($request->input('password'))])->save();
        History::record(['model' => 'AdminLogin_model', 'group' => 'Logins', 'record_id' => $user->id, 'action' => 'updated', 'summary' => 'Changed their password']);

        return redirect()->route('admin.account.password')->with('status', 'Password changed.');
    }

    /** Browsers you're signed in on (database sessions), this one first. */
    public static function devices(Request $request)
    {
        if (config('session.driver') !== 'database' || ! Schema::hasTable(config('session.table', 'sessions'))) {
            return collect();
        }
        $current = $request->session()->getId();

        return DB::table(config('session.table', 'sessions'))->where('user_id', $request->user()->id)->orderByDesc('last_activity')->get()
            ->map(function ($s) use ($current) {
                $data = self::sessionData((string) $s->payload);
                $by = isset($data['impersonator_id']) ? User::find($data['impersonator_id']) : null;

                return (object) ['key' => hash('sha256', $s->id), 'id' => $s->id, 'current' => $s->id === $current, 'ip' => $s->ip_address, 'ua' => (string) $s->user_agent,
                    'browser' => self::browser((string) $s->user_agent), 'last' => Carbon::createFromTimestamp($s->last_activity),
                    'since' => isset($data['signed_in_at']) ? Carbon::createFromTimestamp($data['signed_in_at']) : null,
                    'logged_in_as_by' => $by];
            })
            ->sortByDesc('current')->values();
    }

    /** What a stored session holds (database sessions are base64 of JSON or serialized data, unless encrypted). */
    private static function sessionData(string $payload): array
    {
        try {
            $raw = base64_decode($payload, true);
            if ($raw === false) {
                return [];
            }
            if (config('session.encrypt')) {
                $raw = decrypt($raw, false);
            }
            $data = config('session.serialization') === 'json' ? json_decode($raw, true) : @unserialize($raw, ['allowed_classes' => false]);

            return is_array($data) ? $data : [];
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Your sign-in history, 10 per page: where you're signed in now at the top (this browser first), then the rest
     * newest first. Each row carries ->device (the live session) while it's signed in.
     */
    public static function signIns(Request $request)
    {
        $u = $request->user();
        $devices = self::devices($request)->keyBy('key');
        // Sessions from before sign-ins were recorded get a record now
        foreach ($devices as $key => $d) {
            if (! UserSignIn::where('session_key', $key)->whereNull('ended_at')->exists()) {
                UserSignIn::create(['user_id' => $u->id, 'impersonator_id' => $d->logged_in_as_by?->id, 'session_key' => $key, 'ip' => $d->ip, 'user_agent' => $d->ua,
                    'how' => $d->logged_in_as_by ? 'log_in_as' : 'password', 'signed_in_at' => $d->since ?? $d->last]);
            }
        }
        $keys = $devices->keys()->all();
        $current = hash('sha256', $request->session()->getId());
        $q = UserSignIn::with('impersonator')->where('user_id', $u->id);
        if ($keys) {
            $marks = implode(',', array_fill(0, count($keys), '?'));
            $q->orderByRaw('CASE WHEN session_key = ? THEN 0 WHEN ended_at IS NULL AND session_key IN ('.$marks.') THEN 1 ELSE 2 END', [$current, ...$keys]);
        }
        $page = $q->latest('signed_in_at')->latest('id')->paginate(10, ['*'], 'signins_page')->withQueryString()->fragment('signed-in');
        $page->getCollection()->each(fn ($s) => $s->device = ! $s->ended_at && $s->session_key ? $devices->get($s->session_key) : null);

        return $page;
    }

    /** One of your sign-ins, with ->device while it's still signed in. */
    private function signInOr404(Request $request, UserSignIn $signIn): UserSignIn
    {
        abort_unless($signIn->user_id === $request->user()->id, 404);
        $signIn->device = ! $signIn->ended_at && $signIn->session_key ? self::devices($request)->firstWhere('key', $signIn->session_key) : null;

        return $signIn;
    }

    /** One sign-in: what it is, and while it's signed in, buttons to sign it out, block it or report it. */
    public function device(Request $request, UserSignIn $signIn): View
    {
        $s = $this->signInOr404($request, $signIn->load('impersonator'));

        return view('backend.account.device', ['s' => $s, 'd' => $s->device, 'u' => $request->user(), 'loggedInAs' => $request->session()->has('impersonator_id'),
            'blocked' => UserBlock::where('user_id', $request->user()->id)->where('ip', $s->ip)->where('user_agent', (string) $s->user_agent)->first(),
            'sameDevice' => $s->ip === $request->ip() && (string) $s->user_agent === (string) $request->userAgent(),
            'fromHere' => UserSignIn::where('user_id', $request->user()->id)->where('ip', $s->ip)->whereKeyNot($s->id)->latest('signed_in_at')->limit(5)->get()]);
    }

    /** End that sign-in. Your own browser signs you out. */
    public function deviceSignOut(Request $request, UserSignIn $signIn): RedirectResponse
    {
        abort_if($request->session()->has('impersonator_id'), 403);
        $s = $this->signInOr404($request, $signIn);
        $d = $s->device ?? abort(404, 'That sign-in has already ended.');
        if ($d->current) {
            UserSignIn::end($request, 'Signed out');
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('admin.login')->with('status', 'You are signed out.');
        }
        $by = $s->impersonator;
        $this->endSession($request, $d, $by ? 'Ended '.$by->name.'\'s Log in as' : 'Signed out '.$d->browser.' ('.$d->ip.')', $by ? 'Log in as ended by you' : 'Signed out from another device');

        return redirect()->to(route('admin.account.profile').'#signed-in')->with('status', $by ? $by->name.' is no longer logged in as you.' : 'Signed out '.$d->browser.'.');
    }

    /** Keep that device (this IP address with this browser) from signing in to your account again, signing it out if it's signed in. */
    public function deviceBlock(Request $request, UserSignIn $signIn): RedirectResponse
    {
        $d = $this->blockable($request, $signIn);
        $this->block($request, $d, null);

        return redirect()->to(route('admin.account.profile').'#signed-in')->with('status', 'Blocked '.$d->browser.' at '.$d->ip.'.'.($d->id ? ' It was signed out and' : ' It').' can\'t sign in to your account.');
    }

    /** Block it, and tell the administrators (Sheriff) so they can look into it. */
    public function deviceReport(Request $request, UserSignIn $signIn): RedirectResponse
    {
        $d = $this->blockable($request, $signIn);
        $reason = trim((string) $request->validate(['reason' => ['nullable', 'string', 'max:500']])['reason'] ?? '');
        $this->block($request, $d, $reason ?: 'Reported: not recognized');
        $u = $request->user();
        AdminNotification::notify(AdminNotification::appUsers('sheriff')->reject(fn ($a) => $a->id === $u->id), 'security',
            $u->name.' reported a sign-in they don\'t recognize', $d->browser.' at '.$d->ip.($reason ? ' — '.$reason : '').'. It is blocked from their account.', route('sheriff.users.show', $u));

        return redirect()->to(route('admin.account.profile').'#signed-in')->with('status', 'Reported to the administrators. '.$d->browser.' at '.$d->ip.' is blocked.');
    }

    public function unblock(Request $request, UserBlock $block): RedirectResponse
    {
        abort_if($block->user_id !== $request->user()->id || $request->session()->has('impersonator_id'), 404);
        $block->delete();
        History::record(['model' => 'AdminLogin_model', 'group' => 'Logins', 'record_id' => $request->user()->id, 'action' => 'updated', 'summary' => 'Unblocked '.self::browser((string) $block->user_agent).' at '.$block->ip]);

        return redirect()->to(route('admin.account.profile').'#signed-in')->with('status', 'Unblocked. That device can sign in again.');
    }

    /** You can't block the browser you're using (or one that looks exactly like it), or act while using Log in as. */
    private function blockable(Request $request, UserSignIn $signIn): object
    {
        abort_if($request->session()->has('impersonator_id'), 403);
        $s = $this->signInOr404($request, $signIn);
        // A sign-in that already ended can still be blocked (its device), there's just no session to end
        $d = $s->device ?? (object) ['id' => null, 'current' => false, 'ip' => $s->ip, 'ua' => (string) $s->user_agent, 'browser' => $s->browser()];
        abort_if($d->current || ($d->ip === $request->ip() && $d->ua === (string) $request->userAgent()), 422, 'You can\'t block the browser you are using.');

        return $d;
    }

    private function block(Request $request, object $d, ?string $reason): void
    {
        UserBlock::firstOrCreate(['user_id' => $request->user()->id, 'ip' => $d->ip, 'user_agent' => $d->ua],
            ['reason' => $reason, 'reported_at' => $reason ? now() : null, 'created_by' => $request->user()->id]);
        $this->endSession($request, $d, ($reason ? 'Reported and blocked ' : 'Blocked ').$d->browser.' ('.$d->ip.')', $reason ? 'Reported and blocked by you' : 'Blocked by you');
    }

    /** Remove the sign-in, and reset "remember me" so it can't come back on its own. */
    private function endSession(Request $request, object $d, string $summary, string $reason): void
    {
        if (! $d->id) {   // already ended: nothing to sign out
            History::record(['model' => 'AdminLogin_model', 'group' => 'Logins', 'record_id' => $request->user()->id, 'action' => 'logged', 'summary' => $summary]);

            return;
        }
        UserSignIn::end($d->id, $reason);
        DB::table(config('session.table', 'sessions'))->where('id', $d->id)->where('user_id', $request->user()->id)->delete();
        $request->user()->forceFill(['remember_token' => Str::random(60)])->save();
        History::record(['model' => 'AdminLogin_model', 'group' => 'Logins', 'record_id' => $request->user()->id, 'action' => 'logged', 'summary' => $summary]);
    }

    /** "Chrome on Windows" from a user agent. */
    public static function browser(string $ua): string
    {
        if ($ua === '') {
            return 'Unknown browser';
        }
        $b = match (true) {
            str_contains($ua, 'Edg/') => 'Edge', str_contains($ua, 'OPR/') => 'Opera', str_contains($ua, 'Firefox/') => 'Firefox',
            str_contains($ua, 'Chrome/') || str_contains($ua, 'HeadlessChrome') => 'Chrome', str_contains($ua, 'Safari/') => 'Safari', default => 'Browser',
        };
        $os = match (true) {
            str_contains($ua, 'iPhone') || str_contains($ua, 'iPad') => 'iOS', str_contains($ua, 'Android') => 'Android', str_contains($ua, 'Windows') => 'Windows',
            str_contains($ua, 'Mac OS') => 'Mac', str_contains($ua, 'Linux') => 'Linux', default => null,
        };

        return $os ? "$b on $os" : $b;
    }

    /** Sign out everywhere except this browser. */
    public function signOutOthers(Request $request): RedirectResponse
    {
        abort_if($request->session()->has('impersonator_id'), 403);
        $n = 0;
        if (config('session.driver') === 'database') {
            foreach (DB::table(config('session.table', 'sessions'))->where('user_id', $request->user()->id)->where('id', '!=', $request->session()->getId())->pluck('id') as $id) {
                UserSignIn::end($id, 'Signed out from another device');
            }
            $n = DB::table(config('session.table', 'sessions'))->where('user_id', $request->user()->id)->where('id', '!=', $request->session()->getId())->delete();
        }
        $request->user()->forceFill(['remember_token' => Str::random(60)])->save();
        History::record(['model' => 'AdminLogin_model', 'group' => 'Logins', 'record_id' => $request->user()->id, 'action' => 'logged', 'summary' => 'Signed out their other devices']);

        return redirect()->to(route('admin.account.profile').'#signed-in')->with('status', $n ? 'Signed out of '.$n.' other '.Str::plural('device', $n).'.' : 'You were not signed in anywhere else.');
    }

    /** An app or right in What You Can Open: [name, description, kind], or 404. */
    private function permOr404(string $perm): array
    {
        $apps = AdminApps::all();
        if (isset($apps[$perm])) {
            return [$apps[$perm]['name'], $apps[$perm]['desc'] ?? null, 'app'];
        }
        $rights = config('admin.permissions');

        return isset($rights[$perm]) ? [$rights[$perm], null, 'right'] : abort(404);
    }

    /** Administrators (Sheriff access) can change their own access to one app or right, from What You Can Open. */
    private function canEditAccess(Request $request): bool
    {
        return $request->user()->hasPerm('sheriff') && ! $request->session()->has('impersonator_id');
    }

    public function accessEdit(Request $request, string $perm): View
    {
        abort_unless($this->canEditAccess($request), 403);
        [$name, $desc, $kind] = $this->permOr404($perm);
        $u = $request->user()->load('role');
        $change = $u->accessChanges()[$perm] ?? null;

        return view('backend.account.access-edit', ['u' => $u, 'perm' => $perm, 'name' => $name, 'desc' => $desc, 'kind' => $kind,
            'inRole' => in_array($perm, $u->role?->perms ?? [], true), 'has' => $u->hasPerm($perm),
            'choice' => $change === 'added' ? 'allow' : ($change === 'removed' ? 'deny' : 'role')]);
    }

    /** From your role, always allow, or never allow (the same per-person exceptions as Sheriff → Users). */
    public function accessSave(Request $request, string $perm): RedirectResponse
    {
        abort_unless($this->canEditAccess($request), 403);
        [$name] = $this->permOr404($perm);
        $data = $request->validate(['access' => ['required', 'in:role,allow,deny'], 'access_note' => ['nullable', 'string', 'max:250']]);
        $u = $request->user();
        if ($perm === 'sheriff' && $data['access'] === 'deny') {
            return back()->withErrors(['access' => 'You cannot remove your own Sheriff access: you would lose the Admin rights to put it back.']);
        }
        $inRole = in_array($perm, $u->role?->perms ?? [], true);
        $extra = array_values(array_diff($u->extra_perms ?? [], [$perm]));
        $denied = array_values(array_diff($u->denied_perms ?? [], [$perm]));
        if ($data['access'] === 'allow' && ! $inRole) {
            $extra[] = $perm;
        } elseif ($data['access'] === 'deny' && $inRole) {
            $denied[] = $perm;
        }
        $u->update(['extra_perms' => $extra, 'denied_perms' => $denied,
            'access_note' => ($extra || $denied) ? ($data['access_note'] ?? $u->access_note) : null]);

        return redirect()->to(route('admin.account.profile').'#access')->with('status', $name.': '.($u->fresh()->hasPerm($perm) ? 'you can open it' : 'no access').'.');
    }

    // ---------- My Work, My Bookmarks, My Team, Availability, start page and signature ----------

    /** Everything waiting on you across the apps, newest first, each table 10 a page. */
    public function work(Request $request): View
    {
        $u = $request->user();
        $can = fn (string $app) => $u->hasPerm($app);

        return view('backend.account.work', [
            'tickets' => $can('ranger') ? SupportTicket::with('customer')->active()->where('assigned_to', $u->id)->orderByRaw("CASE priority WHEN 'urgent' THEN 0 WHEN 'high' THEN 1 ELSE 2 END")
                ->latest('last_activity_at')->paginate(10, ['*'], 'tickets_page')->withQueryString()->fragment('tickets') : null,
            'toApprove' => $can('corral') && $u->isLead() ? LeadApproval::with(['customer', 'requester'])->pending()->latest('id')->paginate(10, ['*'], 'approve_page')->withQueryString()->fragment('approvals') : null,
            'myRequests' => $can('corral') && ! $u->isLead() ? LeadApproval::with('customer')->where('requested_by', $u->id)->latest('id')->paginate(10, ['*'], 'requests_page')->withQueryString()->fragment('requests') : null,
            'followUps' => $can('corral') ? CrmActivity::with(['lead', 'partner'])->where('user_id', $u->id)->whereNotNull('due_on')->whereNull('done_at')->orderBy('due_on')
                ->paginate(10, ['*'], 'followups_page')->withQueryString()->fragment('follow-ups') : null,
            'running' => ReportRun::where('user_id', $u->id)->whereIn('status', ['queued', 'running'])->count(),
        ]);
    }

    /** The customer accounts you've bookmarked in Corral. */
    public function bookmarks(Request $request): View
    {
        abort_unless($request->user()->hasPerm('corral'), 403);

        return view('backend.account.bookmarks', ['items' => $request->user()->bookmarks()->orderByPivot('created_at', 'desc')->paginate(10)->withQueryString()]);
    }

    public function removeBookmark(Request $request, Customer $customer): RedirectResponse
    {
        $request->user()->bookmarks()->detach($customer->id);

        return redirect()->route('admin.account.bookmarks')->with('status', 'Removed '.$customer->account.' from your bookmarks.');
    }

    /** Who you report to and who reports to you, with how to reach them and whether they're in. */
    public function team(Request $request): View
    {
        $u = $request->user()->load(['manager.role', 'reports.role']);
        $peers = $u->manager_id ? User::with('role')->where('manager_id', $u->manager_id)->whereKeyNot($u->id)->where('active', true)->orderBy('name')->get() : collect();
        $online = collect();
        if (config('session.driver') === 'database' && Schema::hasTable(config('session.table', 'sessions'))) {
            $online = DB::table(config('session.table', 'sessions'))->whereNotNull('user_id')->where('last_activity', '>=', now()->subMinutes(15)->timestamp)->pluck('user_id')->unique()->flip();
        }

        return view('backend.account.team', ['u' => $u, 'peers' => $peers, 'online' => $online]);
    }

    public function availability(Request $request): View
    {
        return view('backend.account.availability', ['u' => $request->user()]);
    }

    /** Available, Busy, or Out of Office until a date. While you're out, Ranger won't assign you tickets. */
    public function saveAvailability(Request $request): RedirectResponse
    {
        abort_if($request->session()->has('impersonator_id'), 403);
        $data = $request->validate(['availability' => ['required', 'in:available,busy,out'], 'out_until' => ['nullable', 'required_if:availability,out', 'date', 'after_or_equal:today'],
            'out_note' => ['nullable', 'string', 'max:250']], ['out_until.required_if' => 'Pick the day you\'re back.']);
        if ($data['availability'] !== 'out') {
            $data['out_until'] = $data['out_note'] = null;
        }
        $request->user()->update($data);
        History::record(['model' => 'AdminLogin_model', 'group' => 'Logins', 'record_id' => $request->user()->id, 'action' => 'updated', 'summary' => 'Set themselves '.$request->user()->fresh()->availabilityLabel()]);

        return redirect()->route('admin.account.availability')->with('status', 'You\'re '.$request->user()->fresh()->availabilityLabel().'.');
    }

    /** Settings → Start Page: where you land after signing in. */
    public function saveStartPage(Request $request): RedirectResponse
    {
        $u = $request->user();
        $allowed = ['', 'account', ...array_keys(array_filter(AdminApps::all(), fn ($a, $k) => $u->hasPerm($k), ARRAY_FILTER_USE_BOTH))];
        $data = $request->validate(['start_page' => ['nullable', 'in:'.implode(',', $allowed)]]);
        $u->update(['start_page' => ($data['start_page'] ?? '') ?: null]);

        return redirect()->to(route('admin.settings').'#start-page')->with('status', 'Start page saved.');
    }

    /** Settings → Email Signature: added to the emails you send customers (Ranger replies by email, Corral Send Email). */
    public function saveSignature(Request $request): RedirectResponse
    {
        $data = $request->validate(['signature' => ['nullable', 'string', 'max:1000']]);
        $request->user()->update(['signature' => trim((string) ($data['signature'] ?? '')) ?: null]);

        return redirect()->to(route('admin.settings').'#signature')->with('status', 'Signature saved.');
    }

    /** One of your activity entries. */
    public function activityShow(Request $request, HistoryItem $item): View
    {
        abort_unless($item->user_id === $request->user()->id, 404);

        return view('backend.account.activity-show', ['item' => $item->load('customer')]);
    }
}
