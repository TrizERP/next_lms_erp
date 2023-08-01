<!DOCTYPE html>
<html lang="en">

<!-- Mirrored from www.ampleadmin.wrappixel.com/ampleadmin-html/ampleadmin-minimal/login.html by HTTrack Website Copier/3.x [XR&CO'2014], Thu, 30 Aug 2018 10:12:45 GMT -->
@php
$loginpage_link = session()->get('loginpage_link');
$loginpage_logo = session()->get('loginpage_logo');
$loginpage_title = session()->get('loginpage_title');
$loginpage_description = session()->get('loginpage_description');
$loginpage_favicon = session()->get('loginpage_favicon');
$loginpage_backgrond = session()->get('loginpage_backgrond');
@endphp
<!-- old -->
<!-- new  -->

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
    <link href="{{ asset("/admin_dep/css/bootstrap-datepicker.min.css") }}" rel="stylesheet">

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
                    @if(!empty($loginpage_logo))
                    <div class="logo">
                        <a href="#"><img src="{{$loginpage_logo}}"></a>
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
                        @if(!empty($loginpage_backgrond))
                        <img src="{{$loginpage_backgrond}}" style="width:100%">

                        <!-- <div class="lg-info-panel h-100 d-flex align-items-center p-3" style="background:url({{$loginpage_backgrond}}) center;"> -->
                        @else
                        <img src="{{ asset('/Images/login-page-image.svg')}}" style="width:100%">

                        <!-- <div class="lg-info-panel h-100 d-flex align-items-center p-3" style="background:url(https://p0.pikist.com/photos/545/980/students-women-female-woman-happy-girl-young-college-education.jpg) center;"> -->
                        @endif
                        <div class="img-heading">
                            @if(!empty($loginpage_title))
                            {!!$loginpage_title!!}
                            @else
                            <h2 class="text-light">Own Institute of Maximize Learning</h2>
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
                        <h1>Hello! Welcome.</h1>
                    </div>
                    <div class="purple-heading">
                        <h2>Sign up</h2>
                    </div>
                    @if(!empty($data['message']))
                        <div class="success-text d-flex align-items-center">
                            <img src="{{ asset('/Images/green-check-icon.svg')}} ">
                            <span>{{ $data['message'] }}</span>
                        </div>
                        @endif

                        
                        @if(!empty($data['failed']))
                        <div class="danger-text d-flex align-items-center">
                            <img src="{{ asset('/Images/green-check-icon.svg')}} ">
                            <span>{{ $data['failed'] }}</span>
                        </div>
                        @endif
                    @if(!isset($data['mobile']))
                    <form class="" action="{{ route('NewLMS_temp_signup') }}" id="signupform" method="POST">
                        @elseif(!isset($data['confirm']))
                        <form class="form-horizontal new-lg-form" id="loginform" method="POST" action="@if(isset($data['user_type']) && $data['user_type'] == 'Student') {{ route('NewLMS_signup_student') }} @else {{ route('NewLMS_signup') }} @endif
                                ">
                            @else
                            <form class="form-horizontal new-lg-form" id="loginform" method="POST"
                                action="{{route('preload-institute')}}">
                                @endif
                                @csrf
                                <div class="form-group">
                                    <label>Select User Type <span class="red">*</span></label>

                                    <div class="selct-user-box d-flex align-items-center">
                                        <div class="sign-up-radio-design">

                                            <input type="radio" class="form-radio-input" name="user_type"
                                                id="exampleRadios1" value="Admin" onclick="show_hide_block(this.value);"
                                                required checked>
                                            <!--class="d-none imgbgchk"-->
                                            <label class="form-radio-label" for="exampleRadios1">
                                                <img src="{{ asset('/Images/admin-icon.png')}} " alt="Admin">
                                                <span class="d-block text-center">Admin</span>
                                            </label>
                                        </div>
                                        <!--   <div class="sign-up-radio-design">
                                         <input type="radio" name="user_type" id="exampleRadios2" value="LMS Teacher" class="form-radio-input" onclick="show_hide_block(this.value);" required @if(isset($data['user_type']) && $data['user_type'] == 'LMS Teacher') checked @endif>

                                      <label class="form-radio-label" for="exampleRadios2">
                                        <img src="{{ asset('/Images/lms-teacher-icon.png')}}" alt="LMS Teacher">
                                        <span class="d-block text-center">LMS Teacher</span>
                                      </label>
                                </div>
                                <div class="sign-up-radio-design">
                                        <input type="radio" name="user_type" id="exampleRadios3" class="form-radio-input" value="Student" onclick="show_hide_block(this.value);" required @if(isset($data['user_type']) && $data['user_type'] == 'Student')
                                                checked @endif>

                                      <label class="form-radio-label" for="exampleRadios3">
                                        <img src="{{ asset('/Images/student-icon.png')}}" alt="Student">
                                        <span class="d-block text-center">Student</span>
                                      </label>
                                </div> -->
                                    </div>
                                </div>
                                @if(isset($data['mobile']))
                                <input type="hidden" name="institute_name_confirm"
                                    value="@if(isset($data['institute_name'])){{$data['institute_name']}} @endif">
                                @endif
                                <div class="form-row align-items-center">
                                    <div class="col">
                                        <div class="form-group">
                                            <label for="text">First Name <span class="red">*</span></label>
                                            <input type="text" class="form-control" name="first_name"
                                                placeholder="First Name"
                                                value="@if(isset($data['first_name'])){{$data['first_name']}} @endif"
                                                required>
                                        </div>
                                    </div>
                                    <div class="col">
                                        <div class="form-group">
                                            <label for="text">Last Name <span class="red">*</span></label>
                                            <input type="text" class="form-control" name="last_name"
                                                placeholder="Last Name"
                                                value="@if(isset($data['last_name'])){{$data['last_name']}} @endif"
                                                required>
                                        </div>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label for="email">Email Address <span class="red">*</span></label>
                                    <input type="email" class="form-control" name="email" placeholder="Email Address"
                                        value="@if(isset($data['email'])){{$data['email']}} @endif" required>
                                </div>
                                
                                <div class="form-group  m-t-10" id="institute_name_div">
                                    <div class="col-xs-12">
                                        <label>Institute Name<span style="color: red;font-size: large;">*</span></label>
                                        <input class="form-control" name="institute_name" id="institute_name"
                                            type="text" placeholder="Enter institute name"
                                            value="@if(isset($data['institute_name'])){{$data['institute_name']}} @endif">
                                    </div>
                                </div>
                                <div class="form-group  m-t-10">
                                    <div class="col-xs-12">
                                        <label>Select Institute Type<span style="color: red;font-size: large;">*</span></label>
                                        <select class="form-control" name="institute_type" id="institute_type" required>
                                            <option value="">Select Institute</option>
                                            <option value="school" @if(isset($data['institute_type']) && $data['institute_type']="school") selected @endif>School</option>
                                            <option value="college" @if(isset($data['institute_type']) && $data['institute_type']="college") selected @endif>College</option>                                            
                                        </select>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="mobile-number">Mobile Number <span class="red">*</span></label>
                                    <div class="mobile-number-field">
                                        <select class="form-control">
                                            <option selected="">+91</option>
                                            <!--  <option>Default select</option>
                                    <option>Default select</option>
                                    <option>Default select</option> -->
                                        </select>
                                        <input type="tel" class="form-control" name="mobile" placeholder="Mobile Number"
                                            value="@if(isset($data['mobile'])){{$data['mobile']}} @endif" required
                                            autocomplete="off">
                                    </div>
                                </div>
                                @if(isset($data['mobile']) && !isset($data['confirm']))

                                <div class="form-group otp-feild" id="otp_feild">
                                    <label class="d-flex align-items-center justify-content-between">
                                        <div>Enter OTP <span class="red">*</span></div>
                                        <!-- <div class="otp-time">0:30 Sec</div> -->
                                        <input type="hidden" name="otp" class="form-control text-center"
                                            placeholder="0" id="otp" required>

                                    </label>
                                    <div class="otp-number d-flex align-items-center justify-content-between">
                                        <input type="number" class="form-control text-center otp-input" placeholder="0"
                                            id="otpval" maxlength="1" oninput="handleDigitInput(event, 1)" required>
                                        <input type="number" class="form-control text-center otp-input" placeholder="0"
                                            id="otpval" maxlength="1" oninput="handleDigitInput(event, 2)" required>
                                        <input type="number" class="form-control text-center otp-input" placeholder="0"
                                            id="otpval" maxlength="1" oninput="handleDigitInput(event, 3)" required>
                                        <input type="number" class="form-control text-center otp-input" placeholder="0"
                                            id="otpval" maxlength="1" oninput="handleDigitInput(event, 4)" required>
                                        <input type="number" class="form-control text-center otp-input" placeholder="0"
                                            id="otpval" maxlength="1" oninput="handleDigitInput(event, 5)" required>
                                        <input type="number" class="form-control text-center otp-input" placeholder="0"
                                            id="otpval" maxlength="1" oninput="handleDigitInput(event, 6)" required>
                                    </div>
                                    <div>
                                        <!-- 664810 -->
                                    </div id="otp-result">
                                    <div class="otp-bottom d-flex align-items-center justify-content-between ">
                                        <span class="success-text d-flex align-items-center">We have sent you OTP to
                                            your email address</span>
                                        <a href="{{route('Resend_otp')}}">Resend OTP</a>
                                    </div>
                                </div>
                                @endif

                                <input class="form-control" name="type" value="web" type="hidden">
                                @if(isset($data['mobile']) && !isset($data['confirm']))
                                <!-- <input type="hidden" name="signup" value="{{session()->has('data')}}"> -->
                                <button type="submit" class="purple-btn w-100 ">Sign up</button>
                                @elseif(!isset($data['confirm']))
                                <button type="submit" class="purple-btn w-100 ">Send OTP</button>
                                @endif

                                <div class="form-bottom-text mb-2">Already have an account? <a
                                        href="{{route('login')}}">Log in</a></div>


                                <div class="sign-up-success">
                                  
                                    @if(isset($data['confirm']))
                                    <div class="preference-details">
                                        <p>Choose your preference</p>
                                        <div class="preference-btn">
                                            <input type="hidden" name="preload" value="preload">
                                            <button class="purple-btn p-19" name="preload_btn">Use Preload Data</button>
                                            <input type="hidden" name="preload" value="use_institute">
                                            <button class="purple-btn p-19" name="institute_btn">Use Your Institute
                                                Data</button>
                                        </div>
                                    </div>
                                    @endif
                                </div>

                            </form>
                </div>
            </div>
        </div>
    </section>


    <!-- Option 1: jQuery and Bootstrap Bundle (includes Popper) -->
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.5.1/dist/jquery.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="{{ asset("plugins/bower_components/bootstrap-datepicker/bootstrap-datepicker.min.js") }}"></script>

    <script>
    $('#standard_div').hide();
    // $('#institute_name_div').hide();

    $(document).ready(function() {

        // $('input[id="otpval"]').on('input', function() {
        //         var otpString = '';
        //         $('input[id="otpval"]').each(function() {
        //             otpString += $(this).val();
        //         });
        //         $('#otp').val(otpString);
        //     });
        $('input[id="otpval"]').on('input', function() {
            var otpString = '';
            $('input[id="otpval"]').each(function() {
                otpString += $(this).val();
            });
            $('#otp').val(otpString);
        });
        $('#birthdate').attr('required', true);

        jQuery('.mydatepicker, #datepicker').datepicker({
            autoclose: true,
            startDate: '1970-01-01',
            endDate: '+0d',
            format: 'yyyy-mm-dd',
            orientation: 'bottom'
        });


        //   $('#standard_div').hide();
        // $('#institute_name_div').hide();
    });
    // send otp
    // 

function handleDigitInput(event, nextInputIndex) {
  const input = event.target;
  const value = input.value;

  if (!(/^\d$/.test(value))) {
    input.value = ''; // Clear the input field
    return; // Exit the function if the entered value is not a digit
  }

  // If the entered value is a digit and the input field is full
  if (value.length === parseInt(input.getAttribute('maxlength'))) {
    const inputs = document.querySelectorAll('.otp-input');
    const nextInput = inputs[nextInputIndex];

    if (nextInput) {
      nextInput.focus(); // Move focus to the next input field
    }
  }
}


    function show_hide_block(val) {
        // alert("admin");
        if (val == 'Admin' || val == 'LMS Teacher') {
            $("#signupform").attr('action', "{{ route('NewLMS_temp_signup') }}");
            $('#institute_name_div').show();
            $('#standard_div').hide();
        } else {
            $("#signupform").attr('action', "{{ route('NewLMS_temp_signup_student') }}");

            $('#institute_name_div').hide();
            $('#standard_div').show();

            var path = "{{ route('get_trizStandard') }}";
            $('#standard').find('option').remove().end().append('<option value="">Select Standard</option>').val('');
            $.ajax({
                url: path,
                success: function(result) {
                    for (var i = 0; i < result.length; i++) {
                        $("#standard").append($("<option></option>").val(result[i]['id']).html(result[i][
                            'name'
                        ]));
                    }
                }
            });
        }
    }

    function validate_mobile(mobile) {
        var phoneno = /^[7-9][0-9]{0,8}$/;
        if (mobile.value.match(phoneno)) {
            return true;
        } else {
            alert("Wrong mobile number has been entered.");
            return false;
        }
    }
    </script>
</body>

</html>