<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rate_plans', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('room_type_id')->constrained()->cascadeOnDelete();
            $table->foreignId('meal_plan_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->unsignedBigInteger('price')->comment('Minor units (cents TZS)');
            $table->unsignedBigInteger('extra_adult_price')->default(0)->comment('Per extra adult per night (minor units)');
            $table->unsignedBigInteger('extra_child_price')->default(0)->comment('Per extra child per night (minor units)');
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['hotel_id', 'room_type_id']);
            $table->index(['hotel_id', 'meal_plan_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rate_plans');
    }
};
