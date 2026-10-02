<?php

declare(strict_types=1);

namespace App\Livewire\Admin\InvoiceFooters;

use App\Models\Hotel;
use App\Models\InvoiceFooter;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Adds or edits the bill footer of one hotel. A hotel has at most one
 * footer, so picking a hotel that already has one loads it for editing.
 */
#[Title('Add Invoice Footer')]
class Create extends Component
{
    #[Url(as: 'hotel')]
    public ?int $hotelId = null;

    public string $content = '';

    public function mount(): void
    {
        $this->guardSuperAdmin();
        $this->loadFooter();
    }

    public function updatedHotelId(): void
    {
        $this->resetValidation();
        $this->loadFooter();
    }

    /**
     * @return Collection<int, Hotel>
     */
    #[Computed]
    public function hotels(): Collection
    {
        return Hotel::query()->orderBy('name')->get(['id', 'name']);
    }

    #[Computed]
    public function existingFooter(): ?InvoiceFooter
    {
        return $this->hotelId !== null
            ? InvoiceFooter::query()->where('hotel_id', $this->hotelId)->first()
            : null;
    }

    public function save(): void
    {
        $this->guardSuperAdmin();

        $validated = $this->validate([
            'hotelId' => ['required', 'integer', Rule::exists('hotels', 'id')],
            'content' => 'required|string|max:2000',
        ], [], [
            'hotelId' => __('organization'),
            'content' => __('footer text'),
        ]);

        $footer = InvoiceFooter::query()->firstOrNew(['hotel_id' => $validated['hotelId']]);
        $footer->content = trim($validated['content']);
        $footer->forceFill($footer->exists ? ['edited_by' => Auth::id()] : ['created_by' => Auth::id()]);
        $isNew = ! $footer->exists;
        $footer->save();

        unset($this->existingFooter);
        $hotelName = $this->hotels->firstWhere('id', $this->hotelId)?->name;
        Flux::toast(variant: 'success', text: $isNew
            ? __('Invoice footer added to :hotel.', ['hotel' => $hotelName])
            : __('Invoice footer of :hotel updated.', ['hotel' => $hotelName]));
    }

    private function loadFooter(): void
    {
        unset($this->existingFooter);
        $this->content = $this->existingFooter?->content ?? '';
    }

    private function guardSuperAdmin(): void
    {
        abort_unless(Auth::user()?->isSuperAdmin() ?? false, 403);
    }

    public function render(): View
    {
        return view('livewire.admin.invoice-footers.create');
    }
}
