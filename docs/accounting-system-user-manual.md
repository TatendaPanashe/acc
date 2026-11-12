# acc263 Accounting System User Manual

## 1. Overview

acc263 is a multi-company accounting system. Each company works inside its own private workspace. Users register, create or join a company workspace, complete company setup, then use the accounting modules assigned to that workspace.

The system currently includes:

- Company onboarding and settings
- General ledger
- Accounts receivable
- Accounts payable
- Inventory and order entry
- Point of sale
- Reporting and statutory reports
- Client portal
- System administration for companies and users

## 2. Getting Started

### 2.1 Welcome Page

Open the application homepage.

Guests will see two options:

- **Create account**: Register a new company workspace.
- **Sign in**: Log in to an existing workspace.

If you are already logged in, the homepage redirects to the accounting dashboard.

### 2.2 Create an Account

1. Select **Create account**.
2. Enter your full name.
3. Enter your email address.
4. Enter and confirm your password.
5. Submit the form.

After registration, the system creates a new private company workspace and sends you to company setup.

### 2.3 Sign In

1. Select **Sign in**.
2. Enter your email or username.
3. Enter your password.
4. Submit the form.

If your company setup is incomplete, the system redirects you to the setup page before allowing access to the dashboard.

## 3. Company Setup

The person who signs up becomes the **Company Admin**. Before anyone can use the accounting modules, the Company Admin completes a four-step setup wizard. Other users who sign in before it is finished see a "Workspace not ready" page.

### 3.1 Step 1: Company profile

Required: company name, VAT number and at least one bank account. Optional: registration number, email, phone, address.

To add bank accounts:

1. Enter the bank name, account name and account number.
2. Add branch code or SWIFT code if needed.
3. Enter the currency code, for example `USD`.
4. Mark one account as the primary bank account.
5. Use **Add** to create more bank account rows.

### 3.2 Step 2: Currencies

Choose the **base currency** (the currency the books are kept in) and tick any other currencies you trade or bank in.

### 3.3 Step 3: Tax & statutory

Enter the ZIMRA TIN and, optionally, the NSSA employer number. Confirm the standard VAT rate. The tax codes `VAT-STD`, `VAT-ZERO` and (optionally) `VAT-EXEMPT` are created.

### 3.4 Step 4: Fiscal year & chart of accounts

Choose the first month and year of the financial year. Twelve monthly accounting periods and a standard chart of accounts are created. Existing accounts are never overwritten.

When the last step is saved, the workspace opens and the Company Admin is taken to the **Team** page.

### 3.5 Creating user accounts (Team)

Under **Company → Team**, the Company Admin adds users and gives each one a role. Users belong to the admin's company only. The company must always keep at least one Company Admin, and admins cannot change their own role.

### 3.6 Roles

| Role | What they can do |
|---|---|
| **Company Admin** | Everything, including company profile, currencies, tax codes, chart of accounts, fiscal years, periods and the team. |
| **Finance Manager** | Approves and posts journals, supplier invoices, supplier payments, payment runs and credit notes; approves purchase orders, sales orders and stores requisitions; closes periods; manages budgets; sees financial reports and settings (read-only). |
| **Accountant** | Day-to-day work only: customers, suppliers, items, drafts of all documents, POS, stock receipts and issues. **Can post sales invoices and customer receipts.** Everything else they capture waits for a Finance Manager to approve. No access to financial reports, settings or the chart of accounts. |
| **Auditor** | Read-only access to all records, settings and reports. |

Documents waiting for approval show an **Awaiting approval** badge, and approvers see a **Waiting for your approval** list on the dashboard.

### 3.7 Subscription, trial and billing

Every new workspace starts a **14-day free trial** on the plan chosen at sign-up (Professional if none is chosen). The trial includes that plan's features and limits.

