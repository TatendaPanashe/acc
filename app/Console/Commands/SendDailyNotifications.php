<?php

namespace App\Console\Commands;

use App\Console\Concerns\RunsForEachCompany;
use App\Models\Company;
use App\Modules\Accounting\Models\SalesInvoice;
use App\Modules\Accounting\Services\Approvals\ApprovalDocuments;
use App\Notifications\Customers\CustomerInvoiceOverdue;
use App\Notifications\Team\PendingApprovalsDigest;
use App\Services\Notifications\Notifier;
use Illuminate\Console\Command;

/**
 * Daily emails per company (each only when switched on under Company → Notifications):
 *  - a digest of documents waiting for approval, to Company Admins and Finance Managers;
 *  - overdue invoice reminders to customers, at the reminder days in config/notifications.php.
 */
class SendDailyNotifications extends Command
{
    use RunsForEachCompany;

    protected $signature = 'notifications:daily {--company= : Only this company id}';

    protected $description = 'Send the daily approvals digest and overdue invoice reminders';

    public function handle(Notifier $notifier): int
    {
        $this->forEachCompany(function (Company $company) use ($notifier) {
            $digest = $this->approvalsDigest($company, $notifier);
            $reminders = $this->overdueReminders($company, $notifier);
            if ($digest || $reminders) {
                $this->line($company->name.': '.$digest.' digest emails, '.$reminders.' overdue reminders.');
            }
        }, $this->option('company') ? (int) $this->option('company') : null);

        return self::SUCCESS;
    }

    private function approvalsDigest(Company $company, Notifier $notifier): int
    {
        if (!$notifier->enabled('approvals.digest', $company)) {
            return 0;
        }

        $counts = [];
        $oldest = null;
        foreach (array_keys(ApprovalDocuments::TYPES) as $type) {
            $pending = ApprovalDocuments::pending($type);
            $count = (clone $pending)->count();
            if ($count > 0) {
                $counts[ApprovalDocuments::label($type)] = $count;
                $first = (clone $pending)->reorder()->min('created_at');
                $oldest = $first && (!$oldest || $first < $oldest) ? $first : $oldest;
            }
        }
        if (!$counts) {
            return 0;
        }

        $days = $oldest ? (int) \Illuminate\Support\Carbon::parse($oldest)->diffInDays(now()) : 0;

        return $notifier->send('approvals.digest', $notifier->companyUsers($company->id, ['admin', 'finance_manager']),
            new PendingApprovalsDigest($company->name, $counts, $days, $company->id), $company, once: 'digest:'.$company->id.':'.today()->toDateString());
    }

    private function overdueReminders(Company $company, Notifier $notifier): int
    {
        $stages = collect(config('notifications.overdue_reminder_days', []))->sort()->values();
        if ($stages->isEmpty() || !$notifier->enabled('customers.overdue_reminder', $company)) {
            return 0;
        }

        $sent = 0;
        SalesInvoice::with('customer')->whereIn('status', ['posted', 'part_paid'])->whereNotNull('due_date')
            ->whereDate('due_date', '<=', today()->subDays($stages->first())->toDateString())
            ->each(function (SalesInvoice $invoice) use ($notifier, $stages, &$sent) {
                if ($invoice->balance <= 0 || !$invoice->customer?->email) {
                    return;
                }
                $days = (int) $invoice->due_date->diffInDays(today());
                // The latest reminder day reached; a missed run is caught up within a week, never repeated.
                // Older debts are left alone so switching reminders on does not email every old invoice.
                $stage = $stages->filter(fn ($d) => $d <= $days)->last();
                if ($days - $stage > 7) {
                    return;
                }
                $sent += $notifier->send('customers.overdue_reminder', $invoice->customer->email, new CustomerInvoiceOverdue($invoice, $days),
                    (int) $invoice->company_id, once: 'overdue:'.$invoice->id.':'.$stage, subject: $invoice);
            });

        return $sent;
    }
}
