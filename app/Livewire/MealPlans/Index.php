<?php

declare(strict_types=1);

namespace App\Livewire\MealPlans;

use App\Domain\Tenancy\TenantContext;
use App\Models\MealPlan;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Title('Meal plans')]
class Index extends Component
{
    public ?int $editingId = null;

    #[Validate('required|string|max:10')]
    public string $code = '';

    #[Validate('required|string|max:120')]
    public string $name = '';

    #[Validate('nullable|string|max:500')]
    public string $description = '';

    public bool $includes_breakfast = false;

    public bool $includes_lunch = false;

    public bool $includes_dinner = false;

    public bool $is_active = true;

    /**
     * @return Collection<int, MealPlan>
     */
    #[Computed]
    public function mealPlans(): Collection
    {
        return MealPlan::query()->orderBy('code')->get();
    }

    public function openCreate(): void
    {
        $this->reset(['editingId', 'code', 'name', 'description']);
        $this->includes_breakfast = false;
        $this->includes_lunch = false;
        $this->includes_dinner = false;
        $this->is_active = true;
        $this->resetValidation();
        Flux::modal('meal-plan-form')->show();
    }

    public function openEdit(int $id): void
    {
        $plan = MealPlan::query()->findOrFail($id);
        $this->editingId = $plan->id;
        $this->code = $plan->code;
        $this->name = $plan->name;
        $this->description = $plan->description ?? '';
        $this->includes_breakfast = $plan->includes_breakfast;
        $this->includes_lunch = $plan->includes_lunch;
        $this->includes_dinner = $plan->includes_dinner;
        $this->is_active = $plan->is_active;
        $this->resetValidation();
        Flux::modal('meal-plan-form')->show();
    }

    public function save(): void
    {
        $this->validate([
            'code' => [
                'required', 'string', 'max:10',
                Rule::unique('meal_plans', 'code')
                    ->where('hotel_id', app(TenantContext::class)->id())
                    ->ignore($this->editingId),
            ],
        ]);

        $attributes = [
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description ?: null,
            'includes_breakfast' => $this->includes_breakfast,
            'includes_lunch' => $this->includes_lunch,
            'includes_dinner' => $this->includes_dinner,
            'is_active' => $this->is_active,
        ];

        if ($this->editingId) {
            MealPlan::query()->findOrFail($this->editingId)->update($attributes);
            Flux::toast(variant: 'success', text: 'Meal plan updated.');
        } else {
            MealPlan::query()->create($attributes);
            Flux::toast(variant: 'success', text: 'Meal plan created.');
        }

        Flux::modal('meal-plan-form')->close();
        unset($this->mealPlans);
    }

    public function delete(int $id): void
    {
        MealPlan::query()->findOrFail($id)->delete();
        Flux::toast(variant: 'success', text: 'Meal plan deleted.');
        unset($this->mealPlans);
    }

    public function render(): View
    {
        return view('livewire.meal-plans.index');
    }
}
