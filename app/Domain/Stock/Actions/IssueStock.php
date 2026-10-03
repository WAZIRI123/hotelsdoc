<?php

declare(strict_types=1);

namespace App\Domain\Stock\Actions;

use App\Models\MenuItem;
use App\Models\StockIssue;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Takes stock off a stock-tracked product for anything other than a POS sale.
 * A transfer adds the same quantity to the receiving product, switching its
 * stock tracking on if it was off.
 */
final class IssueStock
{
    /**
     * @throws RuntimeException when the product is not stock-tracked or has too little stock
     */
    public function execute(
        MenuItem $item,
        int $quantity,
        string $reason,
        CarbonInterface $issueDate,
        ?MenuItem $transferTo = null,
        ?string $issuedTo = null,
        ?string $description = null,
        ?User $recordedBy = null,
    ): StockIssue {
        if (! array_key_exists($reason, StockIssue::REASONS)) {
            throw new RuntimeException("Unknown stock issue reason [{$reason}].");
        }

        if (($reason === StockIssue::REASON_TRANSFER) !== ($transferTo !== null)) {
            throw new RuntimeException('A transfer needs a receiving product, and only a transfer may have one.');
        }

        if ($transferTo?->is($item)) {
            throw new RuntimeException('Stock cannot be transferred to the same product.');
        }

        return DB::transaction(function () use ($item, $quantity, $reason, $issueDate, $transferTo, $issuedTo, $description, $recordedBy): StockIssue {
            $source = MenuItem::query()->lockForUpdate()->findOrFail($item->id);

            if (! $source->track_stock) {
                throw new RuntimeException("{$source->name} is not stock-tracked.");
            }

            if ($source->stock_quantity < $quantity) {
                throw new RuntimeException("Only {$source->stock_quantity} of {$source->name} in stock.");
            }

            $issue = StockIssue::query()->create([
                'hotel_id' => $source->hotel_id,
                'menu_item_id' => $source->id,
                'to_menu_item_id' => $transferTo?->id,
                'recorded_by' => $recordedBy?->id,
                'issue_date' => $issueDate->toDateString(),
                'reason' => $reason,
                'issued_to' => $issuedTo,
                'description' => $description,
                'quantity' => $quantity,
            ]);

            $source->decrement('stock_quantity', $quantity);

            if ($transferTo !== null) {
                if (! $transferTo->track_stock) {
                    $transferTo->update(['track_stock' => true, 'stock_quantity' => 0]);
                }

                $transferTo->increment('stock_quantity', $quantity);
            }

            return $issue;
        });
    }

    /**
     * Removes a mistaken issue: the quantity goes back to the product it came
     * from and, for a transfer, off the product that received it. A transfer
     * whose stock has since been sold or issued cannot be reversed.
     *
     * @throws RuntimeException when the receiving product no longer holds the quantity
     */
    public function reverse(StockIssue $issue): void
    {
        DB::transaction(function () use ($issue): void {
            $receiver = $issue->to_menu_item_id === null
                ? null
                : MenuItem::query()->lockForUpdate()->find($issue->to_menu_item_id);

            if ($receiver !== null && $receiver->stock_quantity < $issue->quantity) {
                throw new RuntimeException("Only {$receiver->stock_quantity} of {$receiver->name} is left, so this transfer cannot be reversed.");
            }

            $issue->menuItem()->first()?->increment('stock_quantity', $issue->quantity);
            $receiver?->decrement('stock_quantity', $issue->quantity);
            $issue->delete();
        });
    }
}
