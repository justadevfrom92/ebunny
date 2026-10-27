<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\User;
use App\Support\Records;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** One record from a table that has no page of its own: every field, the account and the person it belongs to. */
class RecordController extends Controller
{
    public function show(Request $request, string $type, int $id): View
    {
        [$label, $fields, $model] = Records::find($type, $id);
        $fields = collect($fields)->reject(fn ($v, $k) => Records::hidden($k));

        return view('backend.records.show', ['type' => $type, 'id' => $id, 'label' => $label, 'fields' => $fields,
            'customer' => isset($fields['customer_id']) ? Customer::find($fields['customer_id']) : null,
            'person' => User::find($fields['user_id'] ?? $fields['triggered_by'] ?? null),
            'back' => url()->previous() !== $request->fullUrl() ? url()->previous() : null]);
    }
}