| Plan | Price | Users | Branches | Companies | Adds |
|---|---|---|---|---|---|
| Essential | $19/month | 2 | 1 | 1 | General ledger, invoicing, payables & receivables, basic reports, inventory |
| Professional | $39/month | 5 | 3 | 1 | Payroll, bank reconciliation, multi-currency, tax management, advanced reports |
| Business | $79/month | 15 | 10 | 3 | Advanced permissions (Finance Manager & Auditor roles), consolidated reporting, audit trail, priority support |
| Enterprise | from $199/month | custom | custom | custom | API access, custom integrations, advanced access controls, dedicated onboarding and support (contact us) |

Annual billing costs 10 × the monthly price (two months free).

- In the last 5 days of the trial a banner reminds everyone it is ending.
- **When the trial ends the workspace locks.** The Company Admin is taken to **Company → Billing & Plan** to choose a plan; other users see a "subscription inactive" page. No data is deleted.
- To subscribe, choose a plan (monthly or annual, optional coupon) and pay the invoice with one of the payment methods shown (Paynow / EcoCash, card, or bank transfer). The subscription activates as soon as the payment provider confirms the payment; a failed payment leaves the invoice open to try again.
- A renewal invoice is issued 7 days before each renewal date and emailed to the Company Admins. If it is unpaid on the renewal date the account is **past due** and has a **7-day grace period** (a red banner is shown). After that the workspace is **suspended** (locked to Billing) until the invoice is paid.
- Screens that belong to a higher plan (for example Bank Reconciliation on Essential) show an upgrade message. Adding users or branches beyond the plan limit is refused with an upgrade message.

## 4. Dashboard

The dashboard is the main entry point after login. It shows key metrics and links to accounting modules.

Main module areas:

- General Ledger
- Accounts Receivable
- Accounts Payable
- Inventory / Order Entry
- Banking
- Fixed Assets
- Reporting / Analytics
- CRM
- BOM / Job Costing
- Point of Sale
- Annuity Billing
- Approvals / Audit
- AI Automation
- Client Portal
- Revenue Recognition
- E-Invoicing / Tax Mesh
- ESG Carbon

## 5. General Ledger

Use the general ledger to manage fiscal periods, chart of accounts, journals, budgets, and financial statements.

### 5.1 Fiscal Years

Use fiscal years to define the company reporting year.

Typical actions:

- View fiscal years
- Create a fiscal year
- Edit a fiscal year
- Deactivate or delete a fiscal year

### 5.2 Accounting Periods

Accounting periods divide a fiscal year into months. Every posting is assigned to the period that covers its date; you never choose the period yourself.

- Only **open** periods in an **open** fiscal year accept postings. Invoices, receipts, journals, stock movements and depreciation dated in a locked or closed period are refused with a message naming the period.
- Periods must sit inside their fiscal year and cannot overlap.
- A period cannot be closed while it still has unposted journal entries.
- The dates of a period that already has postings cannot be changed.
- Finance Managers close periods; only the Company Admin can reopen one. Every status change is recorded in the audit log.

### 5.3 Chart of Accounts

The chart of accounts contains accounts used for journals, invoices, payments, VAT, inventory, and reports.

Typical account fields:

- Code
- Name
- Type
- Normal balance
- Parent account
- Status

### 5.4 Journals

Journals record debit and credit entries.

Typical workflow:

1. Create a journal.
2. Add journal lines.
3. Confirm total debits equal total credits.
4. Save the journal as draft.
5. Post the journal when ready.

Posted journals affect ledger balances and reports.

### 5.5 Budgets

Budgets store planned amounts for accounts and periods.

Use budgets to compare planned amounts with actual ledger results.

### 5.6 Financial Reports

All reports are in the company's **base currency** and use posted entries only.

- **Trial balance**: cumulative balances as at the end of the selected period (or today).
- **Profit and loss**: the selected period, or the current fiscal year to date when no period is chosen.
- **Balance sheet**: the position as at the end of the selected period (or today). Revenue and expense balances are shown inside equity as *retained earnings from prior years* and *current year profit*, so the statement always balances. A badge confirms it is balanced. Contra accounts such as accumulated depreciation reduce total assets.
- **Cash flow**: opening cash, cash in and out grouped by source (receipts, supplier payments, point of sale, bank adjustments, manual journals), and closing cash. Cash accounts are those set for payment methods in Account Mapping, plus GL accounts linked to bank accounts.
- **Statutory XLS reports**: the same figures as the screens, in Excel format.

