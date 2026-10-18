<?php

use App\Models\Expense;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->string('description')->nullable();
            $table->timestamps();

            $table->unique(['hotel_id', 'name']);
        });

        // Give every existing hotel the default categories plus any category
        // already used on its recorded expenses.
        $now = now();

        foreach (DB::table('hotels')->pluck('id') as $hotelId) {
            $used = DB::table('expenses')->where('hotel_id', $hotelId)->distinct()->pluck('category')->all();

            $rows = collect([...Expense::CATEGORIES, ...$used])
                ->unique()
                ->map(fn (string $name): array => [
                    'hotel_id' => $hotelId,
                    'name' => $name,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])
                ->values()
                ->all();

            DB::table('expense_categories')->insertOrIgnore($rows);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_categories');
    }
};
