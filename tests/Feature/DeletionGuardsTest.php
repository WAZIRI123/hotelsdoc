<?php

declare(strict_types=1);

use App\Domain\Reservations\Actions\CreateReservation;
use App\Livewire\MealPlans\Index as MealPlansIndex;
use App\Livewire\Menu\Index as MenuIndex;
use App\Livewire\RatePlans\Index as RatePlansIndex;
use App\Livewire\Rooms\Index as RoomsIndex;
use App\Livewire\RoomTypes\Index as RoomTypesIndex;
use App\Models\Guest;
use App\Models\MealPlan;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Outlet;
use App\Models\RatePlan;
use App\Models\ReservationRoom;
use App\Models\Room;
use App\Models\RoomAvailability;
use App\Models\RoomType;
use App\Models\StockEntry;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

beforeEach(function (): void {
    [$this->hotel] = actingAsTenantUser();
    $this->roomType = RoomType::factory()->for($this->hotel)->create();
    $this->room = Room::factory()->for($this->hotel)->for($this->roomType)->create();

    $this->book = fn (string $arrival, string $departure, ?int $ratePlanId = null) => app(CreateReservation::class)->execute(
        guestId: Guest::factory()->for($this->hotel)->create()->id,
        roomTypeId: $this->roomType->id,
        roomId: $this->room->id,
        arrival: CarbonImmutable::parse($arrival),
        departure: CarbonImmutable::parse($departure),
        ratePlanId: $ratePlanId,
    );
});

it('deactivates a room type that has rooms instead of deleting them with it', function (): void {
    Livewire::test(RoomTypesIndex::class)->call('delete', $this->roomType->id);

    expect($this->roomType->fresh()->is_active)->toBeFalse()
        ->and($this->room->fresh())->not->toBeNull();
});

it('refuses to delete a room with upcoming bookings', function (): void {
    ($this->book)(today()->addDays(3)->toDateString(), today()->addDays(5)->toDateString());

    Livewire::test(RoomsIndex::class)->call('delete', $this->room->id);

    expect($this->room->fresh())->not->toBeNull()
        ->and($this->room->fresh()->is_active)->toBeTrue()
        ->and(RoomAvailability::query()->where('room_id', $this->room->id)->count())->toBe(2);
});

it('deactivates a room that only has past bookings', function (): void {
    ($this->book)(today()->toDateString(), today()->addDays(2)->toDateString());
    $this->travelTo(today()->addDays(10));

    Livewire::test(RoomsIndex::class)->call('delete', $this->room->id);

    expect($this->room->fresh()->is_active)->toBeFalse()
        ->and(ReservationRoom::query()->where('room_id', $this->room->id)->exists())->toBeTrue();
});

it('deletes an unused room', function (): void {
    Livewire::test(RoomsIndex::class)->call('delete', $this->room->id);

    expect($this->room->fresh())->toBeNull();
});

it('deactivates a rate plan that bookings are priced on', function (): void {
    $ratePlan = RatePlan::factory()->for($this->hotel)->for($this->roomType)->create();
    ($this->book)(today()->addDay()->toDateString(), today()->addDays(2)->toDateString(), $ratePlan->id);

    Livewire::test(RatePlansIndex::class)->call('delete', $ratePlan->id);

    expect($ratePlan->fresh()->is_active)->toBeFalse();
});

it('deactivates a meal plan that a rate plan includes', function (): void {
    $mealPlan = MealPlan::factory()->for($this->hotel)->create();
    RatePlan::factory()->for($this->hotel)->for($this->roomType)->create(['meal_plan_id' => $mealPlan->id]);

    Livewire::test(MealPlansIndex::class)->call('delete', $mealPlan->id);

    expect($mealPlan->fresh()->is_active)->toBeFalse();
});

it('deactivates a menu item and its category when the item has stock history', function (): void {
    $outlet = Outlet::factory()->for($this->hotel)->create();
    $category = MenuCategory::factory()->for($this->hotel)->for($outlet)->create();
    $item = MenuItem::factory()->for($this->hotel)->for($outlet)->for($category, 'category')->create();
    StockEntry::factory()->for($this->hotel)->create(['menu_item_id' => $item->id, 'unit_cost' => 1_000_00]);

    Livewire::test(MenuIndex::class, ['outlet' => $outlet])->call('deleteItem', $item->id);
    Livewire::test(MenuIndex::class, ['outlet' => $outlet])->call('deleteCategory', $category->id);

    expect($item->fresh()->is_active)->toBeFalse()
        ->and($category->fresh()->is_active)->toBeFalse()
        ->and(StockEntry::query()->where('menu_item_id', $item->id)->exists())->toBeTrue();
});

it('deletes a menu item with no history', function (): void {
    $outlet = Outlet::factory()->for($this->hotel)->create();
    $category = MenuCategory::factory()->for($this->hotel)->for($outlet)->create();
    $item = MenuItem::factory()->for($this->hotel)->for($outlet)->for($category, 'category')->create();

    Livewire::test(MenuIndex::class, ['outlet' => $outlet])->call('deleteItem', $item->id);

    expect($item->fresh())->toBeNull();
});
