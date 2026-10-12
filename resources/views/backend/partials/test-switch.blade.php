{{-- Testing only: switch to another sample admin (hidden in production). --}}
@php $testUsers = \App\Http\Controllers\Backend\AuthController::testUsers(); @endphp
@if ($testUsers->count() > 1)
    <form method="post" class="inline test-switch" data-test-switch>@csrf
        <label class="sr-only" for="test-switch">Switch test user</label>
        <select id="test-switch" aria-label="Switch test user" onchange="if (this.value) { this.form.action = this.value; this.form.requestSubmit(); }">
            <option value="">Switch test user…</option>
            @foreach ($testUsers as $u)@unless ($u->is(auth()->user()))<option value="{{ route('admin.login.test', $u) }}">{{ $u->name }} — {{ $u->role?->name }}</option>@endunless @endforeach
        </select>
    </form>
@endif
