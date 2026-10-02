<?php

declare(strict_types=1);

namespace App\Domain\Extras\Actions;

use App\Models\ExtraOrder;
use App\Models\ExtraService;
use App\Models\Stay;
use App\Models\User;
use App\Support\Money;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Records a sale of extra services.
 *
 * For an in-house guest the order total is posted as a single charge on the
 * stay's folio (mirroring BillPosOrderToFolio). Walk-in orders are kept as
 * standalone records.
 */
final class RecordExtraOrder
{
    /**
     * @param  list<array{extra_service_id: int, quantity: int, unit_price: int}>  $lines  unit_price in minor units
     */
    public function execute(
        ?Stay $stay,
        string $currency,
        CarbonInterface $orderDate,
        array $lines,
        ?User $createdBy = null,
    ): ExtraOrder {
        if ($lines === []) {
            throw new RuntimeException('Add at least one extra item.');
        }

        return DB::transaction(function () use ($stay, $currency, $orderDate, $lines, $createdBy): ExtraOrder {
            $folio = null;

            if ($stay !== null) {
                if ($stay->checked_out_at !== null) {
                    throw new RuntimeException('This guest has already checked out.');
                }

                $folio = $stay->folio;

                if ($folio === null || ! in_array($folio->status, ['open', 'closed'], true)) {
                    throw new RuntimeException('This guest has no open folio.');
                }

                if ($folio->currency !== $currency) {
                    throw new RuntimeException("The guest folio is in {$folio->currency}; record the extras in {$folio->currency}.");
                }
            }

            $services = ExtraService::query()
                ->whereIn('id', array_column($lines, 'extra_service_id'))
                ->get()
                ->keyBy('id');

            $order = ExtraOrder::query()->create([
                'stay_id' => $stay?->id,
                'folio_id' => $folio?->id,
                'created_by' => $createdBy?->id,
                'currency' => $currency,
                'order_date' => $orderDate->toDateString(),
                'total_amount' => 0,
            ]);

            $total = 0;

            foreach ($lines as $line) {
                $service = $services->get($line['extra_service_id'])
                    ?? throw new RuntimeException('Unknown extra item.');

                $lineTotal = $line['unit_price'] * $line['quantity'];
                $total += $lineTotal;

                $order->items()->create([
                    'extra_service_id' => $service->id,
                    'name_snapshot' => $service->name,
                    'quantity' => $line['quantity'],
                    'unit_price' => $line['unit_price'],
                    'line_total' => $lineTotal,
                ]);
            }

            $order->update(['total_amount' => $total]);

            if ($folio !== null) {
                $amount = new Money($total, $currency);

                $folio->charges()->create([
                    'source_type' => 'extra_order',
                    'source_id' => $order->id,
                    'description' => "Extras — order {$order->reference}",
                    'quantity' => 1,
                    'unit_price' => $amount,
                    'tax_amount' => Money::zero($currency),
                    'total' => $amount,
                    'posted_at' => now(),
                ]);

                $folio->refresh();
                $newSubtotal = $folio->subtotal->add($amount);

                $folio->update([
                    'subtotal' => $newSubtotal,
                    'total_amount' => $newSubtotal->add($folio->tax_total),
                ]);
            }

            return $order->load('items');
        });
    }
}
