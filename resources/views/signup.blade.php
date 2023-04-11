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
        .parent{
            height: 70px;
        }
        .parent>.row{
            display: flex;
            align-items: center;
            height: 100%;
        }
        .col img{
            height:100px;
            width: 100%;
            cursor: pointer;
            transition: transform 1s;
            object-fit: cover;
        }
        .col label{
            overflow: hidden;
            position: relative;
        }
        .imgbgchk:checked + label>.tick_container{
            opacity: 1;
        }
/*         aNIMATION */
        .imgbgchk:checked + label>img{
            transform: scale(1.25);
            opacity: 0.3;
        }
        .tick_container {
            transition: .5s ease;
            opacity: 0;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            -ms-transform: translate(-50%, -50%);
            cursor: pointer;
            text-align: center;
        }
        .tick {
            background-color: #4CAF50;
            color: white;
            font-size: 16px;
            padding: 6px 12px;
            height: 40px;
            width: 40px;
            border-radius: 100%;
        }

        div.click-to-top span {
            display: block;
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: #333;
            color: #fff;
            width: 100px;
            margin-bottom: -10%;
            margin-left: 25%;
        }

        /* div.click-to-top:hover span {
           display: block;
         }*/
</style>
<body>
<!-- Preloader -->
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

                                <form class="form-horizontal new-lg-form" id="signupform" method="POST">
                                    @csrf
              <h3 class="box-title m-b-0">New Sign Up</h3>
              <hr style="border-top: 8px solid rgba(92 74 199);">

              @if(!empty($data['message']))
              <div class="alert alert-danger" role="alert">
                  {{ $data['message'] }}
              </div>
              @endif
              @php
              $admin_img = "http://".$_SERVER['HTTP_HOST']."/admin_dep/images/admin.png";
              $teacher_img = "http://".$_SERVER['HTTP_HOST']."/admin_dep/images/teacher.png";
              $student_img = "http://".$_SERVER['HTTP_HOST']."/admin_dep/images/student.png";

              @endphp

              <div class="form-group  m-t-10">
                <div class="col-xs-12">
                  <label>User Type<span style="color: red;font-size: large;">*</span></label>
                  <div class="parent">
                    <div class="row">
                      <div class='col-md-4 text-center click-to-top'>
                        <input type="radio" name="user_type" id="admin" value="Admin" class="imgbgchk" onclick="show_hide_block(this.value);" required><!--class="d-none imgbgchk"-->
                          <label for="admin">
                            <img src="{{$admin_img}}" alt="Admin">
                            <span>Admin</span>
                            <div class="tick_container">
                              <div class="tick"><i class="fa fa-check"></i></div>
                            </div>
                          </label>
                      </div>
                      <div class='col-md-4 text-center click-to-top'>
                         <input type="radio" name="user_type" id="lmsteacher" value="LMS Teacher" class="imgbgchk" onclick="show_hide_block(this.value);" required>
                          <label for="lmsteacher">
                            <img src="{{$teacher_img}}" alt="LMS Teacher">
                              <span>LMS Teacher</span>
                              <div class="tick_container">
                                  <div class="tick"><i class="fa fa-check"></i></div>
                              </div>
                          </label>
                      </div>
                        <div class='col-md-4 text-center click-to-top'>
                            <input type="radio" name="user_type" id="student" class="imgbgchk" value="Student"
                                   onclick="show_hide_block(this.value);" required>
                            <label for="student">
                                <img src="{{$student_img}}" alt="Student">
                                <span>Student</span>
                                <div class="tick_container">
                                    <div class="tick"><i class="fa fa-check"></i></div>
                                </div>
                            </label>
                        </div>
                    </div>
                  </div>
                </div>
              </div>
                                    <div class="form-group m-t-10 mt-5">
                                        <div class="col-xs-12">
                                            <label>First Name<span style="color: red;font-size: large;">*</span></label>
                                            <input class="form-control" name="first_name" type="text" required
                                                   placeholder="Enter first name">
                                        </div>
                                    </div>
                                    <div class="form-group  m-t-10">
                                        <div class="col-xs-12">
                                            <label>Last Name<span style="color: red;font-size: large;">*</span></label>
                                            <input class="form-control" name="last_name" type="text" required
                                                   placeholder="Enter last name">
                </div>
              </div>
              <div class="form-group  m-t-10">
                <div class="col-xs-12">
                  <label>Gender<span style="color: red;font-size: large;">*</span></label>
                  <div class="radio-list">
                      <label class="radio-inline p-0">
                          <div class="radio radio-success">
                              <input type="radio" name="gender" id="male" value="M" required>
                              <label for="male">Male</label>
                          </div>
                      </label>
                      <label class="radio-inline">
                          <div class="radio radio-success">
                              <input type="radio" name="gender" id="female" value="F" required>
                              <label for="female">Female</label>
                          </div>
                      </label>
                      <label class="radio-inline">
                          <div class="radio radio-success">
                              <input type="radio" name="gender" id="other" value="O" required>
                              <label for="other">Other</label>
                          </div>
                      </label>
                  </div>
                </div>
              </div>
              <div class="form-group  m-t-10">
                <div class="col-xs-12">
                  <label>Birth Date<span style="color: red;font-size: large;">*</span></label>
                   <input type="text" id='birthdate' required name="birthdate" class="form-control mydatepicker" autocomplete="off" placeholder="Enter Birthdate">
                </div>
              </div>
              <div class="form-group  m-t-10">
                <div class="col-xs-12">
                  <label>Email Address<span style="color: red;font-size: large;">*</span></label>
                  <input class="form-control" name="email" type="text" required placeholder="Enter email address">
                </div>
              </div>
              <div class="form-group  m-t-10">
                <div class="col-xs-12">
                  <label>Mobile No.<span style="color: red;font-size: large;">*</span></label>
                  <input class="form-control" name="mobile" type="text" required placeholder="Enter mobile no." minlength="10" maxlength="10"><!--onchange="return validate_mobile(this);"-->
                </div>
              </div>
              <div class="form-group  m-t-10" id="institute_name_div">
                <div class="col-xs-12">
                  <label>Institute Name<span style="color: red;font-size: large;">*</span></label>
                    <input class="form-control" name="institute_name" id="institute_name" type="text"
                           placeholder="Enter institute name">
                </div>
              </div>
                                    <div class="form-group  m-t-10" id="standard_div">
                                        <div class="col-xs-12">
                                            <label>Standard<span style="color: red;font-size: large;">*</span></label>
                                            <select class="form-control" name="standard" id="standard">
                                                <option value="">Select Standard</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="form-group text-center m-t-20">
                                        <div class="col-xs-12">
                                            <input class="form-control" name="type" value="web" type="hidden">
                                            <button class="btn btn-primary btn-lg btn-block" type="submit">Signup
                                            </button>
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
