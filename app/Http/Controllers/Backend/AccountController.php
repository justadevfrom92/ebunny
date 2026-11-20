<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\HistoryItem;
use App\Models\SavedReport;
use App\Support\AdminApps;
use App\Support\History;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
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
            'logins' => HistoryItem::where('model', 'AdminLogin_model')->where('record_id', $u->id)->latest('created_at')->latest('id')
                ->paginate(10, ['*'], 'logins_page')->withQueryString()->fragment('sign-ins'),
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
            ->map(fn ($s) => (object) ['current' => $s->id === $current, 'ip' => $s->ip_address, 'browser' => self::browser((string) $s->user_agent),
                'last' => Carbon::createFromTimestamp($s->last_activity)])
            ->sortByDesc('current')->values();
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
