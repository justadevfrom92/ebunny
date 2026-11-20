<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\AdminNotification;
use App\Models\HistoryItem;
use App\Models\SavedReport;
use App\Models\User;
use App\Models\UserBlock;
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
            'devices' => self::devices($request),
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

    /** One of your sign-ins by its key (a hash of the session, so the session itself is never in a link). */
    private function deviceOr404(Request $request, string $key): object
    {
        return self::devices($request)->firstWhere('key', $key) ?? abort(404, 'That sign-in has ended.');
    }

    /** One place you're signed in: what it is, and buttons to sign it out, block it or report it. */
    public function device(Request $request, string $key): View
    {
        $d = $this->deviceOr404($request, $key);

        return view('backend.account.device', ['d' => $d, 'u' => $request->user(), 'loggedInAs' => $request->session()->has('impersonator_id'),
            'blocked' => UserBlock::where('user_id', $request->user()->id)->where('ip', $d->ip)->where('user_agent', $d->ua)->first(),
            'sameDevice' => $d->ip === $request->ip() && $d->ua === (string) $request->userAgent(),
            'logins' => HistoryItem::where('model', 'AdminLogin_model')->where('record_id', $request->user()->id)->where('ip', $d->ip)->latest('created_at')->latest('id')->limit(5)->get()]);
    }

    /** End that sign-in. Your own browser signs you out. */
    public function deviceSignOut(Request $request, string $key): RedirectResponse
    {
        abort_if($request->session()->has('impersonator_id'), 403);
        $d = $this->deviceOr404($request, $key);
        if ($d->current) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('admin.login')->with('status', 'You are signed out.');
        }
        $this->endSession($request, $d, $d->logged_in_as_by ? 'Ended '.$d->logged_in_as_by->name.'\'s Log in as' : 'Signed out '.$d->browser.' ('.$d->ip.')');

        return redirect()->to(route('admin.account.profile').'#signed-in')->with('status', $d->logged_in_as_by ? $d->logged_in_as_by->name.' is no longer logged in as you.' : 'Signed out '.$d->browser.'.');
    }

    /** Sign it out and keep that device (this IP address with this browser) from signing in to your account again. */
    public function deviceBlock(Request $request, string $key): RedirectResponse
    {
        $d = $this->blockable($request, $key);
        $this->block($request, $d, null);

        return redirect()->to(route('admin.account.profile').'#signed-in')->with('status', 'Blocked '.$d->browser.' at '.$d->ip.'. It was signed out and can\'t sign in to your account.');
    }

    /** Block it, and tell the administrators (Sheriff) so they can look into it. */
    public function deviceReport(Request $request, string $key): RedirectResponse
    {
        $d = $this->blockable($request, $key);
        $reason = trim((string) $request->validate(['reason' => ['nullable', 'string', 'max:500']])['reason'] ?? '');
        $this->block($request, $d, $reason ?: 'Reported: not recognized');
        $u = $request->user();
        AdminNotification::notify(AdminNotification::appUsers('sheriff')->reject(fn ($a) => $a->id === $u->id), 'security',
            $u->name.' reported a sign-in they don\'t recognize', $d->browser.' at '.$d->ip.($reason ? ' — '.$reason : '').'. It was signed out and blocked.', route('sheriff.users.show', $u));

        return redirect()->to(route('admin.account.profile').'#signed-in')->with('status', 'Reported to the administrators. '.$d->browser.' at '.$d->ip.' was signed out and blocked.');
    }

    public function unblock(Request $request, UserBlock $block): RedirectResponse
    {
        abort_if($block->user_id !== $request->user()->id || $request->session()->has('impersonator_id'), 404);
        $block->delete();
        History::record(['model' => 'AdminLogin_model', 'group' => 'Logins', 'record_id' => $request->user()->id, 'action' => 'updated', 'summary' => 'Unblocked '.self::browser((string) $block->user_agent).' at '.$block->ip]);

        return redirect()->to(route('admin.account.profile').'#signed-in')->with('status', 'Unblocked. That device can sign in again.');
    }

    /** You can't block the browser you're using (or one that looks exactly like it), or act while using Log in as. */
    private function blockable(Request $request, string $key): object
    {
        abort_if($request->session()->has('impersonator_id'), 403);
        $d = $this->deviceOr404($request, $key);
        abort_if($d->current || ($d->ip === $request->ip() && $d->ua === (string) $request->userAgent()), 422, 'You can\'t block the browser you are using.');

        return $d;
    }

    private function block(Request $request, object $d, ?string $reason): void
    {
        UserBlock::firstOrCreate(['user_id' => $request->user()->id, 'ip' => $d->ip, 'user_agent' => $d->ua],
            ['reason' => $reason, 'reported_at' => $reason ? now() : null, 'created_by' => $request->user()->id]);
        $this->endSession($request, $d, ($reason ? 'Reported and blocked ' : 'Blocked ').$d->browser.' ('.$d->ip.')');
    }

    /** Remove the sign-in, and reset "remember me" so it can't come back on its own. */
    private function endSession(Request $request, object $d, string $summary): void
    {
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
            $n = DB::table(config('session.table', 'sessions'))->where('user_id', $request->user()->id)->where('id', '!=', $request->session()->getId())->delete();
        }
        $request->user()->forceFill(['remember_token' => Str::random(60)])->save();
        History::record(['model' => 'AdminLogin_model', 'group' => 'Logins', 'record_id' => $request->user()->id, 'action' => 'logged', 'summary' => 'Signed out their other devices']);

        return redirect()->to(route('admin.account.profile').'#signed-in')->with('status', $n ? 'Signed out of '.$n.' other '.Str::plural('device', $n).'.' : 'You were not signed in anywhere else.');
    }

    /** One of your activity entries. */
    public function activityShow(Request $request, HistoryItem $item): View
    {
        abort_unless($item->user_id === $request->user()->id, 404);

        return view('backend.account.activity-show', ['item' => $item->load('customer')]);
    }
}
