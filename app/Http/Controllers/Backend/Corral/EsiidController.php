<?php

namespace App\Http\Controllers\Backend\Corral;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * ESIID lookup. Searches the premises already on file; a live ERCOT premise lookup
 * needs an ERCOT/TDSP data integration, which this app does not have yet.
 */
class EsiidController extends Controller
{
    public function index(Request $request): View
    {
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'zip' => ['nullable', 'digits:5'],
        ]);

        $results = null;
        if (! empty($data['q'])) {
            $results = Customer::with('market')
                ->where(fn ($w) => $w->where('address', 'like', '%'.$data['q'].'%')->orWhere('esiid', 'like', $data['q'].'%'))
                ->when($data['zip'] ?? null, fn ($w, $zip) => $w->where('zip', $zip))
                ->orderBy('address')->limit(100)->get();
        }

        return view('backend.corral.esiid', ['q' => $data['q'] ?? '', 'zip' => $data['zip'] ?? '', 'results' => $results]);
    }
}
