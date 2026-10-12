<?php

namespace App\Http\Controllers\Backend\Corral;

use App\Http\Controllers\Controller;
use App\Models\ErcotTransaction;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Corral → ERCOT: search market transactions (814s, 867s, 650s…) across every account. */
class ErcotController extends Controller
{
    public function index(Request $request): View
    {
        $types = ErcotTransaction::query()->distinct()->orderBy('trans_type')->pluck('trans_type');
        $f = $request->validate([
            'esiid' => ['nullable', 'string', 'max:40'],
            'account' => ['nullable', 'string', 'max:20'],
            'tracking' => ['nullable', 'string', 'max:40'],
            'type' => ['nullable', Rule::in($types->all())],
            'purpose' => ['nullable', Rule::in(['request', 'response'])],
            'status' => ['nullable', Rule::in(['linked', 'unlinked', 'cancelled'])],
            'start' => ['nullable', 'date'],
            'end' => ['nullable', 'date', 'after_or_equal:start'],
        ]);

        $rows = ErcotTransaction::with('customer')
            ->when($f['esiid'] ?? null, fn ($q, $v) => $q->where('esiid', 'like', '%'.addcslashes(trim($v), '%_\\').'%'))
            ->when($f['account'] ?? null, fn ($q, $v) => $q->whereHas('customer', fn ($c) => $c->where('account', trim($v))))
            ->when($f['tracking'] ?? null, fn ($q, $v) => $q->where('tracking', 'like', '%'.addcslashes(trim($v), '%_\\').'%'))
            ->when($f['type'] ?? null, fn ($q, $v) => $q->where('trans_type', $v))
            ->when($f['purpose'] ?? null, fn ($q, $v) => $q->where('purpose', $v))
            ->when($f['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($f['start'] ?? null, fn ($q, $v) => $q->whereDate('trans_date', '>=', $v))
            ->when($f['end'] ?? null, fn ($q, $v) => $q->whereDate('trans_date', '<=', $v))
            ->latest('trans_date')->latest('id')->paginate(50)->withQueryString();

        return view('backend.corral.ercot', ['f' => $f, 'types' => $types, 'rows' => $rows,
            'counts' => ErcotTransaction::selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status')]);
    }
}
