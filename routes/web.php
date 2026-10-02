<?php

use App\Domain\Tenancy\TenantContext;
use App\Http\Controllers\SwitchHotelController;
use App\Livewire\Admin\Hotels\Create as AdminHotelsCreate;
use App\Livewire\Admin\Hotels\Index as AdminHotelsIndex;
use App\Livewire\Admin\Hotels\Show as AdminHotelsShow;
use App\Livewire\Admin\InvoiceFooters\Create as AdminInvoiceFootersCreate;
use App\Livewire\Admin\InvoiceFooters\Index as AdminInvoiceFootersIndex;
use App\Livewire\Admin\Organization\SystemControl as AdminSystemControl;
use App\Livewire\Agents\Index as AgentsIndex;
use App\Livewire\Agents\Register as AgentsRegister;
use App\Livewire\Extras\Create as ExtrasCreate;
use App\Livewire\Extras\Orders as ExtrasOrders;
use App\Livewire\Extras\Services as ExtrasServices;
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
use App\Livewire\Reports\Agents as AgentsReport;
use App\Livewire\Reports\CashUp;
use App\Livewire\Reports\Credit as CreditReport;
use App\Livewire\Reports\DrinksSales as DrinksSalesReport;
use App\Livewire\Reports\Expenses as ExpensesReport;
use App\Livewire\Reports\ExtrasSales as ExtrasSalesReport;
use App\Livewire\Reports\FoodSales as FoodSalesReport;
use App\Livewire\Reports\Index as ReportsIndex;
use App\Livewire\Reports\ProfitLoss as ProfitLossReport;
use App\Livewire\Reports\StockProducts as StockProductsReport;
use App\Livewire\Reservations\Create as ReservationsCreate;
use App\Livewire\Reservations\Index as ReservationsIndex;
use App\Livewire\Reservations\Show as ReservationsShow;
use App\Livewire\Rooms\Index as RoomsIndex;
use App\Livewire\RoomTypes\Index as RoomTypesIndex;
use App\Livewire\Stock\Issue as StockIssue;
use App\Livewire\StockTypes\Index as StockTypesIndex;
use App\Livewire\StockTypes\Register as StockTypesRegister;
use App\Livewire\Suppliers\Index as SuppliersIndex;
use App\Livewire\Suppliers\Register as SuppliersRegister;
use App\Models\Folio;
use App\Models\Outlet;
use App\Models\PosOrder;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login')->name('home');

