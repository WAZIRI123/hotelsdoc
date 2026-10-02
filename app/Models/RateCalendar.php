<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Tenancy\Concerns\BelongsToTenant;
use App\Support\Casts\MoneyCast;
use App\Support\Money;
use Database\Factories\RateCalendarFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $hotel_id
 * @property int $rate_plan_id
 * @property Carbon $date
 * @property Money|null $price
 * @property int|null $min_stay
 * @property bool $stop_sell
 */
class RateCalendar extends Model
{
    /** @use HasFactory<RateCalendarFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'hotel_id',
        'rate_plan_id',
        'date',
        'price',
        'min_stay',
        'stop_sell',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
            'price' => MoneyCast::class,
            'min_stay' => 'integer',
            'stop_sell' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<RatePlan, $this>
     */
    public function ratePlan(): BelongsTo
    {
        return $this->belongsTo(RatePlan::class);
    }
}
