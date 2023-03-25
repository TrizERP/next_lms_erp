@include('includes.headcss')
<body class="fix-header">
    <div id="wrapper">
        <div id="page">
          <header class="navbar justify-content-between flex-nowrap fixed-top">
                <div class="d-md-flex align-items-center">
                    <div class="d-flex align-items-center mobile-logo-bar">
                        <button class="collapse-btn left-collapse-btn d-md-none">
                            <em class="fas fa-bars"></em>
                        </button>
                        <div class="text-center flex-fill">
                          <a class="navbar-brand" href="{{ route('dashboard') }}"><img src="/admin_dep/images/{{Session::get('school_logo')}}" style="height: 50px;" alt="home"></a>
                        </div>
                        <button class="collapse-btn right-collapse-btn d-md-none">
                            <em class="fas fa-bars"></em>
                        </button>
                    </div>
                    <div class="header-search d-none">
                        <input type="text" class="form-control" placeholder="Search...">
                        <button type="search" class="btn-search"></button>
                    </div>
                    <h4 class="h5 mb-0 py-2 border-left pl-3">Educational ERP</h4>
                </div>
                <div class="d-md-flex align-items-center justify-content-end header-right">
                    <div class="d-md-flex header-select">
                        <div class="row">
                            <div class="col-lg-6 col-md-6 col-6">
                              <form role="search" id="academicYears" class="app-search hidden-sm hidden-xs m-r-5">
                                  @csrf
                                <select class="cust-select form-control year-sel mb-0">
                                @foreach(Session::get('academicYears') as $kay => $vay)
                                    <option value="{{$vay->syear}}"
                                    @if(Session::get('syear') == $vay->syear)
                                    selected="selected"
                                    @endif
                                    >{{$vay->syear}}</option>
                                  @endforeach
                                </select>
                              </form>
                            </div>
                            <div class="col-lg-6 col-md-6 col-6">
                                <form role="search" id="academicTerms" class="app-search hidden-sm hidden-xs m-r-5">
                                    @csrf
                                <select class="cust-select form-control mb-0">
                                  @foreach(Session::get('academicTerms') as $kat => $vat)
                                    <option value="{{$vat->term_id}}"
                                    @if(Session::get('term_id') == $vat->term_id)
                                    selected="selected"
                                    @endif
                                    >{{$vat->title}}</option>
                                  @endforeach
                                </select>
                                </form>
                            </div>
                        </div>
                    </div>
                    <div class="d-xl-flex d-md-block d-flex flex-wrap align-items-center justify-content-between">
                        <div class="d-flex align-items-center">
                            <div class="user-online d-flex align-items-center text-nowrap">
                                <em class="fas fa-user"></em>
                                <span>1 Users online</span>
                            </div>
                            <div class="notification-block">
                                <em class="far fa-bell"></em>
                                <span class="notify-dot"></span>
                            </div>
                        </div>
                        <div class="dropdown user-dropdown">
                            <button class="dropdown-toggle d-flex align-items-center" type="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                <span class="user-photo"><img src="/storage/user/{{ Session::get('image') }}" alt=""></span>
                                <span class="user-name">{{ Session::get('name') }}</span>
                            </button>
                            <div class="dropdown-menu dropdown-menu-right mt-3" aria-labelledby="dropdownMenuButton">
                                <a class="dropdown-item" href="{{route('change_password.index')}}"><i class="mdi mdi-settings"></i> Account Setting</a>
                                <a class="dropdown-item" href="{{route('dashboard_setting.index')}}"><i class="mdi mdi-vector-triangle"></i> Dashboard Setting</a>
                                <a class="dropdown-item" href="{{route('device_check')}}"><i class="mdi mdi-table-settings"></i> Device Check</a>
                                <a class="dropdown-item" href="{{route('erp_status.index')}}"><i class="mdi mdi-content-save-settings-outline"></i> ERP Status</a>
                                <a class="dropdown-item" href="{{route('implementation')}}"><i class="mdi mdi-checkerboard"></i> Implementation</a>
                                @if(strtoupper(Session::get('user_profile_name')) == 'ADMIN')
                                <a class="dropdown-item" href="{{route('add_groupwise_rights.index')}}"><i class="mdi mdi-lumx"></i> Groupwise Rights</a>
                                <a class="dropdown-item" href="{{route('add_individual_rights.index')}}"><i class="mdi mdi-repeat-once"></i> Individual Rights</a>
                                @endif
                                <a class="dropdown-item" href="{{ url('/logout') }}"><i class="mdi mdi-power"></i>
                                    Logout</a>
                            </div>
                        </div>
                    </div>
                </div>
          </header>
            <aside class="left-sidebar d-flex">
                <div class="main-menu-block">
                    <div class="main-nav nav flex-column nav-pills" role="tablist" aria-orientation="vertical">
                        <a class="nav-link" href="{{route('dashboard')}}">
                <span class="menu-main-icon">

                    <img class="icon-nrml" src="{{ asset('/admin_dep/images/menu-dashboard.png') }}" alt="">

                    <img class="icon-hvr" src="{{ asset('/admin_dep/images/menu-dashboard-white.png') }}" alt="">
                </span>
                            Dashboard
                        </a>
                        @if(!empty($menuMaster))
                            @php
                                $i = 1;
                            @endphp
                            @foreach($menuMaster as $key => $value)
                                @php
                                    $icon_name = $value['icon'];
                                    $icon_nrml = "https://".$_SERVER['HTTP_HOST']."/admin_dep/images/menu-$icon_name.png";
                                    $icon_hvr = "https://".$_SERVER['HTTP_HOST']."/admin_dep/images/menu-$icon_name-white.png";
                                @endphp
                                <a class="nav-link" id="menu-{{$i}}-tab" data-toggle="pill" href="#menu-{{$i}}"
                                   role="tab" aria-controls="menu-{{$i}}" aria-selected="false">
                <span class="menu-main-icon">
                    <img class="icon-nrml" src="{{$icon_nrml}}" alt="">
                    <img class="icon-hvr" src="{{$icon_hvr}}" alt="">
                </span>
                                    {{$value['name']}}
                                </a>
                @php $i++; @endphp
                            @endforeach
                        @endif
        </div>
    </div>
</aside>

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="card">
            <center>
              <!-- <img src="http://dev.triz.co.in/admin_dep/images/404-error-dribbble.gif" style="width: 30%;" /> -->
              <img src="https://erp.triz.co.in/admin_dep/images/404-snow.gif" style="width: 25%;" />
              <h3>
                <b>Oops!</b> There were some problems with your input.
              </h3>
              <div class="alert alert-danger">
                <div class="error">
                  <!-- <div class="error__subtitle">{{$error}}</div></br> -->
                      <div class="error__description">Something went wrong while performing your request. Please contact
                          Triz Administrator.
                      </div>
                </div>
              </div>
                <button class="btn btn-warning" onclick="goBack();">GO BACK</button>
            </center>
        </div>
    </div>
</div>

            @include('includes.footerJs')
<script type="text/javascript">
  function goBack(){
    window.history.back();
  }
</script>
@include('includes.footer')
