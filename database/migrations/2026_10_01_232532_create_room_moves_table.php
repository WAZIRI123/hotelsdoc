<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('room_moves', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reservation_room_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_room_id')->nullable()->constrained('rooms')->nullOnDelete();
            $table->foreignId('to_room_id')->nullable()->constrained('rooms')->nullOnDelete();
            $table->date('effective_date')->comment('First night spent in the new room');
            $table->string('rate_action')->default('keep_rate')->comment('keep_rate|new_rate');
            $table->string('reason')->nullable();
            $table->foreignId('moved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['hotel_id', 'reservation_room_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_moves');
    }
};
