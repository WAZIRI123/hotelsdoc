<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('agent_qualification_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('phone', 32)->nullable();
            $table->string('email')->nullable();
            $table->string('tin', 32)->nullable()->comment('Taxpayer Identification Number');
            $table->string('vrn', 32)->nullable()->comment('VAT Registration Number');
            $table->string('address', 500)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['hotel_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agents');
    }
};
