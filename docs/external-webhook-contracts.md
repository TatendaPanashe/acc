# External Webhook API Contracts

Base endpoint: `POST /webhooks/{source}`

Required headers:
- `Content-Type: application/json`
- `X-Acc263-Signature: sha256=<hmac>` for production senders
- `X-Acc263-Timestamp: <unix timestamp>`

Envelope:
```json
{
  "event_id": "evt_01HZX",
  "event_type": "einvoice.cleared",
  "company_id": 1,
  "occurred_at": "2026-08-09T10:30:00Z",
  "data": {}
}
```

Response:
```json
{
  "id": 1001,
  "status": "received",
  "received_at": "2026-08-09T10:30:01Z"
}
```

Supported event types:
- `ai.forecast.completed`
- `ai.autocoding.suggested`
- `portal.payment.completed`
- `portal.ticket.created`
- `revenue.contract.activated`
- `revenue.schedule.recognized`
- `einvoice.submitted`
- `einvoice.cleared`
- `einvoice.rejected`
- `einvoice.cancelled`
- `tax.authority.status_changed`
- `esg.activity.verified`
- `esg.report.submitted`

Payload contracts:

`ai.forecast.completed`
```json
{
  "model_key": "cashflow-30d-v1",
  "type": "cash_flow",
  "source_module": "accounts-receivable",
  "forecast_date": "2026-08-31",
  "predicted_value": 125000.75,
  "confidence": 87.5,
  "features": {"horizon_days": 30}
}
```

Client portal authentication:
- Customers are client users.
- Clients log in at `GET /client/login` with the email saved on their customer record.
- There is no default password. Finance staff issue a one-time temporary password from **Accounts Receivable → Customers → Edit → Client Portal Access**; it is displayed once and must be shared with the customer through a trusted channel.
- On first login with a temporary password, clients are redirected to `GET /client/password/change` and must set a new password before viewing invoices.
- Staff can reset or revoke portal access from the same screen; revoking ends any active portal session.
- Portal login is rate-limited to 5 attempts per minute per email and IP address (20 per minute per IP).
- Authenticated clients view invoices at `GET /client/dashboard`.
- Payment updates are submitted by `POST /client/invoices/{invoice}/payments` and saved as draft customer receipts for finance review.

`portal.payment.completed`
```json
{
  "portal_token": "cust_abc123",
  "invoice_number": "INV-1009",
  "amount": 450.00,
  "currency": "USD",
  "payment_reference": "pay_123"
}
```

`revenue.schedule.recognized`
```json
{
  "contract_number": "ASC606-2026-001",
  "recognition_date": "2026-08-31",
  "amount": 1000.00,
  "journal_reference": "JRN-0042"
}
```

`einvoice.cleared`
```json
{
  "document_number": "EINV-2026-001",
  "jurisdiction": "ZWE",
  "authority_reference": "ZIMRA-CLR-9921",
  "cleared_at": "2026-08-09T10:30:00Z"
}
```

`esg.activity.verified`
```json
{
  "activity_code": "ESG-FUEL-001",
  "scope": "scope_1",
  "co2e": 238.45,
  "evidence_reference": "meter-reading-august"
}
```
