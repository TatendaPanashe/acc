<?php

namespace App\Services\Notifications;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Notification as BaseNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * The one way the app sends email (see config/notifications.php):
 *  - skips events the company has switched off and recipients without an email address;
 *  - with a $once key, sends at most once per key and recipient (for scheduled reminders);
 *  - sends only after the surrounding database transaction commits, so a rolled-back change never emails anyone;
 *  - never lets a mail failure break the business action that triggered it (it is logged instead).
 */
class Notifier
{
    /** Whether $event is switched on for the company (locked events always are). */
    public function enabled(string $event, Company|int|null $company): bool
    {
        $definition = config('notifications.events')[$event] ?? throw new \InvalidArgumentException("Unknown notification event [{$event}].");
        [, , , $default, $locked] = $definition;
        if ($locked || $company === null) {
            return $locked || $default;
        }

        $company = $company instanceof Company ? $company : Company::find($company);
        $settings = (array) ($company?->notification_settings ?? []);

        return array_key_exists($event, $settings) ? (bool) $settings[$event] : (bool) $default;
    }

    /**
     * @param  User|string|iterable<User|string>|null  $recipients  users, or plain email addresses
     * @param  string|null  $once  dedupe key: the same key is never sent to the same address twice
     * @return int  emails handed to the mailer
     */
    public function send(string $event, mixed $recipients, BaseNotification $notification, Company|int|null $companyId = null, ?string $once = null, ?Model $subject = null): int
    {
        $companyId = $companyId instanceof Company ? $companyId->id : $companyId;
        if (!$this->enabled($event, $companyId)) {
            return 0;
        }

        $sent = 0;
        foreach ($this->normalise($recipients) as $email => $notifiable) {
            if (!$this->log($event, $email, $companyId, $once, $subject)) {
                continue; // already sent
            }
            DB::afterCommit(fn () => $this->deliver($event, $notifiable, $notification));
            $sent++;
        }

        return $sent;
    }

    /** Active Company Admins (and optionally other roles) of a company. */
    public function companyUsers(int $companyId, array $roles = ['admin']): Collection
    {
        return User::where('company_id', $companyId)->whereIn('role', $roles)->whereNotNull('email')->get();
    }

    // ------------------------------------------------------------------

    /** @return array<string, User|AnonymousNotifiable> keyed by lower-cased email, duplicates removed */
    private function normalise(mixed $recipients): array
    {
        $list = $recipients instanceof Collection ? $recipients->all() : (is_iterable($recipients) ? $recipients : [$recipients]);
        $out = [];
        foreach ($list as $recipient) {
            $email = $recipient instanceof Model ? $recipient->email : $recipient;
            $email = is_string($email) ? strtolower(trim($email)) : '';
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || isset($out[$email])) {
                continue;
            }
            $out[$email] = $recipient instanceof User ? $recipient : Notification::route('mail', $email);
        }

        return $out;
    }

    private function log(string $event, string $email, ?int $companyId, ?string $once, ?Model $subject): bool
    {
        $row = [
            'company_id' => $companyId,
            'event' => $event,
            'recipient' => $email,
            'subject_type' => $subject ? $subject::class : null,
            'subject_id' => $subject?->getKey(),
            'dedupe_key' => $once ? substr(sha1($event.'|'.$once.'|'.$email), 0, 40) : null,
            'created_at' => now(),
        ];

        return $once ? DB::table('notification_log')->insertOrIgnore($row) > 0 : DB::table('notification_log')->insert($row);
    }

    /**
     * Delivery (config notifications.delivery):
     *  - "after_response" (default): sent by this PHP process once the page has been returned to the browser,
     *    so no queue worker is needed and the user does not wait for the mail server;
     *  - "queue": handed to the queue (needs `php artisan queue:work` running; adds retries);
     *  - "sync": sent immediately.
     * Console commands (scheduler) always send immediately unless "queue" is chosen.
     */
    private function deliver(string $event, object $notifiable, BaseNotification $notification): void
    {
        $mode = config('notifications.delivery', 'after_response');
        $attempt = function (callable $send) use ($event) {
            try {
                $send();
            } catch (\Throwable $e) {
                Log::error("Email notification [{$event}] failed: ".$e->getMessage());
                report($e);
            }
        };

        if ($mode === 'queue') {
            $attempt(fn () => Notification::send($notifiable, $notification));
        } elseif ($mode === 'after_response' && !app()->runningInConsole()) {
            app()->terminating(fn () => $attempt(fn () => Notification::sendNow($notifiable, $notification)));
        } else {
            $attempt(fn () => Notification::sendNow($notifiable, $notification));
        }
    }
}
