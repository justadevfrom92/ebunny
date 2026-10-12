<?php

namespace App\Http\Controllers\Backend\Corral;

use App\Http\Controllers\Controller;
use App\Models\Phonecall;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/** Corral → Phone Calls: recent calls with their issue, duration, who answered and a link to the transcript. */
class CallController extends Controller
{
    public function index(Request $request): View
    {
        $f = $request->validate([
            'start' => ['nullable', 'date'], 'end' => ['nullable', 'date'], 'account' => ['nullable', 'string', 'max:20'],
            'phone' => ['nullable', 'string', 'max:30'], 'agent' => ['nullable', 'string', 'max:20'], 'user' => ['nullable', 'integer'],
            'direction' => ['nullable', 'in:inbound,outbound'], 'issue' => ['nullable', 'string', 'max:60'],
        ]);
        $digits = preg_replace('/\D/', '', (string) ($f['phone'] ?? ''));
        $calls = Phonecall::with(['customer', 'user'])
            ->when($f['start'] ?? null, fn ($q, $d) => $q->where('started_at', '>=', Carbon::parse($d)->startOfDay()))
            ->when($f['end'] ?? null, fn ($q, $d) => $q->where('started_at', '<=', Carbon::parse($d)->endOfDay()))
            ->when($f['account'] ?? null, fn ($q, $a) => $q->whereHas('customer', fn ($c) => $c->where('account', trim($a))))
            ->when($digits, fn ($q) => $q->whereRaw("replace(replace(replace(replace(phone, '(', ''), ')', ''), '-', ''), ' ', '') like ?", ['%'.$digits.'%']))
            ->when($f['agent'] ?? null, fn ($q, $a) => $q->where('agent_id', trim($a)))
            ->when($f['user'] ?? null, fn ($q, $u) => $q->where('user_id', $u))
            ->when($f['direction'] ?? null, fn ($q, $d) => $q->where('direction', $d))
            ->when($f['issue'] ?? null, fn ($q, $i) => $q->where('disposition', $i))
            ->latest('started_at')->paginate(50)->withQueryString();

        return view('backend.corral.calls.index', ['calls' => $calls, 'f' => $f,
            'issues' => Phonecall::whereNotNull('disposition')->distinct()->orderBy('disposition')->pluck('disposition'),
            'users' => User::whereIn('id', Phonecall::whereNotNull('user_id')->distinct()->pluck('user_id'))->orderBy('name')->get(['id', 'name'])]);
    }

    public function transcript(Phonecall $call): View
    {
        $call->load(['customer', 'user']);

        return view('backend.corral.calls.transcript', ['call' => $call]);
    }
}
