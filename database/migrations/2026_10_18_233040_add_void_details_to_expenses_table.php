<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who voided an expense, when and why. Voided expenses stay on record but
 * leave the report totals.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table): void {
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('void_reason', 255)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('voided_by');
            $table->dropColumn(['voided_at', 'void_reason']);
        });
    }
};
