<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('folio_charges', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('folio_id')->constrained()->cascadeOnDelete();
            $table->string('source_type')->comment('room|pos_order|manual|tax|discount');
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('description');
            $table->unsignedInteger('quantity')->default(1);
            $table->unsignedBigInteger('unit_price')->default(0);
            $table->unsignedBigInteger('tax_amount')->default(0);
            $table->bigInteger('total')->default(0);
            $table->timestamp('posted_at')->useCurrent();
            $table->timestamps();

            $table->index(['hotel_id', 'folio_id']);
            $table->index(['source_type', 'source_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('folio_charges');
    }
};
