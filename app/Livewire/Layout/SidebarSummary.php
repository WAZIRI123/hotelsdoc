<?php

declare(strict_types=1);

namespace App\Livewire\Layout;

use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomOccupant;
use App\Models\RoomType;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Live front-desk snapshot shown at the bottom of the sidebar.
 */
class SidebarSummary extends Component
{
    /**
     * @return array{total: int, occupied: int, todayBookings: int, todayCheckOuts: int}
     */
    #[Computed]
    public function roomsStatus(): array
    {
        $today = CarbonImmutable::today()->toDateString();

        return [
            'total' => Room::query()->where('is_active', true)->count(),
            'occupied' => Room::query()->where('is_active', true)->where('status', 'occupied')->count(),
            'todayBookings' => Reservation::query()
                ->whereDate('arrival', $today)
                ->whereNotIn('status', ['cancelled', 'no_show'])
                ->count(),
            'todayCheckOuts' => Reservation::query()
                ->whereDate('departure', $today)
                ->whereIn('status', ['checked_in', 'checked_out'])
                ->count(),
        ];
    }

    /**
     * Guests still checked in after their departure date.
     */
    #[Computed]
    public function overdueDepartures(): int
    {
        return Reservation::query()->overdueDeparture()->count();
    }

    /**
     * In-house guests by gender; unnamed occupants count towards the total only.
     *
     * @return array{total: int, males: int, females: int}
     */
    #[Computed]
    public function guests(): array
    {
        $occupants = RoomOccupant::query()
            ->active()
            ->whereNotNull('checked_in_at')
            ->with('guest:id,gender')
            ->get(['id', 'guest_id']);

        return [
            'total' => $occupants->count(),
            'males' => $occupants->where('guest.gender', 'male')->count(),
            'females' => $occupants->where('guest.gender', 'female')->count(),
        ];
    }

    /**
     * @return array{total: int, byType: array<string, int>}
     */
    #[Computed]
    public function available(): array
    {
        $byType = RoomType::query()
            ->where('is_active', true)
            ->withCount(['rooms' => fn ($query) => $query->where('is_active', true)->where('status', 'available')])
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (RoomType $roomType): array => [$roomType->name => (int) $roomType->rooms_count])
            ->all();

        return [
            'total' => array_sum($byType),
            'byType' => $byType,
        ];
    }

    public function render(): View
    {
        return view('livewire.layout.sidebar-summary');
    }
}
