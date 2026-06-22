<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Payments\Actions\RecordPayment;
use App\Domain\POS\Actions\BillPosOrderToFolio;
use App\Domain\Reservations\Actions\CancelReservation;
use App\Domain\Reservations\Actions\CheckInReservation;
use App\Domain\Reservations\Actions\CheckOutStay;
use App\Domain\Reservations\Actions\CreateReservation;
use App\Domain\Tenancy\TenantContext;
use App\Models\Guest;
use App\Models\Hotel;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Outlet;
use App\Models\PosOrder;
use App\Models\RatePlan;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Populates the database with a realistic playground so manual testing has
 * something to look at without typing it all in.
 *
 * Run via: `php artisan migrate:fresh --seed`
 *
 * Creates:
 *  - Demo Lodge (Tanzanian beach hotel, 12 rooms, full bar + restaurant)
 *  - Coastal Inn (smaller property, demonstrates tenant isolation)
 *  - Staff per hotel with role-typed accounts
 *  - Past reservations across every status, with folios + payments spanning
 *    the last week so the cash-up report has content to show
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedDemoLodge();
        $this->seedCoastalInn();
    }

    private function seedDemoLodge(): void
    {
        $hotel = Hotel::query()->updateOrCreate(
            ['slug' => 'demo-lodge'],
            [
                'name' => 'Demo Lodge',
                'currency' => 'TZS',
                'timezone' => 'Africa/Dar_es_Salaam',
                'phone' => '+255 22 555 0100',
                'email' => 'hello@demo-lodge.test',
                'address' => 'Msasani Peninsula, Dar es Salaam',
                'tin' => '123-456-789',
            ],
        );

        app(TenantContext::class)->set($hotel);

        $testUser = User::query()->updateOrCreate(
            ['email' => 'test@example.com'],
            ['name' => 'Test User', 'password' => Hash::make('password'), 'email_verified_at' => now()],
        );
        $frontDesk = User::query()->updateOrCreate(
            ['email' => 'frontdesk@demo.test'],
            ['name' => 'Neema Front Desk', 'password' => Hash::make('password'), 'email_verified_at' => now()],
        );
        $cashier = User::query()->updateOrCreate(
            ['email' => 'cashier@demo.test'],
            ['name' => 'Salim Cashier', 'password' => Hash::make('password'), 'email_verified_at' => now()],
        );

        $hotel->users()->syncWithoutDetaching([
            $testUser->id => ['role' => 'admin'],
            $frontDesk->id => ['role' => 'front_desk'],
            $cashier->id => ['role' => 'cashier'],
        ]);

        /* ---------- Rooms ---------- */

        $std = RoomType::query()->create([
            'code' => 'STD',
            'name' => 'Standard',
            'description' => 'Comfortable double room with garden view.',
            'base_occupancy' => 2, 'max_occupancy' => 3,
            'default_rate' => Money::fromMajor(60_000),
        ]);
        $dlx = RoomType::query()->create([
            'code' => 'DLX',
            'name' => 'Deluxe',
            'description' => 'Upgraded room with sea view and balcony.',
            'base_occupancy' => 2, 'max_occupancy' => 3,
            'default_rate' => Money::fromMajor(120_000),
        ]);
        $suite = RoomType::query()->create([
            'code' => 'SUITE',
            'name' => 'Suite',
            'description' => 'Two-room suite with lounge.',
            'base_occupancy' => 2, 'max_occupancy' => 4,
            'default_rate' => Money::fromMajor(250_000),
        ]);
        $family = RoomType::query()->create([
            'code' => 'FAM',
            'name' => 'Family',
            'description' => 'Two beds plus pull-out, sleeps 5.',
            'base_occupancy' => 3, 'max_occupancy' => 5,
            'default_rate' => Money::fromMajor(180_000),
        ]);

        RatePlan::query()->create([
            'room_type_id' => $std->id,
            'name' => 'Standard — Off Season',
            'price' => Money::fromMajor(45_000),
            'valid_from' => null, 'valid_to' => null,
        ]);

        $rooms = collect();
        foreach ([['101', '1', $std], ['102', '1', $std], ['103', '1', $std], ['104', '1', $std]] as [$no, $floor, $type]) {
            $rooms->push(Room::query()->create([
                'room_type_id' => $type->id, 'number' => $no, 'floor' => $floor,
            ]));
        }
        foreach ([['201', '2', $dlx], ['202', '2', $dlx], ['203', '2', $dlx]] as [$no, $floor, $type]) {
            $rooms->push(Room::query()->create([
                'room_type_id' => $type->id, 'number' => $no, 'floor' => $floor,
            ]));
        }
        foreach ([['301', '3', $suite], ['302', '3', $suite]] as [$no, $floor, $type]) {
            $rooms->push(Room::query()->create([
                'room_type_id' => $type->id, 'number' => $no, 'floor' => $floor,
            ]));
        }
        foreach ([['401', '4', $family], ['402', '4', $family], ['403', '4', $family]] as [$no, $floor, $type]) {
            $rooms->push(Room::query()->create([
                'room_type_id' => $type->id, 'number' => $no, 'floor' => $floor,
            ]));
        }

        /* ---------- Outlets + menus ---------- */

        $bar = Outlet::query()->create(['name' => 'Beach Bar', 'type' => 'bar']);
        $restaurant = Outlet::query()->create(['name' => 'Pwani Restaurant', 'type' => 'restaurant']);

        $beers = MenuCategory::query()->create(['outlet_id' => $bar->id, 'name' => 'Beers', 'sort_order' => 10]);
        $spirits = MenuCategory::query()->create(['outlet_id' => $bar->id, 'name' => 'Spirits', 'sort_order' => 20]);
        $softs = MenuCategory::query()->create(['outlet_id' => $bar->id, 'name' => 'Soft drinks', 'sort_order' => 30]);

        $mains = MenuCategory::query()->create(['outlet_id' => $restaurant->id, 'name' => 'Mains', 'sort_order' => 10]);
        $sides = MenuCategory::query()->create(['outlet_id' => $restaurant->id, 'name' => 'Sides', 'sort_order' => 20]);
        $sweets = MenuCategory::query()->create(['outlet_id' => $restaurant->id, 'name' => 'Desserts', 'sort_order' => 30]);

        foreach ([
            [$beers, 'Tusker 500ml', 5_000],
            [$beers, 'Kilimanjaro 500ml', 5_000],
            [$beers, 'Serengeti 500ml', 5_500],
            [$beers, 'Castle Lite 330ml', 4_500],
            [$spirits, 'Konyagi (tot)', 4_000],
            [$spirits, 'Gin & Tonic', 8_000],
            [$spirits, 'Whisky on the rocks', 12_000],
            [$softs, 'Coke 300ml', 2_500],
            [$softs, 'Sparkling water 500ml', 3_000],
            [$softs, 'Fresh passion juice', 5_000],
            [$mains, 'Nyama choma platter', 22_000],
            [$mains, 'Pilau na nyama', 15_000],
            [$mains, 'Grilled fish with ugali', 18_000],
            [$mains, 'Chips mayai', 8_000],
            [$mains, 'Vegetarian curry', 14_000],
            [$sides, 'Chapati', 1_500],
            [$sides, 'Wali (rice)', 2_500],
            [$sides, 'Mboga za majani', 3_000],
            [$sweets, 'Mandazi (3 pcs)', 3_000],
            [$sweets, 'Vanilla ice cream', 4_000],
        ] as [$category, $name, $price]) {
            MenuItem::query()->create([
                'outlet_id' => $category->outlet_id,
                'menu_category_id' => $category->id,
                'name' => $name,
                'price' => Money::fromMajor($price),
            ]);
        }

        /* ---------- Guests ---------- */

        $guests = collect([
            ['Asha', 'Mwakyusa', '+255 712 100 001', 'asha@example.com', 'Tanzania', 'national_id', 'TZ-19850101-001'],
            ['Yusuf', 'Kibwana', '+255 713 100 002', 'yusuf@example.com', 'Tanzania', 'national_id', 'TZ-19770304-007'],
            ['Maria', 'Schmidt', '+49 151 22 33 44', 'maria@example.de', 'Germany', 'passport', 'C0123ABCD'],
            ['Akiko', 'Tanaka', '+81 90 1234 5678', 'akiko@example.jp', 'Japan', 'passport', 'TK8472921'],
            ['Daniel', 'Otieno', '+254 722 555 010', 'daniel@example.co.ke', 'Kenya', 'passport', 'BK0099122'],
            ['Sara', 'Ahmed', '+971 50 555 0101', 'sara@example.ae', 'UAE', 'passport', 'A12345678'],
            ['John', 'Mbeki', '+27 82 555 1010', 'john@example.co.za', 'South Africa', 'passport', 'M9988776'],
            ['Lucia', 'Rossi', '+39 320 555 7788', 'lucia@example.it', 'Italy', 'passport', 'YA1234567'],
            ['Pierre', 'Dubois', '+33 6 12 34 56 78', 'pierre@example.fr', 'France', 'passport', 'FR889922'],
            ['Hawa', 'Mussa', '+255 754 100 010', 'hawa@example.com', 'Tanzania', 'national_id', 'TZ-19920811-022'],
        ])->map(fn (array $g) => Guest::query()->create([
            'first_name' => $g[0], 'last_name' => $g[1], 'phone' => $g[2], 'email' => $g[3],
            'nationality' => $g[4], 'id_type' => $g[5], 'id_number' => $g[6],
        ]));

        /* ---------- Reservations across statuses ---------- */

        $today = CarbonImmutable::today();
        $createRes = app(CreateReservation::class);
        $checkIn = app(CheckInReservation::class);
        $checkOut = app(CheckOutStay::class);
        $cancel = app(CancelReservation::class);
        $recordPay = app(RecordPayment::class);
        $bill = app(BillPosOrderToFolio::class);

        // 4 fully-completed past stays (settled + receipts + cash-up data)
        $completed = [
            [$guests[0], $rooms[0], $today->subDays(8), $today->subDays(5), 'cash'],
            [$guests[1], $rooms[4], $today->subDays(7), $today->subDays(4), 'mpesa'],
            [$guests[2], $rooms[7], $today->subDays(5), $today->subDays(2), 'cash'],
            [$guests[3], $rooms[1], $today->subDays(4), $today->subDays(1), 'crdb'],
        ];

        foreach ($completed as [$guest, $room, $arrival, $departure, $method]) {
            $res = $createRes->execute(
                guestId: $guest->id,
                roomTypeId: $room->room_type_id,
                roomId: $room->id,
                arrival: $arrival,
                departure: $departure,
            );
            CarbonImmutable::setTestNow($arrival->setTime(14, 0));
            $stay = $checkIn->execute($res->fresh(), $testUser);

            CarbonImmutable::setTestNow($departure->setTime(10, 30));
            $folio = $stay->folio->fresh();
            $recordPay->execute(
                folio: $folio,
                methodCode: $method,
                amount: $folio->total_amount,
                reference: $method === 'cash' ? null : 'REF-'.strtoupper(substr(md5($res->id.$method), 0, 8)),
                receivedBy: $cashier,
            );
            $checkOut->execute($stay->fresh(), $testUser);
        }
        CarbonImmutable::setTestNow();

        // 3 in-house stays (some with bar tabs already charged)
        $inHouse = [
            [$guests[4], $rooms[2], $today->subDays(1), $today->addDays(2)],
            [$guests[5], $rooms[5], $today, $today->addDays(3)],
            [$guests[6], $rooms[8], $today->subDays(2), $today->addDays(1)],
        ];

        $inHouseStays = collect();
        foreach ($inHouse as [$guest, $room, $arrival, $departure]) {
            $res = $createRes->execute(
                guestId: $guest->id,
                roomTypeId: $room->room_type_id,
                roomId: $room->id,
                arrival: $arrival,
                departure: $departure,
            );
            CarbonImmutable::setTestNow($arrival->setTime(15, 0));
            $stay = $checkIn->execute($res->fresh(), $frontDesk);
            $inHouseStays->push($stay);
        }
        CarbonImmutable::setTestNow();

        // Bar tab on the first two in-house guests
        $tuskerId = (int) MenuItem::query()->where('name', 'Tusker 500ml')->where('outlet_id', $bar->id)->value('id');
        $chipsId = (int) MenuItem::query()->where('name', 'Chips mayai')->value('id');

        foreach ([$inHouseStays[0], $inHouseStays[1]] as $stay) {
            $order = PosOrder::query()->create([
                'outlet_id' => $bar->id,
                'opened_by' => $cashier->id,
                'table_no' => (string) random_int(1, 12),
            ]);
            foreach ([[$tuskerId, 3, 5_000], [$chipsId, 1, 8_000]] as [$itemId, $qty, $price]) {
                $order->items()->create([
                    'menu_item_id' => $itemId,
                    'name_snapshot' => MenuItem::query()->findOrFail($itemId)->name,
                    'quantity' => $qty,
                    'unit_price' => Money::fromMajor($price),
                    'line_total' => Money::fromMajor($price * $qty),
                ]);
            }
            app(\App\Domain\POS\Actions\RecalculatePosOrderTotals::class)->execute($order);
            $bill->execute($order->fresh(), $stay->folio);
        }

        // 3 upcoming (confirmed, future arrival)
        foreach ([
            [$guests[7], $rooms[10], $today->addDays(2), $today->addDays(5)],
            [$guests[8], $rooms[3], $today->addDays(4), $today->addDays(7)],
            [$guests[9], $rooms[6], $today->addDays(10), $today->addDays(14)],
        ] as [$guest, $room, $arrival, $departure]) {
            $createRes->execute(
                guestId: $guest->id,
                roomTypeId: $room->room_type_id,
                roomId: $room->id,
                arrival: $arrival,
                departure: $departure,
            );
        }

        // 1 cancelled
        $cancelled = $createRes->execute(
            guestId: $guests[0]->id,
            roomTypeId: $rooms[11]->room_type_id,
            roomId: $rooms[11]->id,
            arrival: $today->addDays(20),
            departure: $today->addDays(22),
        );
        $cancel->execute($cancelled->fresh());

        // Walk-in bar sales (last 2 days) so today's cash-up has variety
        foreach ([1, 0] as $daysAgo) {
            CarbonImmutable::setTestNow($today->subDays($daysAgo)->setTime(20, 15));
            $order = PosOrder::query()->create([
                'outlet_id' => $bar->id,
                'opened_by' => $cashier->id,
                'table_no' => (string) random_int(1, 12),
            ]);
            $order->items()->create([
                'menu_item_id' => $tuskerId,
                'name_snapshot' => 'Tusker 500ml',
                'quantity' => 2,
                'unit_price' => Money::fromMajor(5_000),
                'line_total' => Money::fromMajor(10_000),
            ]);
            app(\App\Domain\POS\Actions\RecalculatePosOrderTotals::class)->execute($order);
            $folio = $bill->settleAsWalkIn($order->fresh());
            $recordPay->execute(
                folio: $folio,
                methodCode: 'cash',
                amount: $folio->total_amount,
                receivedBy: $cashier,
            );
        }
        CarbonImmutable::setTestNow();

        app(TenantContext::class)->clear();
    }

    private function seedCoastalInn(): void
    {
        $hotel = Hotel::query()->updateOrCreate(
            ['slug' => 'coastal-inn'],
            [
                'name' => 'Coastal Inn',
                'currency' => 'TZS',
                'timezone' => 'Africa/Dar_es_Salaam',
                'phone' => '+255 23 555 0200',
                'address' => 'Bagamoyo Road, Tanga',
            ],
        );

        app(TenantContext::class)->set($hotel);

        $manager = User::query()->updateOrCreate(
            ['email' => 'manager@coastal.test'],
            ['name' => 'Coastal Manager', 'password' => Hash::make('password'), 'email_verified_at' => now()],
        );
        $hotel->users()->syncWithoutDetaching([$manager->id => ['role' => 'admin']]);

        $std = RoomType::query()->create([
            'code' => 'STD', 'name' => 'Standard',
            'base_occupancy' => 2, 'max_occupancy' => 2,
            'default_rate' => Money::fromMajor(40_000),
        ]);
        foreach (['1', '2', '3', '4', '5'] as $n) {
            Room::query()->create(['room_type_id' => $std->id, 'number' => $n, 'floor' => '1']);
        }

        Outlet::query()->create(['name' => 'Reception Bar', 'type' => 'bar']);

        Guest::query()->create([
            'first_name' => 'Walk', 'last_name' => 'In', 'phone' => '+255 700 000 000',
        ]);

        app(TenantContext::class)->clear();
    }
}
