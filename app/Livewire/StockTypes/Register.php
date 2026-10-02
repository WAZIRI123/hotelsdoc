<?php

declare(strict_types=1);

namespace App\Livewire\StockTypes;

use App\Domain\Tenancy\TenantContext;
use App\Models\StockType;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Add Stock Type')]
class Register extends Component
{
    public ?StockType $stockType = null;

    /** The Manage Stock outlet the user came from, for the back link. */
    #[Url(as: 'outlet')]
    public ?int $outletId = null;

    public string $name = '';

    public string $description = '';

    public function mount(?StockType $stockType = null): void
    {
        $this->authorizeManagement();

        if ($stockType?->exists) {
            $this->stockType = $stockType;
            $this->name = $stockType->name;
            $this->description = $stockType->description ?? '';
        }
    }

    public function save(): void
    {
        $this->authorizeManagement();

        $this->validate([
            'name' => [
                'required', 'string', 'max:80',
                Rule::unique('stock_types', 'name')->where('hotel_id', app(TenantContext::class)->id())->ignore($this->stockType?->id),
            ],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        $attributes = [
            'name' => trim($this->name),
            'description' => $this->description ?: null,
        ];

        if ($this->stockType) {
            $this->stockType->update($attributes);
            Flux::toast(variant: 'success', text: __('Stock type :name updated.', ['name' => $this->stockType->name]));
        } else {
            $stockType = StockType::query()->create($attributes);
            Flux::toast(variant: 'success', text: __('Stock type :name added.', ['name' => $stockType->name]));
        }

        $this->redirectRoute('stock-types.index', array_filter(['outlet' => $this->outletId]), navigate: true);
    }

    public function render(): View
    {
        return view('livewire.stock-types.register');
    }

    private function authorizeManagement(): void
    {
        abort_unless(Auth::user()?->can('manage-stock') ?? false, 403);
    }
}
