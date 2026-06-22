<?php

declare(strict_types=1);

namespace App\Livewire\Reservations;

use App\Domain\Reservations\Actions\CancelReservation;
use App\Domain\Reservations\Actions\CheckInReservation;
use App\Domain\Reservations\Actions\CheckOutStay;
use App\Models\Reservation;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;
use RuntimeException;

#[Title('Reservation')]
class Show extends Component
{
    public Reservation $reservation;

    public function mount(Reservation $reservation): void
    {
        $this->reservation = $reservation->load(['guest', 'rooms.room', 'rooms.roomType', 'stay.folio.charges']);
    }

    public function checkIn(CheckInReservation $action): void
    {
        try {
            $action->execute($this->reservation, Auth::user());
        } catch (RuntimeException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());

            return;
        }

        Flux::toast(variant: 'success', text: 'Checked in.');
        $this->reservation->refresh()->load(['guest', 'rooms.room', 'rooms.roomType', 'stay.folio.charges']);
    }

    public function checkOut(CheckOutStay $action): void
    {
        $stay = $this->reservation->stay;
        if ($stay === null) {
            Flux::toast(variant: 'danger', text: 'No active stay to check out.');

            return;
        }

        try {
            $action->execute($stay, Auth::user());
        } catch (RuntimeException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());

            return;
        }

        Flux::toast(variant: 'success', text: 'Checked out.');
        $this->reservation->refresh()->load(['guest', 'rooms.room', 'rooms.roomType', 'stay.folio.charges']);
    }

    public function cancel(CancelReservation $action): void
    {
        try {
            $action->execute($this->reservation);
        } catch (RuntimeException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());

            return;
        }

        Flux::toast(variant: 'success', text: 'Reservation cancelled.');
        $this->reservation->refresh()->load(['guest', 'rooms.room', 'rooms.roomType', 'stay.folio.charges']);
    }

    public function render(): View
    {
        return view('livewire.reservations.show');
    }
}
