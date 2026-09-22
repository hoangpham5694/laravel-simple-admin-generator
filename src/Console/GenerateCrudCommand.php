<?php

namespace HoangPhamDev\SimpleAdminGenerator\Console;

use HoangPhamDev\SimpleAdminGenerator\Enums\AdminMenuLinkType;
use HoangPhamDev\SimpleAdminGenerator\Models\AdminMenuItem;
use HoangPhamDev\SimpleAdminGenerator\Services\CrudSchemaInspector;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class GenerateCrudCommand extends Command
{
    protected $signature = 'sag:generate_crud
        {--model= : Eloquent model class or App\\Models basename}
        {--table= : Database table name}
        {--route-name= : Kebab-case resource route name}
        {--controller= : Controller basename ending in Controller}
        {--model-class= : App\\Models model class for a table source}
        {--force : Overwrite generated files}
        {--skip-menu : Do not create an admin menu item}';

    protected $description = 'Generate CRUD controller, views and routes from an Eloquent model or table';

    public function __construct(private readonly CrudSchemaInspector $inspector, private readonly Filesystem $files)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        [$table, $modelClass, $createModel] = $this->resolveSource();
        if (!$table) return self::FAILURE;

        $route = $this->resolveRoute($modelClass, $table);
        if (!$route) return self::FAILURE;
        $controller = $this->resolveController($route);
        if (!$controller) return self::FAILURE;

        $fields = $this->inspector->inspect($table);
        if ($fields === []) {
            $this->error("No supported writable columns were found on [{$table}].");
            return self::FAILURE;
        }

        $targets = $this->targets($route, $controller, $modelClass, $createModel);
        if (!$this->canWrite($targets)) return self::FAILURE;

        foreach ($targets['views'] as $view => $path) {
            $this->files->ensureDirectoryExists(dirname($path));
            $this->files->put($path, $this->viewContents($view, $fields, $route, $modelClass));
        }
        $this->files->ensureDirectoryExists(dirname($targets['controller']));
        $this->files->put($targets['controller'], $this->controllerContents($controller, $modelClass, $fields, $route));
        if ($createModel) {
            $this->files->ensureDirectoryExists(dirname($targets['model']));
            $this->files->put($targets['model'], $this->modelContents($modelClass, $table, $fields));
        }
        $this->appendRoute($targets['routes'], $route, $controller);
        $this->createMenu($route);

        $this->info("CRUD generated for table [{$table}].");
        $this->line('Fields: '.implode(', ', array_column($fields, 'name')));
        return self::SUCCESS;
    }

    /** @return array{0: string|null, 1: string|null, 2: bool} */
    private function resolveSource(): array
    {
        $model = trim((string) $this->option('model'));
        $table = trim((string) $this->option('table'));
        if (($model === '' && $table === '') || ($model !== '' && $table !== '')) {
            $this->error('Provide exactly one of --model or --table.');
            return [null, null, false];
        }
        if ($model !== '' && $this->option('model-class')) {
            $this->error('--model-class can only be used with --table.');
            return [null, null, false];
        }
        if ($model !== '') {
            $class = str_contains($model, '\\') ? ltrim($model, '\\') : 'App\\Models\\'.$model;
            if (!class_exists($class) || !is_subclass_of($class, Model::class)) {
                $this->error("[{$class}] is not an Eloquent model.");
                return [null, null, false];
            }
            return [(new $class())->getTable(), $class, false];
        }
        if (!preg_match('/^[A-Za-z0-9_]+$/', $table) || !Schema::hasTable($table)) {
            $this->error("Table [{$table}] does not exist or has an invalid name.");
            return [null, null, false];
        }
        $class = trim((string) $this->option('model-class')) ?: 'App\\Models\\'.Str::studly(Str::singular($table));
        if (!preg_match('/^App\\\\Models\\\\[A-Z][A-Za-z0-9_]*$/', $class)) {
            $this->error('--model-class must be an App\\Models FQCN.');
            return [null, null, false];
        }
        if (class_exists($class)) {
            $instance = new $class();
            if (!$instance instanceof Model || $instance->getTable() !== $table) {
                $this->error("Existing model [{$class}] does not use table [{$table}].");
                return [null, null, false];
            }
        }
        return [$table, $class, !class_exists($class)];
    }

    private function resolveRoute(string $modelClass, string $table): ?string
    {
        $route = trim((string) $this->option('route-name'));
        $route = $route ?: ($this->option('model') ? Str::kebab(Str::pluralStudly(class_basename($modelClass))) : str_replace('_', '-', $table));
        if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $route)) {
            $this->error('--route-name must be kebab-case.');
            return null;
        }
        return $route;
    }

    private function resolveController(string $route): ?string
    {
        $controller = trim((string) $this->option('controller')) ?: Str::studly(Str::singular(str_replace('-', '_', $route))).'Controller';
        if (!preg_match('/^[A-Z][A-Za-z0-9_]*Controller$/', $controller)) {
            $this->error('--controller must be a StudlyCase class name ending in Controller.');
            return null;
        }
        return $controller;
    }

    /** @return array<string, mixed> */
    private function targets(string $route, string $controller, string $modelClass, bool $createModel): array
    {
        $viewPath = resource_path('views/sag/'.$route);
        return [
            'controller' => app_path('Http/Controllers/SAG/'.$controller.'.php'),
            'model' => app_path('Models/'.class_basename($modelClass).'.php'),
            'createModel' => $createModel,
            'routes' => base_path('routes/web.php'),
            'views' => [
                'index' => $viewPath.'/index.blade.php', 'create' => $viewPath.'/create.blade.php',
                'edit' => $viewPath.'/edit.blade.php', 'form' => $viewPath.'/form.blade.php',
            ],
        ];
    }

    /** @param array<string, mixed> $targets */
    private function canWrite(array $targets): bool
    {
        if ($this->option('force')) return true;
        $paths = array_merge([$targets['controller']], array_values($targets['views']), $targets['createModel'] ? [$targets['model']] : []);
        $existing = array_values(array_filter($paths, fn (string $path) => $this->files->exists($path)));
        if ($existing === []) return true;
        $this->error('Generation cancelled; files already exist:');
        foreach ($existing as $path) $this->line(" - {$path}");
        return false;
    }

    private function appendRoute(string $routeFile, string $route, string $controller): void
    {
        $marker = "// sag:generate_crud {$route}";
        $contents = $this->files->exists($routeFile) ? $this->files->get($routeFile) : "<?php\n";
        if (str_contains($contents, $marker)) return;
        $prefix = trim((string) config('sag.prefix', 'admin'), '/');
        $class = 'App\\Http\\Controllers\\SAG\\'.$controller;
        $this->files->append($routeFile, "\n{$marker}\nRoute::resource('/{$prefix}/{$route}', \\{$class}::class)->middleware('admin')->parameters(['{$route}' => 'record'])->names('sag.{$route}');\n");
    }

    private function createMenu(string $route): void
    {
        if ($this->option('skip-menu') || !Schema::hasTable('admin_menu_items')) return;
        AdminMenuItem::query()->firstOrCreate(['key' => Str::snake($route)], [
            'title' => Str::headline($route), 'icon' => 'fas fa-list',
            'sort_order' => ((int) AdminMenuItem::query()->whereNull('parent_id')->max('sort_order')) + 10,
            'link_type' => AdminMenuLinkType::Route, 'target' => "sag.{$route}.index",
            'target_window' => '_self', 'is_active' => true,
        ]);
    }

    /** @param array<int, array<string, mixed>> $fields */
    private function modelContents(string $modelClass, string $table, array $fields): string
    {
        $namespace = Str::beforeLast($modelClass, '\\'); $class = class_basename($modelClass);
        $fillable = var_export(array_column($fields, 'name'), true);
        $casts = [];
        foreach ($fields as $field) {
            $casts[$field['name']] = match ($field['kind']) {
                'boolean' => 'boolean', 'integer' => 'integer', 'decimal' => 'decimal:2',
                'json' => 'array', 'date' => 'date', 'datetime' => 'datetime', default => null,
            };
        }
        $casts = array_filter($casts);
        $castsDeclaration = $casts === [] ? '' : "\n    protected \$casts = ".var_export($casts, true).";\n";
        return "<?php\n\nnamespace {$namespace};\n\nuse Illuminate\\Database\\Eloquent\\Model;\n\n// Generated by sag:generate_crud\nclass {$class} extends Model\n{\n    protected \$table = '{$table}';\n\n    protected \$fillable = {$fillable};{$castsDeclaration}}\n";
    }

    /** @param array<int, array<string, mixed>> $fields */
    private function controllerContents(string $controller, string $modelClass, array $fields, string $route): string
    {
        $rules = [];
        foreach ($fields as $field) $rules[$field['name']] = $this->rules($field);
        $rules = var_export($rules, true);
        $searchable = var_export(array_values(array_column(array_filter($fields, fn ($f) => in_array($f['kind'], ['text', 'email', 'textarea'], true)), 'name')), true);
        $model = class_basename($modelClass); $parameter = 'record';
        return "<?php\n\nnamespace App\\Http\\Controllers\\SAG;\n\nuse App\\Http\\Controllers\\Controller;\nuse {$modelClass};\nuse Illuminate\\Http\\Request;\n\n// Generated by sag:generate_crud\nclass {$controller} extends Controller\n{\n    private const RULES = {$rules};\n    private const SEARCHABLE = {$searchable};\n\n    public function index(Request \$request)\n    {\n        \$query = {$model}::query();\n        \$search = trim((string) \$request->input('search'));\n        if (\$search) {\n            \$query->where(function (\$query) use (\$search) { foreach (self::SEARCHABLE as \$column) \$query->orWhere(\$column, 'like', '%'.\$search.'%'); });\n        }\n        \$records = \$query->orderBy((new {$model}())->getKeyName())->paginate(15)->withQueryString();\n        return view('sag.{$route}.index', compact('records', 'search'));\n    }\n\n    public function create() { return view('sag.{$route}.create'); }\n    public function store(Request \$request) { {$model}::query()->create(\$request->validate(self::RULES)); return redirect()->route('sag.{$route}.index')->with('success', 'Record created.'); }\n    public function edit({$model} \${$parameter}) { return view('sag.{$route}.edit', compact('{$parameter}')); }\n    public function update(Request \$request, {$model} \${$parameter}) { \${$parameter}->update(\$request->validate(self::RULES)); return redirect()->route('sag.{$route}.index')->with('success', 'Record updated.'); }\n    public function destroy({$model} \${$parameter}) { \${$parameter}->delete(); return redirect()->route('sag.{$route}.index')->with('success', 'Record deleted.'); }\n}\n";
    }

    /** @param array<string, mixed> $field */
    private function rules(array $field): array
    {
        $rules = [$field['nullable'] ? 'nullable' : 'required'];
        $rules[] = match ($field['kind']) { 'email' => 'email', 'integer' => 'integer', 'decimal' => 'numeric', 'boolean' => 'boolean', 'date' => 'date', 'time' => 'date_format:H:i', 'datetime' => 'date_format:Y-m-d H:i', 'json' => 'json', default => 'string' };
        if ($field['length']) $rules[] = 'max:'.$field['length'];
        if (in_array($field['kind'], ['enum', 'set'], true)) $rules[] = 'in:'.implode(',', $field['options']);
        return $rules;
    }

    /** @param array<int, array<string, mixed>> $fields */
    private function viewContents(string $view, array $fields, string $route, string $modelClass): string
    {
        $parameter = 'record';
        if ($view === 'form') return $this->formContents($fields, $parameter);
        if ($view === 'index') return "@extends('sag::layouts.app')\n@section('content')\n<section class=\"content\"><div class=\"card\"><div class=\"card-header\"><form action=\"{{ route('sag.{$route}.index') }}\" method=\"GET\" class=\"form-inline\"><input type=\"search\" name=\"search\" value=\"{{ \$search ?? '' }}\" class=\"form-control form-control-sm mr-2\" placeholder=\"Search\"><button class=\"btn btn-default btn-sm\">Search</button><a href=\"{{ route('sag.{$route}.create') }}\" class=\"btn btn-primary btn-sm ml-2\">Create</a></form></div><div class=\"card-body p-0 table-responsive\"><table class=\"table table-striped\"><thead><tr><th>ID</th>".$this->indexHeaders($fields)."<th class=\"text-right\">Actions</th></tr></thead><tbody>@forelse (\$records as \$record)<tr><td>{{ \$record->getKey() }}</td>".$this->indexCells($fields)."<td class=\"text-right\"><a class=\"btn btn-info btn-sm\" href=\"{{ route('sag.{$route}.edit', \$record) }}\">Edit</a><form action=\"{{ route('sag.{$route}.destroy', \$record) }}\" method=\"POST\" class=\"d-inline\">@csrf @method('DELETE')<button class=\"btn btn-danger btn-sm\">Delete</button></form></td></tr>@empty<tr><td colspan=\"".(count($fields) + 2)."\" class=\"text-center\">No records found.</td></tr>@endforelse</tbody></table></div><div class=\"card-footer\">{{ \$records->links() }}</div></div></section>\n@endsection\n";
        $method = $view === 'edit' ? "@method('PUT')" : '';
        $action = $view === 'edit' ? "route('sag.{$route}.update', \${$parameter})" : "route('sag.{$route}.store')";
        return "@extends('sag::layouts.app')\n@section('content')\n<section class=\"content\"><div class=\"card\"><form action=\"{{ {$action} }}\" method=\"POST\">@csrf {$method}<div class=\"card-body\">@include('sag.{$route}.form')</div><div class=\"card-footer\"><a href=\"{{ route('sag.{$route}.index') }}\" class=\"btn btn-default\">Cancel</a><button class=\"btn btn-primary\">Save</button></div></form></div></section>\n@endsection\n";
    }

    /** @param array<int, array<string, mixed>> $fields */
    private function formContents(array $fields, string $parameter): string
    {
        return implode("\n", array_map(function (array $field) use ($parameter) {
            $name = $field['name']; $label = $field['label']; $value = "\${$parameter}->{$name} ?? null";
            if ($field['kind'] === 'date') $value = "isset(\${$parameter}) && \${$parameter}->{$name} ? \${$parameter}->{$name}->format('Y-m-d') : null";
            if ($field['kind'] === 'datetime') $value = "isset(\${$parameter}) && \${$parameter}->{$name} ? \${$parameter}->{$name}->format('Y-m-d H:i') : null";
            if ($field['kind'] === 'json') $value = "isset(\${$parameter}) && is_array(\${$parameter}->{$name}) ? json_encode(\${$parameter}->{$name}) : (\${$parameter}->{$name} ?? null)";
            return match ($field['kind']) {
                'textarea', 'json' => "<x-sag-form.textarea name=\"{$name}\" label=\"{$label}\" :value=\"{$value}\" />",
                'datetime' => "<x-sag-form.datetime name=\"{$name}\" label=\"{$label}\" :value=\"{$value}\" />",
                'boolean' => "<x-sag-form.checkbox name=\"{$name}\" label=\"{$label}\" :checked=\"(bool) ({$value})\" unchecked-value=\"0\" />",
                'enum' => "<x-sag-form.select name=\"{$name}\" label=\"{$label}\" :options=\"".var_export(array_combine($field['options'], $field['options']), true)."\" :selected=\"{$value}\" />",
                'set' => "<x-sag-form.multi-select name=\"{$name}\" label=\"{$label}\" :options=\"".var_export(array_combine($field['options'], $field['options']), true)."\" :selected=\"{$value} ?? []\" />",
                default => "<x-sag-form.input name=\"{$name}\" label=\"{$label}\" type=\"".$this->inputType($field['kind'])."\" :value=\"{$value}\"".($field['kind'] === 'decimal' ? ' step="any"' : '')." />",
            };
        }, $fields));
    }

    private function inputType(string $kind): string { return match ($kind) { 'email' => 'email', 'integer', 'decimal' => 'number', 'date' => 'date', 'time' => 'time', default => 'text' }; }
    /** @param array<int, array<string, mixed>> $fields */
    private function indexHeaders(array $fields): string { return implode('', array_map(fn ($f) => '<th>'.$f['label'].'</th>', $fields)); }
    /** @param array<int, array<string, mixed>> $fields */
    private function indexCells(array $fields): string { return implode('', array_map(fn ($f) => '<td>{{ $record->'.$f['name'].' }}</td>', $fields)); }
}
