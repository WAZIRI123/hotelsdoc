<?php

declare(strict_types=1);

use App\Models\Hotel;
use App\Models\PosOrderItem;
use App\Support\Vat;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * What a line's till discount and price cut took off, split by the VAT in
 * force when the order was rung up, so reports never re-derive it from a VAT
 * setting that may have changed since. Lines adjusted before this column
 * existed are filled in with their hotel's current VAT setting.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pos_order_items', function (Blueprint $table): void {
            $table->unsignedBigInteger('amount_off_net')->default(0)->after('discount_amount')->comment('Minor units, before VAT');
            $table->unsignedBigInteger('amount_off_gross')->default(0)->after('amount_off_net')->comment('Minor units, what came off the bill');
        });

        $vatByHotel = [];

        PosOrderItem::query()
            ->withoutGlobalScopes()
            ->where(fn ($query) => $query->where('discount_amount', '>', 0)->orWhereNotNull('original_unit_price'))
            ->each(function (PosOrderItem $line) use (&$vatByHotel): void {
                $vatByHotel[$line->hotel_id] ??= Vat::forHotel(Hotel::query()->findOrFail($line->hotel_id));
                $split = $vatByHotel[$line->hotel_id]->split($line->amountOff());

                DB::table('pos_order_items')->where('id', $line->id)->update([
                    'amount_off_net' => $split['net']->amount,
                    'amount_off_gross' => $split['gross']->amount,
                ]);
            });
    }

    public function down(): void
    {
        Schema::table('pos_order_items', function (Blueprint $table): void {
            $table->dropColumn(['amount_off_net', 'amount_off_gross']);
        });
    }
};
