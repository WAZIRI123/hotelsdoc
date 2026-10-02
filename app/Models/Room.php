<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Database\Factories\RoomFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $hotel_id
 * @property int $room_type_id
 * @property string $number
 * @property string $status
 * @property string|null $notes
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Room extends Model
{
    /** @use HasFactory<RoomFactory> */
    use BelongsToTenant, HasFactory;

    public const STATUSES = ['available', 'occupied', 'cleaning', 'maintenance', 'out_of_order'];

    /**
     * Statuses staff set by hand. 'occupied' is owned by check-in, check-out
     * and room moves, so it is never chosen manually.
     *
     * @var list<string>
     */
    public const MANUAL_STATUSES = ['available', 'cleaning', 'maintenance', 'out_of_order'];

    /**
     * Statuses that take a room off sale until it is set back to available.
     *
     * @var list<string>
     */
    public const OUT_OF_SERVICE_STATUSES = ['maintenance', 'out_of_order'];

    protected $fillable = [
        'hotel_id',
        'room_type_id',
        'number',
        'status',
        'notes',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * Whether the room can be booked or slept in at all: inactive rooms and
     * rooms under maintenance or out of order cannot.
     */
    public function isOutOfService(): bool
    {
        return ! $this->is_active || in_array($this->status, self::OUT_OF_SERVICE_STATUSES, true);
    }

    /**
     * @return BelongsTo<RoomType, $this>
     */
    public function roomType(): BelongsTo
    {
        return $this->belongsTo(RoomType::class);
    }

    /**
     * Whether a reservation holds this room tonight or on a later night.
     */
    public function hasUpcomingBookings(): bool
    {
        return RoomAvailability::query()
            ->where('room_id', $this->id)
            ->whereNotNull('reservation_id')
            ->whereDate('date', '>=', today())
            ->exists();
    }

    /**
     * Whether any reservation or room move has ever used this room.
     */
    public function hasHistory(): bool
    {
        return ReservationRoom::query()->where('room_id', $this->id)->exists()
            || RoomMove::query()
                ->where(fn ($query) => $query->where('from_room_id', $this->id)->orWhere('to_room_id', $this->id))
                ->exists();
    }
}
