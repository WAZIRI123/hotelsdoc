<?php

declare(strict_types=1);

namespace App\Livewire\ExpenseCategories;

use App\Models\ExpenseCategory;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('View Expenses Category')]
class Index extends Component
{
    public function mount(): void
    {
        $this->authorizeManagement();
    }

    /**
     * @return Collection<int, ExpenseCategory>
     */
    #[Computed]
    public function categories(): Collection
    {
        return ExpenseCategory::query()
            ->withCount('expenses')
            ->orderBy('name')
            ->get();
    }

    public function delete(int $id): void
    {
        $this->authorizeManagement();

        $category = ExpenseCategory::query()->withCount('expenses')->findOrFail($id);

        if ($category->expenses_count > 0) {
            Flux::toast(variant: 'danger', text: __('":name" is used on recorded expenses and cannot be deleted.', ['name' => $category->name]));

            return;
        }

        $category->delete();

        Flux::toast(variant: 'success', text: __('Expense category deleted.'));
        unset($this->categories);
    }

    public function render(): View
    {
        return view('livewire.expense-categories.index');
    }

    private function authorizeManagement(): void
    {
        abort_unless(Auth::user()?->can('manage-payments') ?? false, 403);
    }
}
