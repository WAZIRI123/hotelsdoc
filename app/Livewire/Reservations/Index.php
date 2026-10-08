<?php

declare(strict_types=1);

namespace App\Livewire\Reservations;

use App\Domain\Reservations\Actions\ChangeReservationDates;
use App\Domain\Reservations\Actions\CheckOutStay;
use App\Models\Reservation;
use Carbon\CarbonImmutable;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use RuntimeException;

#[Title('Reservations')]
class Index extends Component
{
    use WithPagination;

    #[Url(as: 'status')]
    public string $filterStatus = 'all';

    #[Url(as: 'q')]
    public string $search = '';

    /** Overdue reservation the "extend stay" modal is open for. */
    public ?int $extendingReservationId = null;

    public string $extendDeparture = '';

    public function updatedFilterStatus(): void
    {
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
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

    /**
     * Guests still checked in after their departure date. Their extra nights
     * are neither booked nor billed until they are checked out or extended.
     *
     * @return Collection<int, Reservation>
     */
    #[Computed]
    public function overdueDepartures(): Collection
    {
        return Reservation::query()
            ->with(['guest', 'rooms.room'])
            ->overdueDeparture()
            ->orderBy('departure')
            ->get();
    }

    public function checkOutOverdue(int $reservationId, CheckOutStay $action): void
    {
        $stay = $this->findOverdue($reservationId)?->stay;
        if ($stay === null) {
            Flux::toast(variant: 'danger', text: __('No active stay to check out.'));

            return;
        }

        try {
            $action->execute($stay, Auth::user());
        } catch (RuntimeException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());

            return;
        }

        Flux::toast(variant: 'success', text: __('Checked out.'));
        unset($this->overdueDepartures, $this->reservations);
    }

    public function startExtending(int $reservationId): void
    {
        if ($this->findOverdue($reservationId) === null) {
            return;
        }

        $this->extendingReservationId = $reservationId;
        $this->extendDeparture = CarbonImmutable::tomorrow()->toDateString();
        $this->resetErrorBag('extendDeparture');
        Flux::modal('extend-stay')->show();
    }

    public function extendStay(ChangeReservationDates $action): void
    {
        $this->validate(['extendDeparture' => ['required', 'date', 'after:today']]);

        $reservation = $this->extendingReservationId !== null ? $this->findOverdue($this->extendingReservationId) : null;
        if ($reservation === null) {
            Flux::modal('extend-stay')->close();

            return;
        }

        try {
            $action->execute($reservation, $reservation->arrival, CarbonImmutable::parse($this->extendDeparture));
        } catch (RuntimeException $e) {
            $this->addError('extendDeparture', $e->getMessage());

            return;
        }

        Flux::toast(variant: 'success', text: __('Stay extended; the extra nights are billed to the guest billing.'));
        Flux::modal('extend-stay')->close();
        $this->extendingReservationId = null;
        unset($this->overdueDepartures, $this->reservations);
    }

    public function render(): View
    {
        return view('livewire.reservations.index');
    }

    private function findOverdue(int $reservationId): ?Reservation
    {
        return $this->overdueDepartures->firstWhere('id', $reservationId);
    }
}
