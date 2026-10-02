<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('expense_date');
            $table->string('category');
            $table->string('description');
            $table->string('payee')->nullable();
            $table->string('method_code')->nullable();
            $table->string('currency', 3);
            $table->unsignedBigInteger('amount')->comment('Minor units, expense currency');
            $table->timestamps();

            $table->index(['hotel_id', 'expense_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
