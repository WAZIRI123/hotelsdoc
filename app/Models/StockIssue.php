<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Database\Factories\StockIssueFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Stock taken off a product's stock on hand other than by a POS sale:
 * moved to another outlet's product, used internally, or written off.
 *
 * @property int $id
 * @property int $hotel_id
 * @property int $menu_item_id
 * @property int|null $to_menu_item_id
 * @property int|null $recorded_by
 * @property Carbon $issue_date
 * @property string $reason
 * @property string|null $issued_to
 * @property string|null $description
 * @property int $quantity
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class StockIssue extends Model
{
    /** @use HasFactory<StockIssueFactory> */
    use BelongsToTenant, HasFactory;

    public const REASON_TRANSFER = 'transfer';

    public const REASON_INTERNAL_USE = 'internal_use';

    public const REASON_WASTAGE = 'wastage';

    /** @var array<string, string> */
    public const REASONS = [
        self::REASON_TRANSFER => 'Transfer to another outlet',
        self::REASON_INTERNAL_USE => 'Internal use',
        self::REASON_WASTAGE => 'Wastage / damage',
    ];

    protected $fillable = [
        'hotel_id',
        'menu_item_id',
        'to_menu_item_id',
        'recorded_by',
        'issue_date',
        'reason',
        'issued_to',
        'description',
        'quantity',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
            'quantity' => 'integer',
        ];
    }

    public function reasonLabel(): string
    {
        return __(self::REASONS[$this->reason] ?? $this->reason);
    }

    /**
     * @return BelongsTo<MenuItem, $this>
     */
    public function menuItem(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class);
    }

    /**
     * The product that received the stock when it was transferred.
     *
     * @return BelongsTo<MenuItem, $this>
     */
    public function toMenuItem(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class, 'to_menu_item_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
