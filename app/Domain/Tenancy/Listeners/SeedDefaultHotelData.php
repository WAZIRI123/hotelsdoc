<?php

declare(strict_types=1);

namespace App\Domain\Tenancy\Listeners;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Hotel;
use App\Models\PaymentMethod;

/**
 * Runs on Hotel::created and stamps the new hotel with a sensible default
 * set of payment methods (cash + the manual mobile money / bank gateways
 * registered in config/integrations.php) and the default expense categories.
 *
 * Adding TRA VFD later: nothing to change here — that's a fiscal issuer, not
 * a payment method. Adding Selcom: keep the same 'mpesa' / 'tigopesa' codes,
 * just swap the gateway class in config; existing PaymentMethod rows still match.
 */
final class SeedDefaultHotelData
{
    /**
     * @var list<array{code: string, label: string, sort_order: int}>
     */
    private const DEFAULT_METHODS = [
        ['code' => 'cash', 'label' => 'Cash', 'sort_order' => 10],
        ['code' => 'mpesa', 'label' => 'M-Pesa', 'sort_order' => 20],
        ['code' => 'tigopesa', 'label' => 'Tigo Pesa', 'sort_order' => 30],
        ['code' => 'airtelmoney', 'label' => 'Airtel Money', 'sort_order' => 40],
        ['code' => 'halopesa', 'label' => 'HaloPesa', 'sort_order' => 50],
        ['code' => 'crdb', 'label' => 'CRDB Bank', 'sort_order' => 60],
        ['code' => 'nmb', 'label' => 'NMB Bank', 'sort_order' => 70],
    ];

    public function handle(Hotel $hotel): void
    {
        foreach (self::DEFAULT_METHODS as $method) {
            PaymentMethod::query()->updateOrCreate(
                ['hotel_id' => $hotel->id, 'code' => $method['code']],
                ['label' => $method['label'], 'sort_order' => $method['sort_order'], 'is_active' => true],
            );
        }

        foreach (Expense::CATEGORIES as $category) {
            ExpenseCategory::query()->firstOrCreate(['hotel_id' => $hotel->id, 'name' => $category]);
        }
    }
}
