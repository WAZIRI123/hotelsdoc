<?php

declare(strict_types=1);

namespace App\Livewire\Reports;

use App\Models\Folio;
use App\Models\PosOrder;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;

/**
 * Folios opened in the period that still owe money, with the balance split
 * across accommodation, restaurant and bar in proportion to what was billed.
 */
#[Title('Credit Report')]
class Credit extends ReportComponent
{
    private const DEPARTMENTS = ['accommodation', 'restaurant', 'bar', 'total'];

    /**
     * Unpaid balances of folios opened in the period, split by department.
     *
     * @return array<int, array{folio: Folio, customer: string|null, opened: CarbonImmutable, accommodation: Money, restaurant: Money, bar: Money, total: Money}>
     */
    #[Computed]
    public function rows(): array
    {
        $folios = Folio::query()
            ->with(['guest', 'charges'])
            ->whereIn('status', ['open', 'closed'])
            ->whereColumn('total_amount', '>', 'paid_total')
            ->whereBetween('created_at', [$this->periodStart(), $this->periodEnd()])
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        $outletTypes = PosOrder::query()
            ->whereIn('id', $folios->flatMap->charges->where('source_type', 'pos_order')->pluck('source_id')->filter()->unique())
            ->with('outlet')
            ->get()
            ->mapWithKeys(fn (PosOrder $order): array => [$order->id => $order->outlet?->type]);

        return $folios
            ->map(function (Folio $folio) use ($outletTypes): array {
                $balance = new Money($folio->total_amount->amount - $folio->paid_total->amount, $folio->currency);
                $unpaidShare = $balance->amount / $folio->total_amount->amount;
                $billed = ['restaurant' => 0, 'bar' => 0];

                foreach ($folio->charges as $charge) {
                    $department = $charge->source_type === 'pos_order' ? self::posDepartment($outletTypes[$charge->source_id] ?? null) : null;

                    if ($department !== null) {
                        $billed[$department] += $charge->total->amount;
                    }
                }

                $restaurant = new Money((int) round($billed['restaurant'] * $unpaidShare), $folio->currency);
                $bar = new Money((int) round($billed['bar'] * $unpaidShare), $folio->currency);

                return [
                    'folio' => $folio,
                    'customer' => $folio->guest?->fullName(),
                    'opened' => CarbonImmutable::parse($folio->created_at),
                    'accommodation' => $balance->subtract($restaurant)->subtract($bar),
                    'restaurant' => $restaurant,
                    'bar' => $bar,
                    'total' => $balance,
                ];
            })
            ->all();
    }

    /**
     * @return array{count: int, accommodation: Money, restaurant: Money, bar: Money, total: Money}
     */
    #[Computed]
    public function totals(): array
    {
        $totals = ['count' => 0];

        foreach (self::DEPARTMENTS as $key) {
            $totals[$key] = Money::zero($this->hotelCurrency());
        }

        foreach ($this->rows() as $row) {
            $totals['count']++;

            foreach (self::DEPARTMENTS as $key) {
                $totals[$key] = $totals[$key]->add($row[$key]);
            }
        }

        return $totals;
    }

    public function render(): View
    {
        return view('livewire.reports.credit');
    }

    protected function reportKey(): string
    {
        return 'credit';
    }

    /**
     * Bar outlets are reported as Bar; every other outlet (restaurant, room service, shop) as Restaurant.
     */
    private static function posDepartment(?string $outletType): string
    {
        return $outletType === 'bar' ? 'bar' : 'restaurant';
    }
}
