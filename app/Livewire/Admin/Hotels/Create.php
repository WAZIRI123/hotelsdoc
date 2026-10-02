<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Hotels;

use App\Domain\Tenancy\Actions\ProvisionHotel;
use App\Models\User;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

#[Title('New hotel')]
class Create extends Component
{
    use WithFileUploads;

    /** @var list<string> */
    public const CURRENCIES = ['TZS', 'USD', 'KES', 'UGX', 'RWF', 'EUR', 'GBP'];

    public string $name = '';

    public string $slug = '';

    public string $currency = 'TZS';

    public string $timezone = 'Africa/Dar_es_Salaam';

    public string $phone = '';

    public string $fax = '';

    public string $email = '';

    public string $address = '';

    public string $street = '';

    public ?TemporaryUploadedFile $logo = null;

    public string $tin = '';

    public string $vrn = '';

    public string $adminName = '';

    public string $adminEmail = '';

    public string $adminPassword = '';

    public bool $slugTouched = false;

    public function mount(): void
    {
        $this->guardSuperAdmin();
    }

    public function updatedName(): void
    {
        if (! $this->slugTouched) {
            $this->slug = Str::slug($this->name);
        }
    }

    public function updatedSlug(): void
    {
        $this->slugTouched = true;
        $this->slug = Str::slug($this->slug);
    }

    /**
     * True when the admin email already belongs to a user, who will be
     * attached to the new hotel instead of creating a new account.
     */
    #[Computed]
    public function adminExists(): bool
    {
        return $this->adminEmail !== ''
            && User::query()->where('email', $this->adminEmail)->exists();
    }

    /**
     * @return list<string>
     */
    #[Computed]
    public function timezones(): array
    {
        return timezone_identifiers_list();
    }

    public function save(ProvisionHotel $provisionHotel): void
    {
        $this->guardSuperAdmin();
        $adminExists = $this->adminExists();

        $validated = $this->validate([
            'name' => 'required|string|max:120',
            'slug' => 'required|alpha_dash:ascii|max:80|unique:hotels,slug',
            'currency' => 'required|in:'.implode(',', self::CURRENCIES),
            'timezone' => 'required|timezone:all',
            'phone' => 'nullable|string|max:32',
            'fax' => 'nullable|string|max:32',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string|max:255',
            'street' => 'nullable|string|max:255',
            'logo' => 'nullable|image|max:2048',
            'tin' => 'nullable|string|max:32',
            'vrn' => 'nullable|string|max:32',
            'adminName' => $adminExists ? 'nullable|string|max:120' : 'required|string|max:120',
            'adminEmail' => 'required|email|max:255',
            'adminPassword' => $adminExists ? 'nullable' : 'required|string|min:8|max:120',
        ]);

        $hotel = $provisionHotel->execute(
            [
                'name' => $validated['name'],
                'slug' => $validated['slug'],
                'currency' => $validated['currency'],
                'timezone' => $validated['timezone'],
                'phone' => $validated['phone'] ?: null,
                'fax' => $validated['fax'] ?: null,
                'email' => $validated['email'] ?: null,
                'address' => $validated['address'] ?: null,
                'street' => $validated['street'] ?: null,
                'tin' => $validated['tin'] ?: null,
                'vrn' => $validated['vrn'] ?: null,
            ],
            [
                'name' => $validated['adminName'] ?? '',
                'email' => $validated['adminEmail'],
                'password' => $validated['adminPassword'] ?: null,
            ],
        );

        $hotel->forceFill([
            'approved_by' => Auth::id(),
            'logo_path' => $this->logo?->store('hotel-logos', 'public'),
        ])->save();

        Flux::toast(variant: 'success', text: __('Hotel :name created.', ['name' => $hotel->name]));

        $this->redirectRoute('admin.hotels.show', ['hotel' => $hotel->id], navigate: true);
    }

    private function guardSuperAdmin(): void
    {
        abort_unless(Auth::user()?->isSuperAdmin() ?? false, 403);
    }

    public function render(): View
    {
        return view('livewire.admin.hotels.create');
    }
}
