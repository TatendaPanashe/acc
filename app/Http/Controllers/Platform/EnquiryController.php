<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Platform\Enquiry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EnquiryController extends Controller
{
    public function index(Request $request): View
    {
        return view('platform.enquiries', [
            'enquiries' => Enquiry::when($request->query('show') !== 'all', fn ($q) => $q->whereNull('handled_at'))->latest()->paginate(25)->withQueryString(),
            'open' => Enquiry::whereNull('handled_at')->count(),
        ]);
    }

    public function handled(Request $request, Enquiry $enquiry): RedirectResponse
    {
        $enquiry->update(['handled_at' => $enquiry->handled_at ? null : now(), 'handled_by' => $request->user()->id]);

        return back()->with('success', 'Enquiry from '.$enquiry->name.' marked '.($enquiry->handled_at ? 'handled' : 'open').'.');
    }
}
