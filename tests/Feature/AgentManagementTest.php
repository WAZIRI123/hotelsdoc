<?php

declare(strict_types=1);

use App\Livewire\Agents\Index;
use App\Livewire\Agents\Register;
use App\Models\Agent;
use App\Models\AgentQualification;
use App\Models\Hotel;
use Livewire\Livewire;

beforeEach(function (): void {
    [$this->hotel, $this->user] = actingAsTenantUser();
});

it('renders the register agent page', function (): void {
    $this->get(route('agents.register'))
        ->assertOk()
        ->assertSee('Register Agent')
        ->assertSee('View agents')
        ->assertSee('Select Qualification');
});

it('registers an agent with a qualification', function (): void {
    $qualification = AgentQualification::factory()->for($this->hotel)->create(['name' => 'Special Agent']);

    Livewire::test(Register::class)
        ->set('name', 'Safari Tours Ltd')
        ->set('phone', '+255 700 000 000')
        ->set('email', 'bookings@safari.test')
        ->set('tin', '123-456-789')
        ->set('vrn', '40-123456-A')
        ->set('address', 'Arusha')
        ->set('agent_qualification_id', (string) $qualification->id)
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('agents.index'));

    $agent = Agent::query()->firstWhere('name', 'Safari Tours Ltd');

    expect($agent->hotel_id)->toBe($this->hotel->id)
        ->and($agent->qualification->name)->toBe('Special Agent')
        ->and($agent->tin)->toBe('123-456-789')
        ->and($agent->vrn)->toBe('40-123456-A')
        ->and($agent->is_active)->toBeTrue();
});

it('requires a unique agent name per hotel', function (): void {
    Agent::factory()->for($this->hotel)->create(['name' => 'Safari Tours Ltd']);

    Livewire::test(Register::class)
        ->set('name', '')
        ->call('save')
        ->assertHasErrors(['name' => 'required'])
        ->set('name', 'Safari Tours Ltd')
        ->call('save')
        ->assertHasErrors(['name' => 'unique']);
});

it('adds a qualification on the fly and selects it', function (): void {
    Livewire::test(Register::class)
        ->set('agent_qualification_id', Register::NEW_QUALIFICATION)
        ->assertSet('addingQualification', true)
        ->assertSet('agent_qualification_id', '')
        ->set('newQualification', '  Normal Agent ')
        ->call('addQualification')
        ->assertHasNoErrors()
        ->assertSet('addingQualification', false)
        ->assertSee('Normal Agent');

    $qualification = AgentQualification::query()->firstWhere('name', 'Normal Agent');

    expect($qualification)->not->toBeNull()
        ->and($qualification->hotel_id)->toBe($this->hotel->id);
});

it('selects the new qualification after adding it on the fly', function (): void {
    $component = Livewire::test(Register::class)
        ->call('startAddingQualification')
        ->set('newQualification', 'Corporate')
        ->call('addQualification');

    $qualification = AgentQualification::query()->firstWhere('name', 'Corporate');

    $component->assertSet('agent_qualification_id', (string) $qualification->id);
});

it('rejects duplicate qualifications', function (): void {
    AgentQualification::factory()->for($this->hotel)->create(['name' => 'Special Agent']);

    Livewire::test(Register::class)
        ->call('startAddingQualification')
        ->set('newQualification', 'Special Agent')
        ->call('addQualification')
        ->assertHasErrors(['newQualification' => 'unique']);
});

it('rejects a qualification from another hotel', function (): void {
    $foreign = AgentQualification::factory()->for(Hotel::factory())->create();

    Livewire::test(Register::class)
        ->set('name', 'Safari Tours Ltd')
        ->set('agent_qualification_id', (string) $foreign->id)
        ->call('save')
        ->assertHasErrors('agent_qualification_id');
});

it('edits an existing agent', function (): void {
    $agent = Agent::factory()->for($this->hotel)->create(['name' => 'Old Name']);

    Livewire::test(Register::class, ['agent' => $agent])
        ->assertSet('name', 'Old Name')
        ->set('name', 'New Name')
        ->set('is_active', false)
        ->call('save')
        ->assertHasNoErrors();

    expect($agent->fresh())
        ->name->toBe('New Name')
        ->is_active->toBeFalse();
});

it('does not expose agents from another hotel', function (): void {
    $foreign = Agent::factory()->for(Hotel::factory())->create();

    $this->get(route('agents.edit', $foreign))->assertNotFound();
});

it('lists, searches and deletes agents', function (): void {
    $qualification = AgentQualification::factory()->for($this->hotel)->create(['name' => 'Special Agent']);
    $agent = Agent::factory()->for($this->hotel)->for($qualification, 'qualification')->create(['name' => 'Safari Tours Ltd']);
    Agent::factory()->for($this->hotel)->create(['name' => 'Coastal Travel']);

    Livewire::test(Index::class)
        ->assertSee('Safari Tours Ltd')
        ->assertSee('Special Agent')
        ->set('search', 'Coastal')
        ->assertSee('Coastal Travel')
        ->assertDontSee('Safari Tours Ltd')
        ->call('delete', $agent->id);

    expect(Agent::query()->find($agent->id))->toBeNull();
});

it('forbids users without reservation permission', function (): void {
    actingAsTenantUser($this->hotel, role: 'waiter');

    $this->get(route('agents.index'))->assertForbidden();
    $this->get(route('agents.register'))->assertForbidden();
});
