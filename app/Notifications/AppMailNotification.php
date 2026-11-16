<?php

namespace App\Notifications;

use App\Models\Company;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

/**
 * Base for app emails. Queued (sent straight away when QUEUE_CONNECTION=sync); send them through
 * App\Services\Notifications\Notifier so company settings, de-duplication and logging apply.
 */
abstract class AppMailNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [60, 300];

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * A new email in the acc263 layout. Pass the company for emails to its own employees and
     * customers: they are then branded as the company (header, sender name, reply-to, footer).
     */
    protected function message(?Company $company = null): AppMailMessage
    {
        return (new AppMailMessage)->brand($company);
    }

    protected static function money(?string $currency, float|string|null $amount): string
    {
        return trim(($currency ?? '').' '.number_format((float) $amount, 2));
    }

    protected static function date(mixed $date, string $format = 'd M Y'): ?string
    {
        return $date ? Carbon::parse($date)->format($format) : null;
    }

    protected static function number(float|string|null $value): string
    {
        return rtrim(rtrim(number_format((float) $value, 2), '0'), '.');
    }

    /** "How to pay" rows from the company's active bank account in this currency (primary first), or [] if none. */
    protected static function paymentDetails(?Company $company, ?string $currency, string $reference): array
    {
        $bank = $company?->bankAccounts()->where('status', true)
            ->orderByRaw('CASE WHEN currency = ? THEN 0 ELSE 1 END', [$currency])->orderByDesc('is_primary')->first();

        return $bank ? [
            'Bank' => $bank->bank_name,
            'Account name' => $bank->account_name,
            'Account number' => $bank->account_number,
            'Branch code' => $bank->branch_code,
            'Currency' => $bank->currency,
            'Payment reference' => $reference,
        ] : [];
    }

    /** Footer line for emails to a company's admins about its workspace. */
    protected static function adminFootnote(?string $companyName): string
    {
        return 'You are receiving this because you are a Company Admin'.($companyName ? ' of '.$companyName : '').' on '.config('app.name').'.';
    }

    /** Footer line for optional emails that admins can switch off. */
    protected static function optionalFootnote(): string
    {
        return 'Company Admins can choose which emails are sent under Company → Notifications.';
    }
}
