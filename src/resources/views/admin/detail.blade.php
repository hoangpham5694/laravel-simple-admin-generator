@extends('sag::layouts.app')
@section('content')
    @include('sag.components.page_header')
    <section class="content">
        <div class="row">
            <div class="col-lg-12">
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
                            <strong>Email</strong>
                            <p class="text-muted">
                                {{$admin->email}}
                            </p>
                            <hr>
                            <strong>First name</strong>
                            <p class="text-muted">{{$admin->first_name}}</p>
                            <hr>
                            <strong>Last name</strong>
                            <p class="text-muted">
                               {{$admin->last_name}}
                            </p>
                            <hr>
                            <strong>Is super user</strong>
                            <p class="text-muted">
                                @if($admin->is_super_user == 1)
                                    Yes
                                @else
                                    No
                                @endif
                            </p>
                        </div>
                        <div class="card-footer">
                            <button type="button" id="btnRedirect" data-url="{{route('sag.admin.index')}}" class="btn btn-default">Back</button>
                        </div>
                    </div>
            </div>
        </div>
    </section>
@endsection
@push('script')
    <script>

    </script>
@endpush
