<?php

use App\Models\Company;
use App\Services\CompanySetup\StandardChartOfAccounts;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_account_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->string('key', 60);
            $table->foreignId('account_id')->constrained('accounting_accounts')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'key']);
        });

        // Companies that already have a chart of accounts get the accounts the default mappings rely on.
        $mappingCodes = collect(config('accounting.account_mappings'))->flatMap(fn ($group) => array_column($group, 1))->unique()->values()->all();
        $companyIds = DB::table('accounting_accounts')->distinct()->pluck('company_id');

        foreach (Company::whereIn('id', $companyIds)->get() as $company) {
            app(StandardChartOfAccounts::class)->install($company, $mappingCodes);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_account_mappings');
    }
};
