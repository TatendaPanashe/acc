<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('platform_payment_gateways')->where('provider', 'pesepay')->exists()) {
            return;
        }

        DB::table('platform_payment_gateways')->insert([
            'provider' => 'pesepay',
            'display_name' => 'Pesepay (EcoCash, InnBucks, ZimSwitch, Visa/Mastercard)',
            'mode' => 'test',
            'status' => false,
            'sort_order' => 0,
            'configuration' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('platform_payment_gateways')->where('provider', 'pesepay')->delete();
    }
};
