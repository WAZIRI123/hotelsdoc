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
        Schema::create('room_occupants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reservation_room_id')->constrained()->cascadeOnDelete();
            $table->foreignId('guest_id')->nullable()->comment('Null = unnamed placeholder until check-in')->constrained()->nullOnDelete();
            $table->string('age_category')->default('adult')->comment('adult|child');
            $table->boolean('is_primary')->default(false);
            $table->timestamp('checked_in_at')->nullable();
            $table->timestamp('checked_out_at')->nullable()->comment('Set when the person leaves the room (removed, transferred or checked out)');
            $table->foreignId('added_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('removed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['hotel_id', 'reservation_room_id']);
            $table->index(['hotel_id', 'guest_id']);
        });

        $this->backfillExistingRooms();
    }

    public function down(): void
    {
        Schema::dropIfExists('room_occupants');
    }

    /**
     * Seed one primary occupant (the booker) plus unnamed placeholders for the
     * rest of each existing room's head count.
     */
    private function backfillExistingRooms(): void
    {
        DB::table('reservation_rooms')
            ->join('reservations', 'reservations.id', '=', 'reservation_rooms.reservation_id')
            ->leftJoin('stays', 'stays.reservation_id', '=', 'reservations.id')
            ->select([
                'reservation_rooms.id', 'reservation_rooms.hotel_id', 'reservation_rooms.adults',
                'reservation_rooms.children', 'reservations.guest_id', 'reservations.status',
                'stays.checked_in_at', 'stays.checked_out_at',
            ])
            ->whereNotIn('reservations.status', ['cancelled', 'no_show'])
            ->orderBy('reservation_rooms.id')
            ->each(function (object $row): void {
                $categories = array_merge(
                    array_fill(0, max(1, (int) $row->adults), 'adult'),
                    array_fill(0, (int) $row->children, 'child'),
                );

                $now = now();
                $rows = [];
                foreach ($categories as $index => $category) {
                    $rows[] = [
                        'hotel_id' => $row->hotel_id,
                        'reservation_room_id' => $row->id,
                        'guest_id' => $index === 0 ? $row->guest_id : null,
                        'age_category' => $category,
                        'is_primary' => $index === 0,
                        'checked_in_at' => $row->checked_in_at,
                        'checked_out_at' => $row->checked_out_at,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                DB::table('room_occupants')->insert($rows);
            });
    }
};
