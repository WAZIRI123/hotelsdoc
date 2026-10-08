<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * How many units of a POS line actually came off stock, so clearing or
 * voiding the order gives back only what it took. Existing lines are
 * backfilled as having taken their full quantity only when the item was
 * already stocked (its first stock entry or transfer in) when the line was
 * rung up; earlier lines were sold before tracking began and took nothing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pos_order_items', function (Blueprint $table): void {
            $table->unsignedInteger('stock_taken')->default(0);
        });

        $firstEntries = DB::table('stock_entries')
            ->selectRaw('menu_item_id, MIN(created_at) as stocked_at')
            ->groupBy('menu_item_id')
            ->pluck('stocked_at', 'menu_item_id');

        $firstTransfers = DB::table('stock_issues')
            ->whereNotNull('to_menu_item_id')
            ->selectRaw('to_menu_item_id, MIN(created_at) as stocked_at')
            ->groupBy('to_menu_item_id')
            ->pluck('stocked_at', 'to_menu_item_id');

        $trackedItemIds = DB::table('menu_items')->where('track_stock', true)->pluck('id');

        foreach ($trackedItemIds as $itemId) {
            $stockedAt = collect([$firstEntries[$itemId] ?? null, $firstTransfers[$itemId] ?? null])->filter()->min();

            if ($stockedAt === null) {
                continue;
            }

            DB::table('pos_order_items')
                ->where('menu_item_id', $itemId)
                ->where('created_at', '>=', $stockedAt)
                ->update(['stock_taken' => DB::raw('quantity')]);
        }
    }

    public function down(): void
    {
        Schema::table('pos_order_items', function (Blueprint $table): void {
            $table->dropColumn('stock_taken');
        });
    }
};
