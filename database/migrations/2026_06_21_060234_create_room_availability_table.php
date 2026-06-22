<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-night materialized availability — single source of truth for both the
 * front-desk calendar and the future channel manager push.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('room_availability', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('room_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('status')->default('open')->comment('open|booked|blocked');
            $table->foreignId('reservation_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->unique(['room_id', 'date']);
            $table->index(['hotel_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_availability');
    }
};
