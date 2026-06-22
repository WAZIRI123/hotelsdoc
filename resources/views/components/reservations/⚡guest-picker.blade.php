<?php

use App\Models\Guest;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    public string $search = '';

    public ?int $selectedGuestId = null;

    public function select(int $id): void
    {
        $this->selectedGuestId = $id;
        $this->search = '';
        $this->dispatch('guest-selected', id: $id);
    }

    public function clear(): void
    {
        $this->selectedGuestId = null;
        $this->dispatch('guest-selected', id: null);
    }

    #[On('guest-created')]
    public function onGuestCreated(int $id): void
    {
        $this->select($id);
    }

    /**
     * @return Collection<int, Guest>
     */
    #[Computed]
    public function guests(): Collection
    {
        if ($this->search === '') {
            return Guest::query()->orderBy('first_name')->limit(15)->get();
        }

        $term = '%'.$this->search.'%';

        return Guest::query()
            ->where(function ($q) use ($term): void {
                $q->where('first_name', 'like', $term)
                    ->orWhere('last_name', 'like', $term)
                    ->orWhere('phone', 'like', $term)
                    ->orWhere('email', 'like', $term);
            })
            ->orderBy('first_name')
            ->limit(15)
            ->get();
    }

    #[Computed]
    public function selectedGuest(): ?Guest
    {
        return $this->selectedGuestId !== null
            ? Guest::query()->find($this->selectedGuestId)
            : null;
    }
};
?>

<div>
    @if ($this->selectedGuest)
        <div class="flex items-center justify-between rounded-lg border border-zinc-200 px-4 py-3 dark:border-zinc-700">
            <div>
                <flux:heading size="sm">{{ $this->selectedGuest->fullName() }}</flux:heading>
                <flux:text class="text-sm">{{ $this->selectedGuest->phone ?? '—' }} · {{ $this->selectedGuest->email ?? '—' }}</flux:text>
            </div>
            <flux:button variant="ghost" size="sm" wire:click="clear">{{ __('Change') }}</flux:button>
        </div>
    @else
        <div class="space-y-3">
            <div class="flex items-center gap-2">
                <input wire:model.live="search" type="search"
                    placeholder="{{ __('Search by name, phone, email…') }}"
                    class="flex-1 rounded-md border border-zinc-200 px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-900" />
                <flux:button variant="ghost" icon="user-plus"
                    x-on:click="$dispatch('new-guest-form:open', { prefillFirstName: $wire.search })">
                    {{ __('New guest') }}
                </flux:button>
            </div>

            <div class="max-h-64 space-y-1 overflow-y-auto rounded border border-zinc-200 dark:border-zinc-700">
                @foreach ($this->guests as $g)
                    <button type="button"
                        wire:click="select({{ $g->id }})"
                        class="flex w-full items-center justify-between px-4 py-2 text-left hover:bg-zinc-100 dark:hover:bg-zinc-800">
                        <span class="font-medium">{{ $g->fullName() }}</span>
                        <span class="text-sm text-zinc-500">{{ $g->phone ?? $g->email ?? '' }}</span>
                    </button>
                @endforeach

                @if ($this->guests->isEmpty())
                    <div class="px-4 py-3 text-sm text-zinc-500">
                        {{ __('No guests match. Use') }}
                        <flux:link x-on:click="$dispatch('new-guest-form:open', { prefillFirstName: $wire.search })">{{ __('New guest') }}</flux:link>
                        {{ __('to create one.') }}
                    </div>
                @endif
            </div>
        </div>
    @endif
</div>
