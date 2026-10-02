<?php

declare(strict_types=1);

namespace App\Livewire\Guests;

use App\Domain\Reservations\Actions\MarkRoomClean;
use App\Models\Folio;
use App\Models\Reservation;
use App\Models\ReservationRoom;
use App\Models\Room;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use RuntimeException;

#[Title('Guests')]
class Index extends Component
{
    use WithPagination;

    #[Url(as: 'status')]
    public string $filterStatus = 'all';

    #[Url(as: 'q')]
    public string $search = '';

    public function updatedFilterStatus(): void
    {
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

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
     * Housekeeping marks a room left for cleaning as ready to sell again.
     */
    public function markClean(int $roomId, MarkRoomClean $markRoomClean): void
    {
        try {
            $room = $markRoomClean->execute(Room::query()->findOrFail($roomId));
        } catch (RuntimeException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());

            return;
        }

        Flux::toast(variant: 'success', text: __('Room :number is clean and available.', ['number' => $room->number]));
        unset($this->rooms);
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

    /**
     * @return LengthAwarePaginator<int, Reservation>
     */
    #[Computed]
    public function reservations(): LengthAwarePaginator
    {
        return Reservation::query()
            ->with(['guest', 'rooms.room'])
            ->when($this->filterStatus !== 'all', fn ($q) => $q->where('status', $this->filterStatus))
            ->when($this->search, function ($q): void {
                $term = '%'.$this->search.'%';
                $q->where(function ($q) use ($term): void {
                    $q->where('confirmation_no', 'like', $term)
                        ->orWhereHas('guest', fn ($q) => $q
                            ->where('first_name', 'like', $term)
                            ->orWhere('last_name', 'like', $term)
                            ->orWhere('phone', 'like', $term));
                });
            })
            ->latest('arrival')
            ->paginate(15);
    }

    public function render(): View
    {
        return view('livewire.guests.index');
    }
}
