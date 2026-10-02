<?php

declare(strict_types=1);

namespace App\Livewire\Reports;

use Livewire\Attributes\Title;

#[Title('Food Sales Report')]
class FoodSales extends PosSales
{
    public function outletTypes(): array
    {
        return ['restaurant', 'room_service'];
    }

    public function heading(): string
    {
        return __('Food Sales Report');
    }

    public function icon(): string
    {
        return 'cake';
    }

    protected function reportKey(): string
    {
        return 'food';
    }
}
