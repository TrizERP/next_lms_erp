@include('includes.headcss')
<link rel="stylesheet" href="../../../tooltip/enjoyhint/jquery.enjoyhint.css">
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">

    @if($data['occupied_space_in_MB'] != "")
    <div class="progress" style="height: 10px;background-color: rgba(0,0,0,.12);">
      <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width:{{$data['used_space_in_MB']}}" aria-valuenow="{{$data['used_space_in_MB']}}" aria-valuemin="0" aria-valuemax="{{$data['occupied_space_in_MB']}}"></div>
    </div>

    <div class="card">
        <a href="{{ route('used_storage_graph.index') }}" class="js-greeting card-title">{{$data['used_space_in_MB']}} of {{$data['occupied_space_in_MB']}} MB used</a>
    </div>
    @endif
    
    <div class="px-4 bg-dark mb-3 pb-5 rounded mt-3">
        <div>
            <div class="col-12 text-white pt-4 pb-5">
                <h4 class="page-title text-white"> {{ Session::get('user_profile_name') }} Dashboard</h4>
                <h3 class="text-white pb-5 mb-5">Welcome, <span class="js-greeting">{{ Session::get('name') }}</span></h3>
            </div>
        </div>
    </div>
        <div class="mt-30 d-none">
            <div class="white-box">
                <div class="row">
                    <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                        <h4 class="page-title">Dashboard</h4>
                        <h5 class="welcome-msg mb-5">Welcome {{ Session::get('name') }}</h5>
                    </div>
                    <div class="col-lg-9 col-sm-8 col-md-8 col-xs-12">
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
    <div class="container-fluid pull-up px-3 mb-3">
        <div class="row">
            <div class="col-md-3">
                <div class="card">
                    <div class="text-center card-body">                        
                        <img src="/storage/dashboard_images/s1.png" class="img-fluid w-100"  />                        
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <div class="text-center card-body">                        
                        <img src="/storage/dashboard_images/s2.jpg" class="img-fluid w-100"  />                        
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <div class="text-center card-body">                         
                        <img src="/storage/dashboard_images/s3.png" class="img-fluid w-100"  />                        
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <div class="text-center card-body">                        
                        <img src="/storage/dashboard_images/s4.png" class="img-fluid w-100"  />                        
                    </div>
                </div>
            </div>
            <!-- @if(isset($data['totalStudent']))
            <div class="col-md-3">
                <div class="card">
                    <div class="text-center card-body">
                        <div class="text-center">
                            <h3>{{$data['totalStudent']}}</h3>
                        </div>
                        <div class="text-overline mb-3 font-weight-bolder">Total Students</div>
                        <div class="progress">
                            <div class="progress-bar bg-success" role="progressbar" style="width: 25%" aria-valuenow="3291" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                    </div>
                </div>
            </div>
            @endif
            @if(isset($data['totalUser']))
            <div class="col-md-3">
                <div class="card">
                    <div class="text-center card-body">
                        <div class="text-center">
                            <h3>{{$data['totalUser']}}</h3>
                        </div>
                        <div class="text-overline mb-3 font-weight-bolder">Total Employees</div>
                        <div class="progress">
                            <div class="progress-bar bg-success" role="progressbar" style="width: 25%" aria-valuenow="3291" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                    </div>
                </div>
            </div>
            @endif
            @if(isset($data['totalAdmission']))
            <div class="col-md-3">
                <div class="card">
                    <div class="text-center card-body">
                        <div class="text-center">
                            <h3>{{$data['totalAdmission']}}</h3>
                        </div>
                        <div class="text-overline mb-3 font-weight-bolder">Admission Inquiry</div>
                        <div class="progress">
                            <div class="progress-bar bg-success" role="progressbar" style="width: 25%" aria-valuenow="3291" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                    </div>
                </div>
            </div>
            @endif
            @if(isset($data['totalFees']))
            <div class="col-md-3">
                <div class="card">
                    <div class="text-center card-body">
                        <div class="text-center">
                            <h3>{{$data['totalFees']}}</h3>
                        </div>
                        <div class="text-overline mb-3 font-weight-bolder">Total Income</div>
                        <div class="progress">
                            <div class="progress-bar bg-success" role="progressbar" style="width: 25%" aria-valuenow="3291" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                    </div>
                </div>
            </div>
            @endif -->
        </div>
    </div>
        
        <div class="row equal slides">
            @if(isset($data['standardsJson']) && Session::get('user_profile_name') != 'Student')
           <div class="col-md-6 mb-4">
                <div class="card h-100">
                    <div class="card-body">
                    <h3 class="card-title">Student Attendance</h3>
                    <div id="container" style="min-width: 310px; max-width: 800px; height: 400px; margin: 0 auto"></div>
                    </div>
                </div>
            </div>
            @endif

            @if(isset($data['admissionBlock']) && Session::get('user_profile_name') != 'Student')
            <div class="col-md-6 mb-4">
                <div class="card h-100">
                    <h3 class="card-title">Admission Information</h3>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Standard Name</th>
                                    <th data-toggle="tooltip" title="Total Admission Enquiry">Total Admission Enquiry</th>
                                    <th data-toggle="tooltip" title="Total Admission Form">Total Admission Form</th>
                                    <th data-toggle="tooltip" title="Total Admission Registration">Total Admission Registration</th>
                                </tr>
                            </thead>
                            <tbody>
                                @if(count($data['admissionBlock']) > 0)
                                    @foreach($data['admissionBlock'] as $key => $value)
                                    <tr>                                        
                                        <td><span>{{$value->standard_name}}</span></td>
                                        <td><a href="{{ route('admission_enquiry_report') }}" style="color:black;"><span>{{$value->total_enquiry}}</span></a></td>
                                        <td><a href="{{ route('admission_registration_report') }}" style="color:black;"><span>{{$value->total_form}}</span></a></td>
                                        <td><a href="{{ route('admission_without_con_report') }}" style="color:black;"><span>{{$value->total_registration}}</span></a></td>
                                    </tr>
                                    @endforeach
                                @else
                                    <tr><td colspan="5" class="font-weight-bold"><center>No Records</center></td></tr>
                                @endif
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            @endif

            @if(isset($data['visitorBlock']) && Session::get('user_profile_name') != 'Student')
            <div class="col-md-6 mb-4">
                <div class="card h-100">
                    <h3 class="card-title">Todays Visitors</h3>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Visitor Name</th>
                                    <th>Appointment Type</th>
                                    <th>Contact</th>
                                    <th>To Meet</th>
                                </tr>
                            </thead>
                            <tbody>
                                @if(count($data['visitorBlock']) > 0)
                                    @foreach($data['visitorBlock'] as $key => $value)
                                    <tr>                                        
                                        <td><span>{{$value->name}}</span></td>
                                        <td><span>{{$value->appointment_type}}</span></td>
                                        <td><span>{{$value->contact}}</span></td>
                                        <td><span>{{$value->staff_name}}</span></td>                                            
                                    </tr>
                                    @endforeach
                                @else
                                    <tr><td colspan="5" class="font-weight-bold"><center>No Records</center></td></tr>
                                @endif
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            @endif

            @if(isset($data['smsNotificationBlock']) && Session::get('user_profile_name') != 'Student')
            <div class="col-md-6 mb-4">
                <div class="card h-100">
                    <h3 class="card-title">SMS Notification</h3>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Total Sms Parents</th>
                                    <th>Total Sms Staff</th>
                                    <th>Total Email Parents</th>                                        
                                </tr>
                            </thead>
                            <tbody>
                                @if(count($data['smsNotificationBlock']) > 0)                                        
                                    <tr>                                        
                                        <td><a href="{{ route('send_sms_report.index') }}" style="color:black;"><span>{{$data['smsNotificationBlock']['Total Sms Parents']}}</span></a></td>
                                        <td><a href="{{ route('send_sms_report.index') }}" style="color:black;"><span>{{$data['smsNotificationBlock']['Total Sms Staff']}}</span></a></td>
                                        <td><a href="{{ route('send_email_report.index') }}" style="color:black;"><span>{{$data['smsNotificationBlock']['Total Email Parents']}}</span></a></td>                                                                                 
                                    </tr>                                        
                                @else
                                    <tr><td colspan="5" class="font-weight-bold"><center>No Records</center></td></tr>
                                @endif
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            @endif

            @if(isset($data['academicBlock']) && Session::get('user_profile_name') != 'Student')
            <div class="col-md-6 mb-4">
                <div class="card h-100">
                    <h3 class="card-title">Academic Information</h3>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Total Homework</th>
                                    <th>Total Circular</th>
                                    <th>Total Remarks</th>
                                    <th data-toggle="tooltip" title="Total Circular Notification"></span>Total Circular Notification</th>
                                    <th data-toggle="tooltip" title="Total Homework Notification">Total Homework Notification</th>
                                </tr>
                            </thead>
                            <tbody>
                                @if(count($data['academicBlock']) > 0)                                        
                                    <tr>                                        
                                        <td><a href="{{ route('student_homework_report_index') }}" style="color:black;"><span>{{$data['academicBlock']['Total Homework']}}</span></a></td>
                                        <td><a href="{{ route('circular.index') }}" style="color:black;"><span>{{$data['academicBlock']['Total Circular']}}</span></a></td>
                                        <td><a href="{{ route('dicipline_report.index') }}" style="color:black;"><span>{{$data['academicBlock']['Total Dicipline']}}</span></a></td>
                                        <td><a href="#" style="color:black;"><span>@if(isset($data['academicBlock']['Circular Notification'])){{$data['academicBlock']['Circular Notification']}}@else 0 @endif</span></a></td>
                                        <td><a href="#" style="color:black;"><span>@if(isset($data['academicBlock']['Homework Notification'])){{$data['academicBlock']['Homework Notification']}}@else 0 @endif</span></a></td>
                                    </tr>                                        
                                @else
                                    <tr><td colspan="5" class="font-weight-bold"><center>No Records</center></td></tr>
                                @endif
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            @endif

            @if(isset($data['recentFeesCollection']) && Session::get('user_profile_name') != 'Student')
            <div class="col-md-12 col-lg-6 col-sm-12 slide">
                <div class="card">
                    <div class="card-body">
                        <!-- <div class="col-md-3 col-sm-4 col-xs-6 pull-right">
                            <select class="form-control pull-right row b-none">
                                <option>March 2017</option>
                                <option>April 2017</option>
                                <option>May 2017</option>
                                <option>June 2017</option>
                                <option>July 2017</option>
                            </select>
                        </div> -->
                        <h3 class="box-title">Recent fees collection</h3>
                        <div class="row sales-report my-0">
                            <div class="col-md-6 col-sm-6 col-xs-6 p-0">
                                <img src="{{asset('admin_dep/images/fees-report.png')}}" class="fees-report-icon" />
                                <h2 class="mt-0 mb-0">{{date('M Y')}}</h2>
                                <p class="mb-0">FEES REPORT</p>
                            </div>
                            <div class="col-md-6 col-sm-6 col-xs-6 p-0">
                                <h1 class="text-right text-info m-t-20 mb-0">
                                    <i class="mdi mdi-currency-inr fa-fw"></i> {{$data['totalFees']}}
                                </h1>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>PAYMENT MODE</th>
                                        <th>AMOUNT</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @php
                                    $j = 1;
                                    @endphp
                                    @if(count($data['recentFeesCollection']) > 0)
                                        @foreach($data['recentFeesCollection'] as $key => $value)
                                        <tr>
                                            <td>{{$j++}}</td>
                                            <!-- <td class="txt-oflo">{{$value['student_name']}}</td> -->
                                            <td>{{$value['payment_mode']}}</td>
                                            <!-- <td class="txt-oflo">{{$value['created_date']}}</td> -->
                                            <td><span class=""> <i
                                                        class="mdi mdi-currency-inr fa-fw"></i>{{$value['total_fees']}}</span>
                                            </td>
                                        </tr>
                                        @endforeach
                                    @else
                                        <tr><td colspan="5" class="font-weight-bold"><center>No Records</center></td></tr>
                                    @endif    
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            @endif

            @if(isset($data['parentCommunications']))
            <div class="col-md-12 col-lg-6 col-sm-12 mb-4">
                <div class="card h-100">
                    <h3 class="card-title">Recent Parent Communication</h3>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Student Name</th>                                      
                                    <th>Reason</th>
                                    <th>Reply</th>
                                </tr>
                            </thead>
                            <tbody>
                                @if(count($data['parentCommunications']) > 0)
                                    @foreach($data['parentCommunications'] as $key => $value)
                                    <tr>
                                        <td><span>{{$value->student_name}}</span> </td>                                           
                                        <td><span>{{$value->message}} [ {{date('d-m-Y h:m:i',strtotime($value->created_at))}} ]</span></td>
                                        <td><span>{{$value->reply}}</span></td>
                                    </tr>
                                    @endforeach
                                @else
                                    <tr><td colspan="5" class="font-weight-bold"><center>No Records</center></td></tr>
                                @endif
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            @endif

            @if(isset($data['stu_homework']))
            <div class="col-md-12 col-lg-6 col-sm-12 mb-4">
                <div class="card h-100">
                    <h3 class="card-title">Homework Data</h3>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Student Name</th>                                      
                                    <th>Title</th>
                                    <th>Description</th>
                                </tr>
                            </thead>
                            <tbody>
                                @if(count($data['stu_homework']) > 0)
                                    @foreach($data['stu_homework'] as $key => $value)
                                    <tr>
                                        <td><span>{{$value->student_name}}</span> </td>                                           
                                        <td><span>{{$value->title}}</span></td>
                                        <td><span>{{$value->description}}</span></td>
                                    </tr>
                                    @endforeach
                                @else
                                    <tr><td colspan="5" class="font-weight-bold"><center>No Records</center></td></tr>
                                @endif
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            @endif

            @if(isset($data['teacherBirthdays']) && Session::get('user_profile_name') != 'Student')
            <div class="col-md-12 col-lg-6 col-sm-12 slide">
                <div class="card">
                    <div class="card-body">
                        <h3 class="card-title">Teachers Birthday</h3>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Teacher Name</th>
                                        <th>Designation</th>
                                        <th>Contact Number</th>
                                        <th>Birthdate</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @if(count($data['teacherBirthdays']) > 0)
                                        @foreach($data['teacherBirthdays'] as $key => $value)
                                        <tr>
                                            <td><span>{{$value->teacher_name}}</span> </td>
                                            <td><span>{{$value->designation}}</span></td>
                                            <td><span>{{$value->contact_number}}</span></td>
                                            <td><span>{{$value->birthdate}}</span></td>
                                        </tr>
                                        @endforeach
                                    @else
                                        <tr><td colspan="5" class="font-weight-bold"><center>No Records</center></td></tr>
                                    @endif
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            @endif

            @if(isset($data['studentBirthdays']) && Session::get('user_profile_name') != 'Student')
            <div class="col-md-12 col-lg-6 col-sm-12 slide">
                <div class="card">
                    <div class="card-body">
                        <h3 class="card-title">Students Birthday</h3>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Student Name</th>
                                        <th>Standard</th>
                                        <th>Division</th>
                                        <th>Birthdate</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @if(count($data['studentBirthdays']) > 0)
                                        @foreach($data['studentBirthdays'] as $key => $value)
                                        <tr>
                                            <td><span>{{$value->student_name}}</span> </td>
                                            <td><span>{{$value->standard_name}}</span></td>
                                            <td><span>{{$value->division_name}}</span></td>
                                            <td><span>{{$value->dob}}</span></td>
                                        </tr>
                                        @endforeach
                                    @else
                                        <tr><td colspan="5" class="font-weight-bold"><center>No Records</center></td></tr>
                                    @endif
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            @endif

            @if(isset($data['calendarEvents']) && Session::get('user_profile_name') != 'Student')
            <div class="col-md-12 col-lg-6 col-sm-12 slide">
                <div class="card">
                    <div class="card-body">
                        <h3 class="card-title">Events</h3>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Event Title</th>
                                        <th>Event Type</th>
                                        <th>Event Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @if( count($data['calendarEvents']) > 0 )
                                        @foreach($data['calendarEvents'] as $key => $value)
                                        <tr>
                                            <td><span>{{$value->title}}</span> </td>
                                            <td><span>{{$value->event_type}}</span></td>
                                            <td><span>{{date('d-M-Y',strtotime($value->school_date))}}</span></td>
                                        </tr>
                                        @endforeach
                                    @else
                                        <tr><td colspan="5" class="font-weight-bold"><center>No Records</center></td></tr>
                                    @endif
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            @endif

            @if(isset($data['studentLeaves']) && Session::get('user_profile_name') != 'Student')
            <div class="col-md-12 col-lg-6 col-sm-12 slide">
                <div class="card">
                    <div class="card-body">
                        <h3 class="card-title">Student Leaves</h3>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Student Name</th>
                                        <th>Standard - Division</th>
                                        <th>Reason</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @if(count($data['studentLeaves']) > 0)
                                        @foreach($data['studentLeaves'] as $key => $value)
                                        <tr>
                                            <td><span>{{$value->standard_name}}</span> </td>
                                            <td><span>{{$value->standard_name." - ".$value->division_name}}</span></td>
                                            <td><span>{{$value->message}}</span></td>
                                        </tr>
                                        @endforeach
                                    @else
                                        <tr><td colspan="5" class="font-weight-bold"><center>No Records</center></td></tr>
                                    @endif
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            @endif

            @if(isset($data['facultytimetableBlock']) && Session::get('user_profile_name') != 'Student')
            <div class="col-md-12 col-lg-6 col-sm-12 slide">
                <div class="card">
                    <div class="card-body">
                        <h3 class="card-title">Faculty Timetable</h3>
                        {!!$data['facultytimetableBlock']!!}
                    </div>
                </div>
            </div>
            @endif

        </div>
        <!-- ============================================================== -->
        <!-- chats, message & profile widgets -->
        <!-- ============================================================== -->

        <!-- ============================================================== -->
        <!-- start right sidebar -->
        <!-- ============================================================== -->
        <!-- <div class="right-sidebar">
            <div class="slimscrollright">
                <div class="rpanel-title"> Choose Theme <span><i class="ti-close right-side-toggle"></i></span> </div>
                <div class="r-panel-body">
                    <ul id="themecolors" class="m-t-20">
                        <li><b>With Light sidebar</b></li>
                        <li><a href="javascript:void(0)" data-theme="default" class="default-theme">1</a></li>
                        <li><a href="javascript:void(0)" data-theme="green" class="green-theme">2</a></li>
                        <li><a href="javascript:void(0)" data-theme="gray" class="yellow-theme">3</a></li>
                        <li><a href="javascript:void(0)" data-theme="blue" class="blue-theme">4</a></li>
                        <li><a href="javascript:void(0)" data-theme="purple" class="purple-theme">5</a></li>
                        <li><a href="javascript:void(0)" data-theme="megna" class="megna-theme">6</a></li>
                    </ul>
                </div>
            </div>
        </div> -->
        <!-- ============================================================== -->
        <!-- end right sidebar -->
        <!-- ============================================================== -->
    </div>
    <!-- /.container-fluid -->
    <!-- ============================================================== -->
    <!-- End Page Content -->
    <!-- ============================================================== -->
