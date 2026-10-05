<!DOCTYPE html>
<html lang="en">
    <head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="">
    <meta name="author" content="">
    <link rel="icon" type="image/png" sizes="16x16" href="images/favicon.png">
    <title>TRIZ ERP</title>

    <link href="{{ asset("/plugins/bower_components/calendar/dist/fullcalendar.css") }}" rel="preload" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="{{ asset("/plugins/bower_components/calendar/dist/fullcalendar.css") }}"></noscript>

    <link href="{{ asset("/admin_dep/css/bootstrap.css") }}" rel="stylesheet" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="{{ asset("/admin_dep/css/bootstrap.css") }}"></noscript>
    
    <link href="{{ asset("/admin_dep/css/bootstrap-select.css") }}" rel="preload" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="{{ asset("/admin_dep/css/bootstrap-select.css") }}"></noscript>
    
    <link href="{{ asset("/admin_dep/css/bootstrap-datepicker.min.css") }}" rel="preload" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="{{ asset("/admin_dep/css/bootstrap-datepicker.min.css") }}"></noscript>
    
    <link href="{{ asset("/admin_dep/css/docs.css") }}" rel="preload" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="{{ asset("/admin_dep/css/bootstrap-datepicker.min.css") }}"></noscript>
    
    <link href="{{ asset("/admin_dep/css/css3.css") }}" rel="preload" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="{{ asset("/admin_dep/css/css3.css") }}"></noscript>
    
    <link href="{{ asset("/admin_dep/css/fontawesome.css") }}" rel="preload" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="{{ asset("/admin_dep/css/fontawesome.min.css") }}"></noscript>
    
    <link href="{{ asset("/admin_dep/css/materialdesignicons.min.css") }}" rel="preload" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="{{ asset("/admin_dep/css/materialdesignicons.min.css") }}"></noscript>
    
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="preload" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons"></noscript>
    
    <link href="{{ asset("/admin_dep/css/elements.css") }}" rel="preload" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="{{ asset("/admin_dep/css/elements.css") }}"></noscript>
    
    <link href="{{ asset("/admin_dep/css/style.css") }}" rel="preload" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="{{ asset("/admin_dep/css/style.css") }}"></noscript>
    

    <link href="{{ asset("/plugins/bower_components/toast-master/css/jquery.toast.css") }}" rel="preload" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="{{ asset("/plugins/bower_components/toast-master/css/jquery.toast.css") }}"></noscript>
     

    <link href="https://cdn.datatables.net/buttons/1.5.6/css/buttons.dataTables.min.css" rel="preload" as="style" onload="this.onload=null;this.rel='stylesheet'"
          type="text/css"/>
    <noscript><link rel="stylesheet" href="https://cdn.datatables.net/buttons/1.5.6/css/buttons.dataTables.min.css"></noscript>
          
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.1/css/all.min.css" integrity="sha512-5Hs3dF2AEPkpNAR7UiOHba+lRSJNeM2ECkwxUIxC1Q/FLycGTbNapWXB4tP889k5T5Ju8fs4b1P5z/iB4nMfSQ==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    
    <style type="text/css">
        @media print {
            .pagebreak {
                page-break-before: always;
            }

        }

        .ui-datepicker-inline {
            display: none !important;
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
        .iframe-container {
        position: relative;
        width: 96%;
        padding-bottom: 86.25%; /* 16:9 aspect ratio */
        height: 0;
        overflow: hidden;
        margin-left: 5%;
    }

    .responsive-iframe {
        position: absolute;
        top: 0;
        left: 0;
        width: 110%;
        height: 100%;
        border: none;
    }
    .iframe-container-new {
        position: relative;
        width: 120%;
        padding-bottom: 86.25%; /* 16:9 aspect ratio */
        overflow: relative;
    }

    .responsive-iframe-new {
        position: absolute;
        top: 0;
        left: 5px;
        width: 100%;
        height: 100%;
        border: none;
    }
    </style>

    <!-- Global site tag (gtag.js) - Google Analytics -->
    <!-- <script async src="https://www.googletagmanager.com/gtag/js?id=UA-153077517-1"></script> -->
    <script src="https://code.jquery.com/jquery-1.10.2.js"></script>
    
    <!-- <script>
        window.dataLayer = window.dataLayer || [];

        function gtag() {
            dataLayer.push(arguments);
        }

        gtag('js', new Date());
        gtag('config', 'UA-153077517-1');
    </script> -->
  
@if(\App\Helpers\is_mobile_embed())
{{-- Rendered inside the mobile app's WebView. header.blade.php and
     sideNavigation.blade.php already skip the topbar, breadcrumbs and
     sidebar; this undoes the space the stylesheet reserves for them
     (#content carries a 70-84px top padding for the fixed header, and the
     sidebar is position:fixed with the body translated clear of it).

     The display:none rules are belt and braces, for a page that renders its
     own copy of the chrome instead of using the shared includes. --}}
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<style type="text/css">
    header.navbar,
    aside.left-sidebar,
    .right-sidebar,
    nav[aria-label="breadcrumb"],
    .collapse-btn {
        display: none !important;
    }

    body.fix-header,
    #content {
        padding-top: 0 !important;
        margin-left: 0 !important;
        transform: none !important;
    }

    #page-wrapper {
        margin-left: 0 !important;
        padding-top: 0 !important;
    }

    .container-fluid {
        padding-left: 12px !important;
        padding-right: 12px !important;
    }

    /* Wide ERP tables are the usual reason a page is unusable on a phone;
       let them scroll sideways instead of forcing the page to. */
    .table-responsive {
        overflow-x: auto !important;
        -webkit-overflow-scrolling: touch;
    }
</style>
@endif

</head>
