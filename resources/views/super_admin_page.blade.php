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
    <title>Super Admin | TRIZ INNOVATION PVT LTD</title>


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
                        <h2>Save Super Admin Details</h2>
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

                    <form class="" action="{{ route('superAdmin.store') }}" id="signupform" method="POST">
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
                                        <span class="d-block text-center">Super Admin</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="email">User Name <span class="red">*</span></label>
                            <input type="text" class="form-control" name="user_name" placeholder="User Name"
                                value="" required>
                        </div>
                        <div class="form-group">
                            <label for="email">First Name <span class="red">*</span></label>
                            <input type="text" class="form-control" name="first_name" placeholder="First Name"
                                value="" required>
                        </div>
                        <div class="form-group">
                            <label for="email">Middle Name <span class="red">*</span></label>
                            <input type="text" class="form-control" name="middle_name" placeholder="Middle Name"
                                value="" required>
                        </div>
                        <div class="form-group">
                            <label for="email">Last Name <span class="red">*</span></label>
                            <input type="text" class="form-control" name="last_name" placeholder="Last Name"
                                value="" required>
                        </div>
                        <div class="form-group">
                            <label for="email">Email Address <span class="red">*</span></label>
                            <input type="email" class="form-control" name="email" placeholder="Email Address" required>
                        </div> 
                        <div class="form-group">
                            <label for="password">Password <span class="red">*</span></label>
                            <input class="form-control" name="password" type="password" required=""
                                placeholder="Password">
                        </div>
                        <div class="form-group">
                            <label for="text">Mobile <span class="red">*</span></label>
                            <input type="text" class="form-control" name="mobile" placeholder="Mobile" required>
                        </div>
                        @php  
                            $get_clients = $data['get_clients'];
                        @endphp
                        <div class="form-group  m-t-10">
                            <div class="col-xs-12">
                                <label>Select Client <span style="color: red;font-size: large;">*</span></label>
                                <select class="form-control" name="client_id" id="client_id" required>
                                    <option value="">Select Client</option>
                                        @if($get_clients !== null && count($get_clients) > 0)
                                            @foreach($get_clients as $key => $value)
                                                <option value="{{$value->client_id}}" @if(isset($data['client_id']))
                                                    @if($data['client_id'] == $key)
                                                    selected='selected'
                                                    @endif
                                                @endif>{{$value->client_name}}</option>
                                            @endforeach
                                        @endif                                            
                                </select>
                            </div>
                        </div>
                        <div class="form-group  m-t-10">
                            <div class="col-xs-12">
                                <label>Select Institutes <span style="color: red;font-size: large;">*</span></label>
                                <select class="form-control" name="institute_id[]" id="institute_id" multiple required>
                                    <option value="">Select Institute</option>
                                                                                  
                                </select>
                            </div>
                        </div>
                        <button type="submit" class="purple-btn w-100 ">Save</button>
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
        $(document).ready(function () {
            $(document).on("change", "#client_id", function(e) {
                $('#institute_id').empty();
                var clientId = $(this).val();
                
                $.ajax({
                    type: "post",
                    url: "{{ route('get.institute') }}",
                    data: { client_id: clientId },
                    success: function(data) {
                        var options = '';
                        $.each(data.getInstitutes, function(index, getInstitute) {
                            
                            options += '<option value="' + getInstitute.Id + '" >' + getInstitute.SchoolName + '</option>';
                        });
                        $('#institute_id').append(options);
                    },
                    error: function(xhr) {
                        console.error(xhr.responseText);
                    }
                });
            });
        });
        </script>
</body>

</html>