</div>

@include('includes.footerJs')

<!-- <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/2.1.3/jquery.min.js"></script> -->
<!-- <script src="https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.11.2/jquery-ui.min.js"></script> -->

<script type="text/javascript">
    $(".slides").sortable({
     placeholder: 'slide-placeholder',
    axis: "y",
    revert: 150,
    start: function(e, ui){

        placeholderHeight = ui.item.outerHeight();
        ui.placeholder.height(placeholderHeight + 15);
        $('').insertAfter(ui.placeholder);

    },
    change: function(event, ui) {

        ui.placeholder.stop().height(0).animate({
            height: ui.item.outerHeight() + 15
        }, 300);

        placeholderAnimatorHeight = parseInt($(".slide-placeholder-animator").attr("data-height"));

        $(".slide-placeholder-animator").stop().height(placeholderAnimatorHeight + 15).animate({
            height: 0
        }, 300, function() {
            $(this).remove();
            placeholderHeight = ui.item.outerHeight();
            $('').insertAfter(ui.placeholder);
        });

    },
    stop: function(e, ui) {

        $(".slide-placeholder-animator").remove();

    },
});
</script>

<script src="https://code.highcharts.com/highcharts.js"></script>
<script src="https://code.highcharts.com/modules/sunburst.js"></script>
<script src="https://code.highcharts.com/modules/exporting.js"></script>
<script src="https://code.highcharts.com/modules/export-data.js"></script>
<script src="https://code.highcharts.com/modules/accessibility.js"></script>
<script type="text/javascript">
$(document).ready(function(){
    $('[data-toggle="tooltip"]').tooltip();   
});
</script>

