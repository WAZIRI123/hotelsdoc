<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_issues', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('menu_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('to_menu_item_id')->nullable()->constrained('menu_items')->nullOnDelete();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('issue_date');
            $table->string('reason', 20);
            $table->string('issued_to')->nullable();
            $table->string('description')->nullable();
            $table->unsignedInteger('quantity');
            $table->timestamps();

            $table->index(['hotel_id', 'issue_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_issues');
    }
};
