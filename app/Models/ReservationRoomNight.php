<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Tenancy\Concerns\BelongsToTenant;
use App\Support\Casts\MoneyCast;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $hotel_id
 * @property int $reservation_room_id
 * @property Carbon $date
 * @property Money $rate
 */
class ReservationRoomNight extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'hotel_id',
        'reservation_room_id',
        'date',
        'rate',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
            'rate' => MoneyCast::class,
        ];
    }

    /**
     * @return BelongsTo<ReservationRoom, $this>
     */
    public function reservationRoom(): BelongsTo
    {
        return $this->belongsTo(ReservationRoom::class);
    }
}
