<?php

declare(strict_types=1);

namespace App\Livewire\Extras;

use App\Domain\Tenancy\TenantContext;
use App\Models\ExtraService;
use App\Support\Money;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Extra services')]
class Services extends Component
{
    public ?int $editingId = null;

    public string $name = '';

    public string $description = '';

    public string $price = '';

    public bool $is_active = true;

    public function mount(): void
    {
        $this->authorizeManagement();
    }

    /**
     * @return Collection<int, ExtraService>
     */
    #[Computed]
    public function services(): Collection
    {
        return ExtraService::query()->orderBy('name')->get();
    }

    public function openCreate(): void
    {
        $this->reset(['editingId', 'name', 'description', 'price']);
        $this->is_active = true;
        $this->resetValidation();
        Flux::modal('extra-service-form')->show();
    }

    public function openEdit(int $id): void
    {
        $service = ExtraService::query()->findOrFail($id);
        $this->editingId = $service->id;
        $this->name = $service->name;
        $this->description = $service->description ?? '';
        $this->price = (string) $service->price->toMajor();
        $this->is_active = $service->is_active;
        $this->resetValidation();
        Flux::modal('extra-service-form')->show();
    }

    public function save(): void
    {
        $this->authorizeManagement();

        $this->validate([
            'name' => [
                'required', 'string', 'max:120',
                Rule::unique('extra_services', 'name')
                    ->where('hotel_id', app(TenantContext::class)->id())
                    ->ignore($this->editingId),
            ],
            'description' => ['nullable', 'string', 'max:500'],
            'price' => ['required', 'numeric', 'min:0'],
        ]);

        $attributes = [
            'name' => $this->name,
            'description' => $this->description ?: null,
            'price' => Money::fromMajor((float) $this->price),
            'is_active' => $this->is_active,
        ];

        if ($this->editingId) {
            ExtraService::query()->findOrFail($this->editingId)->update($attributes);
            Flux::toast(variant: 'success', text: __('Extra service updated.'));
        } else {
            ExtraService::query()->create($attributes);
            Flux::toast(variant: 'success', text: __('Extra service created.'));
        }

        Flux::modal('extra-service-form')->close();
        unset($this->services);
    }

    public function delete(int $id): void
    {
        $this->authorizeManagement();

        $service = ExtraService::query()->findOrFail($id);

        if ($service->orderItems()->exists()) {
            $service->update(['is_active' => false]);
            Flux::toast(text: __('This service has already been sold, so it was deactivated instead of deleted.'));
        } else {
            $service->delete();
            Flux::toast(variant: 'success', text: __('Extra service deleted.'));
        }

        unset($this->services);
    }

    public function render(): View
    {
        return view('livewire.extras.services');
    }

    private function authorizeManagement(): void
    {
        abort_unless(Auth::user()?->can('manage-hotel') ?? false, 403);
    }
}
