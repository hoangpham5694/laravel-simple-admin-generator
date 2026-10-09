@extends('sag::layouts.app')
@section('content')
<section class="content-header">
    <div class="container-fluid"><h1>Search</h1><ol class="breadcrumb"><li class="breadcrumb-item"><a href="{{ route('sag.search') }}">Search</a></li></ol></div>
</section>
<section class="content">
    <div class="card"><div class="card-body">
        <form method="GET" action="{{ route('sag.search') }}">
            <label for="sag-search-query">Search keyword</label>
            <div class="d-flex flex-wrap" style="gap: .5rem">
                <input id="sag-search-query" class="form-control {{ $searchErrors->has('q') ? 'is-invalid' : '' }}" style="flex:1;min-width:180px" type="search" name="q" value="{{ $keyword }}" @if ($searchErrors->has('q')) aria-invalid="true" aria-describedby="sag-search-error" @endif>
                <button type="submit" class="btn btn-primary">Search</button>
                <a href="{{ route('sag.search') }}" class="btn btn-default">Clear search</a>
            </div>
            @if ($searchErrors->has('q'))<p id="sag-search-error" class="text-danger">{{ $searchErrors->first('q') }}</p>@endif
        </form>
        <p class="text-muted mt-3">Up to {{ $limit }} results per search provider</p>
        @if (!$searchErrors->any())
            @if (!$searched)
                <p>Enter at least {{ $minLength }} characters to search.</p>
            @else
                <p>Search results for “{{ $keyword }}” — {{ count($results) }} results displayed</p>
                <ul class="list-group list-group-flush">
                    @forelse ($results as $item)
                        <li class="list-group-item" style="overflow-wrap:anywhere">
                            <a href="{{ $item['result']->url }}">{{ $item['result']->title }}</a>
                            <span class="badge badge-secondary">{{ $item['providerLabel'] }}</span>
                            @if (trim($item['result']->description ?? '') !== '')<p class="mb-0 text-muted">{{ $item['result']->description }}</p>@endif
                        </li>
                    @empty
                        <li class="list-group-item">No results found. Try a different keyword.</li>
                    @endforelse
                </ul>
            @endif
        @endif
    </div></div>
</section>
@endsection
