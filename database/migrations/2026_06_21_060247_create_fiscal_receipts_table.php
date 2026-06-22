<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Receipt-of-record table. Always populated, even when MVP uses the internal
 * issuer. Future TRA VFD integration fills tra_verification_code + qr_payload.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fiscal_receipts', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('folio_id')->constrained()->cascadeOnDelete();
            $table->string('provider')->default('internal')->comment('internal|tra_vfd');
            $table->unsignedBigInteger('sequence');
            $table->string('receipt_no');
            $table->string('status')->default('pending')->comment('pending|issued|failed');
            $table->unsignedBigInteger('total_amount');
            $table->string('tra_verification_code')->nullable();
            $table->text('qr_payload')->nullable();
            $table->json('raw_response')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->timestamps();

            $table->unique(['hotel_id', 'sequence']);
            $table->index(['hotel_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fiscal_receipts');
    }
};
