<?php

namespace HoangPhamDev\SimpleAdminGenerator\Tests\Feature;

use HoangPhamDev\SimpleAdminGenerator\Tests\TestCase;
use HoangPhamDev\SimpleAdminGenerator\Search\AbstractEloquentSearchProvider;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Route;

class GenerateSearchProviderCommandTest extends TestCase
{
    protected function tearDown(): void
    {
        app(Filesystem::class)->deleteDirectory(app_path('Admin/Search'));
        Schema::dropIfExists('sag_search_records');
        parent::tearDown();
    }

    public function testSkeletonAndOverwriteProtection(): void
    {
        $this->artisan('sag:generate_search_provider', ['name' => 'Product'])->assertExitCode(0);
        $path = app_path('Admin/Search/ProductSearchProvider.php');
        $source = file_get_contents($path);
        $this->assertStringContainsString('namespace App\\Admin\\Search;', $source);
        $this->assertStringContainsString('return [];', $source);
        $this->assertStringContainsString("return 'Product';", $source);
        $this->assertValidPhp($source);
        $this->artisan('sag:generate_search_provider', ['name' => 'ProductSearchProvider'])->assertExitCode(1);
        $this->artisan('sag:generate_search_provider', ['name' => 'Product', '--force' => true])->assertExitCode(0);
    }

    public function testInvalidOptionsNeverWriteFiles(): void
    {
        foreach ([['name' => '../Bad'], ['name' => 'App\\Bad'], ['name' => 'Bad', '--columns' => 'name'], ['name' => 'Bad', '--model' => \stdClass::class], ['name' => 'Bad', '--route' => 'missing.route'], ['name' => 'Bad', '--route-parameter' => 'id'], ['name' => 'Bad', '--route' => 'example.route', '--route-parameter' => 'wrong']] as $options) {
            $this->artisan('sag:generate_search_provider', $options)->assertExitCode(1);
        }
        $this->assertFileDoesNotExist(app_path('Admin/Search/BadSearchProvider.php'));
    }

    public function testGeneratedQueryRetainsGlobalScopeAndGroupsOrConditions(): void
    {
        Schema::create('sag_search_records', function (Blueprint $table) { $table->id(); $table->string('name'); $table->string('slug'); $table->boolean('visible'); });
        Route::get('/search-records/{product:slug}', fn () => 'detail')->name('search.record');
        Route::getRoutes()->refreshNameLookups();
        $options = ['name' => 'Scoped', '--model' => SearchRecord::class, '--columns' => ' name,slug,name ', '--title' => 'name', '--route' => 'search.record'];
        $this->artisan('sag:generate_search_provider', $options)->assertExitCode(0);
        $source = file_get_contents(app_path('Admin/Search/ScopedSearchProvider.php'));
        $this->assertValidPhp($source);
        require app_path('Admin/Search/ScopedSearchProvider.php');
        SearchRecord::query()->create(['name' => 'match', 'slug' => 'public', 'visible' => true]);
        SearchRecord::query()->create(['name' => 'hidden', 'slug' => 'match-private', 'visible' => false]);
        $provider = new \App\Admin\Search\ScopedSearchProvider();
        $results = $provider->search('match', new \Illuminate\Auth\GenericUser(['id' => 123]), 1);
        $this->assertCount(1, $results);
        $this->assertSame(route('search.record', ['product' => 'public']), $results[0]->url);
        $this->artisan('sag:generate_search_provider', array_merge($options, ['name' => 'BadColumn', '--columns' => 'missing']))->assertExitCode(1);
        $this->assertFileDoesNotExist(app_path('Admin/Search/BadColumnSearchProvider.php'));
    }

    public function testMultiParameterRoutesProduceInertSkeleton(): void
    {
        Route::get('/tenants/{tenant}/products/{product}', fn () => 'detail')->name('search.multi');
        Route::getRoutes()->refreshNameLookups();
        $this->artisan('sag:generate_search_provider', ['name' => 'Multi', '--model' => SearchRecord::class, '--route' => 'search.multi', '--route-parameter' => 'product'])->assertExitCode(0);
        $source = file_get_contents(app_path('Admin/Search/MultiSearchProvider.php'));
        $this->assertStringContainsString('return [];', $source);
        $this->assertStringContainsString('tenant, product', $source);
        $this->assertValidPhp($source);
    }

    public function testUrlHelperHandlesCustomRouteKeysAndMissingParameters(): void
    {
        Route::get('/records/{id}', fn () => 'detail')->name('search.id');
        Route::getRoutes()->refreshNameLookups();
        $provider = new class extends AbstractEloquentSearchProvider {
            public function label(): string { return 'Records'; }
            public function search(string $keyword, \Illuminate\Contracts\Auth\Authenticatable $admin, int $limit): iterable { return []; }
            protected function routeName(): string { return 'search.id'; }
            protected function routeParameters(Model $record): array { return ['id' => $record]; }
            public function url(Model $record): string { return $this->resultUrl($record); }
        };
        $record = new class extends Model { public function getRouteKeyName() { return 'slug'; } };
        $record->slug = 'custom-key';
        $this->assertSame(route('search.id', ['id' => 'custom-key']), $provider->url($record));
    }

    public function testIncompleteOptionsAndExplicitBindingValidation(): void
    {
        Route::get('/search-records/{record:missing}', fn () => 'detail')->name('search.bad-binding');
        Route::getRoutes()->refreshNameLookups();
        Schema::create('sag_search_records', function (Blueprint $table) { $table->id(); $table->string('name'); });
        $this->artisan('sag:generate_search_provider', ['name' => 'Binding', '--model' => SearchRecord::class, '--route' => 'search.bad-binding'])
            ->assertExitCode(1);
        $this->assertFileDoesNotExist(app_path('Admin/Search/BindingSearchProvider.php'));
        $this->artisan('sag:generate_search_provider', ['name' => 'Incomplete', '--model' => SearchRecord::class, '--columns' => 'name', '--title' => 'name'])
            ->assertExitCode(0);
        $source = file_get_contents(app_path('Admin/Search/IncompleteSearchProvider.php'));
        $this->assertStringContainsString('return [];', $source);
        $this->assertValidPhp($source);
    }

    public function testMissingDetailRoutesAndParametersFailAtRuntime(): void
    {
        Route::get('/records/{record}', fn () => 'detail')->name('search.required');
        Route::getRoutes()->refreshNameLookups();
        $provider = new class extends AbstractEloquentSearchProvider {
            public string $route = 'search.missing';
            public function label(): string { return 'Records'; }
            public function search(string $keyword, \Illuminate\Contracts\Auth\Authenticatable $admin, int $limit): iterable { return []; }
            protected function routeName(): string { return $this->route; }
            protected function routeParameters(Model $record): array { return []; }
            public function url(): string { return $this->resultUrl(new SearchRecord()); }
        };
        try {
            $provider->url();
            $this->fail('Missing route was accepted.');
        } catch (\Symfony\Component\Routing\Exception\RouteNotFoundException) {
            $this->addToAssertionCount(1);
        }
        $provider->route = 'search.required';
        $this->expectException(\Illuminate\Routing\Exceptions\UrlGenerationException::class);
        $provider->url();
    }

    private function assertValidPhp(string $source): void
    {
        token_get_all($source, TOKEN_PARSE);
        $this->addToAssertionCount(1);
    }
}

class SearchRecord extends Model
{
    protected $table = 'sag_search_records';
    protected $guarded = [];
    public $timestamps = false;
    protected static function booted(): void { static::addGlobalScope('visible', fn ($query) => $query->where('visible', true)); }
}
