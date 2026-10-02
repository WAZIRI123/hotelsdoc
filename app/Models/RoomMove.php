<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * History of a reserved room being swapped for another physical room.
 *
 * @property int $id
 * @property int $hotel_id
 * @property int $reservation_room_id
 * @property int|null $from_room_id
 * @property int|null $to_room_id
 * @property Carbon $effective_date
 * @property string $rate_action
 * @property string|null $reason
 * @property int|null $moved_by
 * @property Carbon|null $created_at
 */
class RoomMove extends Model
{
    use BelongsToTenant;

    public const RATE_ACTIONS = ['keep_rate', 'new_rate'];

    protected $fillable = [
        'hotel_id',
        'reservation_room_id',
        'from_room_id',
        'to_room_id',
        'effective_date',
        'rate_action',
        'reason',
        'moved_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'effective_date' => 'date:Y-m-d',
        ];
    }

    /**
     * @return BelongsTo<ReservationRoom, $this>
     */
    public function reservationRoom(): BelongsTo
    {
        return $this->belongsTo(ReservationRoom::class);
    }

    /**
     * @return BelongsTo<Room, $this>
     */
    public function fromRoom(): BelongsTo
    {
        return $this->belongsTo(Room::class, 'from_room_id');
    }

    /**
     * @return BelongsTo<Room, $this>
     */
    public function toRoom(): BelongsTo
    {
        return $this->belongsTo(Room::class, 'to_room_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function movedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moved_by');
    }
}
