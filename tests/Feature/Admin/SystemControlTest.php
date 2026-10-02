<?php

declare(strict_types=1);

use App\Livewire\Admin\Hotels\Create;
use App\Livewire\Admin\Hotels\Show;
use App\Livewire\Admin\Organization\SystemControl;
use App\Models\Hotel;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

it('forbids hotel staff from system control', function (): void {
    actingAsTenantUser(role: 'admin');

    $this->get(route('admin.organization.system-control'))->assertForbidden();
});

it('shows every organization with its details and an add button', function (): void {
    $approver = User::factory()->superAdmin()->create(['name' => 'Platform Owner']);
    Hotel::factory()->create([
        'name' => 'Trial Hotel',
        'tin' => '111-111-111',
        'address' => 'Kilimanjaro',
        'phone' => '345453543',
        'email' => 'info@trial.test',
        'fax' => '3454543',
        'street' => 'Moshi',
        'approved_by' => $approver->id,
    ]);
    Hotel::factory()->create(['name' => 'Second Lodge']);

    $this->actingAs($approver);

    $this->get(route('admin.organization.system-control'))
        ->assertOk()
        ->assertSeeInOrder(['Logo', 'Approved-by', 'Edited', 'Action'])
        ->assertSeeInOrder(['Organization', 'Tax Identification Number', 'Address'])
        ->assertSeeInOrder(['Phone', 'Email', 'Fax', 'Street'])
        ->assertSee(['Trial Hotel', '111-111-111', 'Kilimanjaro', '345453543', 'info@trial.test', '3454543', 'Moshi', 'Platform Owner'])
        ->assertSee('Second Lodge')
        ->assertSee('Block System Access')
        ->assertSee('Delete All Operations')
        ->assertSee('Add Organization')
        ->assertSee(route('admin.hotels.create'));
});

it('blocks and restores system access', function (): void {
    $hotel = Hotel::factory()->create();
    $this->actingAs(User::factory()->superAdmin()->create());

    Livewire::test(SystemControl::class)
        ->call('blockAccess', $hotel->id)
        ->assertSee('Restore System Access');

    expect($hotel->fresh()->isSuspended())->toBeTrue();

    Livewire::test(SystemControl::class)->call('restoreAccess', $hotel->id);

    expect($hotel->fresh()->isSuspended())->toBeFalse();
});

it('only deletes a blocked organization after the slug is typed', function (): void {
    $hotel = Hotel::factory()->create();
    $this->actingAs(User::factory()->superAdmin()->create());

    Livewire::test(SystemControl::class)
        ->call('confirmDelete', $hotel->id)
        ->set('deleteConfirmation', $hotel->slug)
        ->call('deleteOperations')
        ->assertHasErrors('deleteConfirmation');

    $hotel->forceFill(['suspended_at' => now()])->save();

    Livewire::test(SystemControl::class)
        ->call('confirmDelete', $hotel->id)
        ->set('deleteConfirmation', 'wrong')
        ->call('deleteOperations')
        ->assertHasErrors('deleteConfirmation');

    expect(Hotel::query()->whereKey($hotel->id)->exists())->toBeTrue();

    Livewire::test(SystemControl::class)
        ->call('confirmDelete', $hotel->id)
        ->set('deleteConfirmation', $hotel->slug)
        ->call('deleteOperations')
        ->assertHasNoErrors();

    expect(Hotel::query()->whereKey($hotel->id)->exists())->toBeFalse();
});

it('records the approver, logo, fax and street when an organization is added', function (): void {
    Storage::fake('public');
    $superAdmin = User::factory()->superAdmin()->create();
    $this->actingAs($superAdmin);

    Livewire::test(Create::class)
        ->set('name', 'Diamond Delight')
        ->set('fax', '3454543')
        ->set('street', 'Moshi')
        ->set('logo', UploadedFile::fake()->image('logo.png'))
        ->set('adminEmail', 'owner@diamond.test')
        ->set('adminName', 'Owner')
        ->set('adminPassword', 'secret-pass')
        ->call('save')
        ->assertHasNoErrors();

    $hotel = Hotel::query()->where('slug', 'diamond-delight')->firstOrFail();
    expect($hotel->approved_by)->toBe($superAdmin->id)
        ->and($hotel->fax)->toBe('3454543')
        ->and($hotel->street)->toBe('Moshi')
        ->and($hotel->logo_path)->not->toBeNull();

    Storage::disk('public')->assertExists($hotel->logo_path);
});

it('records the editor and replaces the logo when details are saved', function (): void {
    Storage::fake('public');
    $hotel = Hotel::factory()->create();
    $superAdmin = User::factory()->superAdmin()->create();
    $this->actingAs($superAdmin);

    Livewire::test(Show::class, ['hotel' => $hotel])
        ->set('logo', UploadedFile::fake()->image('first.png'))
        ->call('saveDetails')
        ->assertHasNoErrors();
    $firstLogo = $hotel->fresh()->logo_path;

    Livewire::test(Show::class, ['hotel' => $hotel->fresh()])
        ->set('street', 'Moshi')
        ->set('logo', UploadedFile::fake()->image('second.png'))
        ->call('saveDetails')
        ->assertHasNoErrors();

    $hotel->refresh();
    expect($hotel->edited_by)->toBe($superAdmin->id)
        ->and($hotel->street)->toBe('Moshi')
        ->and($hotel->logo_path)->not->toBe($firstLogo);

    Storage::disk('public')->assertMissing($firstLogo);
    Storage::disk('public')->assertExists($hotel->logo_path);
});
