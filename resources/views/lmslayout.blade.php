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

<style type="text/css">
    .\31 {
        pointer-events: none !important;
        cursor: default !important;
        color: var(--primary) !important;
    }

    #profileImage {
        width: 70px;
        height: 50px;
        background: #512DA8;
        font-size: 35px;
        color: #fff;
        border-radius: 0%;
        padding: 19px 8px;
    }
</style>

<body class="fix-header">

@php
    $school_logo = session()->get('school_logo');
    $loginpage_link = session()->get('loginpage_link');
    $getInstitutes = session()->get('getInstitutes');
    $academicYears = session()->get('academicYears');
    $academicTerms = session()->get('academicTerms');
    // dd(count($academicYears));
@endphp
<div id="wrapper">
    <div id="page">
        <header class="navbar justify-content-between flex-nowrap fixed-top">
            <div class="d-md-flex align-items-center">
                <div class="d-flex align-items-center mobile-logo-bar">
                    <button class="collapse-btn left-collapse-btn d-md-none">
                        <em class="fas fa-bars"></em>
                    </button>
                    <div class="text-center flex-fill">
                        @php
                            //  if(session()->get('institute_type')==="college"){
                               //   $col_md = "col-md-3";
                                // }else{ -->
                                     $col_md = "col-md-4";
                                 // } -->
                                  if($school_logo != ""){
                        @endphp
                        <a class="navbar-brand" href="{{ route('dashboard') }}"><img
                                src="/admin_dep/images/{{$school_logo}}" style="height: 50px;" alt="home"></a>
                        @php
                            }
                            else
                            {
                            $words = explode(" ", Session::get('name'));
                            $name_initial = strtoupper($words[0][0] . $words[1][0]);
                        @endphp
                        <div id="profileImage">{{$name_initial}}</div>
                        @php
                            }
                        @endphp
                    </div>

                    <button class="collapse-btn right-collapse-btn d-md-none">
                        <em class="fas fa-bars"></em>
                    </button>
                </div>
                <div class="header-search d-none">
                    <input type="text" class="form-control" placeholder="Search...">
                    <button type="search" class="btn-search"></button>
                </div>
                @php
                    $schoolData = DB::table('school_setup')
                    ->select('SchoolName')
                    ->where('id', session()->get("sub_institute_id"))
                    ->first();
                @endphp
                <h4 class="h5 mb-0 py-2 border-left pl-3">{{ $schoolData->SchoolName ?? 'School Name Not Found' }}</h4>
            </div>
            <div class="d-md-flex align-items-center justify-content-end header-right">
                <div class="d-md-flex header-select">
                    <div class="row">
                        <div class="{{$col_md}}">
                            <div class="ui-widget">
                                <input type="text" name="search_menu" id="search_menu" value="" class="form-control mb-0" autocomplete="off" placeholder="Search Anything">
                            </div>
                        </div>

                        @if(Session::get('is_admin') == 1 && Session::get('user_profile_name') == 'Super Admin')
                            <div class="{{$col_md}}">
                                <form id="institute" class="app-search hidden-sm hidden-xs m-r-5">
                                    @csrf
                                    <select class="cust-select form-control"
                                            onchange="setInstitute('institute',this.value);">
                                        <option value="">Select Institute</option>
                                        @if(count($getInstitutes) > 0)
                                            @foreach($getInstitutes as $k => $v)
                                                <option value="{{$v->Id}}"
                                                        @if(Session::get('sub_institute_id') == $v->Id)
                                                            selected="selected"
                                                    @endif
                                                >{{$v->SchoolName}}</option>
                                            @endforeach
                                        @endif
                                    </select>
                                </form>
                            </div>
                        @endif
                        <div class="{{$col_md}}">
                            <form role="search" id="academicYears" class="app-search hidden-sm hidden-xs m-r-5">
                                @csrf
                                <select class="cust-select form-control year-sel mb-0"
                                        onchange="setSession('syear',this.value);">
                                    @if(count($academicYears) > 0)
                                        @foreach($academicYears as $kay => $vay)
                                            <option value="{{$vay->syear}}"
                                                    @if(Session::get('syear') == $vay->syear)
                                                        selected="selected"
                                                @endif
                                            >{{$vay->syear}}</option>
                                        @endforeach
                                    @endif
                                </select>
                            </form>
                        </div>

                        <div class="{{$col_md}}">
                            <form role="search" id="academicTerms" class="app-search hidden-sm hidden-xs m-r-5">
                                @csrf
                                <select class="cust-select form-control mb-0"
                                        onchange="setSession('term_id',this.value);" style="width: auto;">
                                    @if(count($academicTerms) > 0)
                                        @foreach($academicTerms as $kat => $vat)
                                            <option value="{{$vat->term_id}}"
                                                    @if(Session::get('term_id') == $vat->term_id)
                                                        selected="selected"
                                                @endif
                                            >{{$vat->title}}</option>
                                        @endforeach
                                    @endif
                                </select>
                            </form>
                        </div>

                    </div>
                </div>
                <div class="d-xl-flex d-md-block d-flex flex-wrap align-items-center justify-content-between">

                    <div class="dropdown user-dropdown">
                        <button class="dropdown-toggle d-flex align-items-center" type="button" id="dropdownMenuButton"
                                data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                            <span class="user-photo"><img src="/storage/user/{{ $school_logo }}" alt=""></span>
                            <span class="user-name">{{ Session::get('name') }}</span>
                        </button>
                        <div class="dropdown-menu dropdown-menu-right mt-3" aria-labelledby="dropdownMenuButton">
                            <!-- <a class="dropdown-item" href="#">Profile</a> -->
                            <a class="dropdown-item" href="{{route('change_password.index')}}"><i
                                    class="mdi mdi-settings"></i> Change Password</a>
                            <a class="dropdown-item" href="{{route('dashboard_setting.index')}}"><i
                                    class="mdi mdi-vector-triangle"></i> Dashboard Setting</a>
                            <a class="dropdown-item" href="{{route('device_check')}}"><i
                                    class="mdi mdi-table-settings"></i> Device Check</a>
                            <a class="dropdown-item" href="{{route('erp_status.index')}}"><i
                                    class="mdi mdi-content-save-settings-outline"></i> ERP Status</a>
                            <a class="dropdown-item" href="{{route('implementation')}}"><i
                                    class="mdi mdi-checkerboard"></i> Implementation</a>
                            <a class="dropdown-item" href="{{route('Onboarding')}}"><i
                                    class="mdi mdi-checkerboard"></i> Onboarding</a>
                            @if(Session::get('user_profile_name') == 'Admin')
                                <a class="dropdown-item" href="{{route('norm-clature.index')}}"><i
                                        class="mdi mdi-wallet-travel"></i> Language Setting</a>
                                <a class="dropdown-item" href="{{route('add_groupwise_rights.index')}}"><i
                                        class="mdi mdi-lumx"></i> Groupwise Rights</a>
                                <a class="dropdown-item" href="{{route('add_individual_rights.index')}}"><i
                                        class="mdi mdi-repeat-once"></i> Individual Rights</a>
                                <a class="dropdown-item" href="{{route('add_mobileapp_menu_rights.index')}}"><i
                                        class="mdi mdi-cellphone-lock"></i> Mobile App Menu Rights</a>
                            @endif
                            @if(Session::get('user_profile_name') == 'Super Admin' && Session::get('is_admin') == 1)
                                <a class="dropdown-item" href="{{route('manage_institute.index')}}"><i
                                        class="mdi mdi-home-city"></i> Manage Institute</a>
                            @endif

                            @if(isset($loginpage_link) && $loginpage_link != '')
                                <a class="dropdown-item" href="{{$loginpage_link}}"><i class="mdi mdi-power"></i> Logout</a>
                            @else
                                <a class="dropdown-item" href="{{ url('/logout') }}"><i class="mdi mdi-power"></i>
                                    Logout</a>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </header>

        @if(Route::current()->getName() != 'home')
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mt-2">
                    <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
                    @php
                        $link = url('/');
                        $all_segments = request()->segments();
                        $search = $all_segments[1] ?? $all_segments[0];
                        $bread = DB::table('tblmenumaster')->whereRaw('link LIKE "'.$search.'%"')->get();
                        $i = 1;
                       request()->session()->put('current_menu_id', $bread[0]->id ?? '');
                    @endphp
                    @if(count($bread)>0)
                        @php
                            $main = DB::table('tblmenumaster')->where('id',$bread[0]->parent_menu_id)->get();
                        @endphp
                        <li class="breadcrumb-item"><a href="{{ route('home') }}">{{$main[0]->name ?? ''}}</a></li>
                        <li class="breadcrumb-item "><a href="{{ route($bread[0]->link) }}" class="text-dark">{{$bread[0]->name ?? ''}}</a></li>
                    @else
                        @foreach($all_segments as $segment)
                            @php
                                $segment = str_replace('breackoff','structure',$segment);

                                $newsegment = ucwords(str_replace('_', ' ', $segment));
                                $link .= "/" . request()->segment($loop->iteration);
                            @endphp
                            @if(rtrim(request()->route()->getPrefix(), '/') != $segment && ! preg_match('/[0-9]/', $segment))
                                <li class="breadcrumb-item {{ $loop->last ? 'active' : '' }}">
                                    @if($loop->last)
                                        {{ $newsegment }}
                                    @else
                                        <a href="{{ $link }}" class="{{$i}}">{{ $newsegment }}</a>
                                    @endif
                                </li>
                            @endif
                            @php $i++; @endphp
                        @endforeach
                    @endif
                </ol>
            </nav>
        @endif

        <!-- End Top Navigation -->


        <link href="https://code.jquery.com/ui/1.10.4/themes/ui-lightness/jquery-ui.css" rel="preload" as="style" onload="this.onload=null;this.rel='stylesheet'">
        <script src="{{ asset("/admin_dep/js/jquery-ui.js") }}"></script>
        <script>
            $(document).ready(function () {
                $("#search_menu").autocomplete({
                    source: function (request, response) {
                        $.ajax({
                            url: "{{route('searching_menu')}}",
                            type: 'POST',
                            data: {
                                'value': request.term
                            },
                            success: function (data) {
                                response($.map(data, function (item) {
                                    return {
                                        label: item.name,
                                        value: item.link
                                    }
                                }));
                            }
                        });
                    },
                    select: function (event, ui) {

                        route_link = ui.item.value;
                        $.ajax({
                            url: "{{route('get_search_url')}}",
                            type: 'POST',
                            data: {'value': route_link},
                            success: function (data) {
                                window.location.href = data;
                            }
                        });

                    }
                })
            });


        </script>

        <!-- ============================================================== -->
        <!-- Left Sidebar - style you can find in sidebar.scss  -->
        <!-- ============================================================== -->
        <?php
        $school_logo = session()->get('school_logo');
        ?>
        <div id="content" class="">
            <aside class="left-sidebar d-flex">
                <div class="main-menu-block">
                    <div class="main-nav nav flex-column nav-pills" role="tablist" aria-orientation="vertical">
                        <a class="nav-link" href="{{ route('dashboard') }}">
                    <span class="menu-main-icon">

                        <img class="icon-nrml" src="{{ asset('/admin_dep/images/menu-dashboard.png') }}" alt="">

                        <img class="icon-hvr" src="{{ asset('/admin_dep/images/menu-dashboard-white.png') }}" alt="">
                    </span>
                            Dashboard
                        </a>
                        @if (!empty($menuMaster))
                            @php
                                $i = 1;
                            @endphp
                            @foreach ($menuMaster as $key => $value)
                                @php
                                    $icon_name = $value['icon'];
                                    $icon_nrml = env('APP_URL') . "/admin_dep/images/menu-$icon_name.png";
                                    $icon_hvr = env('APP_URL') . "/admin_dep/images/menu-$icon_name-white.png";
                                @endphp
                                <a class="nav-link" id="menu-{{ $i }}-tab" data-toggle="pill" href="#menu-{{ $i }}" role="tab" aria-controls="menu-{{ $i }}" aria-selected="false">
                    <span class="menu-main-icon">
                        <img class="icon-nrml" src="{{ $icon_nrml }}" alt="">
                        <img class="icon-hvr" src="{{ $icon_hvr }}" alt="">
                    </span>
                                    {{ $value['name'] }}
                                </a>
                                @php $i++; @endphp
                            @endforeach
                        @endif

                        <?php
                        if (session()->get('is_admin') != 1) {
                            $sub_institute_id = session()->get('sub_institute_id');
                            $DUSER_ID = session()->get('DUSER_ID');
                            $DUSER_PWD = session()->get('DUSER_PWD');
                            $syear = session()->get('syear');

                            $client_data = DB::select("select *,if(db_hrms is null,0,1) as rights,
                        if(db_library is null,0,1) as library_rights,s.logo
                    from school_setup s
                    inner join tblclient c on c.id = s.client_id
                    where s.Id = $sub_institute_id");

                            $db_host = $client_data[0]->db_host;
                            $db_user = $client_data[0]->db_user;
                            $db_password = $client_data[0]->db_password;
                            $client_name = $client_data[0]->client_name;
                            $hrms_folder = $client_data[0]->hrms_folder;
                            $hrms_db_hrms = $client_data[0]->db_hrms;
                            $hrms_rights = $client_data[0]->rights;
                            $library_db = $client_data[0]->db_library;
                            $library_rights = $client_data[0]->library_rights;
                            $library_host = $db_host;
                            $library_user = $db_user;
                            $library_password = $db_password;
                            $solution_db = $client_data[0]->db_solution;
                            $school_name = $client_data[0]->SchoolName;
                            $school_logo = $client_data[0]->logo;

                            $lms_data = DB::select("select *,if(db_lms is null,0,1) as lms_rights
                        from school_setup s
                        inner join tblclient c on c.id = s.client_id
                        where s.Id = $sub_institute_id AND s.is_lms = 'Y'");

                            if (count($lms_data) > 0) {
                                $lms_db_host = $lms_data[0]->db_host;
                                $lms_db_user = $lms_data[0]->db_user;
                                $lms_db_password = $lms_data[0]->db_password;
                                $lms_db = $lms_data[0]->db_lms;
                                $lms_rights = $lms_data[0]->lms_rights;
                            } else {
                                $lms_db_host = '';
                                $lms_db_user = '';
                                $lms_db_password = '';
                                $lms_db = '';
                                $lms_rights = '';
                            }

                            $USER_GROUP_ID = "";
                            if (session()->get('user_profile_name') == 'Admin') {
                                $USER_GROUP_ID = 1;
                            } else if (session()->get('user_profile_name') == 'Teacher') {
                                $USER_GROUP_ID = 2;
                            } else if (session()->get('user_profile_name') == 'Student') {
                                $USER_GROUP_ID = 3;
                            }

                            $hrms_link = "?NEW_ERP=1&DUSER_ID=$DUSER_ID&USER_GROUP_ID=$USER_GROUP_ID&DUSER_NAME=$DUSER_ID&hrms_db_host=$db_host&hrms_db_user=$db_user&hrms_db_password=$db_password&hrms_db_hrms=$hrms_db_hrms&client_name=$client_name";

                            //$hrms_link = "?NEW_ERP=1&DUSER_ID=" . base64_encode($DUSER_ID) . "&USER_GROUP_ID=" . base64_encode($USER_GROUP_ID) . "&DUSER_NAME=" . base64_encode($DUSER_ID) . "&hrms_db_host=" . base64_encode($db_host) . "&hrms_db_user=" . base64_encode($db_user) . "&hrms_db_password=" . base64_encode($db_password) . "&hrms_db_hrms=" . base64_encode($hrms_db_hrms) . "&client_name=" . base64_encode($client_name);

                            //$library_link = "?NEW_ERP=1&DUSER_ID=".base64_encode($DUSER_ID)."&USER_GROUP_ID=".base64_encode($USER_GROUP_ID)."&DUSER_PWD=".base64_encode($DUSER_PWD)."&db_host=".base64_encode($library_host)."&db_user=".base64_encode($library_user)."&db_password=".base64_encode($library_password)."&db_library=".base64_encode($library_db)."&solution_db=".base64_encode('development_erp')."&school_name=".base64_encode($school_name)."&SUB_INSTITUTE_ID=".base64_encode($sub_institute_id)."&school_logo=".base64_encode($school_logo)."&dyear=".base64_encode($syear);

                            $library_link = "?NEW_ERP=1&DUSER_ID=$DUSER_ID&USER_GROUP_ID=$USER_GROUP_ID&DUSER_PWD=$DUSER_PWD&db_host=$library_host&db_user=$library_user&db_password=$library_password&db_library=$library_db&solution_db=development_erp&school_name=$school_name&SUB_INSTITUTE_ID=$sub_institute_id&school_logo=$school_logo&dyear=$syear";

                            //$library_link = "?".App\Helpers\encrypt_url('encrypt',"it&1&$DUSER_ID&$USER_GROUP_ID&$DUSER_PWD&$library_host&$library_user&$library_password&$library_db&development_erp&$school_name&$sub_institute_id&$school_logo&$syear");

                            $lms_link =  "lmslogin.php?SUB_INSTITUTE_ID=" . $sub_institute_id . "&U=" . base64_encode($DUSER_ID) . "&P=" . base64_encode($DUSER_PWD) . "";

                            $dailylms_link = "dailylmslogin.php?SUB_INSTITUTE_ID=" . $sub_institute_id . "&U=" . base64_encode($DUSER_ID) . "&P=" . base64_encode($DUSER_PWD) . "";

                        if ($hrms_rights == 1 && Session::get('user_profile_name') != 'Student') {
                            ?>

                        <a class="nav-link" target="_blank" href="http://150.129.172.110/new_hrms/Products/hrms/login.php{{ $hrms_link }}">
                            <span class="menu-main-icon">
                                <i class="mdi mdi-view-compact-outline"></i>
                            </span>
                            HRMS
                        </a>

                        <?php } ?>

                            <?php
                        if ($library_rights == 1 && Session::get('user_profile_name') != 'Student') {
                            ?>
                        <a class="nav-link" target="_blank" href="".env('APP_URL')."/library/admin/index.php{{ $library_link }}">
                        <span class="menu-main-icon">
                                <i class="mdi mdi-library-shelves"></i>
                            </span>
                        Library
                        </a>

                            <?php
                        }
                        }
                        ?>


                    </div>
                </div>

                <div class="tab-content sub-menu-block sub-tab-hide active">
                    {{-- @php
                        echo "<pre>"; print_r($submenuMaster); exit;
                    @endphp --}}
                    @if (!empty($menuMaster))
                        @php

                            $i = 1;

                        @endphp
                        @foreach ($menuMaster as $key => $value)
                            @if (!empty($submenuMaster[$value['id']]))

                                <div class="tab-pane" id="menu-{{ $i }}" role="tabpanel" aria-labelledby="menu-{{ $i }}-tab">
                                    <div class="submenu-header d-flex align-items-center justify-content-between">
                                        <h5 class="m-0">{{ $value['name'] }}</h5>
                                        <a href="javascript:();" class="submenu-sidebar" data-toggle="tooltip1" data-placement="top" title="Hide/Show">
                                            <img src="{{ asset('/admin_dep/images/bar.png') }}" alt=""></a>
                                    </div>
                                    <div class="subnav-wrapper">
                                        @foreach ($submenuMaster[$value['id']] as $subkey => $submenuValue)
                                            @if ($submenuValue['link'] == 'javascript:void(0);' || $submenuValue['link'] == '')
                                                <div class="sub-drop-panel">
                                                    <div class="sub-drop-header d-flex align-items-center justify-content-between">
                                                        <div class="panel-click flex-fill">
                                                            <i class="{{ $submenuValue['icon'] }}" data-icon="v"></i>
                                                            <span class="title" onclick="load_rightside_menu({{ $submenuValue['id'] }},{{ $i }});">{{ $submenuValue['name'] }}</span>
                                                        </div>

                                                        {{-- @if (!empty($submenuValue['quick_menu']) && !empty($quickmenuMaster[$submenuValue['id']]))
                                                            <div class="dot-sub-dropdown">
                                                                <span class="dot-icon">
                                                                    <img src="{{ asset('/admin_dep/images/three-dots.png') }}" alt="">
                                                        </span>
                                                        <div class="abs-submenu">
                                                            <div class="abs-submenu-header d-flex align-items-center justify-content-between">
                                                                <h5 class="m-0">Quick Links</h5>
                                                            </div>
                                                            <div class="abs-submenu-body">
                                                                <ul class="list-unstyled sub-drop-nav">
                                                                    @foreach ($quickmenuMaster[$submenuValue['id']] as $quickkey => $quickValue)
                                                                    <li><a href="{{ route($quickValue['link']) }}">{{$quickValue['name']}}</a></li>
                                                                    @endforeach
                                                                </ul>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    @endif --}}
                                                        <span class="drop-icn">
                            <em class="fas fa-angle-down"></em>
                        </span>

                                                    </div>
                                                    <div class="sub-drop-body">
                                                        <ul class="list-unstyled sub-drop-nav">

                                                            @if (!empty($subChildmenuMaster[$submenuValue['id']]))
                                                                @foreach ($subChildmenuMaster[$submenuValue['id']] as $subChildmenuKey => $subChildmenuValue)
                                                                    @if ($subChildmenuValue['link'] == 'javascript:void(0);' || $subChildmenuValue['link'] == '')
                                                                        <li>
                                                                            <a href="javascript:void(0);" onclick="sessionMenu({{ $subChildmenuValue['id'] }});">
                                                                                <i class="{{ $subChildmenuValue['icon'] }}" data-icon="v"></i>
                                                                                <span class="hide-menu" id="{{ $subChildmenuValue['name'] }}">
                                        {{ $subChildmenuValue['name'] }}
                                    </span>
                                                                            </a>
                                                                        </li>
                                                                    @else
                                                                        @if (Route::has($subChildmenuValue['link']))
                                                                            <li>
                                                                                <a href="{{ route($subChildmenuValue['link']) }}" onclick="sessionMenu({{ $subChildmenuValue['id'] }});redirect_pages_soni('{{ route($subChildmenuValue['link']) }}','{{ $submenuValue['id'] }}','{{ $i }}','{{ $subChildmenuValue['id'] }}');">
                                                                                    <i class="{{ $subChildmenuValue['icon'] }}" data-icon="v"></i>
                                                                                    <span class="hide-menu" id="{{ $subChildmenuValue['name'] }}">
                                        {{ $subChildmenuValue['name'] }}
                                    </span>
                                                                                    <input type="hidden" value="{{App\Helpers\get_string($subChildmenuValue['id'],'menu_id')}}">
                                                                                </a>
                                                                            </li>
                                                                        @else
                                                                            <!-- Handle case when route is not defined -->
                                                                            <li>
                                                                                <a href="#">
                                                                                    <i class="{{ $subChildmenuValue['icon'] }}" data-icon="v"></i>
                                                                                    <span class="hide-menu" id="{{ $subChildmenuValue['name'] }}">
                                        {{ $subChildmenuValue['name'] }}
                                    </span>
                                                                                </a>
                                                                            </li>
                                                                        @endif
                                                                    @endif
                                                                @endforeach

                                                                @if ($submenuValue['name'] == 'Admission')
                                                                    {{-- <li>
                                                                                        <a target="_blank" href="{{route('onlineEnquiryFirst', ['id' => Session::get('sub_institute_id'), 'title' => Session::get('school_name')])}}"><i class="mdi mdi-monitor fa-fw" data-icon="v"></i> Online Admission Form </a>
                                                                    </li> --}}
                                                                @endif
                                                            @endif
                                                        </ul>
                                                    </div>
                                                </div>
                                            @else
                                                <div class="sub-drop-panel">
                                                    <div class="sub-drop-header d-flex align-items-center justify-content-between">
                                                        <li class="sub-drop-header d-flex align-items-center justify-content-between w-100">
                                                            @if(Route::has($submenuValue['link']))

                                                                <a href="{{ route($submenuValue['link']) }}" onclick="sessionMenu({{ $submenuValue['id'] }});redirect_pages_soni('{{ route($submenuValue['link']) }}','{{ $submenuValue['id'] }}','{{ $i }}');" class="panel-click flex-fill">
                                                                    <i class="{{ $submenuValue['icon'] }} mr-1" data-icon="v"></i><span class="title">{{ $submenuValue['name'] }}</span>
                                                                </a>
                                                                {{-- @if (!empty($submenuValue['quick_menu']) && !empty($quickmenuMaster[$submenuValue['id']]))
                                                                     <div class="dot-sub-dropdown">
                                                                        <span class="dot-icon">
                                                                            <img src="{{ asset('/admin_dep/images/three-dots.png') }}" alt="">
                                                                </span>
                                                                <div class="abs-submenu">
                                                                    <div class="abs-submenu-header d-flex align-items-center justify-content-between">
                                                                        <h5 class="m-0">Quick Links</h5>
                                                                    </div>
                                                                    <div class="abs-submenu-body">
                                                                        <ul class="list-unstyled sub-drop-nav">
                                                                            @foreach ($quickmenuMaster[$submenuValue['id']] as $quickkey => $quickValue)
                                                                            <li><a href="{{ route($quickValue['link']) }}">{{$quickValue['name']}}</a></li>
                                                                            @endforeach
                                                                        </ul>
                                                                    </div>
                                                                </div>
                                                        </div>
                                                        @endif --}}
                                                            @endif
                                                        </li>
                                                    </div>
                                                </div>
                                            @endif
                                        @endforeach
                                    </div>
                                </div>
                                @php $i++; @endphp
                            @endif

                        @endforeach
                    @endif

                </div>
            </aside>


            <!-- ============================================================== -->
            <!-- End Left Sidebar -->
            <!-- ============================================================== -->

@section('container')
@show

