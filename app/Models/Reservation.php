<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Database\Factories\ReservationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $uuid
 * @property int $hotel_id
 * @property int $guest_id
 * @property int|null $agent_id
 * @property string $confirmation_no
 * @property string $status
 * @property Carbon $arrival
 * @property Carbon $departure
 * @property int $adults
 * @property int $children
 * @property string $source
 * @property string|null $external_id
 */
class Reservation extends Model
{
    /** @use HasFactory<ReservationFactory> */
    use BelongsToTenant, HasFactory;

    public const STATUSES = ['confirmed', 'checked_in', 'checked_out', 'cancelled', 'no_show'];

    protected $fillable = [
        'hotel_id',
        'guest_id',
        'agent_id',
        'confirmation_no',
        'status',
        'arrival',
        'departure',
        'adults',
        'children',
        'source',
        'external_id',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'arrival' => 'date:Y-m-d',
            'departure' => 'date:Y-m-d',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Reservation $reservation): void {
            $reservation->uuid ??= (string) Str::uuid();
            $reservation->confirmation_no ??= 'CN-'.strtoupper(Str::random(8));
        });
    }

    /**
     * @return BelongsTo<Guest, $this>
     */
    public function guest(): BelongsTo
    {
        return $this->belongsTo(Guest::class);
    }

    /**
     * The agent who booked this reservation; null for walk-in bookings.
     *
     * @return BelongsTo<Agent, $this>
     */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    /**
     * @return HasMany<ReservationRoom, $this>
     */
    public function rooms(): HasMany
    {
        return $this->hasMany(ReservationRoom::class);
    }

    /**
     * Everyone assigned to any of this reservation's rooms.
     *
     * @return HasManyThrough<RoomOccupant, ReservationRoom, $this>
     */
    public function occupants(): HasManyThrough
    {
        return $this->hasManyThrough(RoomOccupant::class, ReservationRoom::class);
    }

    /**
     * Re-derive the reservation-level head count from its rooms.
     */
    public function syncGuestTotals(): void
    {
        $totals = $this->rooms()
            ->selectRaw('COALESCE(SUM(adults), 0) as adults, COALESCE(SUM(children), 0) as children')
            ->toBase()
            ->first();

        $values = [
            'adults' => (int) $totals->adults,
            'children' => (int) $totals->children,
        ];

        // Write unconditionally: another in-memory copy of this reservation may
        // have changed the row, so this instance's dirty-check can't be trusted.
        $this->newQuery()->whereKey($this->getKey())->update($values);
        $this->forceFill($values)->syncOriginalAttributes(array_keys($values));
    }

    /**
     * @return HasOne<Stay, $this>
     */
    public function stay(): HasOne
    {
        return $this->hasOne(Stay::class);
    }
}
