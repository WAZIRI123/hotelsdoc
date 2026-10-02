<?php

declare(strict_types=1);

namespace App\Domain\Reports;

use App\Models\User;

/**
 * Every report the Get Reports hub offers, and who may open it.
 *
 * A user can open a report when they hold any of its permissions, so the
 * same list drives both the hub tiles and each report page's access check.
 */
final class ReportCatalog
{
    /**
     * @var array<string, array{label: string, icon: string, color: string, permissions: list<string>, route: string, params: array<string, string>}>
     */
    public const REPORTS = [
        'reservations' => ['label' => 'Reservations Report', 'icon' => 'calendar-days', 'color' => 'bg-[#008000]', 'permissions' => ['view-reports', 'manage-reservations'], 'route' => 'reports.cash-up', 'params' => ['view' => 'reservations']],
        'drinks' => ['label' => 'Drinks Sales Report', 'icon' => 'beaker', 'color' => 'bg-[#F4A460]', 'permissions' => ['view-reports', 'manage-payments'], 'route' => 'reports.drinks-sales', 'params' => []],
        'food' => ['label' => 'Food Sales Report', 'icon' => 'cake', 'color' => 'bg-[#5F9EA0]', 'permissions' => ['view-reports', 'manage-payments'], 'route' => 'reports.food-sales', 'params' => []],
        'extras' => ['label' => 'Extras Sales Report', 'icon' => 'cube', 'color' => 'bg-[#BC8F8F]', 'permissions' => ['view-reports', 'manage-folios'], 'route' => 'reports.extras-sales', 'params' => []],
        'stock' => ['label' => 'Stock Products Report', 'icon' => 'archive-box', 'color' => 'bg-[#A52A2A]', 'permissions' => ['view-reports', 'manage-menu'], 'route' => 'reports.stock', 'params' => []],
        'expenses' => ['label' => 'Expenses Report', 'icon' => 'plus', 'color' => 'bg-[#DAA520]', 'permissions' => ['view-reports'], 'route' => 'reports.expenses', 'params' => []],
        'payments' => ['label' => 'Payments Report', 'icon' => 'banknotes', 'color' => 'bg-[#2E8B57]', 'permissions' => ['view-reports', 'manage-payments'], 'route' => 'reports.cash-up', 'params' => ['view' => 'general']],
        'credit' => ['label' => 'Credit Report', 'icon' => 'document-text', 'color' => 'bg-[#D2691E]', 'permissions' => ['view-reports', 'manage-folios'], 'route' => 'reports.credit', 'params' => []],
        'agents' => ['label' => 'Agents Report', 'icon' => 'briefcase', 'color' => 'bg-[#4682B4]', 'permissions' => ['view-reports', 'manage-reservations'], 'route' => 'reports.agents', 'params' => []],
        'profit-loss' => ['label' => 'Profit & Loss Report', 'icon' => 'arrows-right-left', 'color' => 'bg-[#FF6347]', 'permissions' => ['view-reports'], 'route' => 'reports.profit-loss', 'params' => []],
    ];

    public static function allows(?User $user, string $report): bool
    {
        $permissions = self::REPORTS[$report]['permissions'] ?? null;

        return $user !== null && $permissions !== null && $user->canAny($permissions);
    }

    /**
     * @return array<string, array{label: string, icon: string, color: string, permissions: list<string>, route: string, params: array<string, string>}>
     */
    public static function visibleTo(?User $user): array
    {
        return array_filter(
            self::REPORTS,
            fn (string $report): bool => self::allows($user, $report),
            ARRAY_FILTER_USE_KEY,
        );
    }
}
