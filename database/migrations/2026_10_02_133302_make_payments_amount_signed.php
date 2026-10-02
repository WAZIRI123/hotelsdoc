<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A refund of a guest's credit balance is recorded as a negative payment, so
 * it nets off the cash-up and folio totals like any other payment.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->bigInteger('amount')->comment('Minor units; negative for a refund')->change();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->unsignedBigInteger('amount')->comment('Minor units (cents TZS)')->change();
        });
    }
};
