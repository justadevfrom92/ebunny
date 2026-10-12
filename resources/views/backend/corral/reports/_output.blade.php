{{-- Output choice + Run button shared by every Corral report --}}
<div class="field"><label for="output">Output</label><select id="output" name="output">
    @foreach (['csv' => 'Output - CSV', 'csv-summary' => 'Output - CSV - Summary', 'xls' => 'Output - XLS', 'xls-summary' => 'Output - XLS - Summary', 'screen' => 'Output - On-Screen', 'summary' => 'Output - On-Screen - Summary', 'backend' => 'Output - Backend'] as $v => $l)
        <option value="{{ $v }}" @selected($f['output'] === $v)>{{ $l }}</option>
    @endforeach
</select></div>
<button class="btn" name="run" value="1">Run Report</button>
