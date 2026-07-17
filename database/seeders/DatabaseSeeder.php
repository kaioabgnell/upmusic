<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database. Ordem respeita as dependências (ver specs/03).
     */
    public function run(): void
    {
        $this->call([
            SetorSeeder::class,
            UserSeeder::class,
            BoardSeeder::class,
            SampleDataSeeder::class,
        ]);
    }
}
