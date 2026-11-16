<?php

/*
|--------------------------------------------------------------------------
| Email notifications
|--------------------------------------------------------------------------
|
| Every email the app sends is one of these events, sent through
| App\Services\Notifications\Notifier. Companies switch the optional ones on
| or off under Company → Notifications; "locked" events (account and billing
| messages about the workspace itself) are always sent.
|
|   'key' => [label, description, group, default on?, locked?]
|
| Customer-facing emails start switched off: a company should choose to
| start emailing its own customers.
*/

return [
    // How emails are sent: "after_response" (default; no queue worker needed, sent right after the page
    // loads), "queue" (needs `php artisan queue:work` running; adds automatic retries) or "sync".
    'delivery' => env('NOTIFICATIONS_DELIVERY', 'after_response'),

    // Platform operator inbox for new sign-ups (null disables the alert).
    'platform_email' => env('NOTIFICATIONS_PLATFORM_EMAIL', env('SITE_CONTACT_EMAIL', env('BILLING_SALES_EMAIL'))),

    // Days before a trial ends to remind the company admins.
    'trial_reminder_days' => [3, 1],

    // Days past due on which customers are reminded about an unpaid invoice.
    'overdue_reminder_days' => [1, 7, 14, 30],

    'groups' => [
        'account' => 'Account & subscription',
        'team' => 'Team & approvals',
        'payroll' => 'Payroll & employee self-service',
        'customers' => 'Customers',
    ],

    'events' => [
        // Account & subscription (sent to Company Admins; always on)
        'account.welcome' => ['Welcome email', 'Sent to the person who signs up.', 'account', true, true],
        'billing.trial_ending' => ['Trial ending soon', 'Reminders before the free trial ends.', 'account', true, true],
        'billing.invoice_due' => ['Subscription invoice due / overdue', 'Renewal invoices and overdue reminders.', 'account', true, true],
        'billing.payment_received' => ['Subscription payment received', 'A receipt when a subscription invoice is paid.', 'account', true, true],
        'billing.payment_failed' => ['Subscription payment failed', 'When a card or mobile payment is declined.', 'account', true, true],
        'billing.subscription_changed' => ['Subscription changes', 'Plan changes, trial expiry, suspension, reactivation and cancellation.', 'account', true, true],

        // Team & approvals
        'team.member_added' => ['New team member', 'Tell people when they are added to the workspace.', 'team', true, false],
        'approvals.decided' => ['Approval decisions', 'Tell the preparer when their document is approved or returned.', 'team', true, false],
        'approvals.digest' => ['Daily approvals digest', 'A morning summary of documents waiting for approval, to approvers.', 'team', true, false],

        // Payroll & employee self-service
        'payroll.payslip_ready' => ['Payslip ready', 'Email each employee when a payroll is approved (portal link, or the PDF if they have no portal access).', 'payroll', true, false],
        'payroll.request_submitted' => ['Leave / overtime submitted', 'Tell approvers when an employee submits a request.', 'payroll', true, false],
        'payroll.request_decided' => ['Leave / overtime decision', 'Tell the employee when their request is approved or declined.', 'payroll', true, false],
        'portal.access_issued' => ['Portal sign-in details', 'Email employees and customers their one-time portal password.', 'payroll', true, false],

        // Customers
        'customers.invoice_issued' => ['Email invoices to customers', 'Send the invoice PDF to the customer when an invoice is posted.', 'customers', false, false],
        'customers.receipt' => ['Payment receipts', 'Confirm to the customer when a receipt is posted.', 'customers', false, false],
        'customers.overdue_reminder' => ['Overdue invoice reminders', 'Remind customers about unpaid invoices 1, 7, 14 and 30 days after the due date.', 'customers', false, false],
    ],
];
