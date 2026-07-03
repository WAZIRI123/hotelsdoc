<?php

declare(strict_types=1);

namespace App\Livewire\Guests;

use App\Models\Folio;
use App\Models\ReservationRoom;
use App\Models\Room;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Guests')]
class Index extends Component
{
    /**
     * @return Collection<int, Room>
     */
    #[Computed]
    public function rooms(): Collection
    {
        return Room::query()
            ->with('roomType')
            ->where('is_active', true)
            ->orderBy('number')
            ->get();
    }

    /**
     * Map of room_id => Folio for rooms currently occupied by a checked-in stay.
     *
     * @return array<int, Folio>
     */
    #[Computed]
    public function activeFolios(): array
    {
        return ReservationRoom::query()
            ->whereNotNull('room_id')
            ->whereHas('reservation', fn ($q) => $q->where('status', 'checked_in'))
            ->with('reservation.stay.folio')
            ->get()
            ->mapWithKeys(function (ReservationRoom $reservationRoom): array {
                $folio = $reservationRoom->reservation?->stay?->folio;

                return $folio ? [$reservationRoom->room_id => $folio] : [];
            })
            ->all();
    }

    public function render(): View
    {
        return view('livewire.guests.index');
    }
}
