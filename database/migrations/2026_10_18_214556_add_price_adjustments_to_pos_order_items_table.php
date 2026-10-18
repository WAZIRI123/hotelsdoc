<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets a manager change a line's price or take a discount off it at the till,
 * keeping the price it was rung up at, the reason and who did it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pos_order_items', function (Blueprint $table): void {
            $table->unsignedBigInteger('original_unit_price')->nullable()->after('unit_price')->comment('Minor units; set when the unit price was overridden');
            $table->unsignedBigInteger('discount_amount')->default(0)->after('original_unit_price')->comment('Minor units taken off the line');
            $table->string('adjustment_reason')->nullable()->after('discount_amount');
            $table->foreignId('adjusted_by')->nullable()->after('adjustment_reason')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('pos_order_items', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('adjusted_by');
            $table->dropColumn(['original_unit_price', 'discount_amount', 'adjustment_reason']);
        });
    }
};
