<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservations', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('guest_id')->constrained()->cascadeOnDelete();
            $table->string('confirmation_no')->unique();
            $table->string('status')->default('confirmed')->comment('hold|confirmed|checked_in|checked_out|cancelled|no_show');
            $table->date('arrival');
            $table->date('departure');
            $table->unsignedSmallInteger('adults')->default(1);
            $table->unsignedSmallInteger('children')->default(0);
            $table->string('source')->default('walk_in')->comment('walk_in|phone|website|booking_com|expedia|airbnb');
            $table->string('external_id')->nullable()->comment('OTA reservation ID for idempotent imports');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['source', 'external_id']);
            $table->index(['hotel_id', 'status']);
            $table->index(['hotel_id', 'arrival']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservations');
    }
};
