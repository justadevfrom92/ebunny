<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\ApiLog;
use Illuminate\View\View;

/** One API call (Sheriff → APIs, Corral → API Log): what was sent, what came back, and the API's other calls. */
class ApiCallController extends Controller
{
    public function show(ApiLog $call): View
    {
        $integration = collect(config('admin.integrations'))->search(fn ($i) => $i['name'] === $call->api);

        return view('backend.api-call', ['call' => $call->load('customer'), 'integration' => $integration ?: null,
            'others' => ApiLog::where('api', $call->api)->whereKeyNot($call->id)->when($call->customer_id, fn ($q) => $q->where('customer_id', $call->customer_id))
                ->latest('created_at')->latest('id')->paginate(10)->withQueryString()]);
    }
}
