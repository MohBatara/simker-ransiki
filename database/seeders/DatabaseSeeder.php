<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(MasterDataSeeder::class);

        // Data contoh dan akun uji (kata sandi "password") tidak boleh ada di server.
        if (app()->isLocal()) {
            $this->call(DemoSeeder::class);
        }
    }
}
