<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('currencies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->string('code', 3);
            $table->string('name', 60);
            $table->string('symbol', 8)->nullable();
            $table->decimal('exchange_rate', 18, 6);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['hotel_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('currencies');
    }
};
