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
                $icon_nrml = "../admin_dep/images/menu-$icon_name.png"; //env('APP_URL') . 
                $icon_hvr = "../admin_dep/images/menu-$icon_name-white.png"; //env('APP_URL') . 
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
                    $hrms_rights = 0;//$client_data[0]->rights;
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

                    $library_link = "?NEW_ERP=1&DUSER_ID=$DUSER_ID&USER_GROUP_ID=$USER_GROUP_ID&DUSER_PWD=$DUSER_PWD&db_host=$library_host&db_user=$library_user&db_password=$library_password&db_library=$library_db&solution_db=development_erp&school_name=$school_name&SUB_INSTITUTE_ID=$sub_institute_id&school_logo=$school_logo&dyear=$syear";

                    $lms_link =  "lmslogin.php?SUB_INSTITUTE_ID=" . $sub_institute_id . "&U=" . base64_encode($DUSER_ID) . "&P=" . base64_encode($DUSER_PWD) . "";

                    $dailylms_link = "dailylmslogin.php?SUB_INSTITUTE_ID=" . $sub_institute_id . "&U=" . base64_encode($DUSER_ID) . "&P=" . base64_encode($DUSER_PWD) . "";

                    if ($hrms_rights == 1 && Session::get('user_profile_name') == 'Admin') {//!= 'Student'
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
                                @php 
                                $subprefix = '';
                                if(in_array($subChildmenuValue['name'],['Course Content'])){
                                    $subprefix = '?activeMenu='.str_replace(' ', '',$subChildmenuValue['text']);
                                }
                                @endphp
                                <a href="{{ route($subChildmenuValue['link'])}}{{$subprefix}}" onclick="sessionMenu({{ $subChildmenuValue['id'] }});redirect_pages_soni('{{ route($subChildmenuValue['link']) }}','{{ $submenuValue['id'] }}','{{ $i }}','{{ $subChildmenuValue['id'] }}');">
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