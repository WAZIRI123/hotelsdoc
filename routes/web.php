<?php

use App\Livewire\Folios\Show as FolioShow;
use App\Livewire\Guests\GuestList as GuestsList;
use App\Livewire\Guests\Index as GuestsIndex;
use App\Livewire\Guests\Register as GuestsRegister;
use App\Livewire\MealPlans\Index as MealPlansIndex;
use App\Livewire\Menu\Index as MenuIndex;
use App\Livewire\Outlets\Index as OutletsIndex;
use App\Livewire\POS\Lobby as PosLobby;
use App\Livewire\POS\Terminal as PosTerminal;
use App\Livewire\POS\Transaction as PosTransaction;
use App\Livewire\RatePlans\Calendar as RatePlansCalendar;
use App\Livewire\RatePlans\Index as RatePlansIndex;
use App\Livewire\Reports\CashUp;
use App\Livewire\Reservations\Create as ReservationsCreate;
use App\Livewire\Reservations\Index as ReservationsIndex;
use App\Livewire\Reservations\Show as ReservationsShow;
use App\Livewire\Rooms\Index as RoomsIndex;
use App\Livewire\RoomTypes\Index as RoomTypesIndex;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login')->name('home');

Route::middleware(['auth', 'verified', 'tenant'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');

    Route::livewire('room-types', RoomTypesIndex::class)->name('room-types.index');
    Route::livewire('meal-plans', MealPlansIndex::class)->name('meal-plans.index');
    Route::livewire('rate-plans', RatePlansIndex::class)->name('rate-plans.index');
    Route::livewire('rate-plans/{ratePlan}/calendar', RatePlansCalendar::class)->name('rate-plans.calendar');
    Route::livewire('rooms', RoomsIndex::class)->name('rooms.index');

    Route::livewire('guests', GuestsIndex::class)->name('guests.index');
    Route::livewire('guests/register', GuestsRegister::class)->name('guests.register');
    Route::livewire('guests/list', GuestsList::class)->name('guests.list');

    Route::livewire('reservations', ReservationsIndex::class)->name('reservations.index');
    Route::livewire('reservations/new', ReservationsCreate::class)->name('reservations.create');
    Route::livewire('reservations/{reservation}', ReservationsShow::class)->name('reservations.show');

    Route::livewire('folios/{folio}', FolioShow::class)->name('folios.show');

    Route::livewire('outlets', OutletsIndex::class)->name('outlets.index');
    Route::livewire('outlets/{outlet}/menu', MenuIndex::class)->name('menu.index');

    Route::livewire('pos', PosLobby::class)->name('pos.lobby');
    Route::livewire('pos/transactions/{posOrder}', PosTransaction::class)->name('pos.transactions.show');
    Route::livewire('pos/{outlet}', PosTerminal::class)->name('pos.terminal');

    Route::livewire('reports/cash-up', CashUp::class)->name('reports.cash-up');
});

require __DIR__.'/settings.php';
