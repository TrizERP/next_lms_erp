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
