<?php

namespace App\Http\Controllers;

use App\Models\Platform\Enquiry;
use App\Models\Platform\Plan;
use App\Notifications\NewEnquiry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Public marketing website: home, services, how we work, contact. */
class SiteController extends Controller
{
    public function home(): View
    {
        return view('site.home', ['plans' => $this->plans()]);
    }

    public function services(): View
    {
        return view('site.services', ['plans' => $this->plans()]);
    }

    public function how(): View
    {
        return view('site.how');
    }

    public function contact(Request $request): View
    {
        return view('site.contact', [
            'topics' => Enquiry::TOPICS,
            'topic' => array_key_exists($request->query('topic'), Enquiry::TOPICS) ? $request->query('topic') : 'sales',
        ]);
    }

    public function send(Request $request): RedirectResponse
    {
        // Honeypot: real visitors never see or fill the "website" field.
        if (filled($request->input('website'))) {
            return redirect()->route('site.contact')->with('sent', true);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190'],
            'company' => ['nullable', 'string', 'max:190'],
            'phone' => ['nullable', 'string', 'max:40'],
            'topic' => ['required', Rule::in(array_keys(Enquiry::TOPICS))],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
        ]);

        $enquiry = Enquiry::create($data + ['ip_address' => $request->ip()]);

        try {
            Notification::route('mail', config('site.email'))->notify(new NewEnquiry($enquiry));
        } catch (\Throwable $e) {
            Log::warning('Could not email website enquiry #'.$enquiry->id.': '.$e->getMessage());
        }

        return redirect()->route('site.contact')->with('sent', true);
    }

    private function plans()
    {
        return Plan::with('features')->where('is_active', true)->orderBy('sort_order')->get();
    }
}
