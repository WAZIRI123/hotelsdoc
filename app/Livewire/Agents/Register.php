<?php

declare(strict_types=1);

namespace App\Livewire\Agents;

use App\Domain\Tenancy\TenantContext;
use App\Models\Agent;
use App\Models\AgentQualification;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Register Agent')]
class Register extends Component
{
    /** Select value that opens the inline "new qualification" input. */
    public const string NEW_QUALIFICATION = '__new';

    public ?Agent $agent = null;

    public string $name = '';

    public string $phone = '';

    public string $email = '';

    public string $tin = '';

    public string $vrn = '';

    public string $address = '';

    public string $agent_qualification_id = '';

    public bool $is_active = true;

    public bool $addingQualification = false;

    public string $newQualification = '';

    public function mount(?Agent $agent = null): void
    {
        $this->authorizeManagement();

        if ($agent?->exists) {
            $this->agent = $agent;
            $this->name = $agent->name;
            $this->phone = $agent->phone ?? '';
            $this->email = $agent->email ?? '';
            $this->tin = $agent->tin ?? '';
            $this->vrn = $agent->vrn ?? '';
            $this->address = $agent->address ?? '';
            $this->agent_qualification_id = (string) ($agent->agent_qualification_id ?? '');
            $this->is_active = $agent->is_active;
        }
    }

    /**
     * @return Collection<int, AgentQualification>
     */
    #[Computed]
    public function qualifications(): Collection
    {
        return AgentQualification::query()->orderBy('name')->get();
    }

    public function updatedAgentQualificationId(string $value): void
    {
        if ($value === self::NEW_QUALIFICATION) {
            $this->agent_qualification_id = '';
            $this->startAddingQualification();
        }
    }

    public function startAddingQualification(): void
    {
        $this->addingQualification = true;
        $this->newQualification = '';
        $this->resetValidation('newQualification');
    }

    public function cancelAddingQualification(): void
    {
        $this->addingQualification = false;
        $this->newQualification = '';
        $this->resetValidation('newQualification');
    }

    public function addQualification(): void
    {
        $this->authorizeManagement();

        $this->newQualification = trim($this->newQualification);

        $this->validate([
            'newQualification' => [
                'required', 'string', 'max:80',
                Rule::unique('agent_qualifications', 'name')->where('hotel_id', app(TenantContext::class)->id()),
            ],
        ], attributes: ['newQualification' => __('qualification')]);

        $qualification = AgentQualification::query()->create(['name' => $this->newQualification]);

        $this->agent_qualification_id = (string) $qualification->id;
        $this->cancelAddingQualification();
        unset($this->qualifications);

        Flux::toast(variant: 'success', text: __('Qualification :name added.', ['name' => $qualification->name]));
    }

    public function save(): void
    {
        $this->authorizeManagement();

        $hotelId = app(TenantContext::class)->id();

        $this->validate([
            'name' => [
                'required', 'string', 'max:160',
                Rule::unique('agents', 'name')->where('hotel_id', $hotelId)->ignore($this->agent?->id),
            ],
            'phone' => ['nullable', 'string', 'max:32'],
            'email' => ['nullable', 'email', 'max:120'],
            'tin' => ['nullable', 'string', 'max:32'],
            'vrn' => ['nullable', 'string', 'max:32'],
            'address' => ['nullable', 'string', 'max:500'],
            'agent_qualification_id' => [
                'nullable',
                Rule::exists('agent_qualifications', 'id')->where('hotel_id', $hotelId),
            ],
        ]);

        $attributes = [
            'name' => trim($this->name),
            'phone' => $this->phone ?: null,
            'email' => $this->email ?: null,
            'tin' => $this->tin ?: null,
            'vrn' => $this->vrn ?: null,
            'address' => $this->address ?: null,
            'agent_qualification_id' => $this->agent_qualification_id !== '' ? (int) $this->agent_qualification_id : null,
            'is_active' => $this->is_active,
        ];

        if ($this->agent) {
            $this->agent->update($attributes);
            Flux::toast(variant: 'success', text: __('Agent :name updated.', ['name' => $this->agent->name]));
        } else {
            $agent = Agent::query()->create($attributes);
            Flux::toast(variant: 'success', text: __('Agent :name registered.', ['name' => $agent->name]));
        }

        $this->redirectRoute('agents.index', navigate: true);
    }

    public function render(): View
    {
        return view('livewire.agents.register');
    }

    private function authorizeManagement(): void
    {
        abort_unless(Auth::user()?->can('manage-reservations') ?? false, 403);
    }
}
