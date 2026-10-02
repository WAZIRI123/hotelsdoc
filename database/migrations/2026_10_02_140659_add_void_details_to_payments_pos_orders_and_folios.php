<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who voided a payment, POS order or folio, when and why.
 */
return new class extends Migration
{
    /** @var list<string> */
    private array $tables = ['payments', 'pos_orders', 'folios'];

    public function up(): void
    {
        foreach ($this->tables as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->timestamp('voided_at')->nullable();
                $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('void_reason', 255)->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->dropConstrainedForeignId('voided_by');
                $table->dropColumn(['voided_at', 'void_reason']);
            });
        }
    }
};
