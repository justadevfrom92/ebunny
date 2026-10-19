{{-- Export format + Run button shared by every Corral report. Reports always run in the background and save a file. --}}
<div class="field"><label>Export as</label><div class="chips small">
    @foreach (\App\Support\Export::FORMATS as $v => [$l])<label><input type="radio" name="format" value="{{ $v }}" @checked(($f['format'] ?? 'xlsx') === $v)><span>{{ $l }}</span></label>@endforeach
</div></div>
<label class="check"><input type="hidden" name="summary" value="0"><input type="checkbox" name="summary" value="1" @checked($f['summary'] ?? false)> Summary only (counts instead of every row)</label>
<button class="btn" name="run" value="1">Run Report</button>
<p class="help">Runs in the background. You'll get a notice at the top right when the file is ready.</p>
