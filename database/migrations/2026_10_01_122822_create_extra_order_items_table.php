<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('extra_order_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('extra_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('extra_service_id')->constrained()->restrictOnDelete();
            $table->string('name_snapshot');
            $table->unsignedInteger('quantity')->default(1);
            $table->unsignedBigInteger('unit_price');
            $table->unsignedBigInteger('line_total');
            $table->timestamps();

            $table->index(['hotel_id', 'extra_order_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('extra_order_items');
    }
};
