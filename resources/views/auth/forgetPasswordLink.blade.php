<!DOCTYPE html>  
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="description" content="">
  <meta name="author" content="">
  <link rel="icon" type="image/png" sizes="16x16" href="../plugins/images/favicon.png">
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
      <div class="lg-info-panel h-100 d-flex align-items-center p-3" style="background:url(https://p0.pikist.com/photos/545/980/students-women-female-woman-happy-girl-young-college-education.jpg) center;">
          <div class="inner-panel">
            <a href="javascript:void(0)" class="p-20 di"><img src="{{ asset("admin_dep/images/icon.png") }}"></a>
            <div class="lg-content">
                <h2 class="text-light">TRIZ INSTITUTE MAXIMIZE LEARNING</h2>
                <p class="text-light">Integrated solution for institutes need !!!</p>
            </div>
          </div>
      </div>
    </div>
    <div class="col-md-6">
      <div class="new-login-box row align-items-center justify-content-center py-4">
        <div class="col-md-8">
          <div class="white-box123"> 
            <form action="{{ route('reset.password.post') }}" method="POST">
            @csrf           
              <h3 class="box-title m-b-0">Reset Password</h3>             
              <input type="hidden" name="token" value="{{ $token }}">
              <div class="form-group m-t-10">
                <div class="col-xs-12">
                  <label>Email Address</label>
                  <input type="text" id="email_address" class="form-control" name="email" value="{{$email}}" required autofocus readonly="readonly">
                    @if ($errors->has('email'))
                        <span class="text-danger">{{ $errors->first('email') }}</span>
                    @endif
                </div>
                <div class="col-xs-12">
                  <label>Password</label>
                  <input type="password" id="password" class="form-control" name="password" required autofocus>
                  @if ($errors->has('password'))
                      <span class="text-danger">{{ $errors->first('password') }}</span>
                  @endif
                </div>
                <div class="col-xs-12">
                  <label>Confirm Password</label>
                    <input type="password" id="password-confirm" class="form-control" name="password_confirmation" required autofocus>
                    @if ($errors->has('password_confirmation'))
                        <span class="text-danger">{{ $errors->first('password_confirmation') }}</span>
                    @endif
                </div>
                <div class="form-group text-center m-t-20">
                  <div class="col-xs-12">
                     <button type="submit" class="btn btn-primary btn-lg btn-block">
                        Reset Password
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
