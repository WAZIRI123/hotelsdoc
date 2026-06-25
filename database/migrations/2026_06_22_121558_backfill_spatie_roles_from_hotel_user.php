<?php

declare(strict_types=1);

use App\Domain\Authorization\Listeners\SeedHotelRoles;
use App\Models\Hotel;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        $seeder = app(SeedHotelRoles::class);
        $registrar = app(PermissionRegistrar::class);

        Hotel::query()->get()->each(function (Hotel $hotel) use ($seeder, $registrar): void {
            $seeder->handle($hotel);

            DB::table('hotel_user')
                ->where('hotel_id', $hotel->id)
                ->get()
                ->each(function ($row) use ($hotel, $registrar): void {
                    /** @var User|null $user */
                    $user = User::query()->find($row->user_id);
                    if ($user === null) {
                        return;
                    }

                    $registrar->setPermissionsTeamId($hotel->id);
                    $user->unsetRelation('roles');
                    $user->syncRoles([$row->role]);
                });
        });

        $registrar->setPermissionsTeamId(null);

        if (Schema::hasColumn('hotel_user', 'role')) {
            Schema::table('hotel_user', function (Blueprint $table): void {
                $table->dropColumn('role');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('hotel_user', 'role')) {
            Schema::table('hotel_user', function (Blueprint $table): void {
                $table->string('role')->default('staff')->comment('admin|manager|front_desk|cashier|waiter|staff');
            });
        }
    }
};
