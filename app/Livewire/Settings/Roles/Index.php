<?php

declare(strict_types=1);

namespace App\Livewire\Settings\Roles;

use App\Domain\Authorization\RolePermissionCatalog;
use App\Domain\Tenancy\TenantContext;
use Flux\Flux;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

#[Title('Roles & permissions')]
class Index extends Component
{
    public ?int $editingRoleId = null;

    /** @var list<string> */
    public array $selectedPermissions = [];

    public function mount(): void
    {
        abort_unless(Auth::user()?->can('manage-roles') ?? false, 403);
    }

    /**
     * @return Collection<int, Role>
     */
    #[Computed]
    public function roles(): Collection
    {
        $hotel = app(TenantContext::class)->current();

        return Role::query()
            ->where('hotel_id', $hotel->id)
            ->with('permissions')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return list<string>
     */
    #[Computed]
    public function allPermissions(): array
    {
        return RolePermissionCatalog::PERMISSIONS;
    }

    public function openEdit(int $id): void
    {
        $this->guardManageRoles();
        $hotel = app(TenantContext::class)->current();
        /** @var Role $role */
        $role = Role::query()
            ->where('hotel_id', $hotel->id)
            ->where('id', $id)
            ->firstOrFail();

        $this->editingRoleId = (int) $role->id;
        $this->selectedPermissions = array_values($role->permissions->pluck('name')->map(fn ($n) => (string) $n)->all());
        Flux::modal('role-form')->show();
    }

    public function save(): void
    {
        $this->guardManageRoles();
        $hotel = app(TenantContext::class)->current();
        /** @var Role $role */
        $role = Role::query()
            ->where('hotel_id', $hotel->id)
            ->findOrFail($this->editingRoleId);

        // Admin always has all permissions — guards against locking the hotel out.
        if ($role->name === 'admin') {
            $role->syncPermissions(RolePermissionCatalog::PERMISSIONS);
        } else {
            $role->syncPermissions(array_intersect($this->selectedPermissions, RolePermissionCatalog::PERMISSIONS));
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Flux::toast(variant: 'success', text: __('Permissions updated.'));
        Flux::modal('role-form')->close();
        unset($this->roles);
    }

    public function resetToDefaults(int $id): void
    {
        $this->guardManageRoles();
        $hotel = app(TenantContext::class)->current();
        /** @var Role $role */
        $role = Role::query()
            ->where('hotel_id', $hotel->id)
            ->findOrFail($id);

        $role->syncPermissions(RolePermissionCatalog::permissionsFor($role->name));
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Flux::toast(variant: 'success', text: __('Role reset to defaults.'));
        unset($this->roles);
    }

    private function guardManageRoles(): void
    {
        if (! Auth::user()?->can('manage-roles')) {
            throw new AuthorizationException;
        }
    }

    public function render(): View
    {
        return view('livewire.settings.roles.index');
    }
}
