<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hotels', function (Blueprint $table): void {
            $table->string('vat_mode', 10)->default('none')->after('currency')
                ->comment('none|inclusive|exclusive — whether entered prices already contain VAT');
            $table->decimal('vat_rate', 5, 2)->default(0)->after('vat_mode')->comment('Percent, e.g. 18.00');
        });
    }

    public function down(): void
    {
        Schema::table('hotels', function (Blueprint $table): void {
            $table->dropColumn(['vat_mode', 'vat_rate']);
        });
    }
};
