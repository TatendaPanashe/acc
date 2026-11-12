<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use App\Modules\Accounting\Models\ApprovalRequest;
use App\Modules\Accounting\Models\ApprovalRule;
use App\Modules\Accounting\Models\AuditLog;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Accounting\Models\Supplier;
use App\Modules\Accounting\Models\SupplierInvoice;
use App\Modules\Accounting\Services\AuditTrailService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use LogicException;
use Tests\Concerns\BuildsLedger;
use Tests\TestCase;

class ApprovalsAuditTest extends TestCase
{
    use BuildsLedger, RefreshDatabase;

    private Company $company;
    private User $admin;
    private User $manager;
    private User $accountant;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-06-10 10:00:00');
        $this->company = $this->createLedgerCompany();
        $this->admin = $this->ledgerUser($this->company, 'admin');
        $this->manager = $this->ledgerUser($this->company, 'finance_manager');
        $this->accountant = $this->ledgerUser($this->company, 'accountant');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function journal(User $by, float $amount = 250): JournalEntry
    {
        $this->actingAs($by)->post(route('accounting.gl.journals.store'), [
            'entry_number' => 'JV-'.uniqid(), 'entry_date' => '2026-06-10', 'currency' => 'USD', 'memo' => 'Accrual',
            'lines' => [
                ['account_id' => $this->ledgerAccount($this->company, '6000')->id, 'debit' => $amount],
                ['account_id' => $this->ledgerAccount($this->company, '2300')->id, 'credit' => $amount],
            ],
        ])->assertSessionHasNoErrors();

        return JournalEntry::latest('id')->first();
    }

    private function supplierInvoice(User $by, float $total): SupplierInvoice
    {
        $this->actingAs($by);
        $supplier = Supplier::firstOrCreate(['supplier_number' => 'S1'], ['name' => 'Harare Paper', 'status' => true]);

        $invoice = SupplierInvoice::create([
            'supplier_id' => $supplier->id, 'invoice_number' => 'SI-'.uniqid(), 'invoice_date' => '2026-06-10', 'due_date' => '2026-07-10',
            'currency' => 'USD', 'subtotal' => $total, 'tax_total' => 0, 'total' => $total, 'status' => 'draft',
        ]);
        $invoice->lines()->create(['company_id' => $this->company->id, 'expense_account_id' => $this->ledgerAccount($this->company, '6000')->id, 'description' => 'Paper', 'quantity' => 1, 'unit_cost' => $total, 'tax_rate' => 0, 'line_total' => $total]);

        return $invoice;
    }

    public function test_changes_are_audited_automatically_with_a_tamper_evident_chain(): void
    {
        $invoice = $this->supplierInvoice($this->accountant, 100);
        $invoice->update(['total' => 120]);

        $created = AuditLog::where('event', 'supplier_invoice.created')->first();
        $this->assertSame($this->accountant->id, $created->user_id);
        $this->assertEquals(['total' => 120], AuditLog::where('event', 'supplier_invoice.updated')->first()->new_values);
        $this->assertEquals(['total' => 100], AuditLog::where('event', 'supplier_invoice.updated')->first()->old_values);

        $audit = app(AuditTrailService::class);
        $this->assertTrue($audit->verify($this->company->id)['valid']);

        // The application refuses to change entries...
        $this->expectExceptionObject(new LogicException('Audit log entries cannot be changed.'));
        try {
            $created->update(['event' => 'x']);
        } finally {
            // ...and a direct database edit breaks the chain.
            DB::table('accounting_audit_logs')->where('id', $created->id)->update(['new_values' => json_encode(['total' => '1.00'])]);
            $result = $audit->verify($this->company->id);
            $this->assertFalse($result['valid']);
            $this->assertSame($created->id, $result['broken_at']);
        }
    }

    public function test_sign_ins_and_team_changes_are_audited(): void
    {
        $this->post('/login', ['login' => $this->accountant->username, 'password' => 'secret-password']);
        $this->assertTrue(AuditLog::where('company_id', $this->company->id)->where('event', 'auth.login')->exists());

        $this->accountant->update(['role' => 'auditor']);
        $log = AuditLog::where('event', 'user.updated')->latest('id')->first();
        $this->assertSame(['role' => 'accountant'], $log->old_values);
    }

