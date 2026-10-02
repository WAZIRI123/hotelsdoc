<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('menu_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('entry_date');
            $table->date('expiry_date')->nullable();
            $table->string('description')->nullable();
            $table->unsignedInteger('quantity');
            $table->timestamps();

            $table->index(['hotel_id', 'entry_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_entries');
    }
};
