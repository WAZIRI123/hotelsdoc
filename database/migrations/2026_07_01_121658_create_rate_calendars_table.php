<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rate_calendars', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('rate_plan_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->unsignedBigInteger('price')->nullable()->comment('Per-night override, minor units. Null = use rate_plan.price');
            $table->unsignedSmallInteger('min_stay')->nullable()->comment('Minimum stay length if arrival lands on this date');
            $table->boolean('stop_sell')->default(false);
            $table->timestamps();

            $table->unique(['rate_plan_id', 'date']);
            $table->index(['hotel_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rate_calendars');
    }
};