@if(isset($data['standardsJson']))
<script type="text/javascript">
    var a = <?php echo $data['standardsJson']; ?>;
//alert(a);
</script>
<script type="text/javascript">
    Highcharts.chart('container', {
    chart: {
        type: 'column'
    },
    credits : false,
    title: {
        text: 'Student Attendance'
    },
    xAxis: {
        categories: <?php echo $data['standardsJson']; ?>//['CBSE-1', 'CBSE-2']
    },
    yAxis: {
        title: {
            text: 'Number of students'
        }
    },
    legend: {
        reversed: true
    },
    plotOptions: {
        series: {
            stacking: 'normal'
        }
    },
    series: [{
        name: 'Present',
        color : '#0facf3',
        data: {{$data['presantsJson']}}//[5, 2]
    }, {
        name: 'Absent',
        color : '#e6346294',
        data: {{$data['absentsJson']}}//[2, 3]
    }]
});


</script>
@endif

@if(Session::get('erpTour')['dashboard']==0)
<script src="../../../tooltip/bower_components/todomvc-common/base.js"></script>
<!-- <script src="../../../tooltip/bower_components/jquery/jquery.js"></script> -->
<script src="../../../tooltip/bower_components/underscore/underscore.js"></script>
<script src="../../../tooltip/bower_components/backbone/backbone.js"></script>
<script src="../../../tooltip/bower_components/backbone.localStorage/backbone.localStorage.js"></script>
<script src="../../../tooltip/js/models/todo.js"></script>
<script src="../../../tooltip/js/collections/todos.js"></script>
<script src="../../../tooltip/js/views/todo-view.js"></script>
<script src="../../../tooltip/js/views/app-view.js"></script>
<script src="../../../tooltip/js/routers/router.js"></script>
<script src="../../../tooltip/js/app.js"></script>
<script src="../../../tooltip/enjoyhint/enjoyhint.js"></script>
<script src="../../../tooltip/enjoyhint/jquery.enjoyhint.js"></script>
<script src="../../../tooltip/enjoyhint/kinetic.min.js"></script>
<script type="text/javascript">
     localStorage.clear();
