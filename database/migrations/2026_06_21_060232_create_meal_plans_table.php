<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meal_plans', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->string('code')->comment('EP|CP|BB|HB|FB|AI or custom');
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('includes_breakfast')->default(false);
            $table->boolean('includes_lunch')->default(false);
            $table->boolean('includes_dinner')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['hotel_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meal_plans');
    }
};
