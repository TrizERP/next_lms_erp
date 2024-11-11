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
<html lang="en">

<head>
    <!-- Required meta tags -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    @if(!empty($loginpage_favicon))
    <link rel="icon" type="image/png" sizes="16x16" href={{$loginpage_favicon}}>
    @else
    <link rel="icon" type="image/png" sizes="16x16" href="../admin_dep/images/icon.png">
    @endif
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">


    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" type="text/css" href="{{asset('css/style2.css')}}">
    <title>Login | TRIZ</title>


</head>

<body class="login-page">
    <section class="container-fluid login-section p-0">
        <div class="row m-0 log-row">
            <div class="col-lg-6 col-xl-5 p-0 l-logo-wrap">
                @if(!empty($loginpage_logo))
                <div class="image-column-content"
                    style="box-shadow:none !important;background: transparent !important;">
                    @else
                    <div class="image-column-content">
                        @endif
                        <div class="logo">
                            @if(!empty($loginpage_logo))
                            @else
                            <center>
                                <a href="#"><img src="{{ asset('/Images/logo.png')}}"></a>
                            </center>
                            @endif

                        </div>
                        @if(!empty($loginpage_backgrond))
                        <div class="img-content" style="padding:0px !important">
                            @else
                            <div class="img-content">
                                @endif
                                @if(!empty($loginpage_backgrond))
                                {!!$loginpage_logo!!}
                                <!-- <div class="lg-info-panel h-100 d-flex align-items-center p-3" style="background:url({{$loginpage_backgrond}}) center;"> -->
                                @else
                                <img src="{{ asset('/Images/login-page-image.svg')}}" style="width:100%">
                                <!-- <div class="lg-info-panel h-100 d-flex align-items-center p-3" style="background:url(https://p0.pikist.com/photos/545/980/students-women-female-woman-happy-girl-young-college-education.jpg) center;"> -->
                                @endif

                                <div class="img-heading hide-imgheading">
                                    @if(!empty($loginpage_title))
                                    {!!$loginpage_title!!}
                                    @else
                                   <h2 class="text-light">Own Institute of Maximize Learning</h2>
                                    @endif

                                    @if(!empty($loginpage_description))
                                    {!!$loginpage_description!!}
                                    @else
                                    <p class="text-light">Integrated Solution for Digital Needs</p>
                                    @endif

                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6 col-xl-7 d-flex align-items-center">
                        <div class="form-content">
                            <div class="heading">
                                <h1>Hello! Welcome Back.</h1>
                            </div>
                            <div class="purple-heading">
                                <h2>Log in to user</h2>
                            </div>
                            @if(!empty($successMsg))
                            <div class="alert alert-success"> {{ $successMsg }}</div>
                            @endif
                            @if(!empty($data))
                            <div class="alert alert-danger" role="alert">
                                {{ $data['message'] }}
                            </div>
                            @endif
                            <form class="form-horizontal new-lg-form" id="loginform" method="POST" action="/login">
                                @csrf
                                <div class="form-group">
                                    <label for="email">Email Address</label>
                                    <input class="form-control" name="email" type="text" required=""
                                        placeholder="Username">
                                </div>
                                <div class="form-group">
                                    <label for="password">Password</label>
                                    <input class="form-control" name="password" type="password" required=""
                                        placeholder="Password">
                                </div>
                                <div class="form-group">
                                    <div class="row">
                                        <div class="col-6">
                                            <label>Captcha</label>
                                            <input class="form-control" autocomplete="off" name="captchaText" type="text" required=""
                                                   placeholder="Enter Captcha">
                                        </div>
                                        <div class="col-6">
                                            {!! captcha_img() !!}
                                            <input type="hidden" name='hid_captcha' id="hid_captcha">
                                        </div>
                                    </div>
                                </div>
                                <button type="submit" class="purple-btn w-100 mb-30">Sign in</button>
                                <div class="form-row align-items-center formrowcolumn">
                                    <div class="col">
                                        <div class="form-check">
                                            <!-- <input class="form-check-input" type="checkbox" id="gridCheck"> -->
                                            <input id="gridCheck" class="form-check-input" type="checkbox">
                                            <label class="form-check-label" for="gridCheck">
                                                Check me out
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col text-right">
                                        <a class="underline-link" href="{{ route('forget.password.get') }}">Forgot
                                            Password</a>
                                    </div>
                                </div>
                                <div class="form-bottom-text">Don’t have an account? <a
                                        href="{{ route('signup') }}">Sign up</a>
                                </div><br>
                                <div class="footer text-center"><a href="{{route('privacyPolicy')}}" style="color:blue;"> Privacy Policy </a> |  <a href="{{ route('termAndCondition')}}" style="color:blue;"> Term & Condition </a> |  <a href="{{ route('otherPolicy') }}" style="color:blue;"> Other Policy </a>

                                </div>

                            </form>
                        </div>
                    </div>
                </div>
    </section>

    <!-- Option 1: jQuery and Bootstrap Bundle (includes Popper) -->
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.5.1/dist/jquery.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
<script>
$(document).ready(function() {
    $.ajax({
        url: "../captcha/getcaptcha.php",
        success: function(result) {
            $("#hid_captcha").val(result);
        }
    });

});
</script>

</html>