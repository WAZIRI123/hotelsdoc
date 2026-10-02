<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The POS terminal used to open a new order on every visit, leaving empty
 * open orders behind. It now opens one only when the first item is added,
 * so the leftovers can go.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('pos_orders')
            ->where('status', 'open')
            ->whereNotExists(fn ($query) => $query->select(DB::raw(1))
                ->from('pos_order_items')
                ->whereColumn('pos_order_items.pos_order_id', 'pos_orders.id'))
            ->delete();
    }

    public function down(): void
    {
        //
    }
};
