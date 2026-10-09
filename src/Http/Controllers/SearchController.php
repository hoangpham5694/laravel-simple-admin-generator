<?php

namespace HoangPhamDev\SimpleAdminGenerator\Http\Controllers;

use HoangPhamDev\SimpleAdminGenerator\Services\SearchManager;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class SearchController extends Controller
{
    public function index(Request $request, SearchManager $manager)
    {
        abort_unless(SearchManager::enabled(), 404);
        [$keyword, $validator] = $this->keyword($request);
        $minLength = max(1, (int) config('sag.search.min_length', 2));
        $limit = max(1, (int) config('sag.search.limit_per_provider', 10));
        $searched = !$validator->fails() && mb_strlen($keyword) >= $minLength;
        $results = $searched ? $manager->search($keyword, Auth::guard('admin')->user(), $limit) : [];
        return response()->view('sag::search.index', [
            'keyword' => $keyword, 'searchErrors' => $validator->errors(),
            'minLength' => $minLength, 'limit' => $limit, 'searched' => $searched, 'results' => $results,
        ], $validator->fails() ? 422 : 200);
    }

    public function suggestions(Request $request, SearchManager $manager)
    {
        if (!SearchManager::enabled() || !config('sag.search.suggestions.enabled', true)) {
            return response()->json(['message' => 'Not found.'], 404);
        }
        [$keyword, $validator] = $this->keyword($request);
        if ($validator->fails()) return response()->json(['message' => 'Invalid search query.', 'errors' => $validator->errors()], 422);
        $results = [];
        if (mb_strlen($keyword) >= max(1, (int) config('sag.search.min_length', 2))) {
            try {
                $items = $manager->search($keyword, Auth::guard('admin')->user(), max(1, (int) config('sag.search.suggestions.limit_per_provider', 3)));
            } catch (\Throwable $exception) {
                report($exception);
                return response()->json(['message' => 'Unable to load search suggestions.'], 500);
            }
            foreach (array_slice($items, 0, max(0, (int) config('sag.search.suggestions.max_results', 10))) as $item) {
                $result = $item['result'];
                $results[] = ['title' => $result->title, 'description' => $result->description, 'url' => $result->url, 'provider_label' => $item['providerLabel']];
            }
        }
        return response()->json(['query' => $keyword, 'results' => $results]);
    }

    private function keyword(Request $request): array
    {
        $raw = $request->query('q');
        $validator = Validator::make(['q' => $raw], ['q' => ['nullable', 'string', 'max:'.max(1, (int) config('sag.search.max_length', 200))]]);
        return [is_string($raw) ? trim($raw) : '', $validator];
    }
}
