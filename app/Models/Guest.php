<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Database\Factories\GuestFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $uuid
 * @property int $hotel_id
 * @property string $first_name
 * @property string|null $last_name
 * @property string|null $caller_name
 * @property string|null $phone
 * @property string|null $email
 * @property string|null $id_type
 * @property string|null $id_number
 */
class Guest extends Model
{
    /** @use HasFactory<GuestFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'hotel_id',
        'first_name',
        'last_name',
        'caller_name',
        'phone',
        'email',
        'nationality',
        'id_type',
        'id_number',
        'address',
        'notes',
    ];

    protected static function booted(): void
    {
        static::creating(function (Guest $guest): void {
            $guest->uuid ??= (string) Str::uuid();
        });
    }

    /**
     * @return HasMany<Reservation, $this>
     */
    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    public function fullName(): string
    {
        return trim($this->first_name.' '.($this->last_name ?? ''));
    }
}
