<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Company;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        $company = Company::firstOrCreate(
            ['name' => 'Demo Enterprise'],
            ['status' => true]
        );

        User::updateOrCreate(
            ['email' => 'finance@example.com'],
            [
                'name' => 'Finance Admin',
                'username' => 'finance_admin',
                'company_id' => $company->id,
                'role' => 'admin',
                'password' => bcrypt('Test123!'),
            ]
        );

        $this->call([
            SuperuserSeeder::class,
            AccountingSeeder::class,
        ]);
    }
}
