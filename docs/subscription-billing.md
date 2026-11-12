# Subscription billing: operator guide

How the acc263 subscription lifecycle works and what must be configured before taking real payments.

## Lifecycle

```
Register → choose plan → 14-day trial ──trial ends──▶ locked (Billing only)
                              │
                  choose plan & pay (activation invoice)
                              ▼
               verify with provider ──failed──▶ invoice stays open, retry
                              │ successful
                              ▼
     active ── 7 days before renewal: renewal invoice + email reminder
        │                         │ paid → next period
        └─ unpaid at renewal → past due (7-day grace, banner) → suspended (Billing only)
                                                              │ paid → active again
```

Access is decided from dates on every request, so a workspace locks on time even if the scheduler is late.

## Data model (all `platform_*` tables)

| Spec table | Implemented as |
|---|---|
| tenants | `companies` (+ `slug`, `status`, `country`) |
| plans, plan_features | `platform_plans`, `platform_plan_features` (limits `users`, `branches`, `companies`; other keys are features) |
| subscriptions, subscription_items | `platform_subscriptions` (`starts_at`, `current_period_ends_at` = ends_at, `grace_ends_at`, `limit_overrides`), `platform_subscription_items` |
| invoices, payments | `platform_invoices` (purpose `activation`/`renewal`, lines, coupon), `platform_payments` |
| payment_gateways, payment_webhooks | `platform_payment_gateways` (credentials encrypted), `platform_payment_webhooks` (deduplicated by provider + event id) |
| usage_records, coupons | `platform_usage_records` (monthly snapshot), `platform_coupons` |
| subscription_events | `platform_subscription_events` (also the MRR ledger for the dashboard) |

## Before going live

1. **Scheduler** – run `php artisan schedule:work` (or a cron entry `* * * * * php artisan schedule:run`). It runs `billing:run` hourly: trial/promotion expiry, renewal invoices and reminder emails, past due, suspension.
2. **Mail** – set `MAIL_MAILER` and SMTP settings; reminders go to each tenant's Company Admins.
3. **APP_URL** – must be the public HTTPS address; provider return and webhook URLs are built from it.
4. **Payment gateways** (System → Payment gateways):
   - **Pesepay** (EcoCash, InnBucks, ZimSwitch, Visa/Mastercard): Integration key and Encryption key (16, 24 or 32 characters) from the Pesepay dashboard. Test mode uses the Pesepay sandbox; switch to Live for real payments. Result URL (sent automatically with each payment): `https://<your-domain>/billing/webhooks/pesepay`. Keys can also be supplied as `PESEPAY_INTEGRATION_KEY` / `PESEPAY_ENCRYPTION_KEY` in `.env`; keys saved on the page take precedence. Pesepay callbacks are not signed, so each one is re-checked with Pesepay's API before a payment is accepted; the amount and currency must match the invoice.
   - **Paynow**: Integration ID and key from the Paynow merchant portal; in test mode also the merchant email. Result URL: `https://<your-domain>/billing/webhooks/paynow`.
   - **Stripe**: secret key and webhook signing secret. Add a webhook endpoint `https://<your-domain>/billing/webhooks/stripe` for `checkout.session.completed`, `checkout.session.async_payment_succeeded`, `checkout.session.async_payment_failed` and `checkout.session.expired`.
   - **Bank transfer**: bank details shown to customers (or set `BILLING_BANK_*` in `.env`). Confirm receipts on the tenant's page (Record payment).
   - **Sandbox**: test-only; cannot be enabled in production. Disable it before launch.
5. Optional `.env`: `BILLING_SALES_EMAIL` (Enterprise "Contact us").

The Pesepay, Paynow and Stripe integrations are implemented against their documented APIs and covered by tests with simulated responses; run an end-to-end payment in each provider's test mode before enabling live mode.

## Feature gating

`config/billing.php` → `route_features` maps screens to plan features (bank reconciliation, exchange rates, cash flow & statutory reports, ZIMRA submission, audit trail). Limits are enforced when adding users (Team) and branches (Warehouses). Finance Manager and Auditor roles need *Advanced permissions*. Features without a screen yet (payroll, consolidated reporting, API access, custom integrations) are listed on plans only.
