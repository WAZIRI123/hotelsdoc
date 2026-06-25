<?php

declare(strict_types=1);

namespace App\Livewire\Settings\Users;

use App\Domain\Authorization\RolePermissionCatalog;
use App\Domain\Tenancy\TenantContext;
use App\Models\User;
use Flux\Flux;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Spatie\Permission\PermissionRegistrar;

#[Title('User management')]
class Index extends Component
{
    public ?int $editingUserId = null;

    #[Validate('required|string|max:120')]
    public string $name = '';

    #[Validate('required|email|max:255')]
    public string $email = '';

    #[Validate('nullable|string|min:8|max:120')]
    public string $password = '';

    #[Validate('required|string')]
    public string $role = 'staff';

    public function mount(): void
    {
        abort_unless(Auth::user()?->can('manage-users') ?? false, 403);
    }

    /**
     * @return Collection<int, User>
     */
    #[Computed]
    public function users(): Collection
    {
        $hotel = app(TenantContext::class)->current();

        return $hotel->users()
            ->with('roles')
            ->orderBy('name')
            ->get();
    }

    /**
     * @return list<string>
     */
    #[Computed]
    public function availableRoles(): array
    {
        return RolePermissionCatalog::roles();
    }

    public function openCreate(): void
    {
        $this->guardManageUsers();
        $this->reset(['editingUserId', 'name', 'email', 'password']);
        $this->role = 'staff';
        $this->resetValidation();
        Flux::modal('user-form')->show();
    }

    public function openEdit(int $id): void
    {
        $this->guardManageUsers();
        $hotel = app(TenantContext::class)->current();
        $user = $hotel->users()->where('users.id', $id)->firstOrFail();

        $this->editingUserId = $user->id;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->password = '';
        $this->role = $user->roles()->value('name') ?? 'staff';
        $this->resetValidation();
        Flux::modal('user-form')->show();
    }

    public function save(): void
    {
        $this->guardManageUsers();
        $hotel = app(TenantContext::class)->current();

        $this->validate([
            'name' => 'required|string|max:120',
            'email' => 'required|email|max:255|unique:users,email,'.($this->editingUserId ?? 'NULL'),
            'password' => $this->editingUserId ? 'nullable|string|min:8|max:120' : 'required|string|min:8|max:120',
            'role' => 'required|in:'.implode(',', RolePermissionCatalog::roles()),
        ]);

        $attributes = [
            'name' => $this->name,
            'email' => $this->email,
        ];
        if ($this->password !== '') {
            $attributes['password'] = Hash::make($this->password);
        }

        if ($this->editingUserId) {
            $user = User::query()->findOrFail($this->editingUserId);
            $user->update($attributes);
        } else {
            $user = User::query()->create($attributes + [
                'email_verified_at' => now(),
                'password' => Hash::make($this->password),
            ]);
        }

        $hotel->users()->syncWithoutDetaching([$user->id]);

        app(PermissionRegistrar::class)->setPermissionsTeamId($hotel->id);
        $user->unsetRelation('roles');
        $user->syncRoles([$this->role]);

        Flux::toast(variant: 'success', text: $this->editingUserId ? __('User updated.') : __('User created.'));
        Flux::modal('user-form')->close();
        unset($this->users);
    }

    public function removeFromHotel(int $id): void
    {
        $this->guardManageUsers();

        if ($id === Auth::id()) {
            Flux::toast(variant: 'danger', text: __('You cannot remove yourself.'));

            return;
        }

        $hotel = app(TenantContext::class)->current();
        $user = User::query()->findOrFail($id);

        app(PermissionRegistrar::class)->setPermissionsTeamId($hotel->id);
        $user->syncRoles([]);
        $hotel->users()->detach($user->id);

        Flux::toast(variant: 'success', text: __('User removed from this hotel.'));
        unset($this->users);
    }

    private function guardManageUsers(): void
    {
        if (! Auth::user()?->can('manage-users')) {
            throw new AuthorizationException;
        }
    }

    public function render(): View
    {
        return view('livewire.settings.users.index');
    }
}
