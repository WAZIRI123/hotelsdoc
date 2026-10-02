<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table): void {
            $table->string('pv_number', 50)->nullable()->after('expense_date')->comment('Payment voucher number');
            $table->decimal('quantity', 12, 2)->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table): void {
            $table->dropColumn(['pv_number', 'quantity']);
        });
    }
};
