@extends('sag::layouts.app')
@section('content')
    @include('sag.components.page_header')
    <section class="content">
        <div class="card">
            <form action="{{route('sag.admin.destroy')}}" method="POST" id="frmDelete">
                @csrf
                <input type="hidden" name="id" class="txt_id">
            </form>
            <div class="card-body p-0">
                <table class="table table-striped projects">
                    <thead>
                    <tr>
                        <th style="width: 1%">
                            ID
                        </th>
                        <th style="width: 15%">
                            First name
                        </th>
                        <th style="width: 15%">
                            Last name
                        </th>
                        <th>
                            Email
                        </th>
                        <th style="width: 14%" class="text-center">
                            Super user
                        </th>
                        <th style="width: 28%">
                        </th>
                    </tr>
                    </thead>
                    <tbody>
                        @foreach($admins as $admin)
                            <tr>
                                <td>
                                    {{$admin->id}}
                                </td>
                                <td>
                                    {{$admin->first_name}}
                                </td>
                                <td>
                                    {{$admin->last_name}}
                                </td>
                                <td>
                                    {{$admin->email}}
                                </td>
                                <td class="text-center">
                                    @if ($admin->is_super_user)
                                        <span class="badge badge-success">Yes</span>
                                    @else
                                        <span class="badge badge-danger">No</span>
                                    @endif
                                </td>
                                <td class="project-actions text-right">
                                    <a class="btn btn-primary btn-sm" href="{{route('sag.admin.detail', $admin->id)}}">
                                        <i class="fas fa-eye">
                                        </i>
                                        View
                                    </a>
                                    <a class="btn btn-info btn-sm" href="{{route('sag.admin.edit', $admin->id)}}">
                                        <i class="fas fa-pencil-alt">
                                        </i>
                                        Edit
                                    </a>
                                    @php
                                        $disabled = '';
                                        if(auth()->guard('admin')->user()->id == $admin->id) {
                                            $disabled = 'disabled';
                                        }
                                    @endphp


                                    <button class="btn btn-danger btn-sm btn_delete {{$disabled}}" {{$disabled}} data-id="{{$admin->id}}" >
                                        <i class="fas fa-trash">
                                        </i>
                                        Delete
                                    </button>

                                </td>
                            </tr>
                        @endforeach


                    </tbody>
                </table>
            </div>
            <div class="card-footer clearfix">
               {{$admins->links('sag::components.paginator')}}
            </div>
        </div>
    </section>

@endsection
@push('script')
<script>
    $(document).ready(function () {
        $('.btn_delete').click(function () {
            $('.txt_id').val($(this).data('id'))
            Swal.fire({
                title: "Do you want to delete this user?",
                showCancelButton: true,
                icon: "warning",
                confirmButtonColor: "#3085d6",
                confirmButtonText: "OK",
            }).then((result) => {
                if (result.isConfirmed) {
                    $("#frmDelete").submit()
                }
            });

        })
    })
</script>
@endpush
