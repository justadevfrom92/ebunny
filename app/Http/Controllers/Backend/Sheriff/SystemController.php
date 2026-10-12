<?php

namespace App\Http\Controllers\Backend\Sheriff;

use App\Http\Controllers\Controller;
use App\Models\ApiLog;
use App\Models\JobRun;
use App\Models\ReferenceRow;
use App\Support\AdminMenu;
use App\Support\History;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** API integrations, scheduled jobs and reference data tables. */
class SystemController extends Controller
{
    /** Shows only whether each .env value is set. Secret values are never displayed. */
    public function integrations(): View
    {
        $integrations = collect(config('admin.integrations'))->map(fn ($i) => $i + [
            'set' => collect($i['env'])->map(fn ($v) => filled($v))->all(),
            'configured' => collect($i['env'])->every(fn ($v) => filled($v)),
        ]);

        return view('backend.sheriff.integrations', ['integrations' => $integrations]);
    }

    /** One integration: which settings are present and its recent calls. */
    public function integration(string $integration): View
    {
        abort_unless(config()->has('admin.integrations.'.$integration), 404);
        $i = config('admin.integrations.'.$integration);

        return view('backend.sheriff.integration', [
            'i' => $i + ['set' => collect($i['env'])->map(fn ($v) => filled($v))->all(), 'configured' => collect($i['env'])->every(fn ($v) => filled($v))],
            'calls' => ApiLog::where('api', $i['name'])->with('customer')->latest('created_at')->latest('id')->limit(50)->get(),
            'stats' => ApiLog::where('api', $i['name'])->where('created_at', '>=', now()->subDays(30))
                ->selectRaw("count(*) as total, sum(case when status like '2%' then 1 else 0 end) as ok, avg(response_ms) as avg_ms")->first(),
        ]);
    }

    /** Config → Update Sitemap */
    public function sitemap(): View
    {
        $file = public_path('sitemap.xml');

        return view('backend.sheriff.sitemap', [
            'builtAt' => is_file($file) ? Carbon::createFromTimestamp(filemtime($file)) : null,
            'urls' => is_file($file) ? substr_count((string) file_get_contents($file), '<url>') : 0,
            'last' => JobRun::where('command', 'et:sitemap')->latest('started_at')->first(),
        ]);
    }

    public function jobs(): View
    {
        $jobs = collect(config('admin.jobs'))->map(fn ($job, $command) => [
            'command' => $command, 'name' => $job[0], 'schedule' => $job[1],
            'last' => JobRun::where('command', $command)->latest('started_at')->first(),
        ]);

        return view('backend.sheriff.jobs', ['jobs' => $jobs, 'history' => JobRun::latest('started_at')->limit(15)->get()]);
    }

    public function runJob(Request $request): RedirectResponse
    {
        $data = $request->validate(['command' => ['required', Rule::in(array_keys(config('admin.jobs')))]]);
        Artisan::call($data['command'], ['--user' => $request->user()->id]);
        $run = JobRun::where('command', $data['command'])->latest('id')->first();
        History::record(['model' => 'JobRun_model', 'group' => 'Admin Changes', 'record_id' => $run?->id, 'action' => 'logged',
            'summary' => 'Ran '.$data['command'].': '.($run?->status ?? 'done'), 'data' => $run?->only(['command', 'status', 'message', 'duration_ms'])]);

        return back()->with('status', config('admin.jobs.'.$data['command'])[0].': '.($run?->status ?? 'done').($run?->message ? ' — '.$run->message : ''));
    }

    /** A reference table, only in the app that owns it. */
    private static function owned(string $table): array
    {
        $t = config('admin.reference_tables.'.$table);
        abort_unless($t && ($t[2] ?? 'sheriff') === AdminMenu::currentApp(), 404);

        return $t;
    }

    public function table(string $table): View
    {
        [$name, $columns] = self::owned($table);

        return view('backend.sheriff.table', [
            'key' => $table, 'name' => $name, 'columns' => $columns,
            'rows' => ReferenceRow::where('table_key', $table)->orderBy('position')->get(),
        ]);
    }

    /** Replaces the table's rows with what was submitted. */
    public function updateTable(Request $request, string $table): RedirectResponse
    {
        [$name, $columns] = self::owned($table);
        $data = $request->validate(['rows' => ['array'], 'rows.*' => ['array'], 'rows.*.*' => ['nullable', 'string', 'max:500']]);

        DB::transaction(function () use ($table, $columns, $data) {
            ReferenceRow::where('table_key', $table)->delete();
            foreach (array_values($data['rows'] ?? []) as $i => $cells) {
                $cells = array_map(fn ($c) => $cells[$c] ?? '', array_keys($columns));
                if (collect($cells)->filter(fn ($v) => trim((string) $v) !== '')->isEmpty()) {
                    continue;
                }
                ReferenceRow::create(['table_key' => $table, 'position' => $i, 'cells' => array_map(fn ($v) => (string) $v, $cells)]);
            }
        });

        History::record(['model' => 'ReferenceTable_model', 'group' => 'Admin Changes', 'action' => 'updated',
            'summary' => $name.' saved ('.ReferenceRow::where('table_key', $table)->count().' rows)',
            'data' => ['table' => $table, 'rows' => ReferenceRow::where('table_key', $table)->orderBy('position')->pluck('cells')->map(fn ($c) => implode(' | ', $c))->implode("\n")]]);

        return back()->with('status', $name.' saved');
    }
}
