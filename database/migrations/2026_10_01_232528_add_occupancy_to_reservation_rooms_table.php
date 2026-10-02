<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservation_rooms', function (Blueprint $table): void {
            $table->unsignedSmallInteger('adults')->default(1)->after('rate_plan_id');
            $table->unsignedSmallInteger('children')->default(0)->after('adults');
        });

        // Until now every reservation held a single room, so the reservation-level
        // head count belongs entirely to that room.
        DB::table('reservation_rooms')
            ->join('reservations', 'reservations.id', '=', 'reservation_rooms.reservation_id')
            ->select('reservation_rooms.id', 'reservations.adults', 'reservations.children')
            ->orderBy('reservation_rooms.id')
            ->each(function (object $row): void {
                DB::table('reservation_rooms')->where('id', $row->id)->update([
                    'adults' => $row->adults,
                    'children' => $row->children,
                ]);
            });
    }

    public function down(): void
    {
        Schema::table('reservation_rooms', function (Blueprint $table): void {
            $table->dropColumn(['adults', 'children']);
        });
    }
};
