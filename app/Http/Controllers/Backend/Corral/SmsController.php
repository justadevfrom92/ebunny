<?php

namespace App\Http\Controllers\Backend\Corral;

use App\Http\Controllers\Controller;
use App\Models\ContactLog;
use App\Models\Customer;
use App\Services\AccountActions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/** Corral → SMS: a table of recent text messages, in and out. Sending goes through the SMS integration in .env. */
class SmsController extends Controller
{
    /** One text message, with the whole conversation with that customer. */
    public function show(ContactLog $message): View
    {
        abort_unless($message->channel === 'SMS', 404);
        $message->load(['customer', 'user']);

        return view('backend.corral.sms-show', ['m' => $message,
            'thread' => ContactLog::where('channel', 'SMS')->where('customer_id', $message->customer_id)->with('user')->oldest('created_at')->oldest('id')->get()]);
    }

    public function index(Request $request): View
    {
        $f = $request->validate([
            'q' => ['nullable', 'string', 'max:100'], 'account' => ['nullable', 'string', 'max:20'], 'direction' => ['nullable', 'in:in,out'],
            'status' => ['nullable', 'string', 'max:20'], 'start' => ['nullable', 'date'], 'end' => ['nullable', 'date'], 'waiting' => ['nullable', 'boolean'],
        ]);
        // Customers whose latest text came from them are waiting on a reply
        $latest = ContactLog::where('channel', 'SMS')->selectRaw('max(id) as id')->groupBy('customer_id');
        $waiting = ContactLog::whereIn('id', $latest)->where('direction', 'in')->pluck('customer_id');
        $q = trim((string) ($f['q'] ?? ''));
        $digits = preg_replace('/\D/', '', $q);

        $messages = ContactLog::where('channel', 'SMS')->with(['customer', 'user'])
            ->when($q !== '', fn ($x) => $x->where(fn ($w) => $w->where('body', 'like', '%'.$q.'%')
                ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', '%'.$q.'%'))
                ->when($digits !== '', fn ($w) => $w->orWhereRaw("replace(replace(replace(replace(phone, '(', ''), ')', ''), '-', ''), ' ', '') like ?", ['%'.$digits.'%']))))
            ->when($f['account'] ?? null, fn ($x, $a) => $x->whereHas('customer', fn ($c) => $c->where('account', trim($a))))
            ->when($f['direction'] ?? null, fn ($x, $d) => $x->where('direction', $d))
            ->when($f['status'] ?? null, fn ($x, $s) => $x->where('status', $s))
            ->when($f['start'] ?? null, fn ($x, $d) => $x->where('created_at', '>=', Carbon::parse($d)->startOfDay()))
            ->when($f['end'] ?? null, fn ($x, $d) => $x->where('created_at', '<=', Carbon::parse($d)->endOfDay()))
            ->when($f['waiting'] ?? false, fn ($x) => $x->whereIn('customer_id', $waiting))
            ->latest('created_at')->latest('id')->paginate(50)->withQueryString();

        return view('backend.corral.sms', ['messages' => $messages, 'f' => $f, 'waiting' => $waiting, 'ready' => self::ready(),
            'statuses' => ContactLog::where('channel', 'SMS')->distinct()->pluck('status')]);
    }

    /** Send a Text: its own page with a simple form. */
    public function create(Request $request): View
    {
        $c = $request->query('account') ? Customer::where('account', $request->query('account'))->first() : null;

        return view('backend.corral.sms-form', ['c' => $c, 'ready' => self::ready(),
            'recent' => $c ? ContactLog::where('channel', 'SMS')->where('customer_id', $c->id)->latest('created_at')->limit(5)->get()->reverse() : collect()]);
    }

    public function send(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'account' => ['required', 'string', 'exists:customers,account'],
            'message' => ['required', 'string', 'max:480'],
        ]);
        $c = Customer::where('account', $data['account'])->firstOrFail();
        abort_if(blank($c->phone), 422, 'This account has no phone number.');

        [$message] = DB::transaction(fn () => (new AccountActions($c, $request->user()))->run('send-text', ['message' => $data['message']]));

        return redirect()->route('corral.sms')->with('status', $message);
    }

    private static function ready(): bool
    {
        return collect(config('admin.integrations.sms.env'))->every(fn ($v) => filled($v));
    }
}
