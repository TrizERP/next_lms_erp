<!DOCTYPE html>  
<html lang="en">

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
</head>
<body>
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
            </div>
          </div>
      </div>
    </div>
    <div class="col-md-6">
      <div class="new-login-box row align-items-center justify-content-center py-4">
        <div class="col-md-8">
          <div class="white-box123"> 
            <form class="form-horizontal new-lg-form" action="{{ route('forget.password.post') }}" method="POST">
            @csrf           
              <h3 class="box-title m-b-0">Reset Password</h3>
              @if (Session::has('message'))
                <div class="alert alert-success" role="alert">
                  {{ Session::get('message') }}
                </div>
              @endif
              <div class="form-group m-t-10">
                <div class="col-xs-12">
                  <label>Email Address</label>
                  <input type="text" id="email_address" class="form-control" name="email" required autofocus>
                    @if ($errors->has('email'))
                        <span class="text-danger">{{ $errors->first('email') }}</span>
                    @endif
                </div>
                <div class="form-group text-center m-t-20">
                  <div class="col-xs-12">
                     <button type="submit" class="btn btn-primary btn-lg btn-block">
                        Send Password Reset Link
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
<script src="{{ asset("plugins/bower_components/jquery/dist/jquery.min.js") }}"></script>
<script src="{{ asset("admin_dep/bootstrap/dist/js/bootstrap.min.js") }}"></script>
<script src="{{ asset("plugins/bower_components/sidebar-nav/dist/sidebar-nav.min.js") }}"></script>
<script src="{{ asset("admin_dep/js/jquery.slimscroll.js") }}"></script>
<script src="{{ asset("admin_dep/js/waves.js") }}"></script>
<script src="{{ asset("admin_dep/js/custom.min.js") }}"></script>
<script src="{{ asset("plugins/bower_components/styleswitcher/jQuery.style.switcher.js") }}"></script>
</body>
</html>
