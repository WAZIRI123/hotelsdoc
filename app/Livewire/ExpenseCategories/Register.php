<?php

declare(strict_types=1);

namespace App\Livewire\ExpenseCategories;

use App\Domain\Tenancy\TenantContext;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Add Expenses Category')]
class Register extends Component
{
    public ?ExpenseCategory $category = null;

    public string $name = '';

    public string $description = '';

    public function mount(?ExpenseCategory $category = null): void
    {
        $this->authorizeManagement();

        if ($category?->exists) {
            $this->category = $category;
            $this->name = $category->name;
            $this->description = $category->description ?? '';
        }
    }

    public function save(): void
    {
        $this->authorizeManagement();

        $this->name = trim($this->name);

        $this->validate([
            'name' => [
                'required', 'string', 'max:80',
                Rule::unique('expense_categories', 'name')->where('hotel_id', app(TenantContext::class)->id())->ignore($this->category?->id),
            ],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        $attributes = [
            'name' => $this->name,
            'description' => $this->description ?: null,
        ];

        if ($this->category) {
            $previousName = $this->category->name;

            DB::transaction(function () use ($attributes, $previousName): void {
                $this->category->update($attributes);

                if ($previousName !== $this->category->name) {
                    Expense::query()->where('category', $previousName)->update(['category' => $this->category->name]);
                }
            });

            Flux::toast(variant: 'success', text: __('Expense category :name updated.', ['name' => $this->category->name]));
        } else {
            $category = ExpenseCategory::query()->create($attributes);
            Flux::toast(variant: 'success', text: __('Expense category :name added.', ['name' => $category->name]));
        }

        $this->redirectRoute('expense-categories.index', navigate: true);
    }

    public function render(): View
    {
        return view('livewire.expense-categories.register');
    }

    private function authorizeManagement(): void
    {
        abort_unless(Auth::user()?->can('manage-payments') ?? false, 403);
    }
}
