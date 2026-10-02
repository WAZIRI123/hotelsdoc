<?php

declare(strict_types=1);

namespace App\Livewire\Reports;

use Livewire\Attributes\Title;

#[Title('Drinks Sales Report')]
class DrinksSales extends PosSales
{
    public function outletTypes(): array
    {
        return ['bar'];
    }

    public function heading(): string
    {
        return __('Drinks Sales Report');
    }

    public function icon(): string
    {
        return 'beaker';
    }

    protected function reportKey(): string
    {
        return 'drinks';
    }
}
