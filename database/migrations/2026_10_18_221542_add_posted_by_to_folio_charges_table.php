<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who posted a charge or credit, so a discount taken off a guest bill shows
 * who gave it. Existing rows stay unknown.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('folio_charges', function (Blueprint $table): void {
            $table->foreignId('posted_by')->nullable()->after('posted_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('folio_charges', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('posted_by');
        });
    }
};
