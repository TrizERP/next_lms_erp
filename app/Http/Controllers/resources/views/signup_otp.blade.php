<!DOCTYPE html>
<html lang="en">

<!-- Mirrored from www.ampleadmin.wrappixel.com/ampleadmin-html/ampleadmin-minimal/login.html by HTTrack Website Copier/3.x [XR&CO'2014], Thu, 30 Aug 2018 10:12:45 GMT -->
<?php

$loginpage_link = session()->get('loginpage_link');
$loginpage_logo = session()->get('loginpage_logo');
$loginpage_title = session()->get('loginpage_title');
$loginpage_description = session()->get('loginpage_description');
$loginpage_favicon = session()->get('loginpage_favicon');
$loginpage_backgrond = session()->get('loginpage_backgrond');

?>

<head>
<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="description" content="">
<meta name="author" content="">
@if(!empty($loginpage_favicon))
  <link rel="icon" type="image/png" sizes="16x16" href={{$loginpage_favicon}} >
    @else
  <link rel="icon" type="image/png" sizes="16x16" href="../admin_dep/images/icon.png">
@endif
<title>TRIZ-ERP || LOGIN</title>
<!-- Bootstrap Core CSS -->
<!-- <link href="{{ asset("admin_dep/bootstrap/dist/css/bootstrap.min.css") }}" rel="stylesheet"> -->
<!-- animation CSS -->
<!-- <link href="{{ asset("admin_dep/css/animate.css") }}" rel="stylesheet"> -->
<!-- Custom CSS -->
<!-- <link href="{{ asset("admin_dep/css/style.css") }}" rel="stylesheet"> -->
<!-- <link href="{{ asset("admin_dep/css/triz-style.css") }}" rel="stylesheet"> -->
<!-- color CSS -->
<!-- <link href="{{ asset("admin_dep/css/colors/default.css") }}" id="theme"  rel="stylesheet"> -->
<link href="{{ asset("/admin_dep/css/colors/default.css") }}" id="theme" rel="stylesheet">
<link href="{{ asset("/admin_dep/css/fontawesome.css") }}" rel="stylesheet">
<link href="{{ asset("/admin_dep/css/bootstrap.css") }}" rel="stylesheet">
<link href="{{ asset("/admin_dep/css/bootstrap-select.css") }}" rel="stylesheet">
<link href="{{ asset("/admin_dep/css/docs.css") }}" rel="stylesheet">
<link href="{{ asset("/admin_dep/css/css3.css") }}" rel="stylesheet">
<link href="{{ asset("/admin_dep/css/materialdesignicons.min.css") }}" rel="stylesheet">
<link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
<link href="{{ asset("/admin_dep/css/elements.css") }}" rel="stylesheet">
<link href="{{ asset("/admin_dep/css/style.css") }}" rel="stylesheet">
<link href="{{ asset("/admin_dep/css/bootstrap-datepicker.min.css") }}" rel="stylesheet">

</head>
<style type="text/css">
    .division_success {
        width: 80%;
        height: 35px;
        font-size: 1.1em;
        color: green;
        font-weight: bold;
    }
</style>
<body>
<!-- Preloader -->
<!--div class="preloader">
  <div class="cssload-speeding-wheel"></div>
</div-->
<section id="wrapper" class="new-login-register container-fluid">
  <div class="row">

    <div class="col-md-6">
      @if(!empty($loginpage_backgrond))
          <div class="lg-info-panel h-100 d-flex align-items-center p-3" style="background:url({{$loginpage_backgrond}}) center;">
      @else
        <div class="lg-info-panel h-100 d-flex align-items-center p-3" style="background:url(https://p0.pikist.com/photos/545/980/students-women-female-woman-happy-girl-young-college-education.jpg) center;">
      @endif
          <div class="inner-panel">
            <div class="lg-content">
                @if(!empty($loginpage_logo))
                    {!!$loginpage_logo!!}
                @else
                    <center>
                        <img src="http://dev.triz.co.in/admin_dep/images/triz.png" width="250px">
                    </center>
                @endif

                @if(!empty($loginpage_title))
                    {!!$loginpage_title!!}
                @else
                    <h2 class="text-light">OWN INSTITUTE MAXIMIZE LEARNING</h2>
                @endif

                @if(!empty($loginpage_description))
                    {!!$loginpage_description!!}
                @else
                    <p class="text-light">Integrated solution for institutes need-DIGITAL!!!</p>
            @endif
            <!-- <a href="#" class="btn btn-rounded btn-danger p-l-20 p-r-20"> Buy now</a> -->
            </div>
          </div>
        </div>
          </div>

            <div class="col-md-6">
                <div class="new-login-box row align-items-center justify-content-center py-4">
                    <div class="col-md-8">
                        <div class="white-box123">
                            <form class="form-horizontal new-lg-form" id="loginform" method="POST"
                                  action="
            @if(isset($data['user_type']) && $data['user_type'] == 'Student')
            {{ route('NewLMS_signup_student') }}
            @else
            {{ route('NewLMS_signup') }}
            @endif
            ">

              @if(!empty($data['message']))
              <div class="alert alert-danger" role="alert">
                  {{ $data['message'] }}
              </div>
                                @endif
                                @csrf
              <div class="form-group">
                  <span id="division_error_span"></span>
              </div>

              <h3 class="box-title m-b-0">Sign Up OTP</h3>
              <div class="form-group  m-t-10">
                <div class="col-xs-12">
                  <label>Enter OTP</label>
                  <input class="form-control" name="otp" type="text" required placeholder="OTP">
                  <input class="form-control" name="mobile" id="mobile" type="hidden" value="@if(isset($data['mobile'])){{$data['mobile']}}@endif">
                    <input class="form-control" name="type" type="hidden" value="web">
                </div>
              </div>
                                <div class="form-group text-center m-t-20">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <button class="btn btn-primary btn-lg btn-block" type="submit">Check OTP
                                            </button>
                                        </div>
                                        <div class="col-md-6">
                                            <button class="btn btn-primary btn-lg btn-block" type="button"
                                                    id="resend_otp">Resend OTP
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
    </div>


</section>
<!-- jQuery -->
<script src="{{ asset("plugins/bower_components/jquery/dist/jquery.min.js") }}"></script>
<!-- Bootstrap Core JavaScript -->
<script src="{{ asset("admin_dep/bootstrap/dist/js/bootstrap.min.js") }}"></script>
<!-- Menu Plugin JavaScript -->
<script src="{{ asset("plugins/bower_components/sidebar-nav/dist/sidebar-nav.min.js") }}"></script>

<!--slimscroll JavaScript -->
<script src="{{ asset("admin_dep/js/jquery.slimscroll.js") }}"></script>
<!--Wave Effects -->
<script src="{{ asset("admin_dep/js/waves.js") }}"></script>
<!-- Custom Theme JavaScript -->
<script src="{{ asset("admin_dep/js/custom.min.js") }}"></script>
<!--Style Switcher -->
<script src="{{ asset("plugins/bower_components/styleswitcher/jQuery.style.switcher.js") }}"></script>
<script src="{{ asset("plugins/bower_components/bootstrap-datepicker/bootstrap-datepicker.min.js") }}"></script>
<script>
    $(document).ready(function () {
        // $.ajax({
        //     url: "../captcha/getcaptcha.php", success: function (result) {
        //         $("#hid_captcha").val(result);
        //     }
        // });

        jQuery('.mydatepicker, #datepicker').datepicker({
            autoclose: true,
            format: 'yyyy-mm-dd',
            orientation: 'bottom'
        });
    });

    $('#resend_otp').click(function () {
        var mobile = $("#mobile").val();

        var path = "{{ route('Resend_otp') }}";
      $.ajax({
          url:path,
          data:'mobile='+mobile,
          success:function(result){
              $("#division_error_span").removeClass().addClass("division_success").text(result);
          }
      });

  });
</script>

</body>

</html>