### 5.7 Journals and posting rules

- Each journal line is either a debit or a credit, and must use an active account that allows posting.
- A journal can be entered in any active currency; it is converted to base currency at posting using the exchange rate for its date.
- Posted journals cannot be edited or deleted. Correct them with a reversing journal.

## 6. Accounts Receivable

Accounts receivable manages customers, sales invoices, credit notes, receipts, and customer aging.

### 6.1 Customers

Use customers to store client details for invoicing and the client portal.

Typical actions:

- View customers
- Add a customer
- Edit customer details
- Deactivate a customer

### 6.2 Sales Invoices

Use sales invoices to bill customers.

Typical workflow:

1. Open **Accounts Receivable > Invoices**.
2. Create an invoice.
3. Select the customer.
4. Add invoice lines.
5. Set quantity, unit price, revenue account, and tax rate.
6. Save the invoice.
7. Download or print the invoice PDF if required.
8. Post the invoice when final.

Posted invoices create accounting entries.

### 6.3 Credit Notes

Use credit notes to reduce or reverse customer invoice balances.

Typical workflow:

1. Create a credit note.
2. Select the customer and invoice.
3. Enter subtotal, tax rate, and reason.
4. Save and post the credit note.

### 6.4 Receipts

Use receipts to record customer payments.

Typical workflow:

1. Select the customer.
2. Select the invoice if applicable.
3. Enter receipt number, date, amount, and payment method.
4. Save the receipt.
5. Post the receipt.

### 6.5 Receivables Aging

Receivables aging shows overdue customer balances grouped by age.

Use it to monitor:

- Current balances
- Overdue invoices
- High-priority collections

## 7. Accounts Payable

Accounts payable manages suppliers, supplier invoices, supplier payments, payment runs, and payable aging.

### 7.1 Suppliers

Use suppliers to store vendor details.

Typical actions:

- View suppliers
- Add a supplier
- Edit supplier details
- Deactivate a supplier

### 7.2 Supplier Invoices

Use supplier invoices to record bills from suppliers.

Typical workflow:

1. Open **Accounts Payable > Supplier Invoices**.
2. Create an invoice.
3. Select the supplier.
4. Add expense lines.
5. Enter quantity, unit cost, expense account, and tax rate.
6. Save the invoice.
7. Download or print the invoice PDF if required.
8. Post the invoice when final.

Posted supplier invoices create accounting entries.

### 7.3 Supplier Payments

Use payments to record money paid to suppliers.

Typical workflow:

1. Select the supplier.
2. Select the supplier invoice if applicable.
3. Enter payment number, date, amount, and payment method.
4. Save and post the payment.

### 7.4 Payment Runs

Payment runs group supplier payments for processing.

Typical workflow:

1. Create a payment run.
2. Select invoices or suppliers to pay.
3. Review the total payment amount.
4. Post the payment run.

### 7.5 Payables Aging

Payables aging shows overdue supplier balances grouped by age.

Use it to plan cash flow and supplier payments.

## 8. Inventory and Order Entry

Inventory manages items, warehouses, sales orders, purchase orders, transfer orders, and stores requisitions.

### 8.1 Inventory Overview

Shows stock on hand by item and warehouse, the weighted-average cost and stock value. A check at the top compares the stock value with the Inventory account in the general ledger and shows any difference.

#### How stock reaches the ledger

| Action | Ledger entry (base currency) |
|---|---|
| Receive a purchase order | Dr Inventory, Cr Goods received not invoiced (GRNI) |
| Fulfil a sales order | Dr Cost of goods sold, Cr Inventory |
| Issue a stores requisition | Dr Stores consumption, Cr Inventory |
| POS sale | Revenue and VAT, plus Dr Cost of goods sold, Cr Inventory |
| Transfer between warehouses | No ledger entry; stock moves at its existing cost |

