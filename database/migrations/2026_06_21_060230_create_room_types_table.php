<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('room_types', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->string('code');
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('base_occupancy')->default(2);
            $table->unsignedSmallInteger('max_occupancy')->default(2);
            $table->unsignedSmallInteger('max_adults')->default(2);
            $table->unsignedSmallInteger('max_children')->default(2);
            $table->unsignedBigInteger('default_rate')->comment('Minor units (cents TZS)');
            $table->unsignedBigInteger('extra_guest_fee')->default(0)->comment('Per-guest-per-night surcharge above base_occupancy (minor units)');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['hotel_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_types');
    }
};
