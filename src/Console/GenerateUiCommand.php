<?php

namespace HoangPhamDev\SimpleAdminGenerator\Console;

use HoangPhamDev\SimpleAdminGenerator\Enums\AdminMenuLinkType;
use HoangPhamDev\SimpleAdminGenerator\Models\AdminMenuItem;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class GenerateUiCommand extends Command
{
    protected $signature = 'sag:generate_ui {name}';

    protected $description = 'Generate ui';

    private function copyFile($file, $destination)
    {
        (new Filesystem)->copy($file, $destination);
    }

    private function copyDirectory($directory, $destination)
    {
        (new Filesystem)->copyDirectory($directory, $destination);
    }
    private function createDirectory($directory)
    {
        (new Filesystem)->ensureDirectoryExists($directory);
    }


    public function handle()
    {
        $this->info('Generate UI');

        $name = $this->argument('name');

        $controllerName = $name . 'Controller';
        $this->info('Generating controller');
        Artisan::call("sag:generate_controller $controllerName");
        $routeName = Str::lower($name);
        $adminPrefix = trim((string) config('sag.prefix', 'admin'), '/');

        $this->info('Generating router');
        file_put_contents(
            './routes/web.php',
            "\nRoute::get('/$adminPrefix/$routeName', [\App\Http\Controllers\SAG\\$controllerName::class, 'index'])->middleware('admin')->name('sag.$routeName.index');\nRoute::get('/$adminPrefix/$routeName/create', [\App\Http\Controllers\SAG\\$controllerName::class, 'create'])->middleware('admin')->name('sag.$routeName.create');\nRoute::get('/$adminPrefix/$routeName/edit/{\$id}', [\App\Http\Controllers\SAG\\$controllerName::class, 'edit'])->middleware('admin')->name('sag.$routeName.edit');\n",
            FILE_APPEND
        );

        $this->info('Generating view');
        $this->createDirectory(resource_path('views/sag/' . $routeName));
        $this->copyDirectory(__DIR__.'/../../stubs/Generator/views/ui', resource_path('views/sag/' . $routeName));

        $content = file_get_contents(resource_path('views/sag/' . $routeName . '/create.blade.php'));
        $content = Str::replace('{{ViewFolder}}', $routeName, $content);
        file_put_contents(resource_path('views/sag/' . $routeName . '/create.blade.php'), $content);

        $content = file_get_contents(resource_path('views/sag/' . $routeName . '/edit.blade.php'));
        $content = Str::replace('{{ViewFolder}}', $routeName, $content);
        file_put_contents(resource_path('views/sag/' . $routeName . '/edit.blade.php'), $content);


        if (Schema::hasTable('admin_menu_items')) {
            AdminMenuItem::query()->firstOrCreate(
                ['key' => Str::slug($name, '_')],
                [
                    'title' => $name,
                    'icon' => 'fas fa-list',
                    'sort_order' => ((int) AdminMenuItem::query()
                        ->whereNull('parent_id')
                        ->max('sort_order')) + 10,
                    'link_type' => AdminMenuLinkType::Route,
                    'target' => "sag.$routeName.index",
                    'target_window' => '_self',
                    'is_active' => true,
                ]
            );
        }

        $this->info('Done');
    }
}
