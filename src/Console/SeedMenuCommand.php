<?php

namespace HoangPhamDev\SimpleAdminGenerator\Console;

use HoangPhamDev\SimpleAdminGenerator\Database\Seeders\AdminMenuItemSeeder;
use Illuminate\Console\Command;

class SeedMenuCommand extends Command
{
    protected $signature = 'sag:seed-menu';

    protected $description = 'Seed the default Simple Admin Generator menu items';

    public function handle(): int
    {
        $status = $this->call('db:seed', [
            '--class' => AdminMenuItemSeeder::class,
        ]);

        if ($status !== self::SUCCESS) {
            return $status;
        }

        $this->info('Default admin menu items seeded.');

        return self::SUCCESS;
    }
}
