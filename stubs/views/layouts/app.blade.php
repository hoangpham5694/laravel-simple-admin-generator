<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{!! isset($meta['title']) ? $meta['title'] . ' | ' . config('sag.page_name') : config('sag.page_name')!!}</title>

    <!-- Google Font: Source Sans Pro -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="{{asset('sag/plugins/fontawesome-free/css/all.min.css')}}">
    <!-- Theme style -->
    <link rel="stylesheet" href="{{asset('sag/dist/css/adminlte.min.css')}}">
    <link rel="stylesheet" href="{{asset('sag/plugins/toastr/toastr.css')}}">
    <link rel="stylesheet" href="{{asset('sag/plugins/sweetalert2/sweetalert2.css')}}">
    @stack('style')
</head>
<body class="hold-transition sidebar-mini">
<div class="wrapper">
    <!-- Navbar -->
    @include('sag.layouts.navbar')
    <!-- /.navbar -->

    <!-- Main Sidebar Container -->
    @include('sag.layouts.sidebar')
    <!-- Content Wrapper. Contains page content -->
    <div class="content-wrapper">

        @yield('content')
    </div>
    <!-- /.content-wrapper -->
    <footer class="main-footer">
        <div class="float-right d-none d-sm-block">
            <b>Version</b> 1.0.4
        </div>
        <strong>Copyright &copy; 2024 <a href="#">Simple admin generation</a>.</strong> All rights reserved.
    </footer>

    <!-- Control Sidebar -->
    <aside class="control-sidebar control-sidebar-dark">
        <!-- Add Content Here -->
    </aside>
    <!-- /.control-sidebar -->
</div>
<!-- ./wrapper -->

<!-- jQuery -->
<script src="{{asset('sag/plugins/jquery/jquery.min.js')}}"></script>
<!-- Bootstrap 4 -->
<script src="{{asset('sag/plugins/bootstrap/js/bootstrap.bundle.min.js')}}"></script>
<!-- ChartJS -->
<script src="{{asset('sag/plugins/chart.js/Chart.min.js')}}"></script>
<!-- AdminLTE App -->
<script src="{{asset('sag/dist/js/adminlte.min.js')}}"></script>
<!-- AdminLTE for demo purposes -->

<script src="{{asset('sag/plugins/jquery-mousewheel/jquery.mousewheel.js')}}"></script>
<script src="{{asset('sag/plugins/raphael/raphael.min.js')}}"></script>
<script src="{{asset('sag/plugins/jquery-mapael/jquery.mapael.min.js')}}"></script>
<script src="{{asset('sag/plugins/jquery-mapael/maps/usa_states.min.js')}}"></script>
<script src="{{asset('sag/plugins/toastr/toastr.min.js')}}"></script>
<script src="{{asset('sag/plugins/sweetalert2/sweetalert2.all.js')}}"></script>
<script src="{{asset('sag/plugins/sweetalert2/sweetalert2.js')}}"></script>
<!-- AdminLTE dashboard demo (This is only for demo purposes) -->
<script src="{{asset('sag/dist/js/pages/dashboard2.js')}}"></script>
<script>
    @if (session('success'))
        toastr["success"]("{{ session('success') }}")
    @endif
    @if (session('error'))
        toastr["error"]("{{ session('error') }}")
    @endif

    toastr.options = {
        "closeButton": false,
        "debug": false,
        "newestOnTop": false,
        "progressBar": false,
        "positionClass": "toast-top-right",
        "preventDuplicates": false,
        "onclick": null,
        "showDuration": "300",
        "hideDuration": "1000",
        "timeOut": "5000",
        "extendedTimeOut": "1000",
        "showEasing": "swing",
        "hideEasing": "linear",
        "showMethod": "fadeIn",
        "hideMethod": "fadeOut"
    }
    $(document).ready(function () {
        $("#btnRedirect").click(function () {
            const url = $(this).data('url')
            window.location.href = url
        })
    })
</script>
@stack('script')

</body>
</html>
