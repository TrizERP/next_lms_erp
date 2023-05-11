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
<!-- old -->
<!-- new  -->
<head>
    <!-- Required meta tags -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
@if(!empty($loginpage_favicon))
  <link rel="icon" type="image/png" sizes="16x16" href={{$loginpage_favicon}} >
    @else
  <link rel="icon" type="image/png" sizes="16x16" href="../admin_dep/images/icon.png">
@endif
<title>TRIZ-ERP || LOGIN</title>
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">


    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" type="text/css" href="{{ asset('/css/style2.css')}}">
    <title>Sign up | TRIZ INNOVATION PVT LTD</title>


  </head>


<!-- new body -->
<body class="login-page">
    <section class="container-fluid login-section p-0">
        <div class="row m-0">
            <div class="col-lg-6 col-xl-5 p-0">
                <div class="image-column-content">
                    @if(!empty($loginpage_backgrond))
                    <div class="logo">
                        <a href="#"><img src="{{$loginpage_backgrond}}"></a>
                    </div>
                    @else
                    <div class="logo">
                        <a href="#"><img src="{{ asset('/Images/logo.png')}} "></a>
                    </div>
                    @endif
                    <!-- <div class="logo">
                        <a href="#"><img src="{{ asset('/Images/logo.png')}} "></a>
                    </div> -->
                    <div class="img-content">
                        <img src="{{ asset('/Images/login-page-image.svg')}} ">
                        <div class="img-heading">
                            @if(!empty($loginpage_title))
                                {!!$loginpage_title!!}
                            @else
                                <h2 class="text-light">Triz Institute of Maximize Learning</h2>
                            @endif
                            @if(!empty($loginpage_description))
                                {!!$loginpage_description!!}
                            @else
                                <p class="text-light">Integrated Solution for Digital Needs!!!</p>
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
                        <h2>Sign up</h2>
                    </div>

                    <form class="">
                         @csrf
                        <div class="form-group">
                            <label>Select User Type <span class="red">*</span></label>
                            <div class="selct-user-box d-flex align-items-center">
                                <div class="sign-up-radio-design">
                                      <input class="form-radio-input" type="radio" name="exampleRadios" id="exampleRadios1" value="option1">
                                      <label class="form-radio-label" for="exampleRadios1">
                                        <img src="{{ asset('/Images/admin-icon.png')}} ">
                                        <span class="d-block text-center">Admin</span>
                                      </label>
                                </div>
                                <div class="sign-up-radio-design">
                                      <input class="form-radio-input" type="radio" name="exampleRadios" id="exampleRadios2" value="option2">
                                      <label class="form-radio-label" for="exampleRadios2">
                                        <img src="{{ asset('/Images/lms-teacher-icon.png')}}">
                                        <span class="d-block text-center">LMS Teacher</span>
                                      </label>
                                </div>
                                <div class="sign-up-radio-design">
                                      <input class="form-radio-input" type="radio" name="exampleRadios" id="exampleRadios3" value="option3">
                                      <label class="form-radio-label" for="exampleRadios3">
                                        <img src="{{ asset('/Images/student-icon.png')}}">
                                        <span class="d-block text-center">Student</span>
                                      </label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-row align-items-center">
                            <div class="col">
                                <div class="form-group">
                                    <label for="text">First Name <span class="red">*</span></label>
                                    <input type="text" class="form-control" placeholder="First Name">
                                </div>
                            </div>
                            <div class="col">
                                <div class="form-group">
                                    <label for="text">Last Name <span class="red">*</span></label>
                                    <input type="text" class="form-control" placeholder="Last Name">
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="email">Email Address <span class="red">*</span></label>
                            <input type="email" class="form-control" placeholder="Email Address">
                        </div>
                        <div class="form-group">
                            <label for="mobile-number">Mobile Number <span class="red">*</span></label>
                            <div class="mobile-number-field">
                                <select class="form-control">
                                    <option selected="">+91</option>
                                    <option>Default select</option>
                                    <option>Default select</option>
                                    <option>Default select</option>
                                </select>
                                <input type="tel" class="form-control" placeholder="Mobile Number">
                            </div>
                        </div>
                        <div class="form-group otp-feild">
                            <label class="d-flex align-items-center justify-content-between">
                                <div>Enter OTP <span class="red">*</span></div>
                                <div class="otp-time">0:30 Sec</div>
                            </label>
                            <div class="otp-number d-flex align-items-center justify-content-between">
                                <input type="number" class="form-control text-center" placeholder="0">
                                <input type="number" class="form-control text-center" placeholder="0">
                                <input type="number" class="form-control text-center" placeholder="0">
                                <input type="number" class="form-control text-center" placeholder="0">
                                <input type="number" class="form-control text-center" placeholder="0">
                                <input type="number" class="form-control text-center" placeholder="0">
                            </div>
                            <div class="otp-bottom d-flex align-items-center justify-content-between">
                                <span>We have sent you OTP to your email address</span>
                                <a href="#">Resend OTP</a>
                            </div>
                        </div>
                        
                        <button type="submit" class="purple-btn w-100 ">Sign up</button>
                        
                        <div class="form-bottom-text mb-2">Already have an account? <a href="#">Log in</a></div>


                        <div class="sign-up-success">
                            <div class="success-text d-flex align-items-center">
                                <img src="{{ asset('/Images/green-check-icon.svg')}} ">
                                <span>Congratulations, You have signed up successfully.</span>
                            </div>
                            <div class="preference-details">
                                <p>Choose your preference</p>
                                <div class="preference-btn">
                                    <button class="purple-btn p-19">Pre Loaded Data</button>
                                    <button class="purple-btn p-19">Use Your Institute Data</button>
                                </div>
                            </div>
                        </div>

                        
                    </form>
                </div>
            </div>
        </div>
    </section>


    <!-- Option 1: jQuery and Bootstrap Bundle (includes Popper) -->
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.5.1/dist/jquery.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
  $(document).ready(function(){

    $('#birthdate').attr('required',true);

    jQuery('.mydatepicker, #datepicker').datepicker({
      autoclose: true,
      startDate: '1970-01-01',
      endDate: '+0d',
      format: 'yyyy-mm-dd',
      orientation: 'bottom'
    });

      $('#standard_div').hide();
    $('#institute_name_div').hide();
  });

  function show_hide_block(val)
  {
    if(val == 'Admin' || val == 'LMS Teacher')
    {
      $("#signupform").attr('action', "{{ route('NewLMS_temp_signup') }}");
      $('#institute_name_div').show();
      $('#standard_div').hide();
    }
    else
    {
      $("#signupform").attr('action', "{{ route('NewLMS_temp_signup_student') }}");

      $('#institute_name_div').hide();
      $('#standard_div').show();

        var path = "{{ route('get_trizStandard') }}";
        $('#standard').find('option').remove().end().append('<option value="">Select Standard</option>').val('');
        $.ajax({
            url: path, success: function (result) {
                for (var i = 0; i < result.length; i++) {
                    $("#standard").append($("<option></option>").val(result[i]['id']).html(result[i]['name']));
                }
            }
        });
    }
  }

  function validate_mobile(mobile) {
      var phoneno = /^[7-9][0-9]{0,8}$/;
      if (mobile.value.match(phoneno)) {
          return true;
    }
    else {
      alert("Wrong mobile number has been entered.");
      return false;
    }
  }

</script>
</body>
</html>
