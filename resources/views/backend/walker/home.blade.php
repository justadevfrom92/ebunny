@extends('backend.layouts.app')

@section('content')
    @include('backend.partials.page-head', ['title' => 'Report Status', 'sub' => 'Every report run from Walker and Corral. Runs move from Running to Errors or Completed on their own; this page updates live.',
        'actions' => '<a class="btn cyan" href="'.route('walker.reports.index').'">Run a Report</a>'])

    <div id="run-tables" class="stack" style="display:flex;flex-direction:column;gap:20px">
        @include('backend.walker._tables')
    </div>

    <script>
    // Swap the three tables in place: every 5 seconds while something is running, every 30 seconds otherwise
    (function () {
        var box = document.getElementById('run-tables');
        function tick() {
            var running = +(document.getElementById('runs-running') || {}).dataset?.count || 0;
            setTimeout(function () {
                if (document.hidden) return tick();
                var q = new URLSearchParams(location.search); q.set('part', 'tables');
                fetch(location.pathname + '?' + q, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
                    .then(function (r) { return r.ok ? r.text() : Promise.reject(); })
                    .then(function (html) { box.innerHTML = html; })
                    .catch(function () {})
                    .finally(tick);
            }, running ? 5000 : 30000);
        }
        tick();
    })();
    </script>
@endsection
