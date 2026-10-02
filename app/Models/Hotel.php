<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Authorization\Listeners\SeedHotelRoles;
use App\Domain\Tenancy\Listeners\SeedDefaultHotelData;
use App\Support\HotelCurrency;
use Database\Factories\HotelFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $uuid
 * @property string $name
 * @property string|null $logo_path
 * @property string $slug
 * @property string $currency
 * @property string $timezone
 * @property string|null $phone
 * @property string|null $fax
 * @property string|null $email
 * @property string|null $address
 * @property string|null $street
 * @property string|null $tin
 * @property string|null $vrn
 * @property array<string, mixed>|null $settings
 * @property Carbon|null $suspended_at
 * @property int|null $approved_by
 * @property int|null $edited_by
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
        'fax',
        'email',
        'address',
        'street',
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
            'suspended_at' => 'datetime',
        ];
    }

    /**
     * Suspended hotels stay in the database but their staff cannot sign in
     * to them. Super-admins can still enter them.
     */
    public function isSuspended(): bool
    {
        return $this->suspended_at !== null;
    }

    /**
     * @param  Builder<Hotel>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->whereNull('hotels.suspended_at');
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

        static::saved(fn () => HotelCurrency::flush());
    }

    /**
     * Public URL of the uploaded logo, or null when none was uploaded.
     */
    public function logoUrl(): ?string
    {
        return $this->logo_path !== null ? Storage::disk('public')->url($this->logo_path) : null;
    }

    /**
     * The super-admin who set the hotel up.
     *
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * The super-admin who last edited the hotel's details.
     *
     * @return BelongsTo<User, $this>
     */
    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'edited_by');
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    /**
     * @return HasOne<InvoiceFooter, $this>
     */
    public function invoiceFooter(): HasOne
    {
        return $this->hasOne(InvoiceFooter::class);
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
