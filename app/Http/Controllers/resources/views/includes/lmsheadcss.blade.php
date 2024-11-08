<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="">
    <meta name="author" content="">
    <link rel="icon" type="image/png" sizes="16x16" href="images/favicon.png">
    <title>TRIZ ERP</title>


    <!-- Bootstrap Core CSS -->
    <!-- <link href="{{ asset('/admin_dep/bootstrap/dist/css/bootstrap.min.css') }}" rel="stylesheet"> -->
    <!-- Menu CSS -->
    <!-- <link href="{{ asset('/plugins/bower_components/sidebar-nav/dist/sidebar-nav.min.css') }}" rel="stylesheet"> -->
    <!-- <link href="{{ asset('/plugins/bower_components/css-chart/css-chart.css') }}" rel="stylesheet"> -->

    <!-- chartist CSS -->
    <!-- <link href="{{ asset('/plugins/bower_components/chartist-js/dist/chartist.min.css') }}" rel="stylesheet"> -->
    <!--  <link
        href="{{ asset('/plugins/bower_components/chartist-plugin-tooltip-master/dist/chartist-plugin-tooltip.css') }}"
        rel="stylesheet"> -->
    <!-- Calendar CSS -->
    <link href="{{ asset('/plugins/bower_components/calendar/dist/fullcalendar.css') }}" rel="stylesheet">
    <!-- animation CSS -->
    <!-- <link href="{{ asset('/admin_dep/css/animate.css') }}" rel="stylesheet"> -->
    <!-- Custom CSS -->
    <!-- <link href="{{ asset('/plugins/bower_components/morrisjs/morris.css') }}" rel="stylesheet"> -->
    <!-- <link href="{{ asset('/admin_dep/css/triz-style.css') }}" rel="stylesheet"> -->
    <!-- color CSS -->
    <!-- <link href="{{ asset('/admin_dep/css/colors/default.css') }}" id="theme" rel="stylesheet"> -->

    <link href="{{ asset('/admin_dep/css/bootstrap.css') }}" rel="stylesheet">
    <link href="{{ asset('/admin_dep/css/bootstrap-select.css') }}" rel="stylesheet">
    <link href="{{ asset('/admin_dep/css/bootstrap-datepicker.min.css') }}" rel="stylesheet">
    <link href="{{ asset('/admin_dep/css/lms-docs.css') }}" rel="stylesheet">
    <link href="{{ asset('/admin_dep/css/css3.css') }}" rel="stylesheet">
    <link href="{{ asset('/admin_dep/css/fontawesome.css') }}" rel="stylesheet">
    <link href="{{ asset('/admin_dep/css/materialdesignicons.min.css') }}" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    @if (strrchr($_SERVER['REQUEST_URI'], 'online_exam_attempt'))
        <link href="{{ asset('/admin_dep/css/lms-online.css') }}" rel="stylesheet">
    @else
        <link href="{{ asset('/admin_dep/css/lms-elements.css') }}" rel="stylesheet">
    @endif
    <!-- <link href="{{ asset('/admin_dep/css/style.css') }}" rel="stylesheet"> -->
    <link href="{{ asset('/admin_dep/css/style_lms.css') }}" rel="stylesheet">
    <!-- Morris CSS -->

    <link href="{{ asset('/plugins/bower_components/toast-master/css/jquery.toast.css') }}" rel="stylesheet">

    <!--    <link href="{{ asset('/plugins/bower_components/bootstrap-datepicker/bootstrap-datepicker.min.css') }}"
        rel="stylesheet" type="text/css" /> -->


    <!-- <link href="{{ asset('/plugins/bower_components/datatables/media/css/dataTables.bootstrap.css') }}" rel="stylesheet"
        type="text/css" /> -->


    <link href="https://cdn.datatables.net/buttons/1.5.6/css/buttons.dataTables.min.css" rel="stylesheet"
        type="text/css" />
    <style type="text/css">
        @media print {
            .pagebreak {
                page-break-before: always;
            }

            /* page-break-after works, as well */
        }
        #loading-overlay {
		position: fixed;
		top: 0;
		left: 0;
		width: 100%;
		height: 100%;
		background-color: rgb(251 251 252); /*rgba(0, 0, 0, 0.5); /* Adjust the opacity as needed */
		z-index: 9999;
	}

	#loading-overlay center {
	position: absolute;
	top: 50%;
	left: 50%;
	transform: translate(-50%, -50%);
	}

     @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>

    <!-- Global site tag (gtag.js) - Google Analytics -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=UA-153077517-1"></script>
    <script>
        window.dataLayer = window.dataLayer || [];

        function gtag() {
            dataLayer.push(arguments);
        }

        gtag('js', new Date());
        gtag('config', 'UA-153077517-1');
    </script>
    {{--    <script type="text/javascript"> --}}
    {{--        $(document).ready(function() { --}}
    {{--            hideRightsideMenu(); --}}
    {{--         }); --}}
    {{--    </script> --}}
</head>