Stock leaves at **weighted-average cost**, or at standard cost for items set to "standard". When the supplier's invoice arrives for goods already received, code its lines to the **Goods Received Not Invoiced** account (2050) so the GRNI balance clears.

### 8.2 Items

Items are products or inventory records used in orders and POS.

Typical item fields:

- SKU
- Name
- Type (stock, service or non-stock)
- Costing method
- Standard cost and selling price
- Reorder level
- Status

The items list shows the **quantity on hand** across all stores, highlighted in red at or below the reorder level.

#### Quantity on hand when adding a product

When adding a stock item, Finance Managers and Company Admins can enter the quantity currently held in each store, a unit cost (the standard cost is used if left blank) and the "as at" date. Saving puts the stock into those stores and posts **Dr Inventory, Cr Opening Balance Equity (3900)** for the value, so the stock ledger and general ledger agree. The date must fall in an open period.

- Existing stock items that have never had stock can get opening quantities from their edit page.
- Once an item has stock movements, bring in more through a purchase order.
- Accountants can add products, but quantities must be entered by a Finance Manager or Company Admin, because they post value to the ledger.

### 8.3 Warehouses

Warehouses represent stock locations.

Typical actions:

- View warehouses
- Add a warehouse
- Edit warehouse details
- Deactivate a warehouse

### 8.4 Sales Orders

Sales orders reserve or request stock for customers.

Typical workflow:

1. Create a sales order.
2. Select customer, order date, and required date.
3. Add items and warehouses.
4. Confirm the order.
5. Fulfill the order when stock is issued.

### 8.5 Purchase Orders

Purchase orders request goods from suppliers.

Typical workflow:

1. Create a purchase order.
2. Select supplier, order date, and expected date.
3. Add item lines.
4. Approve the order.
5. Receive goods when delivered.

### 8.6 Transfer Orders

Transfer orders move stock between warehouses.

Typical workflow:

1. Create a transfer order.
2. Select source and destination warehouses.
3. Add item lines.
4. Ship the transfer.
5. Receive the transfer.

### 8.7 Stores Requisitions

Stores requisitions request stock for internal use.

Typical workflow:

1. Create a requisition.
2. Add requested items.
3. Approve the requisition.
4. Issue the items.

## 9. Point of Sale

Point of sale records direct sales.

Typical workflow:

1. Open the POS terminal.
2. Select items.
3. Enter quantities, prices, discounts, and tax rates.
4. Review subtotal, tax, discount, and total.
5. Process the sale.
6. Print or view the receipt.

Posted POS sales can create accounting and inventory movements.

## 10. Accounting Settings

### 10.1 Currencies

Add every currency you invoice, buy or bank in. One currency is the **base currency** (the currency the books are kept in). It cannot be changed once anything has been posted.

### 10.2 Exchange Rates

Record rates as "1 unit of foreign currency = x base currency" with the date they apply from. Documents use the latest rate on or before their date. Finance Managers and Company Admins can maintain rates. Posting is refused if no rate exists for a document's currency and date.

When a foreign-currency invoice is paid at a different rate, the receivable or payable is cleared at the invoice's original rate and the difference posts automatically to **Exchange gains** or **Exchange losses**.

### 10.3 Tax Codes

Tax codes hold the VAT rates used on documents and the control account they post to.

### 10.4 Account Mapping

Account Mapping decides which GL account each automatic posting uses: where cash, card, mobile money and bank receipts go; the receivable, payable, VAT, inventory, cost of sales, GRNI and depreciation accounts; and exchange gain and loss accounts. Leave a row on "Default" to use the standard account code. Payment methods containing "EcoCash", "OneMoney", "InnBucks" or "mobile" use the mobile money account; "cash" uses cash on hand; "card", "swipe" or "visa" use the card account; everything else uses the bank account.

