<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Company;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SuperuserSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::firstOrCreate(
            ['name' => 'Demo Enterprise'],
            ['status' => true]
        );

        User::updateOrCreate(
            ['email' => 'superuser@superuser.com'],
            [
                'name' => 'System Superuser',
                'username' => 'superuser',
                'company_id' => $company->id,
                'role' => 'superuser',
                'is_superuser' => true,
                'password' => Hash::make('Test123!'),
            ]
        );
    }
}
