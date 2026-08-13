<?php

namespace HoangPhamDev\SimpleAdminGenerator\Console;

use HoangPhamDev\SimpleAdminGenerator\Database\Seeders\AdminSeeder;
use Illuminate\Console\Command;

class SeedAdminCommand extends Command
{
    protected $signature = 'sag:seed-admin';

    protected $description = 'Seed the default Simple Admin Generator administrator account';

    public function handle(): int
    {
        $status = $this->call('db:seed', [
            '--class' => AdminSeeder::class,
        ]);

        if ($status !== self::SUCCESS) {
            return $status;
        }

        $this->info('Default administrator account seeded.');

        return self::SUCCESS;
    }
}