Route::middleware(['auth', 'verified', 'tenant'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');

    Route::middleware('can:manage-rooms')->group(function () {
        Route::view('room-settings', 'room-settings')->name('room-settings');
        Route::livewire('meal-plans', MealPlansIndex::class)->name('meal-plans.index');
        Route::livewire('rate-plans', RatePlansIndex::class)->name('rate-plans.index');
        Route::livewire('rate-plans/{ratePlan}/calendar', RatePlansCalendar::class)->name('rate-plans.calendar');
        Route::livewire('rooms', RoomsIndex::class)->name('rooms.index');
    });
    Route::livewire('room-types', RoomTypesIndex::class)->middleware('can:manage-room-types')->name('room-types.index');

    Route::middleware('can:manage-guests')->group(function () {
        Route::livewire('guests', GuestsIndex::class)->name('guests.index');
        Route::livewire('guests/register', GuestsRegister::class)->name('guests.register');
        Route::livewire('guests/list', GuestsList::class)->name('guests.list');
        Route::livewire('guests/{guest}/edit', GuestsRegister::class)->name('guests.edit');
    });

    Route::middleware('can:manage-reservations')->group(function () {
        Route::livewire('agents', AgentsIndex::class)->name('agents.index');
        Route::livewire('agents/register', AgentsRegister::class)->name('agents.register');
        Route::livewire('agents/{agent}/edit', AgentsRegister::class)->name('agents.edit');

        Route::livewire('reservations', ReservationsIndex::class)->name('reservations.index');
        Route::livewire('reservations/new', ReservationsCreate::class)->name('reservations.create');
        Route::livewire('reservations/{reservation}', ReservationsShow::class)->name('reservations.show');
    });

    Route::middleware('can:manage-folios')->group(function () {
        Route::livewire('folios/{folio}', FolioShow::class)->name('folios.show');
        Route::get('folios/{folio}/print', function (Folio $folio) {
            $folio->load(['charges', 'payments', 'guest', 'stay.reservation.rooms.roomType']);

            return view('folios.print', [
                'folio' => $folio,
                'hotel' => app(TenantContext::class)->current(),
            ]);
        })->name('folios.print');

        Route::livewire('extras', ExtrasCreate::class)->name('extras.create');
        Route::livewire('extras/orders', ExtrasOrders::class)->name('extras.orders');
    });
    Route::livewire('extras/services', ExtrasServices::class)->middleware('can:manage-hotel')->name('extras.services');

    Route::middleware('can:manage-stock')->group(function () {
        Route::get('manage-stock', fn () => view('manage-stock', [
            'outlets' => Outlet::query()->orderByDesc('is_active')->orderBy('name')->get(),
        ]))->name('manage-stock');
        Route::get('manage-stock/{outlet}', fn (Outlet $outlet) => view('manage-stock-outlet', [
            'outlet' => $outlet,
        ]))->name('manage-stock.outlet');
        Route::livewire('manage-stock/{outlet}/issue', StockIssue::class)->name('manage-stock.issue');
        Route::livewire('suppliers', SuppliersIndex::class)->name('suppliers.index');
        Route::livewire('suppliers/new', SuppliersRegister::class)->name('suppliers.create');
        Route::livewire('suppliers/{supplier}/edit', SuppliersRegister::class)->name('suppliers.edit');
        Route::livewire('stock-types', StockTypesIndex::class)->name('stock-types.index');
        Route::livewire('stock-types/new', StockTypesRegister::class)->name('stock-types.create');
        Route::livewire('stock-types/{stockType}/edit', StockTypesRegister::class)->name('stock-types.edit');
    });
    Route::livewire('outlets', OutletsIndex::class)->middleware('can:manage-outlets')->name('outlets.index');
    Route::livewire('outlets/{outlet}/menu', MenuIndex::class)->middleware('can:manage-menu')->name('menu.index');

    Route::middleware('can:operate-pos')->group(function () {
        Route::livewire('pos', PosLobby::class)->name('pos.lobby');
        Route::livewire('pos/transactions/{posOrder}', PosTransaction::class)->name('pos.transactions.show');
        Route::get('pos/transactions/{posOrder}/print', function (PosOrder $posOrder) {
            $posOrder->load(['outlet', 'items', 'folio.guest', 'folio.payments']);

            return view('pos.transactions.print', [
                'order' => $posOrder,
                'hotel' => app(TenantContext::class)->current(),
            ]);
        })->name('pos.transactions.print');
        Route::livewire('pos/{outlet}', PosTerminal::class)->name('pos.terminal');
    });

    Route::livewire('reports', ReportsIndex::class)->name('reports.index');
    Route::livewire('reports/cash-up', CashUp::class)->name('reports.cash-up');
    Route::livewire('reports/drinks-sales', DrinksSalesReport::class)->name('reports.drinks-sales');
    Route::livewire('reports/food-sales', FoodSalesReport::class)->name('reports.food-sales');
    Route::livewire('reports/extras-sales', ExtrasSalesReport::class)->name('reports.extras-sales');
    Route::livewire('reports/stock', StockProductsReport::class)->name('reports.stock');
    Route::livewire('reports/expenses', ExpensesReport::class)->name('reports.expenses');
    Route::livewire('reports/credit', CreditReport::class)->name('reports.credit');
    Route::livewire('reports/agents', AgentsReport::class)->name('reports.agents');
    Route::livewire('reports/profit-loss', ProfitLossReport::class)->name('reports.profit-loss');
});

// Outside 'tenant' on purpose: this is where the active tenant gets chosen.
Route::post('hotels/{hotel}/switch', SwitchHotelController::class)
    ->middleware(['auth', 'verified'])
    ->name('hotels.switch');

// Platform (cross-tenant) admin — deliberately outside the 'tenant' middleware.
Route::middleware(['auth', 'verified', 'super-admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::view('organization', 'admin.organization')->name('organization');
    Route::livewire('organization/system-control', AdminSystemControl::class)->name('organization.system-control');
    Route::livewire('invoice-footers', AdminInvoiceFootersIndex::class)->name('invoice-footers.index');
    Route::livewire('invoice-footers/new', AdminInvoiceFootersCreate::class)->name('invoice-footers.create');
    Route::livewire('hotels', AdminHotelsIndex::class)->name('hotels.index');
    Route::livewire('hotels/new', AdminHotelsCreate::class)->name('hotels.create');
    Route::livewire('hotels/{hotel}', AdminHotelsShow::class)->name('hotels.show');
});

require __DIR__.'/settings.php';
