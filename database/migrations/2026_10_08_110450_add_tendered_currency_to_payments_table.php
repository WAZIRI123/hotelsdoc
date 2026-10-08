<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A payment's amount stays in the hotel currency. When the customer paid in
 * another accepted currency, what they handed over and the rate used are
 * kept alongside it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->string('tendered_currency', 3)->nullable()->after('currency');
            $table->bigInteger('tendered_amount')->nullable()->after('tendered_currency')
                ->comment('Minor units, in tendered_currency');
            $table->decimal('exchange_rate', 18, 6)->nullable()->after('tendered_amount')
                ->comment('Hotel currency units for one unit of tendered_currency at payment time');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropColumn(['tendered_currency', 'tendered_amount', 'exchange_rate']);
        });
    }
};
