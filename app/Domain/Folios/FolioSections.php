<?php

declare(strict_types=1);

namespace App\Domain\Folios;

use App\Models\Folio;
use App\Models\FolioCharge;
use App\Models\Payment;
use App\Support\Money;

/**
 * Splits a guest billing into its sections and works out what has been paid
 * and is still owed on each.
 *
 * A payment made against one section pays that section only. A payment made
 * against the whole bill (and anything paid beyond a section's total) is
 * shared across the sections still owing, in proportion to what each owes.
 * What is left over after that is credit due to the guest.
 */
final class FolioSections
{
    /**
     * @param  array<string, array{section: BillSection, total: Money, paid: Money, balance: Money}>  $sections
     */
    private function __construct(
        public readonly array $sections,
        public readonly Money $credit,
    ) {}

    public static function for(Folio $folio): self
    {
        $folio->loadMissing('charges', 'payments');

        $zero = Money::zero($folio->currency);
        $totals = [];
        $paid = [];

        foreach (BillSection::cases() as $section) {
            $totals[$section->value] = $zero;
            $paid[$section->value] = $zero;
        }

        foreach ($folio->charges as $charge) {
            /** @var FolioCharge $charge */
            $key = BillSection::forSourceType($charge->source_type)->value;
            $totals[$key] = $totals[$key]->add($charge->total);
        }

        $pool = $zero;

        foreach ($folio->payments->where('status', 'confirmed') as $payment) {
            /** @var Payment $payment */
            if ($payment->bill_section === null) {
                $pool = $pool->add($payment->amount);
            } else {
                $paid[$payment->bill_section] = $paid[$payment->bill_section]->add($payment->amount);
            }
        }

        foreach (BillSection::cases() as $section) {
            $over = $paid[$section->value]->amount - max(0, $totals[$section->value]->amount);

            if ($over > 0) {
                $paid[$section->value] = $paid[$section->value]->subtract(new Money($over, $folio->currency));
                $pool = $pool->add(new Money($over, $folio->currency));
            }
        }

        $owed = [];

        foreach (BillSection::cases() as $section) {
            $owed[$section->value] = max(0, $totals[$section->value]->amount - $paid[$section->value]->amount);
        }

        $totalOwed = array_sum($owed);
        $available = min($totalOwed, max(0, $pool->amount));
        $shared = 0;
        $sections = [];

        foreach (BillSection::cases() as $section) {
            // The last section owing takes the rounding remainder, so the shares add up exactly.
            $fill = $totalOwed === 0 ? 0 : intdiv($owed[$section->value] * $available, $totalOwed);
            $shared += $fill;

            $paid[$section->value] = $paid[$section->value]->add(new Money($fill, $folio->currency));
        }

        foreach (array_reverse(BillSection::cases()) as $section) {
            if ($shared < $available && $owed[$section->value] > 0) {
                $paid[$section->value] = $paid[$section->value]->add(new Money($available - $shared, $folio->currency));
                $shared = $available;
            }
        }

        $pool = $pool->subtract(new Money($available, $folio->currency));

        foreach (BillSection::cases() as $section) {
            $sections[$section->value] = [
                'section' => $section,
                'total' => $totals[$section->value],
                'paid' => $paid[$section->value],
                'balance' => $totals[$section->value]->subtract($paid[$section->value]),
            ];
        }

        return new self($sections, $pool->isPositive() ? $pool : $zero);
    }

    /**
     * @return array{section: BillSection, total: Money, paid: Money, balance: Money}
     */
    public function get(BillSection $section): array
    {
        return $this->sections[$section->value];
    }

    public function balance(BillSection $section): Money
    {
        return $this->sections[$section->value]['balance'];
    }

    /**
     * Sections that have any charge on them, for display.
     *
     * @return array<string, array{section: BillSection, total: Money, paid: Money, balance: Money}>
     */
    public function used(): array
    {
        return array_filter($this->sections, fn (array $row): bool => ! $row['total']->isZero() || ! $row['paid']->isZero());
    }
}
