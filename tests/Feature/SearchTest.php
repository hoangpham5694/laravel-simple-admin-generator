<?php

namespace HoangPhamDev\SimpleAdminGenerator\Tests\Feature;

use HoangPhamDev\SimpleAdminGenerator\Contracts\SearchProvider;
use HoangPhamDev\SimpleAdminGenerator\Data\SearchResult;
use Illuminate\Auth\GenericUser;
use HoangPhamDev\SimpleAdminGenerator\Services\SearchManager;
use HoangPhamDev\SimpleAdminGenerator\Tests\TestCase;
use Illuminate\Contracts\Auth\Authenticatable;
use LogicException;

class SearchTest extends TestCase
{
    private RecordingSearchProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->provider = new RecordingSearchProvider();
        app()->instance(RecordingSearchProvider::class, $this->provider);
        config(['sag.search.enabled' => true, 'sag.search.providers' => [RecordingSearchProvider::class]]);
        $this->actingAs(new GenericUser(['id' => 123, 'first_name' => 'Search admin', 'password' => '', 'remember_token' => null]), 'admin');
    }

    public function testDisabledAndLegacyConfigurationHidesBothFormsAndReturns404(): void
    {
        foreach ([[], ['providers' => [RecordingSearchProvider::class]], ['enabled' => false, 'providers' => [RecordingSearchProvider::class]], ['enabled' => true], ['enabled' => true, 'providers' => []]] as $config) {
            config(['sag.search' => $config]);
            foreach (['navbar', 'sidebar'] as $view) {
                $html = view('sag::layouts.'.$view)->render();
                $this->assertStringNotContainsString('name="q"', $html);
                $this->assertStringNotContainsString('data-widget="navbar-search"', $html);
            }
            $this->get(route('sag.search'))->assertNotFound();
            $this->getJson(route('sag.search.suggestions'))->assertNotFound();
        }
    }

    public function testValidationAndShortQueriesNeverCallProviders(): void
    {
        foreach (['', ' ', 'a'] as $q) {
            $this->get(route('sag.search', ['q' => $q]))->assertOk()->assertSee('at least');
            $this->getJson(route('sag.search.suggestions', ['q' => $q]))->assertOk()->assertJsonPath('results', []);
        }
        foreach ([['bad'], str_repeat('a', 201)] as $q) {
            $this->get(route('sag.search', ['q' => $q]))->assertStatus(422)->assertSee('sag-search-error', false);
            $this->getJson(route('sag.search.suggestions', ['q' => $q]))->assertStatus(422)->assertJsonValidationErrors('q');
        }
        $this->assertSame([], $this->provider->calls);
    }

    public function testResultsAreFlatLimitedEscapedAndJsonOnlyContainsPublicFields(): void
    {
        config(['sag.search.limit_per_provider' => 2, 'sag.search.suggestions.limit_per_provider' => 2, 'sag.search.suggestions.max_results' => 1]);
        $this->get(route('sag.search', ['q' => ' keyword ']))->assertOk()
            ->assertSee('2 results displayed')->assertSee('&lt;title&gt;', false)
            ->assertSee('&lt;label&gt;', false)->assertDontSee(RecordingSearchProvider::class);
        $response = $this->getJson(route('sag.search.suggestions', ['q' => ' keyword ']))->assertOk()->assertJsonCount(1, 'results')->assertJsonPath('query', 'keyword');
        $this->assertSame(['title', 'description', 'url', 'provider_label'], array_keys($response->json('results.0')));
        $this->assertSame('keyword', $this->provider->calls[0][0]);
        $this->assertSame(2, $this->provider->calls[0][2]);
        $this->assertSame(auth('admin')->user(), $this->provider->calls[0][1]);
    }

    public function testManagerPreservesProviderOrderAndMetadata(): void
    {
        config(['sag.search.providers' => [RecordingSearchProvider::class, SecondSearchProvider::class]]);
        $results = app(SearchManager::class)->search('word', auth('admin')->user(), 1);
        $this->assertCount(2, $results);
        $this->assertSame(RecordingSearchProvider::class, $results[0]['providerClass']);
        $this->assertSame(SecondSearchProvider::class, $results[1]['providerClass']);
        $this->assertSame('<label>', $results[1]['providerLabel']);
    }

    public function testEmptyLabelsFailClearly(): void
    {
        $this->provider->label = ' ';
        $this->expectException(LogicException::class);
        app(SearchManager::class)->search('word', auth('admin')->user(), 1);
    }

    public function testInvalidContractFailsClearly(): void
    {
        config(['sag.search.providers' => [\stdClass::class]]);
        $this->expectException(LogicException::class);
        app(SearchManager::class)->search('word', auth('admin')->user(), 1);
    }

    public function testFormsSubmitAndSuggestionsCanBeDisabledIndependently(): void
    {
        config(['sag.search.suggestions.enabled' => false]);
        foreach (['navbar', 'sidebar'] as $view) {
            $html = view('sag::layouts.'.$view)->render();
            $this->assertStringContainsString('method="GET"', $html);
            $this->assertStringContainsString('action="'.route('sag.search').'"', $html);
            $this->assertStringContainsString('name="q"', $html);
            $this->assertStringNotContainsString('data-sag-search-input', $html);
            $this->assertStringNotContainsString('data-widget="sidebar-search"', $html);
        }
        $this->getJson(route('sag.search.suggestions', ['q' => 'word']))->assertNotFound();
        $this->get(route('sag.search', ['q' => 'word']))->assertOk();
    }

    public function testUnauthenticatedRequestsUseHtmlRedirectAndJson401(): void
    {
        auth('admin')->logout();
        $this->get(route('sag.search'))->assertRedirect(route('sag.login'));
        $this->getJson(route('sag.search.suggestions'))->assertUnauthorized();
    }

    public function testEmptyResultsHaveOneSharedMessage(): void
    {
        $this->provider->empty = true;
        $this->get(route('sag.search', ['q' => 'word']))->assertOk()->assertSee('No results found.');
    }

    public function testSuggestionProviderErrorsAreReportedWithoutExposingDetails(): void
    {
        config(['sag.search.providers' => [\stdClass::class]]);
        $this->getJson(route('sag.search.suggestions', ['q' => 'word']))
            ->assertStatus(500)->assertExactJson(['message' => 'Unable to load search suggestions.']);
    }

    public function testSourceAndStubFormsStayInSync(): void
    {
        $root = dirname(__DIR__, 2);
        foreach (['navbar', 'sidebar'] as $layout) {
            $this->assertSame(
                file_get_contents($root.'/src/resources/views/layouts/'.$layout.'.blade.php'),
                file_get_contents($root.'/stubs/views/layouts/'.$layout.'.blade.php')
            );
        }
    }

    public function testUrlsRejectUnsafeSchemesAndBrowserNormalization(): void
    {
        foreach (['javascript:alert(1)', '//evil.example', '/\\evil.example', "https://example.com/\nfoo", 'data:text/html,test', 'relative'] as $url) {
            try {
                new SearchResult('title', null, $url);
                $this->fail('Unsafe URL was accepted.');
            } catch (\InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
        $this->assertSame('/admin/item/1', (new SearchResult('title', null, '/admin/item/1'))->url);
    }
}

class RecordingSearchProvider implements SearchProvider
{
    public array $calls = [];
    public string $label = '<label>';
    public bool $empty = false;
    public function label(): string { return $this->label; }
    public function search(string $keyword, Authenticatable $admin, int $limit): iterable
    {
        $this->calls[] = [$keyword, $admin, $limit];
        if ($this->empty) return;
        for ($i = 0; $i < 5; $i++) yield new SearchResult('<title>', '<description>', '/admin/items/'.$i);
    }
}
class SecondSearchProvider extends RecordingSearchProvider {}
