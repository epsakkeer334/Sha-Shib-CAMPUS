<?php

namespace Database\Seeders\admin;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CountrySeeder extends Seeder
{
    public function run(): void
    {
        DB::table('countries')->updateOrInsert(
            ['code' => 'IN'], // check condition
            [
                'name' => 'India',
                'phone_code' => '+91',
                'status' => true,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }
}
