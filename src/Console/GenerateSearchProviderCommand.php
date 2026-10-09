<?php

namespace HoangPhamDev\SimpleAdminGenerator\Console;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Route;
use InvalidArgumentException;
use Throwable;

class GenerateSearchProviderCommand extends Command
{
    protected $signature = 'sag:generate_search_provider {name} {--model=} {--columns=} {--title=} {--route=} {--route-parameter=} {--force}';
    protected $description = 'Generate an application search provider (authorization and tenant scope must be completed).';

    public function handle(Filesystem $files): int
    {
        try {
            $name = (string) $this->argument('name');
            $this->require(preg_match('/^[A-Z][A-Za-z0-9]*$/D', $name) === 1, 'Name must be a StudlyCase basename without a namespace or path.');
            $class = str_ends_with($name, 'SearchProvider') ? $name : $name.'SearchProvider';
            $path = app_path('Admin/Search/'.$class.'.php');
            $this->require($this->option('force') || !$files->exists($path), 'File already exists; use --force to overwrite.');
            $modelClass = ltrim(trim((string) $this->option('model')), '\\');
            $columns = array_values(array_unique(array_filter(array_map('trim', explode(',', (string) $this->option('columns'))), fn ($value) => $value !== '')));
            $title = trim((string) $this->option('title'));
            $routeName = trim((string) $this->option('route'));
            $parameter = trim((string) $this->option('route-parameter'));
            $this->require($modelClass !== '' || ($columns === [] && $title === ''), '--columns and --title require --model.');
            $this->require($parameter === '' || $routeName !== '', '--route-parameter requires --route.');
            $model = null;
            $schemaColumns = [];
            if ($modelClass !== '') {
                $this->require(preg_match('/^[A-Za-z_][A-Za-z0-9_]*(?:\\\\[A-Za-z_][A-Za-z0-9_]*)*$/D', $modelClass) === 1 && is_subclass_of($modelClass, Model::class), '--model must be an existing Eloquent model FQCN.');
                $model = app($modelClass);
                if ($columns !== [] || $title !== '') {
                    $schemaColumns = $this->schemaColumns($model);
                    foreach (array_merge($columns, $title !== '' ? [$title] : []) as $column) {
                        $this->require(in_array($column, $schemaColumns, true), "Unknown model column [{$column}].");
                    }
                }
            }
            $route = null;
            $parameters = [];
            $binding = null;
            if ($routeName !== '') {
                $route = Route::getRoutes()->getByName($routeName);
                $this->require($route !== null, "Named route [{$routeName}] does not exist.");
                $parameters = $route->parameterNames();
                if ($parameter !== '') $this->require(in_array($parameter, $parameters, true), '--route-parameter must match a placeholder on the route.');
                if ($parameter === '' && count($parameters) === 1) $parameter = $parameters[0];
                if ($parameter !== '') {
                    $binding = $route->bindingFieldFor($parameter);
                    if ($model && $binding) {
                        if ($schemaColumns === []) $schemaColumns = $this->schemaColumns($model);
                        $this->require(in_array($binding, $schemaColumns, true), "Unknown route binding column [{$binding}].");
                    }
                }
            }
            $completeRoute = $route && count($parameters) === 1 && $parameter !== '';
            $queryReady = $model && $columns !== [] && $title !== '' && $completeRoute;
            $replacements = [
                '{{class}}' => $class,
                '{{label}}' => var_export(substr($class, 0, -strlen('SearchProvider')) ?: 'Search', true),
                '{{route}}' => $route ? 'return '.var_export($routeName, true).';' : "// TODO: Declare an existing named detail route.\n        throw new \\LogicException('Configure the detail route.');",
                '{{parameters}}' => $completeRoute ? 'return ['.var_export($parameter, true).' => '.($binding ? '$record->getAttribute('.var_export($binding, true).')' : '$record').'];' : "// TODO: Map all detail route placeholders: ".implode(', ', $parameters)."\n        throw new \\LogicException('Configure detail route parameters.');",
                '{{query}}' => "// TODO: Complete query, title and route parameter mapping.\n        return [];",
            ];
            if ($queryReady) {
                $replacements['{{query}}'] = 'return \\'.$modelClass."::query()\n            ->where(function (\$query) use (\$keyword) {\n                foreach (".var_export($columns, true)." as \$column) {\n                    \$query->orWhere(\$column, 'like', '%'.\$keyword.'%');\n                }\n            })\n            ->limit(\$limit)\n            ->get()\n            ->map(fn (Model \$record) => new SearchResult(\n                title: (string) \$record->getAttribute(".var_export($title, true)."),\n                description: null,\n                url: \$this->resultUrl(\$record),\n            ));";
            }
            $stub = __DIR__.'/../../stubs/search/'.($model ? 'eloquent' : 'provider').'.php.stub';
            $contents = strtr($files->get($stub), $replacements);
            $files->ensureDirectoryExists(dirname($path));
            $files->put($path, $contents);
            $this->info("Created {$path}");
            $this->warn('TODO: Review label, permissions and tenant scope before registering the provider.');
            if (!$queryReady) $this->warn('Skeleton returns no results. Complete query and detail route mapping'.($parameters ? ' for: '.implode(', ', $parameters) : '').'.');
            $this->line("Set sag.search.enabled to true and add App\\Admin\\Search\\{$class}::class to sag.search.providers in config/sag.php.");
            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error('Provider generation failed: '.($exception instanceof InvalidArgumentException ? $exception->getMessage() : 'Unable to generate the provider; check model, schema access and filesystem permissions.'));
            return self::FAILURE;
        }
    }

    private function schemaColumns(Model $model): array
    {
        try {
            return $model->getConnection()->getSchemaBuilder()->getColumnListing($model->getTable());
        } catch (Throwable) {
            throw new InvalidArgumentException('Unable to inspect the model schema. Check its connection and table.');
        }
    }

    private function require(bool $condition, string $message): void
    {
        if (!$condition) throw new InvalidArgumentException($message);
    }
}
