<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Who cleared an unbilled POS order from the terminal, and when. Orders the
 * terminal used to "void" before they were billed carry no void reason;
 * they become cleared orders so real voids stand on their own.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pos_orders', function (Blueprint $table): void {
            $table->timestamp('cleared_at')->nullable();
            $table->foreignId('cleared_by')->nullable()->constrained('users')->nullOnDelete();
        });

        DB::table('pos_orders')
            ->where('status', 'voided')
            ->whereNull('void_reason')
            ->whereNull('billed_at')
            ->update([
                'status' => 'cleared',
                'cleared_at' => DB::raw('updated_at'),
            ]);
    }

    public function down(): void
    {
        DB::table('pos_orders')->where('status', 'cleared')->update(['status' => 'voided']);

        Schema::table('pos_orders', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('cleared_by');
            $table->dropColumn('cleared_at');
        });
    }
};
