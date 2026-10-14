{{-- Shown on every admin page while using Log in as: who you are looking as, and the way back --}}
@if (session('impersonator_id') && auth('web')->check() && ($origin = \App\Models\User::find(session('impersonator_id'))))
    <div class="login-as-bar" role="status">
        <span>You're logged in as <b>{{ auth('web')->user()->name }}</b> ({{ auth('web')->user()->role?->name ?? 'no role' }}). Everything you do is recorded as them.</span>
        <form method="post" action="{{ route('admin.login-as.return') }}" class="inline">@csrf<button class="btn sm">Back to {{ $origin->name }}</button></form>
    </div>
@endif
