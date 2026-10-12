<?php

namespace App\Http\Controllers\Frontend\MyAccount;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** My Account sign-in for customers: login, create account, forgot password / username, and QuickPay. */
class AuthController extends Controller
{
    public function show(): View
    {
        return view('frontend.myaccount.login', ['testCustomers' => self::testCustomers()]);
    }

    /** Sample customers with a My Account login, offered for one-click sign-in while testing (none in production). */
    public static function testCustomers()
    {
        return config('admin.test_logins') ? Customer::whereNotNull('password')->whereNotNull('username')->where('email', 'like', '%@example.com')->orderBy('id')->limit(12)->get() : collect();
    }

    /** Testing only: sign in to My Account as a sample customer, or switch to another one, without a password. */
    public function testLogin(Request $request, Customer $customer): RedirectResponse
    {
        abort_unless(config('admin.test_logins') && $customer->password && $customer->username && str_ends_with((string) $customer->email, '@example.com'), 404);
        Auth::guard('customer')->login($customer);
        $request->session()->regenerate();
        $customer->apiLogs()->create(['api' => 'MyAccount', 'action' => 'test-login', 'status' => '200', 'created_at' => now()]);

        return redirect()->route('myaccount.dashboard')->with('status', 'Signed in as '.$customer->name.' for testing.');
    }

    public function login(Request $request): RedirectResponse
    {
        $data = $request->validate(['login' => ['required', 'string', 'max:200'], 'password' => ['required', 'string']]);
        $field = str_contains($data['login'], '@') ? 'email' : 'username';
        $customer = Customer::where($field, $data['login'])->whereNotNull('password')->orderByDesc('id')->first();

        if (! $customer || ! Hash::check($data['password'], $customer->password)) {
            return back()->withInput($request->only('login'))->withErrors(['login' => 'That username or password isn\'t right.']);
        }
        Auth::guard('customer')->login($customer, $request->boolean('remember'));
        $request->session()->regenerate();
        $customer->apiLogs()->create(['api' => 'MyAccount', 'action' => 'login', 'status' => '200', 'created_at' => now()]);

        // Only go back to a My Account page (an admin page someone tried earlier isn't for customers)
        $intended = (string) $request->session()->pull('url.intended');

        return redirect(str_starts_with($intended, url('myaccount')) ? $intended : route('myaccount.dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('customer')->logout();
        $request->session()->regenerateToken();

        return redirect()->route('myaccount.login')->with('status', 'You\'re signed out.');
    }

    /** Create Account: prove it's your account (account #, zip, email on file), then pick a login. */
    public function registerForm(): View
    {
        return view('frontend.myaccount.register');
    }

    public function register(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'account' => ['required', 'string', 'max:20'],
            'zip' => ['required', 'digits:5'],
            'email' => ['required', 'email'],
            'username' => ['required', 'string', 'min:4', 'max:40', 'regex:/^[A-Za-z0-9._-]+$/', Rule::unique('customers', 'username')],
            'password' => ['required', 'string', 'min:8', 'max:100', 'confirmed'],
        ]);
        $c = Customer::where('account', $data['account'])->where('zip', $data['zip'])->whereRaw('lower(email) = ?', [strtolower($data['email'])])->first();
        if (! $c) {
            return back()->withInput($request->except('password', 'password_confirmation'))->withErrors(['account' => 'We couldn\'t match those details to an account. Check your bill or call us.']);
        }
        if ($c->password) {
            return back()->withErrors(['account' => 'This account already has a login. Use Forgot Password if you need to reset it.']);
        }
        $c->update(['username' => $data['username'], 'password' => $data['password']]);
        Auth::guard('customer')->login($c);
        $request->session()->regenerate();

        return redirect()->route('myaccount.dashboard')->with('status', 'Your My Account login is ready.');
    }

    public function forgotForm(string $what = 'password'): View
    {
        return view('frontend.myaccount.forgot', ['what' => $what]);
    }

    /** Emails a reset link (or the username). The answer is the same whether or not the account exists. */
    public function forgot(Request $request, string $what = 'password'): RedirectResponse
    {
        $data = $request->validate(['login' => ['required', 'string', 'max:200']]);
        $field = str_contains($data['login'], '@') ? 'email' : 'username';
        $customers = Customer::where($field, $data['login'])->whereNotNull('password')->get();

        foreach ($customers as $c) {
            if ($what === 'username') {
                Mail::raw('Your '.config('brand.name').' My Account username is: '.$c->username, fn ($m) => $m->to($c->email)->subject('Your username'));

                continue;
            }
            $token = Str::random(48);
            DB::table('customer_password_resets')->updateOrInsert(['customer_id' => $c->id], ['token' => Hash::make($token), 'created_at' => now()]);
            $link = route('myaccount.reset', ['token' => $token, 'account' => $c->account]);
            Mail::raw("Reset your My Account password (link works for 60 minutes):\n\n$link\n", fn ($m) => $m->to($c->email)->subject('Reset your password'));
        }

        return back()->with('status', 'If that matches an account, we\'ve emailed '.($what === 'username' ? 'the username' : 'a reset link').' to the address on file.');
    }

    public function resetForm(Request $request, string $token): View
    {
        return view('frontend.myaccount.reset', ['token' => $token, 'account' => $request->query('account')]);
    }

    public function reset(Request $request): RedirectResponse
    {
        $data = $request->validate(['token' => ['required'], 'account' => ['required'], 'password' => ['required', 'string', 'min:8', 'max:100', 'confirmed']]);
        $c = Customer::where('account', $data['account'])->first();
        $row = $c ? DB::table('customer_password_resets')->where('customer_id', $c->id)->first() : null;
        if (! $row || now()->subMinutes(60)->gt($row->created_at) || ! Hash::check($data['token'], $row->token)) {
            return back()->withErrors(['password' => 'This reset link has expired. Request a new one.']);
        }
        $c->update(['password' => $data['password']]);
        DB::table('customer_password_resets')->where('customer_id', $c->id)->delete();

        return redirect()->route('myaccount.login')->with('status', 'Password changed. Sign in with your new password.');
    }
}
