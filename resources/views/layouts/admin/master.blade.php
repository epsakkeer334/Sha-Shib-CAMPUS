<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0">
	<meta name="description" content="Smarthr - Bootstrap Admin Template">
	<meta name="keywords" content="admin, estimates, bootstrap, business, html5, responsive, Projects">
	<meta name="author" content="Dreams technologies - Bootstrap Admin Template">
	<meta name="robots" content="noindex, nofollow">
	<title>
        @php
            $role = auth()->check() ? auth()->user()->getRoleNames()->first() : 'Guest';
        @endphp
        {{ ucfirst($role) }} / {{ config('app.name') }}
    </title>

	<!-- Favicon -->
	<link rel="shortcut icon" type="image/x-icon" href="{{ asset('admin/assets/img/favicon.png') }}">
	<!-- Apple Touch Icon -->
	<link rel="apple-touch-icon" sizes="180x180" href="{{ asset('admin/assets/img/apple-touch-icon.png') }}">
	<!-- Theme Script js -->
	<script src="{{ asset('admin/assets/js/theme-script.js') }}"></script>
	<!-- Bootstrap CSS -->
	<link rel="stylesheet" href="{{ asset('admin/assets/css/bootstrap.min.css') }}">
	<!-- Feather CSS -->
	<link rel="stylesheet" href="{{ asset('admin/assets/plugins/icons/feather/feather.css') }}">
	<!-- Tabler Icon CSS -->
    <link rel="stylesheet" href="{{ asset('admin/assets/plugins/tabler-icons/tabler-icons.min.css') }}">
	<!-- Select2 CSS -->
	<link rel="stylesheet" href="{{ asset('admin/assets/plugins/select2/css/select2.min.css') }}">
	<!-- Fontawesome CSS -->
	<link rel="stylesheet" href="{{ asset('admin/assets/plugins/fontawesome/css/fontawesome.min.css') }}">
	<link rel="stylesheet" href="{{ asset('admin/assets/plugins/fontawesome/css/all.min.css') }}">
	<!-- Datetimepicker CSS -->
	<link rel="stylesheet" href="{{ asset('admin/assets/css/bootstrap-datetimepicker.min.css') }}">
    <!-- Bootstrap Tagsinput CSS -->
    <link rel="stylesheet" href="{{ asset('admin/assets/plugins/bootstrap-tagsinput/bootstrap-tagsinput.css') }}">
	<!-- Summernote CSS -->
	<link rel="stylesheet" href="{{ asset('admin/assets/plugins/summernote/summernote-lite.min.css') }}">
	<!-- Daterangepikcer CSS -->
	<link rel="stylesheet" href="{{ asset('admin/assets/plugins/daterangepicker/daterangepicker.css') }}">
	<!-- Color Picker Css -->
	<link rel="stylesheet" href="{{ asset('admin/assets/plugins/flatpickr/flatpickr.min.css') }}">
	<link rel="stylesheet" href="{{ asset('admin/assets/plugins/@simonwep/pickr/themes/nano.min.css') }}">
	<!-- Main CSS -->
    <link rel="stylesheet" href="{{ asset('admin/assets/css/style.css') }}">
    <link rel="stylesheet" href="{{ asset('admin/assets/css/app-style.css') }}">

    @livewireStyles
</head>
<body>
    <div id="global-loader">
		<div class="page-loader"></div>
	</div>
    <!-- Toast Container -->
    <div id="toast-container" class="position-fixed top-0 end-0 p-3" style="z-index: 1080;"></div>

    <div class="main-wrapper">
        @include('layouts.admin.partials.navbar')   {{-- Navbar --}}
        @include('layouts.admin.partials.sidebar')  {{-- Sidebar --}}

        <div class="page-wrapper">
                {{ $slot }}

            @include('layouts.admin.partials.footer')
        </div>

    </div>


    <script src="{{ asset('admin/assets/js/jquery-3.7.1.min.js') }}"></script>
	<!-- Bootstrap Core JS -->
	<script src="{{ asset('admin/assets/js/bootstrap.bundle.min.js') }}"></script>
	<!-- Feather Icon JS -->
	<script src="{{ asset('admin/assets/js/feather.min.js') }}"></script>
	<!-- Slimscroll JS -->
	<script src="{{ asset('admin/assets/js/jquery.slimscroll.min.js') }}"></script>
	<!-- Chart JS -->
	<script src="{{ asset('admin/assets/plugins/apexchart/apexcharts.min.js') }}"></script>
	<script src="{{ asset('admin/assets/plugins/apexchart/chart-data.js') }}"></script>
	<!-- Chart JS -->
	<script src="{{ asset('admin/assets/plugins/chartjs/chart.min.js') }}"></script>
	<script src="{{ asset('admin/assets/plugins/chartjs/chart-data.js') }}"></script>
	<!-- Datetimepicker JS -->
	<script src="{{ asset('admin/assets/js/moment.min.js') }}"></script>
	<script src="{{ asset('admin/assets/js/bootstrap-datetimepicker.min.js') }}"></script>
	<!-- Daterangepikcer JS -->
	<script src="{{ asset('admin/assets/plugins/daterangepicker/daterangepicker.js') }}"></script>
	<!-- Summernote JS -->
	<script src="{{ asset('admin/assets/plugins/summernote/summernote-lite.min.js') }}"></script>
	<!-- Bootstrap Tagsinput JS -->
	<script src="{{ asset('admin/assets/plugins/bootstrap-tagsinput/bootstrap-tagsinput.js') }}"></script>
	<!-- Select2 JS -->
	<script src="{{ asset('admin/assets/plugins/select2/js/select2.min.js') }}"></script>
	<!-- Color Picker JS -->
	<script src="{{ asset('admin/assets/plugins/%40simonwep/pickr/pickr.es5.min.js') }}"></script>
	<!-- Custom JS -->
	<script src="{{ asset('admin/assets/js/todo.js') }}"></script>
	<script src="{{ asset('admin/assets/js/theme-colorpicker.js') }}"></script>
    <script src="{{ asset('admin/assets/js/script.js') }}"></script>
    <script src="{{ asset('admin/assets/js/custom-script.js') }}"></script>

    <script src="{{ asset('admin/assets/plugins/flatpickr/flatpickr.min.js') }}"></script>

    {{-- <script src="{{ asset('admin/assets/js/forms-pickers.js') }}"></script> --}}

    @livewireScripts
<script>
document.addEventListener('livewire:load', function () {
    flatpickr('.timepicker', {
        enableTime: true,
        noCalendar: true,
        dateFormat: "H:i",
        time_24hr: false,
        minuteIncrement: 30,
    });
});

window.assetPath = "{{ asset('admin/assets') }}";

</script>


</body>
</html>
