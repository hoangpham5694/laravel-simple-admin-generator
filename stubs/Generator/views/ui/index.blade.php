@extends('sag::layouts.app')
@section('content')
    @include('sag.components.page_header')
    <section class="content">
        <div class="card">
            <div class="card-header">
                <div class="card-tools d-flex align-items-center">
                    <form action="{{ route('sag.{{ViewFolder}}.index') }}" method="GET" class="form-inline mr-2">
                        <label class="sr-only" for="search">Search</label>
                        <input type="search" id="search" name="search" value="{{ $search ?? '' }}" class="form-control form-control-sm mr-2" placeholder="Search">
                        <button type="submit" class="btn btn-default btn-sm">Search</button>
                    </form>
                    <a href="{{ route('sag.{{ViewFolder}}.create') }}" class="btn btn-primary btn-sm">Create</a>
                </div>
            </div>
            <div class="card-body p-0">
                <table class="table table-striped projects">
                    <thead>
                    <tr>
                        <th style="width: 1%">
                            ID
                        </th>
                        <th >
                            Column 1
                        </th>
                        <th >
                            Column 2
                        </th>
                        <th>
                            Column 3
                        </th>
                        <th >
                            Column 4
                        </th>

                    </tr>
                    </thead>
                    <tbody>
                       <tr>
                           <td>1</td>
                           <td>Data column 1</td>
                           <td>Data column 2</td>
                           <td>Data column 3</td>
                           <td>Data colum 4</td>

                       </tr>
                        {{--Binding your data here--}}
                    </tbody>
                </table>
            </div>
            <div class="card-footer clearfix">

            </div>
        </div>
    </section>

@endsection
@push('script')

@endpush
