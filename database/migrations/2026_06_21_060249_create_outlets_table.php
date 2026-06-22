<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outlets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('type')->default('bar')->comment('bar|restaurant|room_service|shop');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['hotel_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outlets');
    }
};
