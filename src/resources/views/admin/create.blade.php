@extends('sag::layouts.app')
@section('content')
    @include('sag.components.page_header')
    <section class="content">
        <div class="row">
            <div class="col-md-8">
                <form action="{{route('sag.admin.store')}}" method="POST">
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
                            <div class="form-group">
                                <label for="txtPassword">Password</label>
                                <input type="password" name="password" id="txtPassword" class="form-control @error('password') is-invalid @enderror">
                                @error('password')
                                <span id="txtPassword-error" class="error invalid-feedback">{{$message}}</span>
                                @enderror
                            </div>
                            <div class="form-group">
                                <label for="txtPasswordConfirm">Confirm Password</label>
                                <input type="password" name="password_confirmation" id="txtPasswordConfirm" class="form-control">
                            </div>
                        </div>
                        <div class="card-footer">
                            <button type="button" id="btnRedirect" data-url="{{route('sag.admin.index')}}" class="btn btn-default">Cancel</button>
                            <button type="submit" class="btn btn-info">Submit</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </section>

@endsection
@push('script')
    <script>

    </script>
@endpush
