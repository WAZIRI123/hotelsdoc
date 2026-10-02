<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('extra_orders', function (Blueprint $table): void {
            $table->unsignedBigInteger('tax_amount')->default(0)->after('total_amount')
                ->comment('VAT contained in total_amount, minor units');
        });
    }

    public function down(): void
    {
        Schema::table('extra_orders', function (Blueprint $table): void {
            $table->dropColumn('tax_amount');
        });
    }
};
