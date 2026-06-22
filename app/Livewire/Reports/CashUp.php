<?php

declare(strict_types=1);

namespace App\Livewire\Reports;

use App\Models\Payment;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Cash-up')]
class CashUp extends Component
{
    #[Url(as: 'date')]
    public string $date = '';

    public function mount(): void
    {
        if ($this->date === '') {
            $this->date = CarbonImmutable::today()->toDateString();
        }
    }

    /**
     * @return Collection<int, Payment>
     */
    #[Computed]
    public function payments(): Collection
    {
        $day = CarbonImmutable::parse($this->date);

        return Payment::query()
            ->with('folio')
            ->where('status', 'confirmed')
            ->whereBetween('confirmed_at', [$day->startOfDay(), $day->endOfDay()])
            ->orderBy('confirmed_at')
            ->get();
    }

    /**
     * @return array<string, array{label: string, count: int, total: Money}>
     */
    #[Computed]
    public function totalsByMethod(): array
    {
        $groups = [];
        foreach ($this->payments() as $payment) {
            $code = $payment->method_code;
            if (! isset($groups[$code])) {
                $groups[$code] = [
                    'label' => ucwords(str_replace('_', ' ', $code)),
                    'count' => 0,
                    'total' => Money::zero(),
                ];
            }
            $groups[$code]['count']++;
            $groups[$code]['total'] = $groups[$code]['total']->add($payment->amount);
        }

        return $groups;
    }

    #[Computed]
    public function grandTotal(): Money
    {
        return $this->payments()->reduce(
            fn (Money $carry, Payment $p): Money => $carry->add($p->amount),
            Money::zero(),
        );
    }

    public function render(): View
    {
        return view('livewire.reports.cash-up');
    }
}
