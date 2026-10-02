<?php

declare(strict_types=1);

namespace App\Domain\Stock\Actions;

use App\Models\MenuItem;
use App\Models\StockEntry;
use App\Models\Supplier;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Logs stock received for a menu item and adds it to the item's stock on hand,
 * switching stock tracking on if it was off.
 */
final class RecordStockEntry
{
    public function execute(
        MenuItem $item,
        int $quantity,
        CarbonInterface $entryDate,
        ?CarbonInterface $expiryDate = null,
        ?string $description = null,
        ?User $recordedBy = null,
        ?Supplier $supplier = null,
        ?int $unitCost = null,
    ): StockEntry {
        return DB::transaction(function () use ($item, $quantity, $entryDate, $expiryDate, $description, $recordedBy, $supplier, $unitCost): StockEntry {
            $entry = StockEntry::query()->create([
                'hotel_id' => $item->hotel_id,
                'menu_item_id' => $item->id,
                'supplier_id' => $supplier?->id,
                'recorded_by' => $recordedBy?->id,
                'entry_date' => $entryDate->toDateString(),
                'expiry_date' => $expiryDate?->toDateString(),
                'description' => $description,
                'quantity' => $quantity,
                'unit_cost' => $unitCost,
            ]);

            if (! $item->track_stock) {
                $item->update(['track_stock' => true, 'stock_quantity' => 0]);
            }

            $item->increment('stock_quantity', $quantity);

            return $entry;
        });
    }

    /**
     * Removes a mistaken entry and takes its quantity back off the stock on hand.
     */
    public function reverse(StockEntry $entry): void
    {
        DB::transaction(function () use ($entry): void {
            $entry->menuItem()->first()?->decrement('stock_quantity', $entry->quantity);
            $entry->delete();
        });
    }
}
