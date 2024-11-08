@include('includes.headcss')
    <link rel="stylesheet" href="../../../tooltip/enjoyhint/jquery.enjoyhint.css">
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="mt-30">
            <div class="white-box">
                <div class="row">
                    <div class="col-lg-6 col-md-8 col-sm-8 col-xs-12">
                        <h4 class="page-title">Getting started to TRIZ ERP</h4>
                        <h5 class="welcome-msg">Alright, let's set this up! Tell us a bit about yourself</h5>
                    </div>
                    <div class="col-lg-6 col-sm-8 col-md-8 col-xs-12">
                        <button
                            class="right-side-toggle waves-effect waves-light btn-info btn-circle pull-right m-l-20">
                            <i class="ti-settings text-white"></i>
                        </button>
                    </div>
                    <!-- /.col-lg-12 -->
                </div>
            </div>
        </div>
        <!-- /.row -->
        <!-- ============================================================== -->
        <!-- Different data widgets @if(!empty($data['message'])){{ $data['message'] }} @endif -->
        <!-- ============================================================== -->
        <!-- .row -->
        <div class="row">

            <div class="col-lg-3 col-sm-6 col-xs-12">
                <div class="white-box">
                    <h3 class="box-title">Upload Data</h3>
                    <div class="text-left"> <a href="{{route('implementation_2')}}?moduleId=1"><span class="text-muted">Setup Now</span></a>
                         </div> <span class="text-success">100%</span>
                    <div class="progress m-b-0">
                        <div class="progress-bar progress-bar-success" role="progressbar" aria-valuenow="50" aria-valuemin="0" aria-valuemax="100" style="width:100%;"> <span class="sr-only">100% Complete</span> </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-sm-6 col-xs-12">
                <div class="white-box">
                    <h3 class="box-title">Fees Setup</h3>
                    <div class="text-left"> <a href="{{route('implementation_2')}}?moduleId=2"><span class="text-muted">Setup Now</span></a>
                         </div> <span class="text-success">20%</span>
                    <div class="progress m-b-0">
                        <div class="progress-bar progress-bar-success" role="progressbar" aria-valuenow="50" aria-valuemin="0" aria-valuemax="100" style="width:20%;"> <span class="sr-only">20% Complete</span> </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-sm-6 col-xs-12">
                <div class="white-box">
                    <h3 class="box-title">Result</h3>
                    <div class="text-left"> <a href="{{route('implementation_2')}}?moduleId=3"><span class="text-muted">Setup Now</span></a>
                         </div> <span class="text-success">50%</span>
                    <div class="progress m-b-0">
                        <div class="progress-bar progress-bar-success" role="progressbar" aria-valuenow="50" aria-valuemin="0" aria-valuemax="100" style="width:50%;"> <span class="sr-only">50% Complete</span> </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-sm-6 col-xs-12">
                <div class="white-box">
                    <h3 class="box-title">Report View</h3>
                    <div class="text-left"> <a href="{{route('implementation_2')}}?moduleId=4"><span class="text-muted">Setup Now</span></a>
                         </div> <span class="text-success">100%</span>
                    <div class="progress m-b-0">
                        <div class="progress-bar progress-bar-success" role="progressbar" aria-valuenow="50" aria-valuemin="0" aria-valuemax="100" style="width:100%;"> <span class="sr-only">100% Complete</span> </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-sm-6 col-xs-12">
                <div class="white-box">
                    <h3 class="box-title">Rights </h3>
                    <div class="text-left"> <a href="{{route('implementation_2')}}?moduleId=5"><span class="text-muted">Setup Now</span></a>
                         </div> <span class="text-success">100%</span>
                    <div class="progress m-b-0">
                        <div class="progress-bar progress-bar-success" role="progressbar" aria-valuenow="50" aria-valuemin="0" aria-valuemax="100" style="width:100%;"> <span class="sr-only">100% Complete</span> </div>
                    </div>
                </div>
            </div>

        </div>

    </div>
    <!-- /.container-fluid -->
<!-- ============================================================== -->
<!-- End Page Content -->
<!-- ============================================================== -->
</div>

@include('includes.footerJs')

@include('includes.footer')