</script>
<!-- <script>
    localStorage.clear();
      var enjoyhint_script_data = [
        {
            onBeforeStart: function(){
            $('#openToggle').click(function(e){

                enjoyhint_instance.trigger('new_todo');

            });
          },
          selector:'#openToggle',
          event:'new_todo',
          event_type:'custom',
          description:'You can see your profile here.'
        },
        {
          onBeforeStart: function(){
            $('#academicYears').click(function(e){

                enjoyhint_instance.trigger('new_todo');

            });
          },
          selector:'#academicYears',
          event:'new_todo',
          event_type:'custom',
          description:'You can change the Academic Years from here.',
        },
        {
          selector:'#academicTerms',
          event:'click',
          description:'You can change the Academic Terms from here.',
          timeout:100
        },
        {
          selector:'#openToggle',
          event:'click',
          description:'You can set up all ERP related things here. Click on SYSTEM SETTING.',
          timeout:100
        }
      ];
      var enjoyhint_instance = null;
      $(document).ready(function(){
        enjoyhint_instance = new EnjoyHint({});
        enjoyhint_instance.setScript(enjoyhint_script_data);
        enjoyhint_instance.runScript();
      });
</script> -->
<script type="text/javascript">
    var url = "http://202.47.117.124/tourUpdate?module=dashboard";
        var xhttp = new XMLHttpRequest();
        xhttp.onreadystatechange = function() {
            if (this.readyState == 4 && this.status == 200) {
              console.log("success");
            }
        };
        xhttp.open("GET", url, true);
        xhttp.send();
</script>
@endif
@include('includes.footer')