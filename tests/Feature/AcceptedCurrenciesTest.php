<?php

declare(strict_types=1);

use App\Livewire\Currencies\Index;
use App\Livewire\Extras\Create as ExtrasCreate;
use App\Livewire\Folios\RecordPaymentForm;
use App\Livewire\Folios\Show;
use App\Livewire\POS\Terminal;
use App\Models\AcceptedCurrency;
use App\Models\ExtraService;
use App\Models\Folio;
use App\Models\Hotel;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Outlet;
use App\Models\Payment;
use App\Models\PosOrder;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function (): void {
    [$this->hotel, $this->user] = actingAsTenantUser();
    $this->usd = AcceptedCurrency::factory()->for($this->hotel)->create(['code' => 'USD', 'rate' => '2600']);
});

it('lets a hotel add an accepted currency with its rate', function (): void {
    Livewire::test(Index::class)
        ->call('openCreate')
        ->set('code', 'eur')
        ->set('rate', '2850.5')
        ->call('save')
        ->assertHasNoErrors();

    $eur = AcceptedCurrency::query()->where('code', 'EUR')->sole();
    expect($eur->rate)->toBe('2850.500000')
        ->and($eur->hotel_id)->toBe($this->hotel->id)
        ->and($eur->is_active)->toBeTrue();
});

it('rejects the hotel currency, duplicates and non-positive rates', function (): void {
    Livewire::test(Index::class)
        ->set('code', 'TZS')->set('rate', '1')->call('save')->assertHasErrors('code')
        ->set('code', 'USD')->set('rate', '2700')->call('save')->assertHasErrors('code')
        ->set('code', 'KES')->set('rate', '0')->call('save')->assertHasErrors('rate');
});

it('keeps each hotel\'s currencies to itself', function (): void {
    $other = Hotel::factory()->create();
    AcceptedCurrency::factory()->for($other)->create(['code' => 'GBP', 'rate' => '3300']);

    Livewire::test(Index::class)->assertSee('USD')->assertDontSee('GBP');
});

it('links hotel managers to currencies from the sidebar, but not other staff or super-admins', function (): void {
    actingAsTenantUser($this->hotel, role: 'manager');
    $this->get(route('dashboard'))->assertSee(route('currencies.index'));

    actingAsTenantUser($this->hotel, role: 'front_desk');
    $this->get(route('dashboard'))->assertDontSee(route('currencies.index'));

    actingAsTenantUser($this->hotel, User::factory()->superAdmin()->create());
    $this->get(route('dashboard'))->assertDontSee(route('currencies.index'));
});

it('forbids staff without manage-hotel from managing currencies', function (): void {
    actingAsTenantUser($this->hotel, role: 'front_desk');

    Livewire::test(Index::class)->assertForbidden();
});

it('deactivates instead of deleting a currency payments were taken in', function (): void {
    $folio = Folio::query()->create(['hotel_id' => $this->hotel->id, 'total_amount' => 26_000_00, 'status' => 'open']);

    Livewire::test(RecordPaymentForm::class, ['folio' => $folio])
        ->set('method_code', 'cash')
        ->set('tenderCurrency', 'USD')
        ->set('tenderAmount', 10)
        ->call('submit');

    Livewire::test(Index::class)->call('delete', $this->usd->id);

    expect($this->usd->fresh()->is_active)->toBeFalse();
});

it('records a folio payment in a foreign currency at its rate', function (): void {
    $folio = Folio::query()->create(['hotel_id' => $this->hotel->id, 'total_amount' => 52_000_00, 'status' => 'open']);

    Livewire::test(RecordPaymentForm::class, ['folio' => $folio])
        ->set('method_code', 'cash')
        ->set('tenderCurrency', 'USD')
        ->assertSet('tenderAmount', 20)
        ->call('submit')
        ->assertHasNoErrors();

    $payment = $folio->payments()->sole();
    expect($payment->amount->toMajor())->toBe(52_000.0)
        ->and($payment->currency)->toBe('TZS')
        ->and($payment->tendered_currency)->toBe('USD')
        ->and($payment->tendered_amount)->toBe(20_00)
        ->and($payment->exchange_rate)->toBe('2600.000000')
        ->and($payment->amountLabel())->toBe('USD 20 (TZS 52,000)')
        ->and($folio->fresh()->status)->toBe('settled');

    Livewire::test(Show::class, ['folio' => $folio])->assertSee('USD 20 (TZS 52,000)');
});

it('credits only the balance and records change when foreign money is worth more', function (): void {
    $folio = Folio::query()->create(['hotel_id' => $this->hotel->id, 'total_amount' => 50_000_00, 'status' => 'open']);

    Livewire::test(RecordPaymentForm::class, ['folio' => $folio])
        ->set('method_code', 'cash')
        ->set('tenderCurrency', 'USD')
        ->set('tenderAmount', 20)
        ->call('submit')
        ->assertHasNoErrors();

    $payment = $folio->payments()->sole();
    expect($payment->amount->toMajor())->toBe(50_000.0)
        ->and($payment->changeGiven()?->toMajor())->toBe(2_000.0)
        ->and($folio->fresh()->paid_total->toMajor())->toBe(50_000.0);
});

