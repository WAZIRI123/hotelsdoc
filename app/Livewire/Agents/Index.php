<?php

declare(strict_types=1);

namespace App\Livewire\Agents;

use App\Models\Agent;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Agents')]
class Index extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    public function mount(): void
    {
        $this->authorizeManagement();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    /**
     * @return LengthAwarePaginator<int, Agent>
     */
    #[Computed]
    public function agents(): LengthAwarePaginator
    {
        return Agent::query()
            ->with('qualification')
            ->when($this->search, function ($q): void {
                $term = '%'.$this->search.'%';
                $q->where(function ($q) use ($term): void {
                    $q->where('name', 'like', $term)
                        ->orWhere('phone', 'like', $term)
                        ->orWhere('email', 'like', $term)
                        ->orWhere('tin', 'like', $term)
                        ->orWhere('vrn', 'like', $term);
                });
            })
            ->orderBy('name')
            ->paginate(15);
    }

    public function delete(int $id): void
    {
        $this->authorizeManagement();

        Agent::query()->findOrFail($id)->delete();

        Flux::toast(variant: 'success', text: __('Agent deleted.'));
        unset($this->agents);
    }

    public function render(): View
    {
        return view('livewire.agents.index');
    }

    private function authorizeManagement(): void
    {
        abort_unless(Auth::user()?->can('manage-reservations') ?? false, 403);
    }
}
