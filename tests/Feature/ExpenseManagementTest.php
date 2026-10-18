<?php

declare(strict_types=1);

use App\Livewire\ExpenseCategories\Index as CategoriesIndex;
use App\Livewire\ExpenseCategories\Register as CategoriesRegister;
use App\Livewire\Expenses\Index as ExpensesIndex;
use App\Livewire\Expenses\Record as ExpensesRecord;
use App\Livewire\Reports\Expenses as ExpensesReport;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Hotel;
use Livewire\Livewire;

beforeEach(function (): void {
    [$this->hotel, $this->user] = actingAsTenantUser(role: 'accountant');
});

it('gives every new hotel the default expense categories', function (): void {
    $hotel = Hotel::factory()->create();

    $names = ExpenseCategory::query()->withoutGlobalScopes()->where('hotel_id', $hotel->id)->pluck('name')->all();

    expect($names)->toEqualCanonicalizing(Expense::CATEGORIES);
});

it('shows the expenses panel with its four sections', function (): void {
    $this->get(route('expenses'))
        ->assertOk()
        ->assertSee('Expenses Panel')
        ->assertSee(route('expense-categories.create'))
        ->assertSee(route('expense-categories.index'))
        ->assertSee(route('expenses.create'))
        ->assertSee(route('expenses.list'));

    $this->get(route('dashboard'))->assertSee(route('expenses'));
});

it('adds, renames and deletes expense categories', function (): void {
    Livewire::test(CategoriesRegister::class)
        ->set('name', 'Staff Meals')
        ->set('description', 'Lunch for staff on duty')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('expense-categories.index'));

    $category = ExpenseCategory::query()->where('name', 'Staff Meals')->sole();

    expect($category->hotel_id)->toBe($this->hotel->id);

    Livewire::test(CategoriesRegister::class)
        ->set('name', 'Staff Meals')
        ->call('save')
        ->assertHasErrors(['name' => 'unique']);

    Livewire::test(CategoriesRegister::class, ['category' => $category])
        ->set('name', 'Staff Food')
        ->call('save')
        ->assertHasNoErrors();

    expect($category->fresh()->name)->toBe('Staff Food');

    Livewire::test(CategoriesIndex::class)
        ->assertSee('Staff Food')
        ->call('delete', $category->id);

    expect(ExpenseCategory::query()->whereKey($category->id)->exists())->toBeFalse();
});

it('renames the category on its recorded expenses', function (): void {
    $category = ExpenseCategory::query()->where('name', 'Transport')->sole();
    $expense = Expense::factory()->for($this->hotel)->create(['category' => 'Transport']);

    Livewire::test(CategoriesRegister::class, ['category' => $category])
        ->set('name', 'Transport & Fuel')
        ->call('save')
        ->assertHasNoErrors();

    expect($expense->fresh()->category)->toBe('Transport & Fuel');
});

it('keeps a category that is used on recorded expenses', function (): void {
    $category = ExpenseCategory::query()->where('name', 'Transport')->sole();
    Expense::factory()->for($this->hotel)->create(['category' => 'Transport']);

    Livewire::test(CategoriesIndex::class)->call('delete', $category->id);

    expect($category->fresh())->not->toBeNull();
});

it('records a daily expense that shows in the list and the expenses report', function (): void {
    $this->travelTo('2026-10-19 10:00:00');

    Livewire::test(ExpensesRecord::class)
        ->set('expenseDate', '2026-10-18')
        ->set('pvNumber', 'PV-0100')
        ->set('category', 'Transport')
        ->set('description', 'Taxi to the market')
        ->set('quantity', '2')
        ->set('payee', 'City Taxi')
        ->set('method', 'cash')
        ->set('amount', '15000')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('expenses.list'));

    $expense = Expense::query()->sole();

    expect($expense->category)->toBe('Transport')
        ->and($expense->money()->toMajor())->toBe(15_000.0)
        ->and($expense->recorded_by)->toBe($this->user->id)
        ->and($expense->hotel_id)->toBe($this->hotel->id);

    Livewire::test(ExpensesIndex::class)->assertSee('Taxi to the market');

    Livewire::test(ExpensesReport::class, ['from' => '2026-10-01', 'to' => '2026-10-31'])
        ->assertSee('Taxi to the market')
        ->assertSee('PV-0100');
});

it('validates daily expenses', function (): void {
    Livewire::test(ExpensesRecord::class)
        ->set('expenseDate', now()->addDay()->toDateString())
        ->set('category', 'Not a category')
        ->set('quantity', '-1')
        ->set('amount', '0')
        ->call('save')
        ->assertHasErrors(['expenseDate', 'category', 'description', 'quantity', 'amount']);
});

it('edits and deletes a daily expense', function (): void {
    $expense = Expense::factory()->for($this->hotel)->create(['category' => 'Transport', 'description' => 'Bus fare', 'amount' => 5_000_00]);

    Livewire::test(ExpensesRecord::class, ['expense' => $expense])
        ->assertSet('amount', '5000')
        ->set('amount', '7500')
        ->call('save')
        ->assertHasNoErrors();

    expect($expense->fresh()->money()->toMajor())->toBe(7_500.0);

    Livewire::test(ExpensesIndex::class)->call('delete', $expense->id);

    expect(Expense::query()->count())->toBe(0);
});

it('filters daily expenses by date and category', function (): void {
    Expense::factory()->for($this->hotel)->create(['expense_date' => '2026-09-05', 'category' => 'Transport', 'description' => 'Fuel run', 'amount' => 20_000_00]);
    Expense::factory()->for($this->hotel)->create(['expense_date' => '2026-09-06', 'category' => 'Utilities', 'description' => 'Water bill', 'amount' => 30_000_00]);
    Expense::factory()->for($this->hotel)->create(['expense_date' => '2026-08-06', 'category' => 'Transport', 'description' => 'August taxi']);

    $component = Livewire::withQueryParams(['from' => '2026-09-01', 'to' => '2026-09-30'])
        ->test(ExpensesIndex::class)
        ->assertSee('Fuel run')
        ->assertSee('Water bill')
        ->assertDontSee('August taxi');

    expect($component->instance()->total->toMajor())->toBe(50_000.0);

    $component->set('category', 'Transport')
        ->assertSee('Fuel run')
        ->assertDontSee('Water bill');
});

it('lets the expenses report record against a hotel-defined category', function (): void {
    ExpenseCategory::factory()->for($this->hotel)->create(['name' => 'Laundry']);

    Livewire::test(ExpensesReport::class)
        ->call('openCreate')
        ->set('expenseCategory', 'Laundry')
        ->set('expenseDescription', 'Detergent')
        ->set('expenseAmount', '8000')
        ->call('save')
        ->assertHasNoErrors();

    expect(Expense::query()->sole()->category)->toBe('Laundry');
});