it('takes a part payment on a folio in a foreign currency', function (): void {
    $folio = Folio::query()->create(['hotel_id' => $this->hotel->id, 'total_amount' => 100_000_00, 'status' => 'open']);

    Livewire::test(RecordPaymentForm::class, ['folio' => $folio])
        ->set('method_code', 'cash')
        ->set('tenderCurrency', 'USD')
        ->set('tenderAmount', 12.5)
        ->call('submit')
        ->assertHasNoErrors();

    expect($folio->fresh()->paid_total->toMajor())->toBe(32_500.0)
        ->and($folio->fresh()->status)->toBe('open')
        ->and($folio->payments()->sole()->amountLabel())->toBe('USD 12.50 (TZS 32,500)');
});

it('does not take payment in an inactive currency', function (): void {
    $this->usd->update(['is_active' => false]);
    $folio = Folio::query()->create(['hotel_id' => $this->hotel->id, 'total_amount' => 26_000_00, 'status' => 'open']);

    Livewire::test(RecordPaymentForm::class, ['folio' => $folio])
        ->set('method_code', 'cash')
        ->set('tenderCurrency', 'USD')
        ->assertSet('tenderCurrency', '')
        ->set('tenderAmount', 10);

    expect(Payment::query()->where('tendered_currency', 'USD')->exists())->toBeFalse();
});

describe('point of sale', function (): void {
    beforeEach(function (): void {
        $this->outlet = Outlet::factory()->for($this->hotel)->create();
        $category = MenuCategory::factory()->for($this->hotel)->for($this->outlet)->create();
        $this->meal = MenuItem::factory()->for($this->hotel)->for($this->outlet)->for($category, 'category')->create([
            'price' => 39_000_00,
        ]);
    });

    it('refuses foreign money that does not cover the order', function (): void {
        Livewire::test(Terminal::class, ['outlet' => $this->outlet])
            ->call('addItem', $this->meal->id)
            ->call('openSettle')
            ->set('settleMethod', 'cash')
            ->set('tenderCurrency', 'USD')
            ->set('tenderAmount', 10)
            ->call('settleDirect')
            ->assertHasErrors('tenderAmount');

        expect(PosOrder::query()->sole()->status)->toBe('open')
            ->and(Payment::query()->count())->toBe(0);
    });

    it('settles an order paid in a foreign currency', function (): void {
        Livewire::test(Terminal::class, ['outlet' => $this->outlet])
            ->call('addItem', $this->meal->id)
            ->call('openSettle')
            ->set('settleMethod', 'cash')
            ->set('tenderCurrency', 'USD')
            ->set('tenderAmount', 20)
            ->call('settleDirect')
            ->assertHasNoErrors();

        $payment = Payment::query()->sole();
        expect($payment->amount->toMajor())->toBe(39_000.0)
            ->and($payment->tendered_amount)->toBe(20_00)
            ->and($payment->changeGiven()?->toMajor())->toBe(13_000.0)
            ->and(PosOrder::query()->sole()->status)->toBe('billed');
    });

    it('defaults to exactly the amount due when no amount is entered', function (): void {
        Livewire::test(Terminal::class, ['outlet' => $this->outlet])
            ->call('addItem', $this->meal->id)
            ->call('openSettle')
            ->set('settleMethod', 'cash')
            ->set('tenderCurrency', 'USD')
            ->call('settleDirect')
            ->assertHasNoErrors();

        expect(Payment::query()->sole()->tendered_amount)->toBe(15_00);
    });
});

it('checks foreign money covers an extras order paid now', function (): void {
    $service = ExtraService::factory()->for($this->hotel)->create(['price' => 26_000_00]);

    Livewire::test(ExtrasCreate::class)
        ->set('customer', ExtrasCreate::WALK_IN)
        ->set('paymentMethod', 'cash')
        ->set('lines.0.extra_service_id', (string) $service->id)
        ->set('tenderCurrency', 'USD')
        ->set('tenderAmount', 5)
        ->call('save')
        ->assertHasErrors('tenderAmount')
        ->set('tenderAmount', 10)
        ->call('save')
        ->assertHasNoErrors();

    $payment = Payment::query()->sole();
    expect($payment->amount->toMajor())->toBe(26_000.0)
        ->and($payment->amountLabel())->toBe('USD 10 (TZS 26,000)');
});

it('does not count the rounding of foreign money to the cent as change', function (): void {
    $this->usd->update(['rate' => '2567']);
    $folio = Folio::query()->create(['hotel_id' => $this->hotel->id, 'total_amount' => 120_000_00, 'status' => 'open']);

    $component = Livewire::test(RecordPaymentForm::class, ['folio' => $folio])
        ->set('method_code', 'cash')
        ->set('tenderCurrency', 'USD')
        ->assertSet('tenderAmount', 46.75)
        ->assertSee('Covers the amount due.')
        ->assertDontSee('change');

    expect($component->instance()->tenderSummary()['change'])->toBeNull();

    $component->call('submit')->assertHasNoErrors();
    $payment = $folio->payments()->sole();
    expect($payment->amount->toMajor())->toBe(120_000.0)
        ->and($payment->changeGiven())->toBeNull();

    // A full cent or more over is real change.
    Livewire::test(RecordPaymentForm::class, ['folio' => Folio::query()->create(['hotel_id' => $this->hotel->id, 'total_amount' => 120_000_00, 'status' => 'open'])])
        ->set('tenderCurrency', 'USD')
        ->set('tenderAmount', 50)
        ->assertSee('Covers it — change');
});
