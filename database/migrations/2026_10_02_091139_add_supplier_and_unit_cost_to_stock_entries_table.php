<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_entries', function (Blueprint $table): void {
            $table->foreignId('supplier_id')->nullable()->after('menu_item_id')->constrained()->nullOnDelete();
            $table->unsignedBigInteger('unit_cost')->nullable()->after('quantity')->comment('Minor units, hotel currency');
        });
    }

    public function down(): void
    {
        Schema::table('stock_entries', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('supplier_id');
            $table->dropColumn('unit_cost');
        });
    }
};
