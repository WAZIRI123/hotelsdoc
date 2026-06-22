<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Demo seeder relies on the Hotel::created listener to seed default
        // payment methods — do not use WithoutModelEvents here.
        $this->call(DemoDataSeeder::class);
    }
}
