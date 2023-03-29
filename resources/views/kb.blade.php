@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="mt-30">
            <div class="white-box">
                <div class="row">
                    <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                        <h4 class="page-title">Knowledge Base</h4>
                    </div>
                    <!-- <div class="col-lg-9 col-sm-8 col-md-8 col-xs-12">
                        <button class="right-side-toggle waves-effect waves-light btn-info btn-circle pull-right m-l-20">
                            <i class="ti-settings text-white"></i>
                        </button>
                    </div> -->
            <!-- /.col-lg-12 -->
                </div>
            </div>
        </div>

        <!-- /.row -->
        <!-- ============================================================== -->
        <!-- Different data widgets @if(!empty($data['message'])){{ $data['message'] }} @endif -->
        <!-- ============================================================== -->
        <!-- .row -->
        <div class="row slides">

            @if(isset($data['data']))
            @foreach($data['data'] as $key => $value)
                <div class="col-lg-3 col-sm-6 col-xs-12 slide">
                    <div class="white-box  box-radius">
                        <a href="{{route('knowledge_base_detail',['id'=>$value['id'],'title'=>str_replace(' ','-',$value['name'])])}}"><h3 class="">{{$value['name']}}</h3></a>
                        <ul class="list-inline two-part" style="text-align: center;">
                            <li>
                                <img src="{{$value['image']}}" />
                                <!-- <div id="sparklinedash"></div> -->
                            </li>
                        </ul>
                    </div>
                </div>
            @endforeach
            @endif
            
        </div>

        <div class="row">
        <div class="right-sidebar">
            <div class="slimscrollright">
                <div class="rpanel-title"> Choose Theme <span><i class="ti-close right-side-toggle"></i></span> </div>
                <div class="r-panel-body">
                    <ul id="themecolors" class="m-t-20">
                        <li><b>With Light sidebar</b></li>
                        <li><a href="javascript:void(0)" data-theme="default" class="default-theme">1</a></li>
                        <li><a href="javascript:void(0)" data-theme="green" class="green-theme">2</a></li>
                        <li><a href="javascript:void(0)" data-theme="gray" class="yellow-theme">3</a></li>
                        <li><a href="javascript:void(0)" data-theme="blue" class="blue-theme">4</a></li>
                        <li><a href="javascript:void(0)" data-theme="purple" class="purple-theme">5</a></li>
                        <li><a href="javascript:void(0)" data-theme="megna" class="megna-theme">6</a></li>
                    </ul>
                </div>
            </div>
        </div>
        <!-- ============================================================== -->
        <!-- end right sidebar -->
        <!-- ============================================================== -->
        </div>
    <!-- /.container-fluid -->
<!-- ============================================================== -->
<!-- End Page Content -->
<!-- ============================================================== -->
</div>

@include('includes.footerJs')
@include('includes.footer')
