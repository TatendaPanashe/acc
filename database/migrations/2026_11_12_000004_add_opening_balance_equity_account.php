<?php

use App\Models\Company;
use App\Services\CompanySetup\StandardChartOfAccounts;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** Companies with a chart of accounts get 3900 Opening Balance Equity, used when opening stock is entered. */
    public function up(): void
    {
        $companyIds = DB::table('accounting_accounts')->distinct()->pluck('company_id');

        foreach (Company::whereIn('id', $companyIds)->get() as $company) {
            app(StandardChartOfAccounts::class)->install($company, ['3900']);
        }
    }

    public function down(): void
    {
        // Accounts may already carry postings; leave them in place.
    }
};
