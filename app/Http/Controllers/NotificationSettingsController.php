<?php

namespace App\Http\Controllers;

use App\Notifications\Account\TestEmail;
use App\Services\Notifications\Notifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/** Company → Notifications: which emails the company sends, a test email, and recent sends. */
class NotificationSettingsController extends Controller
{
    public function edit(Request $request, Notifier $notifier): View
    {
        $company = $request->user()->company;
        $events = collect(config('notifications.events'))->map(fn ($e, $key) => [
            'key' => $key, 'label' => $e[0], 'description' => $e[1], 'group' => $e[2], 'locked' => $e[4],
            'enabled' => $notifier->enabled($key, $company),
        ])->groupBy('group');

        return view('company.notifications.edit', [
            'groups' => config('notifications.groups'),
            'events' => $events,
            'recent' => DB::table('notification_log')->where('company_id', $company->id)->latest('id')->limit(20)->get(),
            'mailer' => config('mail.default'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $company = $request->user()->company;
        $on = (array) $request->input('events', []);
        $settings = [];
        foreach (config('notifications.events') as $key => $e) {
            if (!$e[4]) {
                $settings[$key] = array_key_exists($key, $on);
            }
        }
        $company->forceFill(['notification_settings' => $settings])->save();

        return back()->with('success', 'Notification settings saved.');
    }

    public function test(Request $request): RedirectResponse
    {
        $user = $request->user();
        try {
            \Illuminate\Support\Facades\Notification::sendNow($user, new TestEmail($user->company?->name));
        } catch (\Throwable $e) {
            return back()->with('error', 'The test email could not be sent: '.$e->getMessage());
        }

        return back()->with('success', 'Test email sent to '.$user->email.(config('mail.default') === 'log' ? ' (mail is set to "log": it was written to the log file, not delivered).' : '.'));
    }
}
