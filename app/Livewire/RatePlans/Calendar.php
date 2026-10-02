<?php

declare(strict_types=1);

namespace App\Livewire\RatePlans;

use App\Models\RateCalendar;
use App\Models\RatePlan;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Title('Rate calendar')]
class Calendar extends Component
{
    public RatePlan $ratePlan;

    /** YYYY-MM anchor for the month being edited. */
    public string $month = '';

    public ?string $editingDate = null;

    #[Validate('nullable|integer|min:0')]
    public ?int $price_tsh = null;

    #[Validate('nullable|integer|min:1|max:60')]
    public ?int $min_stay = null;

    public bool $stop_sell = false;

    public function mount(RatePlan $ratePlan): void
    {
        $this->ratePlan = $ratePlan;
        $this->month = CarbonImmutable::today()->format('Y-m');
    }

    /**
     * @return array<int, array{date: string, dow: string, isWeekend: bool, entry: ?RateCalendar, effectivePrice: Money}>
     */
    #[Computed]
    public function days(): array
    {
        $anchor = CarbonImmutable::createFromFormat('Y-m-d', $this->month.'-01')
            ?: CarbonImmutable::today()->startOfMonth();

        $start = $anchor->startOfMonth();
        $end = $anchor->endOfMonth();

        $entries = RateCalendar::query()
            ->where('rate_plan_id', $this->ratePlan->id)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->get()
            ->keyBy(fn (RateCalendar $c) => $c->date->toDateString());

        $days = [];
        for ($cursor = $start; $cursor->lessThanOrEqualTo($end); $cursor = $cursor->addDay()) {
            $date = $cursor->toDateString();
            $entry = $entries->get($date);
            $days[] = [
                'date' => $date,
                'dow' => $cursor->format('D'),
                'isWeekend' => $cursor->isWeekend(),
                'entry' => $entry,
                'effectivePrice' => $entry->price ?? $this->ratePlan->price,
            ];
        }

        return $days;
    }

    public function prevMonth(): void
    {
        $this->month = CarbonImmutable::createFromFormat('Y-m-d', $this->month.'-01')
            ->subMonth()->format('Y-m');
        unset($this->days);
    }

    public function nextMonth(): void
    {
        $this->month = CarbonImmutable::createFromFormat('Y-m-d', $this->month.'-01')
            ->addMonth()->format('Y-m');
        unset($this->days);
    }

    public function openEdit(string $date): void
    {
        $entry = RateCalendar::query()
            ->where('rate_plan_id', $this->ratePlan->id)
            ->where('date', $date)
            ->first();

        $this->editingDate = $date;
        $this->price_tsh = $entry?->price !== null ? (int) $entry->price->toMajor() : null;
        $this->min_stay = $entry?->min_stay;
        $this->stop_sell = $entry->stop_sell ?? false;
        $this->resetValidation();
        Flux::modal('rate-calendar-form')->show();
    }

    public function save(): void
    {
        $this->validate();

        if ($this->editingDate === null) {
            return;
        }

        $shouldStore = $this->price_tsh !== null || $this->min_stay !== null || $this->stop_sell;

        if (! $shouldStore) {
            RateCalendar::query()
                ->where('rate_plan_id', $this->ratePlan->id)
                ->where('date', $this->editingDate)
                ->delete();
        } else {
            RateCalendar::query()->updateOrCreate(
                ['rate_plan_id' => $this->ratePlan->id, 'date' => $this->editingDate],
                [
                    'price' => $this->price_tsh !== null ? Money::fromMajor($this->price_tsh) : null,
                    'min_stay' => $this->min_stay,
                    'stop_sell' => $this->stop_sell,
                ],
            );
        }

        Flux::modal('rate-calendar-form')->close();
        Flux::toast(variant: 'success', text: 'Calendar updated.');
        unset($this->days);
    }

    public function clear(string $date): void
    {
        RateCalendar::query()
            ->where('rate_plan_id', $this->ratePlan->id)
            ->where('date', $date)
            ->delete();

        Flux::toast(variant: 'success', text: 'Override removed.');
        unset($this->days);
    }

    public function render(): View
    {
        return view('livewire.rate-plans.calendar');
    }
}
