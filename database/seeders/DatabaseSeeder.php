<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * php artisan db:seed
 *
 * Safe to run again at any time: every seeder only adds what is missing.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            PermissionSeeder::class,
            RoleSeeder::class,
            SuperAdminSeeder::class,
            CountrySeeder::class,
            CatalogueSeeder::class,
        ]);

        // Funded test accounts (customer / agent / reseller) on development machines.
        // Elsewhere (not production): php artisan db:seed --class=DemoSeeder
        if (app()->environment('local')) {
            $this->call(DemoSeeder::class);
        }
    }
}
