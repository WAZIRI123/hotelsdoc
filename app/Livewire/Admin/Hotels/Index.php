<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Hotels;

use App\Models\Hotel;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Hotels')]
class Index extends Component
{
    #[Url(as: 'q')]
    public string $search = '';

    public function mount(): void
    {
        abort_unless(Auth::user()?->isSuperAdmin() ?? false, 403);
    }

    /**
     * Runs outside the tenant middleware, so the room / user counts span
     * every hotel rather than being scoped to one.
     *
     * @return Collection<int, Hotel>
     */
    #[Computed]
    public function hotels(): Collection
    {
        return Hotel::query()
            ->withCount(['users', 'rooms'])
            ->when($this->search !== '', function ($query): void {
                $query->where(function ($query): void {
                    $query->where('name', 'like', "%{$this->search}%")
                        ->orWhere('slug', 'like', "%{$this->search}%")
                        ->orWhere('email', 'like', "%{$this->search}%");
                });
            })
            ->orderBy('name')
            ->get();
    }

    public function render(): View
    {
        return view('livewire.admin.hotels.index');
    }
}
