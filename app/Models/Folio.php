<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Tenancy\Concerns\BelongsToTenant;
use App\Support\Casts\MoneyCast;
use App\Support\HotelCurrency;
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
 * @property Carbon|null $voided_at
 * @property int|null $voided_by
 * @property string|null $void_reason
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
        'voided_at',
        'voided_by',
        'void_reason',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'subtotal' => MoneyCast::class,
            'tax_total' => MoneyCast::class,
            'total_amount' => MoneyCast::class,
            'paid_total' => MoneyCast::class,
            'closed_at' => 'datetime',
            'settled_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Folio $folio): void {
            $folio->uuid ??= (string) Str::uuid();
            $folio->reference ??= 'F-'.strtoupper(Str::random(10));
            $folio->currency ??= HotelCurrency::forHotel($folio->hotel_id);
        });
    }

    /**
     * Whether new charges may be posted. A settled folio still can: posting
     * onto it reopens it, so a guest who paid up front can keep spending.
     */
    public function acceptsCharges(): bool
    {
        return in_array($this->status, ['open', 'closed', 'settled'], true);
    }

    /**
     * The status a settled folio goes back to when it is owed money again.
     */
    public function unsettledStatus(): string
    {
        return $this->closed_at === null ? 'open' : 'closed';
    }

    /**
     * Mark the folio's fiscal receipt as no longer current, after what the
     * guest owes or has paid has changed.
     */
    public function supersedeReceipts(): void
    {
        FiscalReceipt::query()
            ->where('folio_id', $this->id)
            ->where('status', 'issued')
            ->update(['status' => 'superseded']);
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