    public function test_preparer_cannot_approve_their_own_document_when_the_rule_says_so(): void
    {
        ApprovalRule::withoutGlobalScopes()->create(['company_id' => $this->company->id, 'document_type' => 'all', 'min_amount' => 0, 'approver_role' => 'finance_manager', 'require_different_user' => true, 'status' => true]);

        $journal = $this->journal($this->manager);
        $this->actingAs($this->manager)->post(route('accounting.gl.journals.post', $journal))->assertSessionHas('error');
        $this->assertSame('draft', $journal->fresh()->status);

        $this->actingAs($this->admin)->post(route('accounting.gl.journals.post', $journal))->assertSessionHasNoErrors();
        $this->assertSame('posted', $journal->fresh()->status);

        $decision = ApprovalRequest::where('approvable_id', $journal->id)->first();
        $this->assertSame(['approved', $this->manager->id, $this->admin->id, 250.0], [$decision->status, $decision->requested_by, $decision->resolved_by, (float) $decision->amount]);
    }

    public function test_amount_threshold_needs_a_company_admin(): void
    {
        ApprovalRule::withoutGlobalScopes()->create(['company_id' => $this->company->id, 'document_type' => 'supplier_invoice', 'min_amount' => 1000, 'approver_role' => 'admin', 'require_different_user' => false, 'status' => true]);

        $large = $this->supplierInvoice($this->accountant, 1500);
        $small = $this->supplierInvoice($this->accountant, 500);

        $this->actingAs($this->manager)->post(route('accounting.ap.supplier-invoices.post', $large))->assertSessionHas('error', fn ($m) => str_contains($m, 'needs Company Admin approval'));
        $this->assertSame('draft', $large->fresh()->status);

        $this->post(route('accounting.ap.supplier-invoices.post', $small));
        $this->assertNotSame('draft', $small->fresh()->status);

        $this->actingAs($this->admin)->post(route('accounting.ap.supplier-invoices.post', $large));
        $this->assertNotSame('draft', $large->fresh()->status);
    }

    public function test_inbox_lists_drafts_and_returned_documents_go_back_to_the_preparer(): void
    {
        $journal = $this->journal($this->accountant);
        $otherDraft = $this->journal($this->manager, 75);

        $this->actingAs($this->manager)->get(route('accounting.approvals-audit.index'))->assertOk()->assertSee($journal->entry_number)->assertSee($otherDraft->entry_number);

        $this->post(route('accounting.approvals-audit.reject', ['journal', $journal->id]), ['note' => 'Attach the supporting schedule'])->assertSessionHasNoErrors();
        $this->assertSame('rejected', ApprovalRequest::where('approvable_id', $journal->id)->value('status'));

        // The accountant sees only their own document, marked as returned with the reason.
        $this->actingAs($this->accountant)->get(route('accounting.approvals-audit.index'))
            ->assertOk()->assertSee($journal->entry_number)->assertDontSee($otherDraft->entry_number)->assertSee('Attach the supporting schedule');

        // Accountants can neither return documents nor open the audit trail; auditors can read it.
        $this->post(route('accounting.approvals-audit.reject', ['journal', $otherDraft->id]), ['note' => 'x'])->assertForbidden();
        $this->get(route('accounting.approvals-audit.audit.index'))->assertForbidden();
        // The audit trail screen is part of the Business plan and above.
        app(\App\Services\Platform\SubscriptionService::class)->changePlan($this->company->fresh()->subscription, \App\Models\Platform\Plan::where('code', 'business')->first(), 'monthly');
        $auditor = $this->ledgerUser($this->company, 'auditor');
        $this->actingAs($auditor)->get(route('accounting.approvals-audit.audit.index', ['verify' => 1]))->assertOk()->assertSee('Audit trail intact');
        $this->get(route('accounting.approvals-audit.audit.export'))->assertOk();
    }

    public function test_only_company_admins_manage_rules(): void
    {
        $payload = ['document_type' => 'journal', 'min_amount' => 5000, 'approver_role' => 'admin', 'require_different_user' => 1, 'status' => 1];

        $this->actingAs($this->manager)->post(route('accounting.approvals-audit.rules.store'), $payload)->assertForbidden();
        $this->actingAs($this->admin)->post(route('accounting.approvals-audit.rules.store'), $payload)->assertSessionHasNoErrors();
        $this->get(route('accounting.approvals-audit.rules.index'))->assertOk()->assertSee('Company Admin only');
        $this->assertSame(1, ApprovalRule::count());
    }
}
