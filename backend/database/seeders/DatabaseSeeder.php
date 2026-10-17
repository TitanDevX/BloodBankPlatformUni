<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Modules\Identity\Database\Seeders\IdentityDatabaseSeeder;
use Modules\Organizations\Database\Seeders\OrganizationsDatabaseSeeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(OrganizationsDatabaseSeeder::class);
        $this->call(IdentityDatabaseSeeder::class);
    }
}
