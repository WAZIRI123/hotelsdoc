<?php

declare(strict_types=1);

use App\Livewire\Admin\InvoiceFooters\Create;
use App\Livewire\Admin\InvoiceFooters\Index;
use App\Models\Hotel;
use App\Models\InvoiceFooter;
use App\Models\User;
use Livewire\Livewire;

it('forbids hotel staff from managing invoice footers', function (): void {
    actingAsTenantUser(role: 'admin');

    $this->get(route('admin.invoice-footers.create'))->assertForbidden();
    $this->get(route('admin.invoice-footers.index'))->assertForbidden();
});

it('adds an invoice footer to the chosen organization only', function (): void {
    $hotel = Hotel::factory()->create(['name' => 'Trial Hotel']);
    $otherHotel = Hotel::factory()->create();
    $superAdmin = User::factory()->superAdmin()->create();
    $this->actingAs($superAdmin);

    $this->get(route('admin.invoice-footers.create'))->assertOk()->assertSee('Trial Hotel');

    Livewire::test(Create::class)
        ->set('hotelId', $hotel->id)
        ->set('content', "Bank: CRDB\nAccount: 0150-123456")
        ->call('save')
        ->assertHasNoErrors();

    $footer = InvoiceFooter::query()->sole();
    expect($footer->hotel_id)->toBe($hotel->id)
        ->and($footer->content)->toBe("Bank: CRDB\nAccount: 0150-123456")
        ->and($footer->created_by)->toBe($superAdmin->id)
        ->and($footer->edited_by)->toBeNull()
        ->and($otherHotel->invoiceFooter()->exists())->toBeFalse();
});

it('loads and replaces an existing footer instead of adding a second one', function (): void {
    $hotel = Hotel::factory()->create();
    InvoiceFooter::factory()->create(['hotel_id' => $hotel->id, 'content' => 'Old footer']);
    $superAdmin = User::factory()->superAdmin()->create();
    $this->actingAs($superAdmin);

    Livewire::test(Create::class)
        ->set('hotelId', $hotel->id)
        ->assertSet('content', 'Old footer')
        ->assertSee('Update Invoice Footer')
        ->set('content', 'New footer')
        ->call('save')
        ->assertHasNoErrors();

    $footer = InvoiceFooter::query()->sole();
    expect($footer->content)->toBe('New footer')
        ->and($footer->edited_by)->toBe($superAdmin->id);
});

it('validates the invoice footer form', function (): void {
    $this->actingAs(User::factory()->superAdmin()->create());

    Livewire::test(Create::class)
        ->call('save')
        ->assertHasErrors(['hotelId', 'content']);

    expect(InvoiceFooter::query()->count())->toBe(0);
});

it('lists every footer and deletes one', function (): void {
    $first = Hotel::factory()->create(['name' => 'Alpha Lodge']);
    $second = Hotel::factory()->create(['name' => 'Beta Inn']);
    $footer = InvoiceFooter::factory()->create(['hotel_id' => $first->id, 'content' => 'Alpha footer']);
    InvoiceFooter::factory()->create(['hotel_id' => $second->id, 'content' => 'Beta footer']);
    $this->actingAs(User::factory()->superAdmin()->create());

    $this->get(route('admin.invoice-footers.index'))
        ->assertOk()
        ->assertSeeInOrder(['Alpha Lodge', 'Alpha footer', 'Beta Inn', 'Beta footer'])
        ->assertSee(route('admin.invoice-footers.create', ['hotel' => $first->id]));

    Livewire::test(Index::class)
        ->call('delete', $footer->id)
        ->assertDontSee('Alpha footer')
        ->assertSee('Beta footer');

    expect(InvoiceFooter::query()->whereKey($footer->id)->exists())->toBeFalse();
});
