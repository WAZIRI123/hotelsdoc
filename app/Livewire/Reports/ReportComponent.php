<?php

declare(strict_types=1);

namespace App\Livewire\Reports;

use App\Domain\Reports\ReportCatalog;
use App\Domain\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Shared behaviour for the reports listed in ReportCatalog: an access check
 * against the catalog entry and a from/to period kept in the query string.
 */
abstract class ReportComponent extends Component
{
    #[Url]
    public string $from = '';

    #[Url]
    public string $to = '';

    /**
     * The ReportCatalog key whose permissions guard this page.
     */
    abstract protected function reportKey(): string;

    public function boot(): void
    {
        abort_unless(ReportCatalog::allows(Auth::user(), $this->reportKey()), 403);
    }

    public function mount(): void
    {
        if ($this->from === '') {
            $this->from = CarbonImmutable::today()->startOfMonth()->toDateString();
        }

        if ($this->to === '') {
            $this->to = CarbonImmutable::today()->toDateString();
        }
    }

    public function getReport(): void
    {
        $this->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
        ]);
    }

    /**
     * The start of the selected period, falling back to today while the input is invalid.
     */
    public function periodStart(): CarbonImmutable
    {
        try {
            return CarbonImmutable::parse($this->from)->startOfDay();
        } catch (InvalidFormatException) {
            return CarbonImmutable::today();
        }
    }

    /**
     * The end of the selected period, never before its start.
     */
    public function periodEnd(): CarbonImmutable
    {
        try {
            $end = CarbonImmutable::parse($this->to)->endOfDay();
        } catch (InvalidFormatException) {
            $end = $this->periodStart()->endOfDay();
        }

        return $end->max($this->periodStart()->endOfDay());
    }

    public function hotelCurrency(): string
    {
        return app(TenantContext::class)->current()->currency;
    }
}
