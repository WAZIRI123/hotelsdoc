<?php

use App\Livewire\Settings\Appearance;
use App\Livewire\Settings\Profile;
use App\Livewire\Settings\Roles\Index as RolesIndex;
use App\Livewire\Settings\Security;
use App\Livewire\Settings\Users\Index as UsersIndex;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    Route::livewire('settings/profile', Profile::class)->name('profile.edit');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('settings/appearance', Appearance::class)->name('appearance.edit');

    Route::livewire('settings/security', Security::class)
        ->middleware([
            'password.confirm',
        ])
        ->name('security.edit');
});

Route::middleware(['auth', 'verified', 'tenant'])->group(function () {
    Route::livewire('settings/users', UsersIndex::class)
        ->middleware('can:manage-users')
        ->name('users.index');

    Route::livewire('settings/roles', RolesIndex::class)
        ->middleware('can:manage-roles')
        ->name('roles.index');
});

Route::get('.well-known/passkey-endpoints', function () {
    return response()->json([
        'enroll' => route('security.edit'),
        'manage' => route('security.edit'),
    ]);
})->name('well-known.passkeys');
