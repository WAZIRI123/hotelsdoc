<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Database\Factories\ReservationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $uuid
 * @property int $hotel_id
 * @property int $guest_id
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

    public const STATUSES = ['hold', 'confirmed', 'checked_in', 'checked_out', 'cancelled', 'no_show'];

    protected $fillable = [
        'hotel_id',
        'guest_id',
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
     * @return HasMany<ReservationRoom, $this>
     */
    public function rooms(): HasMany
    {
        return $this->hasMany(ReservationRoom::class);
    }

    /**
     * @return HasOne<Stay, $this>
     */
    public function stay(): HasOne
    {
        return $this->hasOne(Stay::class);
    }
}