## 10A. Bank Reconciliation

Open **Bank Reconciliation** under Enterprise Modules.

1. **Link each bank account** to its GL account (Company Admin). Setup creates one GL account per bank (1010, 1011, …) and links them automatically.
2. **Import a statement** as CSV with the statement date, opening and closing balance. The file needs a header row with `Date`, `Description`, optional `Reference`, and either `Amount` (positive in, negative out) or `Credit` and `Debit` columns. Dates may be `2026-01-31` or `31/01/2026`.
3. Lines are **auto-matched** to posted GL entries on that bank account with the same amount within 7 days. Where there are several possibilities, choose the right one from the list.
4. For bank-only items (charges, interest, direct debits), choose the account and click **Post** (Finance Manager or Company Admin). A journal is posted and matched.
5. **Complete reconciliation** (Finance Manager or Company Admin) once every statement line is matched, the statement adds up, and the difference is zero. Entries in the books but not yet on the statement are listed separately and carry to the next statement.

## 10B. Depreciation Runs

Open **Depreciation Runs** under Enterprise Modules. Choose an open period to preview the charge for each active asset, then post it (Finance Manager or Company Admin).

- Straight line: (cost − salvage value) ÷ useful life in months.
- Declining balance: net book value × 2 ÷ useful life in months.
- Depreciation starts in the month the asset was acquired and never takes net book value below salvage value.
- A run covers every month not yet depreciated, so a missed month is caught up next time. Each period can be run once.
- The run posts Dr Depreciation expense, Cr Accumulated depreciation, and updates each asset's accumulated depreciation and net book value.

## 11. Client Portal

The client portal allows customers to access selected invoice and payment information.

Client portal features include:

- Client login
- Password change
- Client dashboard
- Invoice PDF download
- Customer payment submission
- Token-based portal access

### 11.1 Giving a customer portal access

Customers cannot log in to the portal until finance staff issue them a password:

1. Open **Accounts Receivable → Customers** and edit the customer. The customer must have an email address.
2. Under **Client Portal Access**, click **Issue portal password**.
3. Copy the temporary password shown. It is displayed only once.
4. Send it to the customer through a trusted channel. On first login they must choose a new password.

Use **Reset portal password** if a customer forgets their password, and **Revoke access** to remove portal access immediately.

## 12. System Administration

System administration is for the acc263 platform team (superusers). Superusers land on the **Platform Overview** after signing in.

### 12.0 Platform Overview (super-admin dashboard)

Open **System → Platform** (`/system/platform`). Choose a month in the top-right selector; comparisons are against the previous month.

| Module | What it shows |
|---|---|
| Monthly recurring revenue (MRR) | Paying subscriptions normalised to a month (annual plans ÷ 12). Trials and promotional subscriptions add nothing. |
| Annual recurring revenue (ARR) | MRR × 12 |
| MRR growth | Month-end MRR for the last 12 months (hover for values, or "View as table") |
| New / Expansion / Churned / Net new MRR | MRR movements in the month: new customers, upgrades, cancellations, suspensions and downgrades |
| Registered businesses | All tenant companies, and how many registered in the month |
| Active subscriptions | Right now, split into paying, promotional and past due; the side list shows the highest-value subscriptions |
| Trial accounts | Trials still running, and expired trials that did not convert |
| Suspended tenants | Right now |
| Failed payments | Subscription invoices that failed in the month and the amount not collected |
| Subscription cancellations | Cancellations in the month and the MRR lost |
| Revenue by country / by payment provider | Subscription invoices paid in the month |
| New registrations & conversion | Sign-ups per month; conversion = businesses that became paying ÷ businesses whose trial has finished (those still on trial are not counted yet) |

Every new business starts on a 14-day trial (see 3.7). Platform billing is configured under **Plans**, **Coupons** and **Payment gateways** in the platform sidebar; see `docs/subscription-billing.md`.

### 12.0.1 Managing a tenant

