<?php

declare(strict_types=1);

namespace App\Domain\Extras\Actions;

use App\Domain\Folios\Actions\PostFolioCharge;
use App\Models\ExtraOrder;
use App\Models\ExtraService;
use App\Models\Folio;
use App\Models\Stay;
use App\Models\User;
use App\Support\Money;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Records a sale of extra services.
 *
 * The order total is posted as a single charge on a folio, with VAT applied
 * per the hotel's setting (mirroring
 * BillPosOrderToFolio): the stay's folio for an in-house guest, or a fresh
 * walk-in folio for a walk-in customer, which the caller then settles with
 * RecordPayment so the sale reaches the cash-up.
 */
final class RecordExtraOrder
{
    public function __construct(private readonly PostFolioCharge $postCharge) {}

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
            if ($stay === null) {
                $folio = Folio::query()->create(['status' => 'open']);

                if ($folio->currency !== $currency) {
                    throw new RuntimeException("Walk-in extras are paid in {$folio->currency}; record the extras in {$folio->currency}.");
                }
            } else {
                if ($stay->checked_out_at !== null) {
                    throw new RuntimeException('This guest has already checked out.');
                }

                $folio = $stay->folio;

                if ($folio === null || ! $folio->acceptsCharges()) {
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
                'folio_id' => $folio->id,
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

            $charge = $this->postCharge->price(
                folio: $folio,
                sourceType: 'extra_order',
                sourceId: $order->id,
                description: "Extras — order {$order->reference}",
                price: new Money($total, $currency),
            );

            // What the customer pays, VAT included, and the VAT inside it.
            $order->update([
                'total_amount' => $charge->total->amount,
                'tax_amount' => $charge->tax_amount->amount,
            ]);

            return $order->load('items');
        });
    }
}
