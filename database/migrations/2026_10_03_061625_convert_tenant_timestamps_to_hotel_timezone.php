<?php

use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Hotel data used to be timestamped in UTC; it is now timestamped in each
 * hotel's own timezone. Shift every stored date-time on tenant tables so
 * existing records keep showing the moment they really happened.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->shift(toHotelTime: true);
    }

    public function down(): void
    {
        $this->shift(toHotelTime: false);
    }

    private function shift(bool $toHotelTime): void
    {
        $hotels = DB::table('hotels')->pluck('timezone', 'id');

        foreach (Schema::getTables() as $table) {
            $name = $table['name'];

            if (! Schema::hasColumn($name, 'hotel_id') || ! Schema::hasColumn($name, 'id')) {
                continue;
            }

            $columns = collect(Schema::getColumns($name))
                ->filter(fn (array $column): bool => in_array(strtolower($column['type_name']), ['datetime', 'timestamp'], true))
                ->pluck('name')
                ->all();

            if ($columns === []) {
                continue;
            }

            foreach ($hotels as $hotelId => $timezone) {
                if ($timezone === null || $timezone === '' || $timezone === 'UTC') {
                    continue;
                }

                [$from, $to] = $toHotelTime ? ['UTC', $timezone] : [$timezone, 'UTC'];

                DB::table($name)
                    ->where('hotel_id', $hotelId)
                    ->select(['id', ...$columns])
                    ->chunkById(500, function ($rows) use ($name, $columns, $from, $to): void {
                        foreach ($rows as $row) {
                            $changes = [];

                            foreach ($columns as $column) {
                                if ($row->{$column} !== null) {
                                    $changes[$column] = CarbonImmutable::parse($row->{$column}, $from)->setTimezone($to)->format('Y-m-d H:i:s');
                                }
                            }

                            if ($changes !== []) {
                                DB::table($name)->where('id', $row->id)->update($changes);
                            }
                        }
                    });
            }
        }
    }
};
