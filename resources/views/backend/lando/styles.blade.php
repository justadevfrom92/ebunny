@extends('backend.layouts.app')

@section('head')
<style>
    .sg-swatch { width: 34px; height: 34px; border-radius: 8px; border: 1px solid var(--line); background: var(--sg); display: inline-block; vertical-align: middle; }
    .sg-pick { position: relative; width: 34px; height: 34px; display: inline-block; }
    .sg-pick input { position: absolute; inset: 0; opacity: 0; width: 100%; height: 100%; cursor: pointer; border: 0; padding: 0; }
    .sg-row.changed td { background: var(--warn-bg); }
    .sg-row.flash td { background: var(--info-bg); transition: background .2s; }
    .sg-uses summary { cursor: pointer; color: var(--cyan); }
    .sg-uses ul { margin: 6px 0 0 16px; font-size: .8rem; color: var(--muted); max-height: 220px; overflow: auto; }
    .sg-uses b { display: block; margin-top: 6px; color: var(--ink); font-size: .8rem; }
    .sg-comp { display: grid; grid-template-columns: repeat(auto-fill, minmax(min(100%, 420px), 1fr)); gap: 16px; }
    .sg-comp .panel-body { display: grid; gap: 12px; }
    .sg-chips { display: flex; flex-wrap: wrap; gap: 6px; }
    .sg-chip { display: inline-flex; align-items: center; gap: 6px; border: 1px solid var(--line); border-radius: 999px; padding: 2px 10px 2px 4px; font-size: .78rem; background: #fff; cursor: pointer; color: var(--ink); }
    .sg-chip i { width: 16px; height: 16px; border-radius: 50%; border: 1px solid var(--line); background: var(--sg); display: inline-block; }
    .sg-chip:hover { border-color: var(--cyan); }
    .sg-frame { width: 100%; border: 1px dashed var(--line); border-radius: 8px; background: #fff; min-height: 80px; }
    .sg-font { font-size: 1.4rem; color: var(--navy); }
    .sg-out { width: 100%; min-height: 160px; font-family: ui-monospace, Menlo, Consolas, monospace; font-size: .8rem; border: 1px solid var(--line); border-radius: 6px; padding: 10px; }
</style>
@endsection

@section('content')
    @include('backend.partials.page-head', ['title' => 'Style Guide',
        'sub' => 'Every color and style variable used by the website and all admin apps, in one place. Shared colors live in <span class="mono">public/shared/css/tokens.css</span>; pick a swatch to try a new value live on this page.',
        'actions' => '<span class="muted" id="sg-changed" hidden></span><button type="button" class="btn ghost" id="sg-reset" disabled>Reset</button><button type="button" class="btn cyan" id="sg-copy" disabled>Copy Changed CSS</button>'])

    <div class="panel" id="sg-export" hidden style="margin-bottom:16px">
        <div class="panel-head"><h2>Changed Variables</h2><span class="muted">Paste into the file named in each comment to make the change permanent</span></div>
        <div class="panel-body"><textarea class="sg-out" id="sg-css" readonly aria-label="Changed CSS"></textarea></div>
    </div>

    <div class="panel" data-tabs>
        <div class="tabs" role="tablist">
            <button type="button" data-tab="colors">Colors ({{ $vars->where('kind', 'color')->count() }})</button>
            <button type="button" data-tab="components">Components</button>
            <button type="button" data-tab="type">Fonts &amp; Sizes</button>
            <button type="button" data-tab="hardcoded">Hard-coded Colors ({{ $literals->count() }})</button>
            <button type="button" data-tab="files">Files</button>
        </div>

        {{-- Colors: one table per group --}}
        <div data-pane="colors">
            @foreach ($groups as $group => $list)
                @continue(! in_array($group, ['Brand colors', 'Status colors', 'App colors'], true))
                <div class="panel-head"><h2>{{ $group }}</h2><span class="muted">{{ ['Brand colors' => 'website + every admin app', 'Status colors' => 'alerts, pills and messages, website + admin', 'App colors' => 'admin apps only'][$group] }}</span></div>
                <div class="table-wrap"><table class="table">
                    <thead><tr><th style="width:56px">Color</th><th>Variable</th><th>Value</th><th>Defined In</th><th class="num">Used</th><th>Where</th></tr></thead>
                    <tbody>@foreach ($list as $v)
                        @php $hex = preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6})$/i', $v['resolved']) ? (strlen($v['resolved']) === 4 ? '#'.implode('', array_map(fn ($c) => $c.$c, str_split(substr($v['resolved'], 1)))) : $v['resolved']) : null; @endphp
                        <tr class="sg-row" id="var{{ $v['name'] }}" data-row="{{ $v['name'] }}">
                            <td>@if ($hex)<label class="sg-pick" title="Try a new {{ $v['name'] }}"><span class="sg-swatch" style="--sg:var({{ $v['name'] }})"></span><input type="color" value="{{ strtolower($hex) }}" data-var="{{ $v['name'] }}" data-file="{{ $v['file'] }}" data-original="{{ strtolower($hex) }}" aria-label="{{ $v['name'] }}"></label>@else<span class="sg-swatch" style="--sg:var({{ $v['name'] }})"></span>@endif</td>
                            <td class="mono"><b>{{ $v['name'] }}</b></td>
                            <td class="mono"><span data-value>{{ $v['value'] }}</span>@if ($v['value'] !== $v['resolved'])<div class="muted">= {{ $v['resolved'] }}</div>@endif</td>
                            <td class="muted">{{ $files[$v['file']] }}</td>
                            <td class="num">{{ $v['count'] }}</td>
                            <td>@if ($v['count'])<details class="sg-uses"><summary>{{ collect($v['uses'])->keys()->map(fn ($f) => ['shared/css/tokens.css' => 'Shared', 'admin-assets/admin.css' => 'Admin', 'css/style.css' => 'Website'][$f])->implode(' + ') }}</summary>
                                @foreach ($v['uses'] as $f => $sels)<b>{{ $files[$f] }}</b><ul>@foreach (array_unique($sels) as $s)<li class="mono">{{ $s }}</li>@endforeach</ul>@endforeach
                            </details>@else<span class="muted">not used yet</span>@endif</td>
                        </tr>
                    @endforeach</tbody>
                </table></div>
            @endforeach
        </div>

        {{-- Components built from the variables; picking a color above repaints these --}}
        <div data-pane="components" hidden>
            <div class="panel-head"><h2>Admin Apps</h2><span class="muted">admin-assets/admin.css · click a variable to jump to it</span></div>
            <div class="panel-body sg-comp">
                @foreach ($admin as $c)
                    <div class="panel"><div class="panel-head"><h3 style="margin:0">{{ $c['title'] }}</h3></div><div class="panel-body">
                        <div>{!! $c['html'] !!}</div>
                        @include('backend.lando._chips', ['names' => $c['vars']])
                    </div></div>
                @endforeach
                <div class="panel"><div class="panel-head"><h3 style="margin:0">Multi-select</h3></div><div class="panel-body">
                    @include('backend.partials.multiselect', ['id' => 'sg-multi', 'name' => 'sg[]', 'label' => 'Sample', 'options' => ['Submitted', 'Good - On Flow', 'Dropped - Churned'], 'selected' => ['Good - On Flow']])
                    @include('backend.lando._chips', ['names' => \App\Support\StyleSheets::varsFor(['.multi'], 'admin-assets/admin.css')])
                </div></div>
            </div>
            <div class="panel-head"><h2>Website</h2><span class="muted">css/style.css · shown in frames so the website's own styles apply</span></div>
            <div class="panel-body sg-comp">
                @foreach ($site as $i => $c)
                    <div class="panel"><div class="panel-head"><h3 style="margin:0">{{ $c['title'] }}</h3></div><div class="panel-body">
                        <iframe class="sg-frame" title="{{ $c['title'] }} sample" data-site-sample="{{ $i }}"></iframe>
                        <script type="text/plain" id="sg-sample-{{ $i }}">{!! $c['html'] !!}</script>
                        @include('backend.lando._chips', ['names' => $c['vars']])
                    </div></div>
                @endforeach
            </div>
        </div>

        <div data-pane="type" hidden>
            <div class="panel-head"><h2>Fonts</h2></div>
            <div class="table-wrap"><table class="table"><thead><tr><th>Variable</th><th>Sample</th><th>Value</th><th class="num">Used</th></tr></thead><tbody>
                @foreach ($vars->where('kind', 'font') as $v)
                    <tr><td class="mono"><b>{{ $v['name'] }}</b><div class="muted">{{ $files[$v['file']] }}</div></td><td><span class="sg-font" style="font-family:var({{ $v['name'] }})">Texas-sized service, 1,234 kWh</span></td><td class="mono muted">{{ $v['value'] }}</td><td class="num">{{ $v['count'] }}</td></tr>
                @endforeach
            </tbody></table></div>
            <div class="panel-head"><h2>Sizes &amp; Effects</h2></div>
            <div class="table-wrap"><table class="table"><thead><tr><th>Variable</th><th>Value</th><th>Defined In</th><th class="num">Used</th></tr></thead><tbody>
                @foreach ($vars->where('kind', 'other') as $v)
                    <tr><td class="mono"><b>{{ $v['name'] }}</b></td><td class="mono">{{ $v['value'] }}</td><td class="muted">{{ $files[$v['file']] }}</td><td class="num">{{ $v['count'] }}</td></tr>
                @endforeach
            </tbody></table></div>
        </div>

        <div data-pane="hardcoded" hidden>
            <div class="panel-body"><p class="muted" style="margin:0">Colors typed straight into a rule instead of using a variable. These don't change when a variable does; mostly white and see-through overlays on navy.</p></div>
            <div class="table-wrap"><table class="table"><thead><tr><th style="width:56px">Color</th><th>Value</th><th class="num">Used</th><th>Where</th></tr></thead><tbody>
                @foreach ($literals as $l)
                    <tr><td><span class="sg-swatch" style="--sg:{{ $l['value'] }};background-image:linear-gradient(45deg,#ddd 25%,transparent 25%,transparent 75%,#ddd 75%),linear-gradient(45deg,#ddd 25%,transparent 25%,transparent 75%,#ddd 75%);background-size:10px 10px;background-position:0 0,5px 5px;box-shadow:inset 0 0 0 40px {{ $l['value'] }}"></span></td>
                        <td class="mono">{{ $l['value'] }}</td><td class="num">{{ $l['count'] }}</td>
                        <td><details class="sg-uses"><summary>{{ $l['count'] }} {{ Str::plural('rule', $l['count']) }}</summary><ul>@foreach (array_unique($l['uses']) as $u)<li class="mono">{{ $u }}</li>@endforeach</ul></details></td></tr>
                @endforeach
            </tbody></table></div>
        </div>

        <div data-pane="files" hidden>
            <div class="table-wrap"><table class="table"><thead><tr><th>File</th><th>Used By</th><th class="num">Variables Defined</th><th class="num">Variable Uses</th></tr></thead><tbody>
                @foreach ($files as $f => $who)
                    <tr><td class="mono">public/{{ $f }}</td><td>{{ $who }}</td><td class="num">{{ $vars->where('file', $f)->count() }}</td><td class="num">{{ $vars->sum(fn ($v) => count($v['uses'][$f] ?? [])) }}</td></tr>
                @endforeach
            </tbody></table></div>
            <div class="panel-body"><p class="muted" style="margin:0">Every admin app (Corral, Lando, Astro, Walker, Caboose, Bounty, Rodeo, Sheriff and any app added from the launcher) uses the same admin stylesheet, so a change here applies to all of them.</p></div>
        </div>
    </div>

    {{-- The website stylesheet, for the website component frames --}}
    <script type="text/plain" id="sg-site-css">{!! str_replace('</', '<\/', file_get_contents(public_path('shared/css/tokens.css'))."\n".preg_replace('/@import[^;]+tokens\.css[^;]*;/', '', file_get_contents(public_path('css/style.css')))) !!}</script>
    <script>
    (function () {
        var root = document.documentElement, changed = {};
        var css = document.getElementById('sg-site-css').textContent.replace(/<\\\//g, '</');
        var frames = [].slice.call(document.querySelectorAll('[data-site-sample]'));

        // Frames are sized when visible (a hidden tab measures 0) and again once web fonts load
        function sizeFrame(f) { try { if (f.offsetParent) f.style.height = (f.contentDocument.body.scrollHeight + 34) + 'px'; } catch (e) {} }
        function sizeAll() { frames.forEach(sizeFrame); }
        document.querySelector('[data-tab="components"]').addEventListener('click', function () { sizeAll(); setTimeout(sizeAll, 300); });
        window.addEventListener('resize', sizeAll);
        frames.forEach(function (f) {
            var html = document.getElementById('sg-sample-' + f.dataset.siteSample).textContent;
            f.srcdoc = '<!doctype html><html><head><meta charset="utf-8"><style>' + css + '</style><style>body{padding:16px;background:#fff}.reveal{opacity:1!important;transform:none!important}</style></head><body>' + html + '</body></html>';
            f.addEventListener('load', function () { applyAll(f); sizeFrame(f); setTimeout(function () { sizeFrame(f); }, 400); try { f.contentDocument.fonts.ready.then(function () { sizeFrame(f); }); } catch (e) {} });
        });

        function applyAll(f) { Object.keys(changed).forEach(function (n) { try { f.contentDocument.documentElement.style.setProperty(n, changed[n]); } catch (e) {} }); }
        function set(name, value) {
            root.style.setProperty(name, value);
            frames.forEach(function (f) { try { f.contentDocument.documentElement.style.setProperty(name, value); } catch (e) {} });
        }
        function refresh() {
            var names = Object.keys(changed), n = names.length, label = document.getElementById('sg-changed');
            label.hidden = !n; label.textContent = n + ' changed (this page only)';
            document.getElementById('sg-reset').disabled = !n;
            document.getElementById('sg-copy').disabled = !n;
            var byFile = {};
            names.forEach(function (name) { var f = document.querySelector('[data-var="' + name + '"]').dataset.file; (byFile[f] = byFile[f] || []).push('  ' + name + ': ' + changed[name] + ';'); });
            document.getElementById('sg-css').value = Object.keys(byFile).map(function (f) { return '/* public/' + f + ' */\n:root {\n' + byFile[f].join('\n') + '\n}'; }).join('\n\n');
            if (!n) document.getElementById('sg-export').hidden = true;
        }

        [].slice.call(document.querySelectorAll('input[data-var]')).forEach(function (input) {
            input.addEventListener('input', function () {
                var name = input.dataset.var, row = input.closest('tr');
                if (input.value === input.dataset.original) { delete changed[name]; root.style.removeProperty(name); frames.forEach(function (f) { try { f.contentDocument.documentElement.style.removeProperty(name); } catch (e) {} }); }
                else { changed[name] = input.value; set(name, input.value); }
                row.classList.toggle('changed', !!changed[name]);
                row.querySelector('[data-value]').textContent = changed[name] || row.querySelector('[data-value]').dataset.was || row.querySelector('[data-value]').textContent;
                refresh();
            });
            var v = input.closest('tr').querySelector('[data-value]'); v.dataset.was = v.textContent;
        });

        document.getElementById('sg-reset').addEventListener('click', function () {
            [].slice.call(document.querySelectorAll('input[data-var]')).forEach(function (i) { if (changed[i.dataset.var]) { i.value = i.dataset.original; i.dispatchEvent(new Event('input')); } });
        });
        document.getElementById('sg-copy').addEventListener('click', function () {
            var box = document.getElementById('sg-export'), ta = document.getElementById('sg-css');
            box.hidden = false; ta.focus(); ta.select();
            if (navigator.clipboard) navigator.clipboard.writeText(ta.value).catch(function () {});
        });

        // A variable chip on a component jumps to that variable's row
        document.addEventListener('click', function (e) {
            var chip = e.target.closest('[data-jump]'); if (!chip) return;
            var row = document.getElementById('var' + chip.dataset.jump); if (!row) return;
            document.querySelector('[data-tab="colors"]').click();
            row.scrollIntoView({ block: 'center' }); row.classList.add('flash'); setTimeout(function () { row.classList.remove('flash'); }, 1200);
        });
    })();
    </script>
@endsection
