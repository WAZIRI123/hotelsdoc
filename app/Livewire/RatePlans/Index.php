<?php

declare(strict_types=1);

namespace App\Livewire\RatePlans;

use App\Domain\Tenancy\TenantContext;
use App\Models\MealPlan;
use App\Models\RatePlan;
use App\Models\RoomType;
use App\Support\Money;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Title('Add rates')]
class Index extends Component
{
    public ?int $editingId = null;

    public ?int $room_type_id = null;

    public ?int $meal_plan_id = null;

    #[Validate('required|string|max:120')]
    public string $name = '';

    /** Prices in whole TSh (no decimals). */
    #[Validate('required|integer|min:0')]
    public int $price_tsh = 0;

    #[Validate('required|integer|min:0')]
    public int $extra_adult_price_tsh = 0;

    #[Validate('required|integer|min:0')]
    public int $extra_child_price_tsh = 0;

    #[Validate('nullable|date')]
    public ?string $valid_from = null;

    #[Validate('nullable|date')]
    public ?string $valid_to = null;

    public bool $is_active = true;

    public bool $is_agent_rate = false;

    /**
     * @return Collection<int, RatePlan>
     */
    #[Computed]
    public function ratePlans(): Collection
    {
        return RatePlan::query()
            ->with(['roomType', 'mealPlan'])
            ->orderBy('name')
            ->get();
    }

    /**
     * @return Collection<int, RoomType>
     */
    #[Computed]
    public function roomTypes(): Collection
    {
        return RoomType::query()->orderBy('name')->get();
    }

    /**
     * @return Collection<int, MealPlan>
     */
    #[Computed]
    public function mealPlans(): Collection
    {
        return MealPlan::query()->where('is_active', true)->orderBy('code')->get();
    }

    public function openCreate(): void
    {
        $this->reset(['editingId', 'name', 'valid_from', 'valid_to']);
        $this->room_type_id = null;
        $this->meal_plan_id = null;
        $this->price_tsh = 0;
        $this->extra_adult_price_tsh = 0;
        $this->extra_child_price_tsh = 0;
        $this->is_active = true;
        $this->is_agent_rate = false;
        $this->resetValidation();
        Flux::modal('rate-plan-form')->show();
    }

    public function openEdit(int $id): void
    {
        $plan = RatePlan::query()->findOrFail($id);
        $this->editingId = $plan->id;
        $this->room_type_id = $plan->room_type_id;
        $this->meal_plan_id = $plan->meal_plan_id;
        $this->name = $plan->name;
        $this->price_tsh = (int) $plan->price->toMajor();
        $this->extra_adult_price_tsh = (int) $plan->extra_adult_price->toMajor();
        $this->extra_child_price_tsh = (int) $plan->extra_child_price->toMajor();
        $this->valid_from = $plan->valid_from?->toDateString();
        $this->valid_to = $plan->valid_to?->toDateString();
        $this->is_active = $plan->is_active;
        $this->is_agent_rate = $plan->is_agent_rate;
        $this->resetValidation();
        Flux::modal('rate-plan-form')->show();
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function rules(): array
    {
        $hotelId = app(TenantContext::class)->id();

        return [
            'room_type_id' => ['required', 'integer', Rule::exists('room_types', 'id')->where('hotel_id', $hotelId)],
            'meal_plan_id' => ['nullable', 'integer', Rule::exists('meal_plans', 'id')->where('hotel_id', $hotelId)],
        ];
    }

    public function save(): void
    {
        $this->validate();

        $attributes = [
            'room_type_id' => $this->room_type_id,
            'meal_plan_id' => $this->meal_plan_id ?: null,
            'name' => $this->name,
            'price' => Money::fromMajor($this->price_tsh),
            'extra_adult_price' => Money::fromMajor($this->extra_adult_price_tsh),
            'extra_child_price' => Money::fromMajor($this->extra_child_price_tsh),
            'valid_from' => $this->valid_from ?: null,
            'valid_to' => $this->valid_to ?: null,
            'is_active' => $this->is_active,
            'is_agent_rate' => $this->is_agent_rate,
        ];

        if ($this->editingId) {
            RatePlan::query()->findOrFail($this->editingId)->update($attributes);
            Flux::toast(variant: 'success', text: 'Rate plan updated.');
        } else {
            RatePlan::query()->create($attributes);
            Flux::toast(variant: 'success', text: 'Rate plan created.');
        }

        Flux::modal('rate-plan-form')->close();
        unset($this->ratePlans);
    }

    public function delete(int $id): void
    {
        $ratePlan = RatePlan::query()->findOrFail($id);

        if ($ratePlan->isInUse()) {
            $ratePlan->update(['is_active' => false]);
            Flux::toast(text: __('Bookings are priced on this rate plan, so it was deactivated instead of deleted.'));
        } else {
            $ratePlan->delete();
            Flux::toast(variant: 'success', text: 'Rate plan deleted.');
        }
        unset($this->ratePlans);
    }

    public function render(): View
    {
        return view('livewire.rate-plans.index');
    }
}
