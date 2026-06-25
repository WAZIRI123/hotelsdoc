<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Authorization\Listeners\SeedHotelRoles;
use App\Domain\Tenancy\Listeners\SeedDefaultHotelData;
use Database\Factories\HotelFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $uuid
 * @property string $name
 * @property string $slug
 * @property string $currency
 * @property string $timezone
 * @property string|null $phone
 * @property string|null $email
 * @property string|null $address
 * @property string|null $tin
 * @property string|null $vrn
 * @property array<string, mixed>|null $settings
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Hotel extends Model
{
    /** @use HasFactory<HotelFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'currency',
        'timezone',
        'phone',
        'email',
        'address',
        'tin',
        'vrn',
        'settings',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'settings' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Hotel $hotel): void {
            $hotel->uuid ??= (string) Str::uuid();
        });

        static::created(function (Hotel $hotel): void {
            app(SeedDefaultHotelData::class)->handle($hotel);
            app(SeedHotelRoles::class)->handle($hotel);
        });
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    /**
     * @return HasMany<Room, $this>
     */
    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class);
    }

    /**
     * @return HasMany<RoomType, $this>
     */
    public function roomTypes(): HasMany
    {
        return $this->hasMany(RoomType::class);
    }

    /**
     * @return HasMany<Outlet, $this>
     */
    public function outlets(): HasMany
    {
        return $this->hasMany(Outlet::class);
    }
}
