@extends('sag::layouts.app')
@section('content')
    @include('sag.components.page_header')
    <section class="content">
        <div class="row">
            <div class="col-md-8">
                <form action="{{route('sag.admin.update', $admin->id)}}" method="POST">
                    @csrf
                    <div class="card card-primary">
                        <div class="card-header">
                            <h3 class="card-title">Basic Info</h3>
                            <div class="card-tools">
                                <button type="button" class="btn btn-tool" data-card-widget="collapse" title="Collapse">
                                    <i class="fas fa-minus"></i>
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            @include('sag::admin.form')

                        </div>
                        <div class="card-footer">
                                <button type="button" id="btnRedirect" data-url="{{route('sag.admin.index')}}" class="btn btn-default">Cancel</button>
                                <button type="submit" class="btn btn-info">Submit</button>
                        </div>
                    </div>
                </form>
            </div>
            <div class="col-md-4">
                @include("sag::admin.form_update_password")
            </div>
        </div>
    </section>

@endsection
@push('script')
    <script>

    </script>
@endpush
