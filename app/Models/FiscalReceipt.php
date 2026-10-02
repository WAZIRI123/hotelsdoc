<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Tenancy\Concerns\BelongsToTenant;
use App\Support\Casts\MoneyCast;
use App\Support\Money;
use Database\Factories\FiscalReceiptFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $uuid
 * @property int $hotel_id
 * @property int $folio_id
 * @property string $provider
 * @property int $sequence
 * @property string $receipt_no
 * @property string $status
 * @property Money $total_amount
 * @property string|null $tra_verification_code
 * @property string|null $qr_payload
 * @property array<string, mixed>|null $raw_response
 * @property Carbon|null $issued_at
 */
class FiscalReceipt extends Model
{
    /** @use HasFactory<FiscalReceiptFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'hotel_id',
        'folio_id',
        'provider',
        'sequence',
        'receipt_no',
        'status',
        'total_amount',
        'tra_verification_code',
        'qr_payload',
        'raw_response',
        'issued_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'total_amount' => MoneyCast::class,
            'raw_response' => 'array',
            'issued_at' => 'datetime',
            'sequence' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (FiscalReceipt $r): void {
            $r->uuid ??= (string) Str::uuid();
        });
    }

    /**
     * @return BelongsTo<Folio, $this>
     */
    public function folio(): BelongsTo
    {
        return $this->belongsTo(Folio::class);
    }
}
