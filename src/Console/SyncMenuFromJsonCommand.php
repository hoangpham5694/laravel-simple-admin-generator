<?php

namespace HoangPhamDev\SimpleAdminGenerator\Console;

use HoangPhamDev\SimpleAdminGenerator\Services\AdminMenuJsonService;
use Illuminate\Console\Command;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Validation\ValidationException;
use JsonException;

class SyncMenuFromJsonCommand extends Command
{
    protected $signature = 'sag:sync-menu-from-json
                            {--connection= : Database connection to import into}';

    protected $description = 'Replace all admin menu records with the configured menu JSON file';

    public function handle(AdminMenuJsonService $service): int
    {
        try {
            $count = $service->syncFromJson($this->option('connection') ?: null);
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $messages) {
                foreach ($messages as $message) {
                    $this->error($message);
                }
            }

            return self::FAILURE;
        } catch (FileNotFoundException | JsonException $exception) {
            $this->error('Unable to import admin menus: ' . $exception->getMessage());

            return self::FAILURE;
        }

        $this->info("Admin menu records replaced from JSON: {$count} items imported.");

        return self::SUCCESS;
    }
}
