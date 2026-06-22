<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('folio_id')->constrained()->cascadeOnDelete();
            $table->string('method_code')->comment('Stable gateway code: cash|mpesa|...');
            $table->unsignedBigInteger('amount')->comment('Minor units (cents TZS)');
            $table->string('currency', 3)->default('TZS');
            $table->string('reference')->nullable();
            $table->string('status')->default('pending')->comment('pending|confirmed|failed|refunded');
            $table->json('provider_payload')->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();

            $table->index(['hotel_id', 'status']);
            $table->index(['hotel_id', 'method_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
