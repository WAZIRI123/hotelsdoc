<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Hotels;

use App\Domain\Authorization\Listeners\SeedHotelRoles;
use App\Domain\Authorization\RolePermissionCatalog;
use App\Domain\Tenancy\Actions\DeleteHotel;
use App\Domain\Tenancy\Listeners\SeedDefaultHotelData;
use App\Models\Hotel;
use App\Models\MealPlan;
use App\Models\Outlet;
use App\Models\PaymentMethod;
use App\Models\RatePlan;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Spatie\Permission\PermissionRegistrar;

class Show extends Component
{
    use WithFileUploads;

    public Hotel $hotel;

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

    public ?int $editingUserId = null;

    public string $userName = '';

    public string $userEmail = '';

    public string $userPassword = '';

    public string $userRole = 'admin';

    public string $deleteConfirmation = '';

    public function mount(Hotel $hotel): void
    {
        $this->guardSuperAdmin();
        $this->hotel = $hotel;
        $this->fillDetails();
    }

    /**
     * @return Collection<int, User>
     */
    #[Computed]
    public function users(): Collection
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->hotel->id);

        return $this->hotel->users()
            ->with('roles')
            ->orderBy('name')
            ->get();
    }

    /**
     * Counts of the records a hotel needs before it can take bookings.
     * Queried by hotel_id explicitly: no TenantContext is set in admin.
     *
     * @return list<array{label: string, count: int, hint: string}>
     */
    #[Computed]
    public function setupChecklist(): array
    {
        $hotelId = $this->hotel->id;

        return [
            ['label' => __('Users'), 'count' => $this->hotel->users()->count(), 'hint' => __('At least one admin who can sign in.')],
            ['label' => __('Payment methods'), 'count' => PaymentMethod::query()->where('hotel_id', $hotelId)->count(), 'hint' => __('Seeded automatically on creation.')],
            ['label' => __('Room types'), 'count' => RoomType::query()->where('hotel_id', $hotelId)->count(), 'hint' => __('Set up by the hotel admin under Inventory.')],
            ['label' => __('Rooms'), 'count' => Room::query()->where('hotel_id', $hotelId)->count(), 'hint' => __('Set up by the hotel admin under Inventory.')],
            ['label' => __('Meal plans'), 'count' => MealPlan::query()->where('hotel_id', $hotelId)->count(), 'hint' => __('Optional.')],
            ['label' => __('Rate plans'), 'count' => RatePlan::query()->where('hotel_id', $hotelId)->count(), 'hint' => __('Needed to price reservations.')],
            ['label' => __('Outlets'), 'count' => Outlet::query()->where('hotel_id', $hotelId)->count(), 'hint' => __('Optional: bar / restaurant POS.')],
        ];
    }

    /**
     * @return list<string>
     */
    #[Computed]
    public function availableRoles(): array
    {
        return RolePermissionCatalog::roles();
    }

    /**
     * @return list<string>
     */
    #[Computed]
    public function timezones(): array
    {
        return timezone_identifiers_list();
    }

    /**
     * Every amount a hotel stores is in its currency, so once money has been
     * recorded, changing the currency would silently relabel all of it.
     */
    #[Computed]
    public function currencyIsLocked(): bool
    {
        return collect(['folios', 'pos_orders', 'extra_orders', 'expenses'])
            ->contains(fn (string $table): bool => DB::table($table)->where('hotel_id', $this->hotel->id)->exists());
    }

    public function saveDetails(): void
    {
        $this->guardSuperAdmin();

        if ($this->currency !== $this->hotel->currency && $this->currencyIsLocked()) {
            $this->addError('currency', __('The currency cannot be changed once the hotel has recorded sales or expenses.'));

            return;
        }

        $validated = $this->validate([
            'name' => 'required|string|max:120',
            'slug' => ['required', 'alpha_dash:ascii', 'max:80', Rule::unique('hotels', 'slug')->ignore($this->hotel->id)],
            'currency' => 'required|in:'.implode(',', Create::CURRENCIES),
            'timezone' => 'required|timezone:all',
            'phone' => 'nullable|string|max:32',
            'fax' => 'nullable|string|max:32',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string|max:255',
            'street' => 'nullable|string|max:255',
            'tin' => 'nullable|string|max:32',
            'vrn' => 'nullable|string|max:32',
            'logo' => 'nullable|image|max:2048',
        ]);
        unset($validated['logo']);

        $this->hotel->fill(array_map(
            fn (string $value): ?string => $value === '' ? null : $value,
            $validated,
        ));
        $this->hotel->forceFill(['edited_by' => Auth::id()]);

        if ($this->logo !== null) {
            $previousLogo = $this->hotel->logo_path;
            $this->hotel->forceFill(['logo_path' => $this->logo->store('hotel-logos', 'public')]);

            if ($previousLogo !== null) {
                Storage::disk('public')->delete($previousLogo);
            }

            $this->logo = null;
        }

        $this->hotel->save();

        Flux::toast(variant: 'success', text: __('Hotel details saved.'));
    }

    /**
     * Re-runs the creation listeners. Both are idempotent, so this repairs a
     * hotel created before a new permission or payment method was added.
     */
    public function resyncDefaults(): void
    {
        $this->guardSuperAdmin();

        app(SeedDefaultHotelData::class)->handle($this->hotel);
        app(SeedHotelRoles::class)->handle($this->hotel);

        unset($this->setupChecklist);
        Flux::toast(variant: 'success', text: __('Default roles and payment methods synced.'));
    }

    public function openCreateUser(): void
    {
        $this->guardSuperAdmin();
        $this->reset(['editingUserId', 'userName', 'userEmail', 'userPassword']);
        $this->userRole = 'admin';
        $this->resetValidation();
        Flux::modal('hotel-user-form')->show();
    }

    public function openEditUser(int $id): void
    {
        $this->guardSuperAdmin();
        $user = $this->hotel->users()->where('users.id', $id)->firstOrFail();

        app(PermissionRegistrar::class)->setPermissionsTeamId($this->hotel->id);

        $this->editingUserId = $user->id;
        $this->userName = $user->name;
        $this->userEmail = $user->email;
        $this->userPassword = '';
        $this->userRole = $user->roles()->value('name') ?? 'staff';
        $this->resetValidation();
        Flux::modal('hotel-user-form')->show();
    }

    /**
     * Adds (or updates) a user of this hotel. An email that already exists
     * is attached to this hotel rather than rejected, so one person can
     * work across several hotels.
     */
    public function saveUser(): void
    {
        $this->guardSuperAdmin();

        $existing = $this->editingUserId === null
            ? User::query()->where('email', $this->userEmail)->first()
            : null;

        $this->validate([
            'userName' => 'required|string|max:120',
            'userEmail' => $this->editingUserId === null
                ? 'required|email|max:255'
                : 'required|email|max:255|unique:users,email,'.$this->editingUserId,
            'userPassword' => $this->editingUserId !== null || $existing !== null
                ? 'nullable|string|min:8|max:120'
                : 'required|string|min:8|max:120',
            'userRole' => 'required|in:'.implode(',', RolePermissionCatalog::roles()),
        ]);

        if ($this->editingUserId !== null) {
            $user = $this->hotel->users()->where('users.id', $this->editingUserId)->firstOrFail();
            $user->update(['name' => $this->userName, 'email' => $this->userEmail]);
            if ($this->userPassword !== '') {
                $user->update(['password' => Hash::make($this->userPassword)]);
            }
        } elseif ($existing !== null) {
            $user = $existing;
        } else {
            $user = User::query()->create([
                'name' => $this->userName,
                'email' => $this->userEmail,
                'password' => Hash::make($this->userPassword),
            ]);
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        $this->hotel->users()->syncWithoutDetaching([$user->id]);

        app(PermissionRegistrar::class)->setPermissionsTeamId($this->hotel->id);
        $user->unsetRelation('roles');
        $user->syncRoles([$this->userRole]);

        Flux::toast(variant: 'success', text: $this->editingUserId !== null ? __('User updated.') : __('User added to hotel.'));
        Flux::modal('hotel-user-form')->close();
        unset($this->users, $this->setupChecklist);
    }

    public function removeUser(int $id): void
    {
        $this->guardSuperAdmin();
        $user = $this->hotel->users()->where('users.id', $id)->firstOrFail();

        app(PermissionRegistrar::class)->setPermissionsTeamId($this->hotel->id);
        $user->syncRoles([]);
        $this->hotel->users()->detach($user->id);

        Flux::toast(variant: 'success', text: __('User removed from this hotel.'));
        unset($this->users, $this->setupChecklist);
    }

    /**
     * Blocks the hotel's staff from signing in to it; data is untouched.
     */
    public function suspend(): void
    {
        $this->guardSuperAdmin();
        $this->hotel->forceFill(['suspended_at' => now()])->save();

        Flux::toast(variant: 'warning', text: __('Hotel suspended. Its staff can no longer sign in to it.'));
    }

    public function reactivate(): void
    {
        $this->guardSuperAdmin();
        $this->hotel->forceFill(['suspended_at' => null])->save();

        Flux::toast(variant: 'success', text: __('Hotel reactivated.'));
    }

    public function deleteHotel(DeleteHotel $deleteHotel): void
    {
        $this->guardSuperAdmin();

        if (! $this->hotel->isSuspended()) {
            $this->addError('deleteConfirmation', __('Suspend the hotel before deleting it.'));

            return;
        }

        if ($this->deleteConfirmation !== $this->hotel->slug) {
            $this->addError('deleteConfirmation', __('Type the hotel slug exactly to confirm.'));

            return;
        }

        $name = $this->hotel->name;
        $deleteHotel->execute($this->hotel);

        Flux::toast(variant: 'success', text: __('Hotel :name and all of its data were deleted.', ['name' => $name]));
        $this->redirectRoute('admin.hotels.index', navigate: true);
    }

    private function fillDetails(): void
    {
        $this->name = $this->hotel->name;
        $this->slug = $this->hotel->slug;
        $this->currency = $this->hotel->currency;
        $this->timezone = $this->hotel->timezone;
        $this->phone = $this->hotel->phone ?? '';
        $this->fax = $this->hotel->fax ?? '';
        $this->email = $this->hotel->email ?? '';
        $this->address = $this->hotel->address ?? '';
        $this->street = $this->hotel->street ?? '';
        $this->tin = $this->hotel->tin ?? '';
        $this->vrn = $this->hotel->vrn ?? '';
    }

    private function guardSuperAdmin(): void
    {
        abort_unless(Auth::user()?->isSuperAdmin() ?? false, 403);
    }

    public function render(): View
    {
        return view('livewire.admin.hotels.show')
            ->title($this->hotel->name);
    }
}
