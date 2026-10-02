<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('extra_orders', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stay_id')->nullable()->comment('Null for walk-in customers')->constrained()->nullOnDelete();
            $table->foreignId('folio_id')->nullable()->comment('Set when posted to an in-house guest folio')->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reference')->unique();
            $table->string('currency', 3);
            $table->date('order_date');
            $table->unsignedBigInteger('total_amount')->default(0);
            $table->timestamps();

            $table->index(['hotel_id', 'order_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('extra_orders');
    }
};
