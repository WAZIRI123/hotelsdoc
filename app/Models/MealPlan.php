<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Database\Factories\MealPlanFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $hotel_id
 * @property string $code
 * @property string $name
 * @property string|null $description
 * @property bool $includes_breakfast
 * @property bool $includes_lunch
 * @property bool $includes_dinner
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class MealPlan extends Model
{
    /** @use HasFactory<MealPlanFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'hotel_id',
        'code',
        'name',
        'description',
        'includes_breakfast',
        'includes_lunch',
        'includes_dinner',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'includes_breakfast' => 'boolean',
            'includes_lunch' => 'boolean',
            'includes_dinner' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<RatePlan, $this>
     */
    public function ratePlans(): HasMany
    {
        return $this->hasMany(RatePlan::class);
    }

    public function mealsIncludedCount(): int
    {
        return (int) $this->includes_breakfast
            + (int) $this->includes_lunch
            + (int) $this->includes_dinner;
    }

    /**
     * Whether any rate plan includes this meal plan.
     */
    public function isInUse(): bool
    {
        return $this->ratePlans()->exists();
    }
}
