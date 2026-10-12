<?php

namespace App\Http\Controllers\Backend\Corral;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Messages sent through the website's Contact Us form. */
class MessageController extends Controller
{
    public function index(): View
    {
        return view('backend.corral.messages', [
            'open' => ContactMessage::open()->latest()->get(),
            'handled' => ContactMessage::whereNotNull('handled_at')->latest('handled_at')->limit(25)->get(),
        ]);
    }

    public function handled(Request $request, ContactMessage $message): RedirectResponse
    {
        $message->update(['handled_at' => now(), 'handled_by' => $request->user()->id]);

        return back()->with('status', 'Marked as handled');
    }
}