Open **Tenants & billing**, filter by status, plan or name, and open a business to see its subscription, **billing history** and a timeline of every change (who made it and its effect on MRR). Actions:

- **Change plan**: switch plan and monthly/annual billing immediately (recorded as expansion or contraction).
- **Grant promotional subscription**: free access to a plan for 1–36 months with a reason; adds no MRR.
- **Extend trial**: add days to a running trial; an ended trial is extended from today and the workspace unlocks.
- **Record payment**: on an open invoice in the billing history, confirm a bank transfer (or other offline payment) with its reference; the subscription activates or renews.
- **Custom price and limits** (under Change plan): for Enterprise deals, set the price per billing period and user/branch/company limits.
- **Suspend account**: blocks sign-in for all of the business's users immediately (including anyone already signed in) and removes its MRR. **Reactivate** restores the previous status. (Automatic suspension for non-payment is different: users can still sign in, but only reach Billing.)
- **Cancel subscription**: records a cancellation with a reason; users can still sign in.

To see the dashboard with sample data on a development copy, run `php artisan db:seed --class=PlatformDemoSeeder`. It adds about 120 sample businesses (example.com addresses, no users) with a year of history. Do not run it on a live database.

System administration is available to superusers.

### 12.1 Company Management

Superusers can:

- View companies
- Create companies
- Edit company details
- Update VAT number
- Upload company logo
- Activate or deactivate companies

### 12.2 User Management

Superusers can:

- View users
- Create users
- Edit users
- Assign users to companies
- Set roles
- Manage superuser access

## 13. Multi-Tenancy Rules

Each normal user belongs to one company workspace.

Tenant-scoped records are automatically filtered by company. This means a normal user only sees records for their own company.

Superusers can manage system-level records and companies.

## 14. Common Workflows

### 14.1 New Company Setup

1. User opens the homepage.
2. User selects **Create account**.
3. User registers.
4. System creates a company workspace.
5. User completes company setup.
6. User enters the dashboard.
7. User configures currencies, tax codes, accounts, customers, and suppliers.

### 14.2 Customer Invoice to Receipt

1. Create or confirm the customer.
2. Create a sales invoice.
3. Add invoice lines.
4. Save and post the invoice.
5. Send or download the invoice PDF.
6. Record receipt when payment is received.
7. Post the receipt.
8. Review receivables aging.

### 14.3 Supplier Invoice to Payment

1. Create or confirm the supplier.
2. Create a supplier invoice.
3. Add expense lines.
4. Save and post the invoice.
5. Record payment or include invoice in a payment run.
6. Post payment.
7. Review payables aging.

### 14.4 Inventory Purchase to Sale

1. Create items and warehouses.
2. Create a purchase order.
3. Approve and receive the purchase order.
4. Create a sales order or POS sale.
5. Fulfill the sales order or complete the POS sale.
6. Review inventory and accounting reports.

## 15. Troubleshooting

### 15.1 I am redirected to company setup

Your company setup is incomplete. Enter the company name, VAT number, and at least one bank account.

### 15.2 I cannot see another company’s data

This is expected. Normal users only see records for their assigned company workspace.

### 15.3 A company account is inactive

Contact a system administrator or superuser to reactivate the company.

### 15.4 A report looks incomplete

Confirm that transactions have been posted. Draft transactions may not appear in final financial reports.

## 16. Recommended First-Time Configuration

After company setup, configure the system in this order:

1. Currencies
2. Tax codes
3. Fiscal year
4. Accounting periods
5. Chart of accounts
6. Customers
7. Suppliers
8. Items and warehouses
9. Opening journals or balances

## 17. Glossary

- **Workspace**: A private company environment inside the system.
- **Tenant**: A company whose records are isolated from other companies.
- **Posting**: Finalizing a transaction so it affects accounting records.
- **AR**: Accounts receivable, money owed by customers.
- **AP**: Accounts payable, money owed to suppliers.
- **GL**: General ledger.
- **VAT**: Value-added tax.
