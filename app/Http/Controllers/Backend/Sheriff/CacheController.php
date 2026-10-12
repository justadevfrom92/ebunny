<?php

namespace App\Http\Controllers\Backend\Sheriff;

use App\Http\Controllers\Controller;
use App\Models\TableSnapshot;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/** Sheriff → Cached Tables: the copies of page tables (as visited) and database tables (as records were added). */
class CacheController extends Controller
{
    public function index(Request $request): View
    {
        $kind = $request->query('kind') === 'record' ? 'record' : 'page';
        $app = $request->query('app');
        $latest = TableSnapshot::where('kind', $kind)->when($app, fn ($q) => $q->where('app', $app))
            ->selectRaw('max(id) as id, count(*) as copies')->groupBy('key');

        return view('backend.sheriff.cache', [
            'kind' => $kind,
            'app' => $app,
            'apps' => TableSnapshot::where('kind', 'page')->whereNotNull('app')->distinct()->orderBy('app')->pluck('app'),
            'rows' => TableSnapshot::with('user')->joinSub($latest, 'l', 'l.id', '=', 'table_snapshots.id')
                ->select('table_snapshots.*', 'l.copies')->orderByDesc('table_snapshots.id')->paginate(50)->withQueryString(),
            'counts' => TableSnapshot::selectRaw('kind, count(distinct '.DB::getQueryGrammar()->wrap('key').') as n')->groupBy('kind')->pluck('n', 'kind'),
        ]);
    }

    public function show(TableSnapshot $snapshot): View
    {
        return view('backend.sheriff.cache-show', [
            's' => $snapshot->load('user'),
            'versions' => TableSnapshot::with('user')->where('kind', $snapshot->kind)->where('key', $snapshot->key)->latest('id')->get(['id', 'created_at', 'user_id', 'row_count', 'title', 'record_id']),
        ]);
    }
}
