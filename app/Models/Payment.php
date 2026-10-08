<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Folios\BillSection;
use App\Domain\Payments\Tender;
use App\Domain\Tenancy\Concerns\BelongsToTenant;
use App\Support\Casts\MoneyCast;
use App\Support\Money;
use Database\Factories\PaymentFactory;
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
 * @property string|null $bill_section accommodation|outlets|extras; null pays the whole bill
 * @property string $method_code
 * @property Money $amount
 * @property string $currency
 * @property string|null $tendered_currency Set when the customer paid in an accepted foreign currency
 * @property int|null $tendered_amount Minor units, in tendered_currency
 * @property string|null $exchange_rate Hotel currency units for one unit of tendered_currency
 * @property string|null $reference
 * @property string|null $payer_phone
 * @property string|null $note
 * @property string $status
 * @property array<string, mixed>|null $provider_payload
 * @property Carbon|null $confirmed_at
 * @property Carbon|null $voided_at
 * @property int|null $voided_by
 * @property string|null $void_reason
 */
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'hotel_id',
        'folio_id',
        'bill_section',
        'method_code',
        'amount',
        'currency',
        'tendered_currency',
        'tendered_amount',
        'exchange_rate',
        'reference',
        'payer_phone',
        'note',
        'status',
        'provider_payload',
        'received_by',
        'confirmed_at',
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
            'amount' => MoneyCast::class,
            'exchange_rate' => 'decimal:6',
            'provider_payload' => 'array',
            'confirmed_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Payment $payment): void {
            $payment->uuid ??= (string) Str::uuid();
        });
    }

    /**
     * A refund is recorded as a payment with a negative amount.
     */
    public function isRefund(): bool
    {
        return $this->amount->isNegative();
    }

    public function paidInForeignCurrency(): bool
    {
        return $this->tendered_currency !== null && $this->tendered_amount !== null && $this->exchange_rate !== null;
    }

    /**
     * What the customer handed over in a foreign currency, or null when they
     * paid in the hotel currency.
     */
    public function tendered(): ?Tender
    {
        if (! $this->paidInForeignCurrency()) {
            return null;
        }

        return Tender::foreign(
            new Money((int) $this->tendered_amount, (string) $this->tendered_currency),
            (string) $this->exchange_rate,
            $this->amount->currency,
        );
    }

    /**
     * Change handed back in the hotel currency when a foreign-currency
     * tender was worth more than the amount credited.
     */
    public function changeGiven(): ?Money
    {
        return $this->tendered()?->changeFor($this->amount);
    }

    /**
     * The amount as shown on screens and prints: "USD 50 (TZS 130,000)" when
     * paid in a foreign currency, otherwise the hotel-currency amount.
     */
    public function amountLabel(): string
    {
        $tender = $this->tendered();

        return $tender === null
            ? $this->amount->format()
            : $tender->amount->formatExact().' ('.$tender->localValue()->format().')';
    }

    /**
     * The part of the guest billing this payment was made against, or null
     * when it was made against the whole bill.
     */
    public function billSection(): ?BillSection
    {
        return $this->bill_section === null ? null : BillSection::from($this->bill_section);
    }

    /**
     * @return BelongsTo<Folio, $this>
     */
    public function folio(): BelongsTo
    {
        return $this->belongsTo(Folio::class);
    }

    /**
     * The staff member who took this payment.
     *
     * @return BelongsTo<User, $this>
     */
    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }
}
