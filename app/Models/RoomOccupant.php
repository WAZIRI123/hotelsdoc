<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Database\Factories\RoomOccupantFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A person sleeping in a reserved room. Rows are never deleted once the
 * person has checked in, so the register shows who was in a room on any date.
 *
 * @property int $id
 * @property int $hotel_id
 * @property int $reservation_room_id
 * @property int|null $guest_id
 * @property string $age_category
 * @property bool $is_primary
 * @property Carbon|null $checked_in_at
 * @property Carbon|null $checked_out_at
 * @property int|null $added_by
 * @property int|null $removed_by
 */
class RoomOccupant extends Model
{
    /** @use HasFactory<RoomOccupantFactory> */
    use BelongsToTenant, HasFactory;

    public const AGE_CATEGORIES = ['adult', 'child'];

    protected $fillable = [
        'hotel_id',
        'reservation_room_id',
        'guest_id',
        'age_category',
        'is_primary',
        'checked_in_at',
        'checked_out_at',
        'added_by',
        'removed_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'checked_in_at' => 'datetime',
            'checked_out_at' => 'datetime',
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
     * @return BelongsTo<Guest, $this>
     */
    public function guest(): BelongsTo
    {
        return $this->belongsTo(Guest::class);
    }

    /**
     * Occupants still assigned to their room (not removed, transferred or checked out).
     *
     * @param  Builder<RoomOccupant>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->whereNull('checked_out_at');
    }

    public function isActive(): bool
    {
        return $this->checked_out_at === null;
    }

    public function isChild(): bool
    {
        return $this->age_category === 'child';
    }

    public function displayName(): string
    {
        return $this->guest?->fullName() ?? ($this->isChild() ? __('Unnamed child') : __('Unnamed adult'));
    }
}
