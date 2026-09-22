<?php

namespace HoangPhamDev\SimpleAdminGenerator\Tests\Feature;

use HoangPhamDev\SimpleAdminGenerator\Tests\TestCase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Schema;

class GenerateCrudCommandTest extends TestCase
{
    private Filesystem $files;
    private string $routesPath;
    private string $routesContents;

    protected function setUp(): void
    {
        parent::setUp();

        $this->files = app(Filesystem::class);
        $this->routesPath = base_path('routes/web.php');
        $this->routesContents = $this->files->exists($this->routesPath) ? $this->files->get($this->routesPath) : '';

        Schema::dropIfExists('sag_crud_people');
        Schema::create('sag_crud_people', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 120);
            $table->string('email')->nullable();
            $table->text('profile')->nullable();
            $table->integer('stock')->default(0);
            $table->decimal('salary', 10, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->date('birth_date')->nullable();
            $table->time('starts_at')->nullable();
            $table->dateTime('published_at')->nullable();
            $table->json('settings')->nullable();
            $table->enum('status', ['draft', 'published'])->default('draft');
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        foreach ([
            app_path('Http/Controllers/SAG/CrudPersonController.php'),
            app_path('Http/Controllers/SAG/CrudModelPersonController.php'),
            app_path('Models/CrudPerson.php'),
            app_path('Models/SagCrudPerson.php'),
            resource_path('views/sag/crud-people'),
            resource_path('views/sag/crud-model-people'),
        ] as $path) {
            if (is_dir($path)) $this->files->deleteDirectory($path);
            elseif ($this->files->exists($path)) $this->files->delete($path);
        }
        $this->files->put($this->routesPath, $this->routesContents);
        Schema::dropIfExists('sag_crud_people');

        parent::tearDown();
    }

    public function testItGeneratesCrudFromATableWithMatchingSagComponents(): void
    {
        $this->artisan('sag:generate_crud', [
            '--table' => 'sag_crud_people',
            '--route-name' => 'crud-people',
            '--controller' => 'CrudPersonController',
            '--model-class' => 'App\\Models\\CrudPerson',
            '--skip-menu' => true,
        ])->assertExitCode(0);

        $form = $this->files->get(resource_path('views/sag/crud-people/form.blade.php'));
        $controller = $this->files->get(app_path('Http/Controllers/SAG/CrudPersonController.php'));
        $model = $this->files->get(app_path('Models/CrudPerson.php'));

        $this->assertStringContainsString('<x-sag-form.input name="name"', $form);
        $this->assertStringContainsString('type="email"', $form);
        $this->assertStringContainsString('<x-sag-form.textarea name="profile"', $form);
        $this->assertStringContainsString('type="number"', $form);
        $this->assertStringContainsString('<x-sag-form.checkbox name="is_active"', $form);
        $this->assertStringContainsString('type="date"', $form);
        $this->assertStringContainsString('type="time"', $form);
        $this->assertStringContainsString('<x-sag-form.datetime name="published_at"', $form);
        $this->assertStringContainsString('<x-sag-form.select name="status"', $form);
        $this->assertStringNotContainsString('created_at', $form);
        $this->assertStringContainsString("'name' =>", $controller);
        $this->assertStringContainsString("'required'", $controller);
        $this->assertStringContainsString("\$search = trim((string) \$request->input('search'));", $controller);
        $this->assertStringContainsString('function update(Request $request, CrudPerson $record)', $controller);
        $this->assertStringContainsString('protected $table = \'sag_crud_people\'', $model);
        $this->assertStringContainsString('protected $casts', $model);
        $this->assertStringContainsString("'is_active' => 'boolean'", $model);
        $this->assertStringContainsString("'settings' => 'array'", $model);
        $this->assertStringContainsString('// sag:generate_crud crud-people', $this->files->get($this->routesPath));
        $this->assertStringContainsString("->names('sag.crud-people')", $this->files->get($this->routesPath));
        $this->assertStringContainsString("->parameters(['crud-people' => 'record'])", $this->files->get($this->routesPath));
    }

    public function testItAcceptsAnEloquentModelAndDoesNotGenerateAnotherModel(): void
    {
        $this->artisan('sag:generate_crud', [
            '--model' => 'CrudEmployee',
            '--route-name' => 'crud-model-people',
            '--controller' => 'CrudModelPersonController',
            '--skip-menu' => true,
        ])->assertExitCode(0);

        $this->assertFileExists(app_path('Http/Controllers/SAG/CrudModelPersonController.php'));
        $this->assertFileDoesNotExist(app_path('Models/CrudModelPerson.php'));
        $this->assertStringContainsString('use App\\Models\\CrudEmployee;', $this->files->get(app_path('Http/Controllers/SAG/CrudModelPersonController.php')));
    }

    public function testItRejectsInvalidSourceAndExistingFilesWithoutForce(): void
    {
        $this->artisan('sag:generate_crud')->expectsOutput('Provide exactly one of --model or --table.')->assertExitCode(1);
        $this->artisan('sag:generate_crud', ['--table' => 'sag_crud_people', '--route-name' => 'not/a-route'])
            ->expectsOutput('--route-name must be kebab-case.')->assertExitCode(1);

        $arguments = ['--table' => 'sag_crud_people', '--route-name' => 'crud-people', '--controller' => 'CrudPersonController', '--skip-menu' => true];
        $this->artisan('sag:generate_crud', $arguments)->assertExitCode(0);
        $this->artisan('sag:generate_crud', $arguments)->assertExitCode(1);
        $this->artisan('sag:generate_crud', $arguments + ['--force' => true])->assertExitCode(0);
    }
}
