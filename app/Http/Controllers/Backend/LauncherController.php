<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Support\AdminApps;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** The page the website's Admin button opens: one tile per admin app, built-in or custom. */
class LauncherController extends Controller
{
    public function __invoke(Request $request): View
    {
        $denied = AdminApps::get($request->query('denied'));

        return view('backend.launcher', [
            'apps' => AdminApps::all(),
            'denied' => $denied ? $denied['name'] : null,
        ]);
    }
}
