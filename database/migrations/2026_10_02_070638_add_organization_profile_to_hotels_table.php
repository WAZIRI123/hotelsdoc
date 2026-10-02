<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hotels', function (Blueprint $table): void {
            $table->string('logo_path')->nullable()->after('name');
            $table->string('fax', 32)->nullable()->after('phone');
            $table->string('street')->nullable()->after('address');
            $table->foreignId('approved_by')->nullable()->after('suspended_at')->constrained('users')->nullOnDelete();
            $table->foreignId('edited_by')->nullable()->after('approved_by')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('hotels', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('edited_by');
            $table->dropConstrainedForeignId('approved_by');
            $table->dropColumn(['logo_path', 'fax', 'street']);
        });
    }
};
