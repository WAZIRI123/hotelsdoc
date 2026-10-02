<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A room credit (e.g. a guest leaving a shared room) is posted as a negative
 * charge, and its VAT is negative too, so tax_amount must be signed like total.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('folio_charges', function (Blueprint $table): void {
            $table->bigInteger('tax_amount')->default(0)->change();
        });
    }

    public function down(): void
    {
        Schema::table('folio_charges', function (Blueprint $table): void {
            $table->unsignedBigInteger('tax_amount')->default(0)->change();
        });
    }
};
