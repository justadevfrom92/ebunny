{{--
    Export Columns: the columns this report's files have. Each is a model key on the record (customer.name,
    payments.*.amount…) and the title its column gets, in running order. Empty = the report's own columns.
--}}
@php
    $rows = old('cols', $export ?: []);
    $nested = [];
    foreach ($export as $c) { data_set($nested, str_replace('*', '_all', $c['key']), $c['title']); }
@endphp
<div class="panel" id="export-columns" style="margin-bottom:20px">
    <div class="panel-head"><h2>Export Columns</h2>
        <span class="help">@if ($export){{ count($export) }} custom {{ Str::plural('column', count($export)) }}@if ($exportBy) · {{ $exportBy->name }}, {{ $exportAt?->diffForHumans() }}@endif @else Using the report's own columns @endif</span></div>
    @if (! $export)<div class="panel-body help" style="padding-bottom:0">Now: {{ implode(' · ', $columns) }}</div>@endif
    <form method="post" action="{{ route('walker.reports.columns', $def['key']) }}">@csrf
        <div class="table-wrap"><table class="table export-cols">
            <thead><tr><th style="width:70px">Order</th><th>Model key</th><th>Column title</th><th style="width:90px"><span class="sr-only">Move or remove</span></th></tr></thead>
            <tbody id="export-rows">
                @foreach ($rows as $i => $c)
                    <tr><td><input name="cols[{{ $i }}][order]" type="number" value="{{ $c['order'] ?? $loop->iteration }}" class="ec-order" aria-label="Order"></td>
                        <td><input name="cols[{{ $i }}][key]" value="{{ $c['key'] }}" class="mono" list="export-fields" aria-label="Model key"></td>
                        <td><input name="cols[{{ $i }}][title]" value="{{ $c['title'] }}" aria-label="Column title"></td>
                        <td class="actions nowrap"><button type="button" class="btn sm ghost icon" data-ec-move="-1" aria-label="Move up">↑</button><button type="button" class="btn sm ghost icon" data-ec-move="1" aria-label="Move down">↓</button><button type="button" class="btn sm ghost icon" data-ec-remove aria-label="Remove">×</button></td></tr>
                @endforeach
            </tbody>
        </table></div>
        <template id="export-row"><tr><td><input name="cols[__i__][order]" type="number" class="ec-order" aria-label="Order"></td><td><input name="cols[__i__][key]" class="mono" list="export-fields" aria-label="Model key" placeholder="e.g. customer.name"></td><td><input name="cols[__i__][title]" aria-label="Column title" placeholder="e.g. Name"></td>
            <td class="actions nowrap"><button type="button" class="btn sm ghost icon" data-ec-move="-1" aria-label="Move up">↑</button><button type="button" class="btn sm ghost icon" data-ec-move="1" aria-label="Move down">↓</button><button type="button" class="btn sm ghost icon" data-ec-remove aria-label="Remove">×</button></td></tr></template>
        <datalist id="export-fields">@foreach ($fields as $k)<option value="{{ $k }}">@endforeach</datalist>
        @if ($fields)
            <div class="panel-body" style="padding-bottom:0"><div class="help" style="margin-bottom:6px">Keys on these records — click one to add it:</div>
                <div class="ec-fields">@foreach ($fields as $k)<button type="button" class="ec-field mono" data-ec-add="{{ $k }}">{{ $k }}</button>@endforeach</div></div>
        @endif
        @error('cols')<div class="panel-body"><div class="alert bad">{{ $message }}</div></div>@enderror
        <div class="panel-body actions"><button type="button" class="btn ghost" data-clone="#export-row" data-into="#export-rows">Add Column</button><button class="btn cyan">Save Columns</button>
            @if ($export)<button class="btn ghost" form="export-reset">Reset</button>@endif</div>
    </form>
    @if ($export)<form id="export-reset" method="post" action="{{ route('walker.reports.columns', $def['key']) }}" data-confirm="Reset export columns?|Files go back to the report's own columns.|Reset">@csrf<input type="hidden" name="reset" value="1"></form>@endif
    <details class="panel-body ec-array" @if ($errors->has('key_array')) open @endif><summary>As a key array</summary>
        <form method="post" action="{{ route('walker.reports.columns', $def['key']) }}">@csrf
            <p class="help">The same columns as a nested <span class="mono">key =&gt; title</span> array, in running order. Paste one to replace them all. Use <span class="mono">_all</span> for every item of a list.</p>
            <textarea name="key_array" class="mono" rows="8" style="width:100%">{{ old('key_array', $export ? json_encode($nested, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : "{\n    \"customer\": { \"name\": \"Name\", \"account\": \"Account\" },\n    \"status\": \"Status\"\n}") }}</textarea>
            @error('key_array')<div class="alert bad">{{ $message }}</div>@enderror
            <div class="actions" style="margin-top:8px"><button class="btn">Save Key Array</button></div>
        </form>
    </details>
</div>
