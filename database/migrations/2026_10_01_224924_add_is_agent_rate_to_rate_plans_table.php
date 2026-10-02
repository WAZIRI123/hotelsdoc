<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rate_plans', function (Blueprint $table): void {
            $table->boolean('is_agent_rate')->default(false)->after('is_active')
                ->comment('Contract (STO) rate, only bookable when an agent is the booker');
        });
    }

    public function down(): void
    {
        Schema::table('rate_plans', function (Blueprint $table): void {
            $table->dropColumn('is_agent_rate');
        });
    }
};
