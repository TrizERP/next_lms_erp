@include('includes.headcss')
    <link rel="stylesheet" href="../../../tooltip/enjoyhint/jquery.enjoyhint.css">
    <!-- Timeline CSS -->
    <link href="../../../plugins/bower_components/horizontal-timeline/css/horizontal-timeline.css" rel="stylesheet">
@include('includes.header')
{{-- @include('includes.sideNavigation') --}}

<div id="page-wrapper">
    <div class="container-fluid pt-5 mt-5">
        <div class="card">
            <div class="row align-items-center justify-content-center">
                <div class="col-md-12">
                    <div class="px-4 bg-dark mb-4 rounded text-white pt-4 pb-4 text-center">
                        <h3 class="page-title">Getting started to TRIZ ERP</h3>
                        <h5 class="welcome-msg">Alright, let's set this up! Tell us a bit about yourself</h5>
                    </div>
                </div>
                <!-- <div class="col-lg-6 col-sm-8 col-md-8 col-xs-12">
                    <button
                        class="right-side-toggle waves-effect waves-light btn-info btn-circle pull-right m-l-20">
                        <i class="ti-settings text-white"></i>
                    </button>
                </div> -->
                <!-- /.col-lg-12 -->
            </div>
        </div>
        <div class="card">            
            <div class="row">
                <div class="col-md-12">
                    <section class="cd-horizontal-timeline mb-3 mt-0">
                        <div class="timeline">
                            <div class="events-wrapper card">
                                <div class="box-title text-center">
                                    <h4>Progress of implementation</h4>
                                </div>
                                <div class="events">
                                    <ol>
                                        <li><a href="#0" data-date="08/01/2020" class="selected">Welcome</a></li>
                                        <li><a href="#1" data-date="09/04/2020">Data</a></li>
                                        <li><a href="#2" data-date="09/06/2020">Fees</a></li>
                                        <li><a href="#3" data-date="09/08/2020">Result</a></li>
                                        <li><a href="#4" data-date="09/10/2020">Report</a></li>
                                        <li><a href="#5" data-date="09/12/2020">Rights</a></li>
                                    </ol> <span class="filling-line" aria-hidden="true"></span>
                                </div>
                            </div>
                        </div>
                    </section>
                </div>
            </div>
        </div>

        <!-- ============================================================== -->
        <!-- Different data widgets @if(!empty($data['message'])){{ $data['message'] }} @endif -->
        <!-- ============================================================== -->
        <!-- .row -->
        <div class="card">
            <div id="errorbox" style="display:none;">
                <div class="alert alert-danger alert-block">
                    <strong>Password and confirm password does not match.</strong>
                </div>
            </div>
            <div class="row">
                <div class="col-md-4 form-group">
                    <label>Account Number </label>
                    <input type="text" id='account_number' required @if(isset($data['userdata']['user_name'])) value="{{str_pad($data['userdata']['id'], 5, '0', STR_PAD_LEFT)}}" @endif readonly="readonly" class="form-control">
                </div>
                <div class="col-md-4 form-group">
                    <label>Creation Date </label>
                    <input type="text" id='created_at' required @if(isset($data['schooldata']['created_at'])) value="{{date('d-m-Y',strtotime($data['schooldata']['created_at']))}}" @endif readonly="readonly" class="form-control">
                </div>
                <div class="col-md-4 form-group">
                    <label>School Name </label>
                    <input type="text" id='school_name' @if(isset($data['schooldata']['SchoolName'])) value="{{$data['schooldata']['SchoolName']}}" @endif required name='school_name' readonly="readonly" class="form-control">
                </div>
                <div class="col-md-4 form-group">
                    <label>User Name </label>
                    <input type="text" id='contact_person' @if(isset($data['userdata']['user_name'])) value="{{$data['userdata']['user_name']}}" @endif required name='contact_person' readonly="readonly" class="form-control">
                </div>
                <div class="col-md-4 form-group">
                    <label>Mobile </label>
                    <input type="text" @if(isset($data['userdata']['mobile'])) value="{{$data['userdata']['mobile']}}" @endif  readonly="readonly" id='mobile' required name='mobile' class="form-control">
                </div>
                <div class="col-md-4 form-group">
                    <label>Email </label>
                    <input type="text" id='email' @if(isset($data['userdata']['email'])) value="{{$data['userdata']['email']}}" @endif  required name='email' class="form-control" readonly="readonly">
                </div>
                <div class="col-md-4 form-group">
                    <label>First Name </label>
                    <input type="text" id='first_name' @if(isset($data['userdata']['first_name'])) value="{{$data['userdata']['first_name']}}" @endif  required name='first_name' class="form-control" readonly="readonly">
                </div>
                <div class="col-md-4 form-group">
                    <label>Last Name </label>
                    <input type="text" id='last_name' @if(isset($data['userdata']['last_name'])) value="{{$data['userdata']['last_name']}}" @endif  required name='last_name' class="form-control" readonly="readonly">
                </div>
                <div class="col-md-12 form-group mb-0">
                    <center>
                    <a href="{{route('implementation_2')}}?moduleId=1"><input type="submit" name="submit" value="Continue" class="btn btn-primary mr-2"></a>
                    <a href="{{route('skip_implementation')}}"><input type="submit" name="submit" value="Skip" class="btn btn-warning"></a>
                    </center>
                </div>
            </div>
        </div>
    </div>
    <!-- /.container-fluid -->
<!-- ============================================================== -->
<!-- End Page Content -->
<!-- ============================================================== -->
</div>
<!-- @include('includes.footerJs') -->
<script src="../../../plugins/bower_components/horizontal-timeline/js/horizontal-timeline.js"></script>
<script type="text/javascript">
    document.body.className = document.body.className.replace("fix-header", "fix-header show-sidebar hide-sidebar");
    document.getElementById('main-header').style.display = 'none';
</script>

@include('includes.footer')
