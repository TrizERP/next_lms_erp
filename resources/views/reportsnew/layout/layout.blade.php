@include('includes.headcss')

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="../reports/assets/img/favicon/favicon.ico" />

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,500;1,600;1,700&display=swap"
        rel="stylesheet" />

    <!-- Icons. Uncomment required icon fonts -->
    <link rel="stylesheet" href="../reports/assets/vendor/fonts/boxicons.css" />

    <!-- Core CSS -->
    <link rel="stylesheet" href="../reports/assets/vendor/css/core.css" class="template-customizer-core-css" />
    <link rel="stylesheet" href="../reports/assets/vendor/css/theme-default.css" class="template-customizer-theme-css" />
    <link rel="stylesheet" href="../reports/assets/css/demo.css" />

    <!-- Vendors CSS -->
    <link rel="stylesheet" href="../reports/assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css" />

    <link rel="stylesheet" href="../reports/assets/vendor/libs/apex-charts/apex-charts.css" />

    <!-- Page CSS -->

    <!-- Helpers -->
    <script src="../reports/assets/vendor/js/helpers.js"></script>

    <script src="../reports/assets/js/config.js"></script>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="../reports/assets/vendor/libs/jquery/jquery.js"></script>
@include('includes.header')
@include('includes.sideNavigation')
<style>
    .layout-content-navbar .layout-navbar{
        z-index:0 !important;
    }
    .container, .container-fluid, .container-xxl, .container-xl, .container-lg, .container-md, .container-sm {
        margin-bottom: 3px;
    }
</style>
<div id="page-wrapper">
    <div class="container-fluid">

    <div class="layout-wrapper layout-content-navbar">
        <div class="layout-container">
            @include('reportsnew.layout.sidebar')
            <div class="layout-page">
                @include('reportsnew.layout.nav')
                <div class="content-wrapper">
                    @yield('content')
                    {{-- @include('reportsnew.layout.footer') --}}
                    <div class="content-backdrop fade"></div>
                </div>
            </div>
        </div>
        <div class="layout-overlay layout-menu-toggle"></div>
    </div>


</div>
</div>
    <script src="../reports/assets/vendor/libs/popper/popper.js"></script>
    <script src="../reports/assets/vendor/js/bootstrap.js"></script>
    <script src="../reports/assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js"></script>

    <script src="../reports/assets/vendor/js/menu.js"></script>
    <!-- endbuild -->

    <!-- Vendors JS -->
    <script src="../reports/assets/vendor/libs/apex-charts/apexcharts.js"></script>

    <!-- Main JS -->
    <script src="../reports/assets/js/main.js"></script>

    <!-- Page JS -->
    <script src="../reports/assets/js/dashboards-analytics.js"></script>

    <!-- Place this tag in your head or just before your close body tag. -->
    <script async defer src="https://buttons.github.io/buttons.js"></script>


@include('includes.footerJs')
@include('includes.footer')