<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Organization;

use App\Domain\Tenancy\Actions\DeleteHotel;
use App\Models\Hotel;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * @property-read Collection<int, Hotel> $hotels
 * @property-read Hotel|null $deletingHotel
 */
#[Title('System Control')]
class SystemControl extends Component
{
    public ?int $deletingHotelId = null;

    public string $deleteConfirmation = '';

    public function mount(): void
    {
        $this->guardSuperAdmin();
    }

    /**
     * @return Collection<int, Hotel>
     */
    #[Computed]
    public function hotels(): Collection
    {
        return Hotel::query()
            ->with(['approver:id,name', 'editor:id,name'])
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function deletingHotel(): ?Hotel
    {
        return $this->deletingHotelId !== null ? Hotel::query()->find($this->deletingHotelId) : null;
    }

    /**
     * Blocks the hotel's staff from signing in to it; data is untouched.
     */
    public function blockAccess(int $hotelId): void
    {
        $this->guardSuperAdmin();
        Hotel::query()->findOrFail($hotelId)->forceFill(['suspended_at' => now()])->save();

        unset($this->hotels);
        Flux::toast(variant: 'warning', text: __('System access blocked. Its staff can no longer sign in to it.'));
    }

    public function restoreAccess(int $hotelId): void
    {
        $this->guardSuperAdmin();
        Hotel::query()->findOrFail($hotelId)->forceFill(['suspended_at' => null])->save();

        unset($this->hotels);
        Flux::toast(variant: 'success', text: __('System access restored.'));
    }

    public function confirmDelete(int $hotelId): void
    {
        $this->guardSuperAdmin();
        $this->deletingHotelId = Hotel::query()->findOrFail($hotelId)->id;
        $this->reset('deleteConfirmation');
        $this->resetErrorBag();
        unset($this->deletingHotel);

        Flux::modal('delete-operations')->show();
    }

    /**
     * Permanently deletes the hotel and all its data, with the same
     * two-step safety as the manage page: blocked first, slug typed.
     */
    public function deleteOperations(DeleteHotel $deleteHotel): void
    {
        $this->guardSuperAdmin();
        $hotel = $this->deletingHotel;

        if ($hotel === null) {
            return;
        }

        if (! $hotel->isSuspended()) {
            $this->addError('deleteConfirmation', __('Block system access before deleting.'));

            return;
        }

        if ($this->deleteConfirmation !== $hotel->slug) {
            $this->addError('deleteConfirmation', __('Type the hotel slug exactly to confirm.'));

            return;
        }

        $deleteHotel->execute($hotel);

        $this->reset('deletingHotelId', 'deleteConfirmation');
        unset($this->hotels, $this->deletingHotel);
        Flux::modal('delete-operations')->close();
        Flux::toast(variant: 'success', text: __('Hotel :name and all of its operations were deleted.', ['name' => $hotel->name]));
    }

    private function guardSuperAdmin(): void
    {
        abort_unless(Auth::user()?->isSuperAdmin() ?? false, 403);
    }

    public function render(): View
    {
        return view('livewire.admin.organization.system-control');
    }
}
