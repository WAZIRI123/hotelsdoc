<?php

declare(strict_types=1);

use App\Livewire\MealPlans\Index;
use App\Models\Hotel;
use App\Models\MealPlan;
use Livewire\Livewire;

beforeEach(function (): void {
    [$this->hotel, $this->user] = actingAsTenantUser();
});

it('lists meal plans for the current hotel', function (): void {
    MealPlan::factory()->for($this->hotel)->create(['code' => 'BB', 'name' => 'Bed & Breakfast']);
    MealPlan::factory()->for($this->hotel)->create(['code' => 'FB', 'name' => 'Full Board']);

    Livewire::test(Index::class)
        ->assertSee('Bed & Breakfast')
        ->assertSee('Full Board');
});

it('creates a new meal plan', function (): void {
    Livewire::test(Index::class)
        ->set('code', 'HB')
        ->set('name', 'Half Board')
        ->set('description', 'Breakfast and dinner included')
        ->set('includes_breakfast', true)
        ->set('includes_dinner', true)
        ->set('is_active', true)
        ->call('save')
        ->assertHasNoErrors();

    $plan = MealPlan::query()->firstWhere('code', 'HB');
    expect($plan)->not->toBeNull()
        ->and($plan->hotel_id)->toBe($this->hotel->id)
        ->and($plan->includes_breakfast)->toBeTrue()
        ->and($plan->includes_lunch)->toBeFalse()
        ->and($plan->includes_dinner)->toBeTrue()
        ->and($plan->mealsIncludedCount())->toBe(2);
});

it('rejects duplicate codes within the same hotel', function (): void {
    MealPlan::factory()->for($this->hotel)->create(['code' => 'BB']);

    Livewire::test(Index::class)
        ->set('code', 'BB')
        ->set('name', 'Different Name')
        ->call('save')
        ->assertHasErrors('code');
});

it('allows the same code in a different hotel', function (): void {
    MealPlan::factory()->for($this->hotel)->create(['code' => 'BB']);
    $otherHotel = Hotel::factory()->create();
    MealPlan::factory()->for($otherHotel)->create(['code' => 'BB']);

    expect(MealPlan::withoutGlobalScopes()->where('code', 'BB')->count())->toBe(2);
});

it('updates an existing meal plan', function (): void {
    $plan = MealPlan::factory()->for($this->hotel)->create([
        'name' => 'Old name',
        'includes_breakfast' => false,
    ]);

    Livewire::test(Index::class)
        ->call('openEdit', $plan->id)
        ->set('name', 'New name')
        ->set('includes_breakfast', true)
        ->call('save')
        ->assertHasNoErrors();

    $fresh = $plan->fresh();
    expect($fresh->name)->toBe('New name')
        ->and($fresh->includes_breakfast)->toBeTrue();
});

it('deletes a meal plan', function (): void {
    $plan = MealPlan::factory()->for($this->hotel)->create();

    Livewire::test(Index::class)->call('delete', $plan->id);

    expect(MealPlan::find($plan->id))->toBeNull();
});

it('does not leak meal plans across tenants', function (): void {
    MealPlan::factory()->for($this->hotel)->create(['code' => 'BB', 'name' => 'Mine']);

    $otherHotel = Hotel::factory()->create();
    MealPlan::factory()->for($otherHotel)->create(['code' => 'BB', 'name' => 'Theirs']);

    Livewire::test(Index::class)
        ->assertSee('Mine')
        ->assertDontSee('Theirs');
});
