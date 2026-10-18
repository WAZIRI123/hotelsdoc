<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Tenancy\Concerns\BelongsToTenant;
use App\Support\Money;
use Database\Factories\ExpenseFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Money the hotel paid out (utilities, salaries, supplies…).
 *
 * The amount is integer minor units in the expense's own currency, so it is
 * not cast to Money (MoneyCast is fixed to one currency).
 *
 * Expenses are never deleted: one recorded by mistake is voided, which keeps
 * it on record but takes it out of every report total.
 *
 * @property int $id
 * @property int $hotel_id
 * @property int|null $recorded_by
 * @property Carbon $expense_date
 * @property string|null $pv_number
 * @property string $category
 * @property string $description
 * @property string|null $quantity
 * @property string|null $payee
 * @property string|null $method_code
 * @property string $currency
 * @property int $amount
 * @property Carbon|null $voided_at
 * @property int|null $voided_by
 * @property string|null $void_reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Expense extends Model
{
    /** @use HasFactory<ExpenseFactory> */
    use BelongsToTenant, HasFactory;

    /**
     * Expense categories every hotel starts with; hotels manage their own
     * list as ExpenseCategory rows.
     *
     * @var list<string>
     */
    public const CATEGORIES = [
        'Salaries & Wages',
        'Utilities',
        'Food & Beverage Purchases',
        'Supplies',
        'Repairs & Maintenance',
        'Transport',
        'Marketing',
        'Rent',
        'Taxes & Licences',
        'Other',
    ];

    protected $fillable = [
        'hotel_id',
        'recorded_by',
        'expense_date',
        'pv_number',
        'category',
        'description',
        'quantity',
        'payee',
        'method_code',
        'currency',
        'amount',
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
            'expense_date' => 'date',
            'quantity' => 'decimal:2',
            'amount' => 'integer',
            'voided_at' => 'datetime',
        ];
    }

    public function isVoided(): bool
    {
        return $this->voided_at !== null;
    }

    public function money(): Money
    {
        return new Money($this->amount, $this->currency);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function voider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    /**
     * Expenses that count towards the books (not voided).
     *
     * @param  Builder<Expense>  $query
     */
    #[Scope]
    protected function unvoided(Builder $query): void
    {
        $query->whereNull('expenses.voided_at');
    }
}
