<?php

declare(strict_types=1);

namespace App\Livewire\Reports;

use App\Models\Agent;
use App\Models\Reservation;
use App\Models\ReservationRoom;
use App\Support\Money;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;

/**
 * What each agent brought in: bookings arriving in the period, the room nights
 * and room revenue they booked, and what their guests' folios were billed,
 * paid and still owe. Billing stays with the guest folio.
 */
#[Title('Agents Report')]
class Agents extends ReportComponent
{
    /** Reservations that never turned into a stay. */
    private const LOST_STATUSES = ['cancelled', 'no_show'];

    /**
     * @return Collection<int, Reservation>
     */
    #[Computed]
    public function reservations(): Collection
    {
        return Reservation::query()
            ->whereNotNull('agent_id')
            ->with(['agent', 'rooms.nights', 'stay.folio'])
            ->whereDate('arrival', '>=', $this->periodStart())
            ->whereDate('arrival', '<=', $this->periodEnd())
            ->get();
    }

    /**
     * One row per agent with bookings arriving in the period, busiest first.
     *
     * @return array<int, array{agent: Agent, bookings: int, lost: int, room_nights: int, room_revenue: Money, billed: Money, paid: Money, balance: Money}>
     */
    #[Computed]
    public function rows(): array
    {
        return $this->reservations()
            ->filter(fn (Reservation $reservation): bool => $reservation->agent !== null)
            ->groupBy('agent_id')
            ->map(function (Collection $reservations): array {
                /** @var Agent $agent */
                $agent = $reservations->first()->agent;
                $kept = $reservations->reject(fn (Reservation $reservation): bool => in_array($reservation->status, self::LOST_STATUSES, true));
                $rooms = $kept->flatMap->rooms;
                $folios = $kept->map(fn (Reservation $reservation) => $reservation->stay?->folio)->filter();

                $billed = $this->money((int) $folios->sum(fn ($folio): int => $folio->total_amount->amount));
                $paid = $this->money((int) $folios->sum(fn ($folio): int => $folio->paid_total->amount));

                return [
                    'agent' => $agent,
                    'bookings' => $kept->count(),
                    'lost' => $reservations->count() - $kept->count(),
                    'room_nights' => (int) $rooms->sum(fn (ReservationRoom $room): int => $room->nights->count()),
                    'room_revenue' => $this->money((int) $rooms->sum(fn (ReservationRoom $room): int => $room->totalRate()->amount)),
                    'billed' => $billed,
                    'paid' => $paid,
                    'balance' => $billed->subtract($paid),
                ];
            })
            ->sortByDesc(fn (array $row): int => $row['room_revenue']->amount)
            ->values()
            ->all();
    }

    /**
     * @return array{bookings: int, lost: int, room_nights: int, room_revenue: Money, billed: Money, paid: Money, balance: Money}
     */
    #[Computed]
    public function totals(): array
    {
        $totals = [
            'bookings' => 0,
            'lost' => 0,
            'room_nights' => 0,
            'room_revenue' => $this->money(0),
            'billed' => $this->money(0),
            'paid' => $this->money(0),
            'balance' => $this->money(0),
        ];

        foreach ($this->rows() as $row) {
            foreach (['bookings', 'lost', 'room_nights'] as $key) {
                $totals[$key] += $row[$key];
            }

            foreach (['room_revenue', 'billed', 'paid', 'balance'] as $key) {
                $totals[$key] = $totals[$key]->add($row[$key]);
            }
        }

        return $totals;
    }

    public function getReport(): void
    {
        parent::getReport();

        unset($this->reservations, $this->rows, $this->totals);
    }

    public function render(): View
    {
        return view('livewire.reports.agents');
    }

    protected function reportKey(): string
    {
        return 'agents';
    }

    private function money(int $amount): Money
    {
        return new Money($amount, $this->hotelCurrency());
    }
}
