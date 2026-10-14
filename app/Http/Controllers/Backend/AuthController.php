<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\History;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function show(): View
    {
        return view('backend.auth.login', ['testUsers' => self::testUsers()]);
    }

    /** The sample admins offered for one-click sign-in while testing (none in production). */
    public static function testUsers()
    {
        return config('admin.test_logins') ? User::with('role')->where('active', true)->where('email', 'like', '%@example.com')->orderBy('id')->get() : collect();
    }

    /** Testing only: sign in as a sample admin, or switch to another one, without a password. */
    public function testLogin(Request $request, User $user): RedirectResponse
    {
        abort_unless(config('admin.test_logins') && $user->active && str_ends_with($user->email, '@example.com'), 404);
        if (Auth::check()) {
            History::record(['model' => 'AdminLogin_model', 'group' => 'Logins', 'record_id' => Auth::id(), 'action' => 'logged', 'summary' => 'Switched to test user '.$user->name]);
        }
        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->forget('impersonator_id');
        $user->forceFill(['last_login_at' => now()])->save();
        History::record(['model' => 'AdminLogin_model', 'group' => 'Logins', 'record_id' => $user->id, 'action' => 'logged', 'summary' => 'Signed in to the admin as a test user']);

        return redirect()->route('admin.launcher')->with('status', 'Signed in as '.$user->name.' ('.($user->role?->name ?? 'no role').').');
    }

    /** Also receives the website's Admin prompt, which posts here in a new tab. */
    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials + ['active' => true], $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => 'That email and password do not match an active account.',
            ])->redirectTo(route('admin.login'));
        }

        $request->session()->regenerate();
        $request->session()->forget('impersonator_id');
        $request->user()->forceFill(['last_login_at' => now()])->save();
        History::record(['model' => 'AdminLogin_model', 'group' => 'Logins', 'record_id' => $request->user()->id,
            'action' => 'logged', 'summary' => 'Signed in to the admin', 'data' => ['user_agent' => substr((string) $request->userAgent(), 0, 250)]]);

        // Only go back to an admin page (not a My Account page left over in the session)
        $intended = (string) $request->session()->pull('url.intended');

        return redirect(str_starts_with($intended, url('admin')) ? $intended : route('admin.launcher'));
    }

    /** End "Log in as": switch back to the person who started it. */
    public function returnToSelf(Request $request): RedirectResponse
    {
        $original = User::where('active', true)->find($request->session()->get('impersonator_id'));
        abort_unless($original, 404);
        $was = $request->user();
        History::record(['model' => 'AdminLogin_model', 'group' => 'Logins', 'record_id' => $was->id, 'action' => 'logged', 'summary' => 'Log in as ended; back to '.$original->name]);
        Auth::login($original);
        $request->session()->regenerate();
        $request->session()->forget('impersonator_id');
        History::record(['model' => 'AdminLogin_model', 'group' => 'Logins', 'record_id' => $original->id, 'action' => 'logged', 'summary' => 'Back from logging in as '.$was->name]);

        return redirect()->route('sheriff.users.show', $was)->with('status', 'Back to '.$original->name.'.');
    }

    public function logout(Request $request): RedirectResponse
    {
        History::record(['model' => 'AdminLogin_model', 'group' => 'Logins', 'record_id' => $request->user()->id,
            'action' => 'logged', 'summary' => 'Signed out of the admin']);
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login')->with('status', 'Signed out.');
    }
}
