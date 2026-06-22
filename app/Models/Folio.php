<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Tenancy\Concerns\BelongsToTenant;
use App\Support\Casts\MoneyCast;
use App\Support\Money;
use Database\Factories\FolioFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $uuid
 * @property int $hotel_id
 * @property int|null $stay_id
 * @property int|null $guest_id
 * @property string $reference
 * @property string $status
 * @property string $currency
 * @property Money $subtotal
 * @property Money $tax_total
 * @property Money $total_amount
 * @property Money $paid_total
 * @property Carbon|null $closed_at
 * @property Carbon|null $settled_at
 */
class Folio extends Model
{
    /** @use HasFactory<FolioFactory> */
    use BelongsToTenant, HasFactory;

    public const STATUSES = ['open', 'closed', 'settled', 'void'];

    /** @var array<string, mixed> */
    protected $attributes = [
        'subtotal' => 0,
        'tax_total' => 0,
        'total_amount' => 0,
        'paid_total' => 0,
        'currency' => 'TZS',
        'status' => 'open',
    ];

    protected $fillable = [
        'hotel_id',
        'stay_id',
        'guest_id',
        'reference',
        'status',
        'currency',
        'subtotal',
        'tax_total',
        'total_amount',
        'paid_total',
        'closed_at',
        'settled_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'subtotal' => MoneyCast::class.':TZS',
            'tax_total' => MoneyCast::class.':TZS',
            'total_amount' => MoneyCast::class.':TZS',
            'paid_total' => MoneyCast::class.':TZS',
            'closed_at' => 'datetime',
            'settled_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Folio $folio): void {
            $folio->uuid ??= (string) Str::uuid();
            $folio->reference ??= 'F-'.strtoupper(Str::random(10));
        });
    }

    /**
     * @return BelongsTo<Stay, $this>
     */
    public function stay(): BelongsTo
    {
        return $this->belongsTo(Stay::class);
    }

    /**
     * @return BelongsTo<Guest, $this>
     */
    public function guest(): BelongsTo
    {
        return $this->belongsTo(Guest::class);
    }

    /**
     * @return HasMany<FolioCharge, $this>
     */
    public function charges(): HasMany
    {
        return $this->hasMany(FolioCharge::class);
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
