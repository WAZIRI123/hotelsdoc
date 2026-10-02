<?php

declare(strict_types=1);

namespace App\Livewire\Reports;

use App\Domain\Reports\ReportCatalog;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Get Reports')]
class Index extends Component
{
    /**
     * @return array<string, array{label: string, icon: string, color: string, permissions: list<string>, route: string, params: array<string, string>}>
     */
    #[Computed]
    public function reports(): array
    {
        return ReportCatalog::visibleTo(Auth::user());
    }

    public function roleTitle(): string
    {
        $role = Auth::user()?->getRoleNames()->first() ?? 'staff';

        return ucwords(str_replace('_', ' ', $role));
    }

    public function render(): View
    {
        return view('livewire.reports.index');
    }
}
