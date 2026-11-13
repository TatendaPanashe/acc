<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use App\Services\Help\HelpCentre;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsLedger;
use Tests\TestCase;

class HelpCentreTest extends TestCase
{
    use BuildsLedger, RefreshDatabase;

    private Company $company;
    private User $accountant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->company = $this->createLedgerCompany();
        $this->accountant = $this->ledgerUser($this->company, 'accountant');
    }

    public function test_every_manual_section_belongs_to_a_help_category(): void
    {
        $guides = app(HelpCentre::class)->guides();

        $this->assertGreaterThan(20, $guides->count());
        $this->assertSame([], $guides->whereNull('category')->pluck('number')->values()->all(), 'Add new manual sections to config/help.php.');
        foreach (config('help.start_here') as $number) {
            $this->assertNotNull($guides->firstWhere('number', $number), "Start-here section $number is missing from the manual.");
        }
    }

    public function test_help_centre_lists_guides_and_downloads_for_signed_in_users(): void
    {
        $this->get(route('help.index'))->assertRedirect(route('login'));

        $this->actingAs($this->accountant)->get(route('dashboard'))->assertSee(route('help.index'));
        $this->get(route('help.index'))->assertOk()
            ->assertSee('Start here')->assertSee('Payroll')->assertSee('Complete user manual')->assertSee('Setup checklist')
            ->assertDontSee('Platform administration');

        $this->get(route('help.show', 'payroll'))->assertOk()
            ->assertSee('Running payroll')->assertSee('Open payroll')->assertSee('Download PDF')
            ->assertSee('id="', false);
    }

    public function test_guides_download_as_pdfs_and_templates_as_csv(): void
    {
        $this->actingAs($this->accountant);

        $this->get(route('help.pdf', 'company-setup'))->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->get(route('help.manual'))->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->get(route('help.checklist'))->assertOk()->assertHeader('content-type', 'application/pdf');
        $csv = $this->get(route('help.template', 'bank-statement'))->assertOk();
        $this->assertStringContainsString('Date,Description,Reference,Money in,Money out', file_get_contents($csv->baseResponse->getFile()->getPathname()));
        $this->get(route('help.template', 'nope'))->assertNotFound();
        $this->get(route('help.show', 'no-such-guide'))->assertNotFound();
    }

    public function test_platform_guides_are_only_for_platform_administrators(): void
    {
        $this->actingAs($this->accountant)->get(route('help.show', 'system-administration'))->assertNotFound();
        $this->get(route('help.pdf', 'system-administration'))->assertNotFound();

        $root = User::create(['name' => 'Root', 'username' => 'root', 'email' => 'root@example.com', 'password' => 'secret-password', 'role' => 'admin', 'is_superuser' => true]);
        $this->actingAs($root)->get(route('help.show', 'system-administration'))->assertOk()->assertSee('Open the platform dashboard');
    }

    public function test_help_is_available_before_company_setup_is_finished(): void
    {
        $newCompany = Company::create(['name' => 'New Co', 'status' => true]);
        $admin = User::create(['name' => 'New Admin', 'username' => 'newadmin', 'email' => 'new@example.com', 'password' => 'secret-password', 'company_id' => $newCompany->id, 'role' => 'admin']);

        $this->actingAs($admin)->get(route('dashboard'))->assertRedirect();
        $this->get(route('help.show', 'company-setup'))->assertOk()->assertSee('Step 1: Company profile');
    }
}
