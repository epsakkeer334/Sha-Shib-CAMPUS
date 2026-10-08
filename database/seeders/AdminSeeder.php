<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Database\Seeders\admin\{RoleSeeder, CountrySeeder, StateSeeder, MasterDataSeeder};


class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            CountrySeeder::class,
            StateSeeder::class,
            MasterDataSeeder::class,
            \Database\Seeders\admin\AcademicYearSeeder::class,
        ]);
    }
}
