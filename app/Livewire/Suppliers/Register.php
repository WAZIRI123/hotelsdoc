<?php

declare(strict_types=1);

namespace App\Livewire\Suppliers;

use App\Domain\Tenancy\TenantContext;
use App\Models\Supplier;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Add Supplier')]
class Register extends Component
{
    public ?Supplier $supplier = null;

    /** The Manage Stock outlet the user came from, for the back link. */
    #[Url(as: 'outlet')]
    public ?int $outletId = null;

    public string $name = '';

    public string $contact_person = '';

    public string $phone = '';

    public string $email = '';

    public string $tin = '';

    public string $address = '';

    public bool $is_active = true;

    public function mount(?Supplier $supplier = null): void
    {
        $this->authorizeManagement();

        if ($supplier?->exists) {
            $this->supplier = $supplier;
            $this->name = $supplier->name;
            $this->contact_person = $supplier->contact_person ?? '';
            $this->phone = $supplier->phone ?? '';
            $this->email = $supplier->email ?? '';
            $this->tin = $supplier->tin ?? '';
            $this->address = $supplier->address ?? '';
            $this->is_active = $supplier->is_active;
        }
    }

    public function save(): void
    {
        $this->authorizeManagement();

        $this->validate([
            'name' => [
                'required', 'string', 'max:160',
                Rule::unique('suppliers', 'name')->where('hotel_id', app(TenantContext::class)->id())->ignore($this->supplier?->id),
            ],
            'contact_person' => ['nullable', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:32'],
            'email' => ['nullable', 'email', 'max:120'],
            'tin' => ['nullable', 'string', 'max:32'],
            'address' => ['nullable', 'string', 'max:500'],
        ]);

        $attributes = [
            'name' => trim($this->name),
            'contact_person' => $this->contact_person ?: null,
            'phone' => $this->phone ?: null,
            'email' => $this->email ?: null,
            'tin' => $this->tin ?: null,
            'address' => $this->address ?: null,
            'is_active' => $this->is_active,
        ];

        if ($this->supplier) {
            $this->supplier->update($attributes);
            Flux::toast(variant: 'success', text: __('Supplier :name updated.', ['name' => $this->supplier->name]));
        } else {
            $supplier = Supplier::query()->create($attributes);
            Flux::toast(variant: 'success', text: __('Supplier :name added.', ['name' => $supplier->name]));
        }

        $this->redirectRoute('suppliers.index', array_filter(['outlet' => $this->outletId]), navigate: true);
    }

    public function render(): View
    {
        return view('livewire.suppliers.register');
    }

    private function authorizeManagement(): void
    {
        abort_unless(Auth::user()?->can('manage-stock') ?? false, 403);
    }
}
