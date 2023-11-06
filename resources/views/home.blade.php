@include('includes.headcss')
<link rel="stylesheet" href="../../../tooltip/enjoyhint/jquery.enjoyhint.css" rel="preload" as="style" onload="this.onload=null;this.rel='stylesheet'">
@include('includes.header')
@include('includes.sideNavigation')

<style>
.divHide{
    display: none !important;
}
.white-box .box-title 
{
    margin-bottom: 30px !important;
}
.white-box .box-title:after 
{
    bottom: -15px !important;
}
.list-inline>li 
{
    padding-top: 8px !important;
}
.white-box .box-title:after 
{
    bottom: -15px;
}

.expire_button {
  background-color: #004A7F;
  -webkit-border-radius: 10px;
  border-radius: 10px;
  border: none;
  color: #FFFFFF;
  cursor: pointer;
  display: inline-block;
  font-family: Arial;
  font-size: 20px;
  padding: 5px 10px;
  text-align: center;
  text-decoration: none;
  -webkit-animation: glowing 1500ms infinite;
  -moz-animation: glowing 1500ms infinite;
  -o-animation: glowing 1500ms infinite;
  animation: glowing 1500ms infinite;
}
@-webkit-keyframes glowing {
  0% { background-color: #B20000; -webkit-box-shadow: 0 0 3px #B20000; }
  50% { background-color: #FF0000; -webkit-box-shadow: 0 0 40px #FF0000; }
  100% { background-color: #B20000; -webkit-box-shadow: 0 0 3px #B20000; }
}

@-moz-keyframes glowing {
  0% { background-color: #B20000; -moz-box-shadow: 0 0 3px #B20000; }
  50% { background-color: #FF0000; -moz-box-shadow: 0 0 40px #FF0000; }
  100% { background-color: #B20000; -moz-box-shadow: 0 0 3px #B20000; }
}

@-o-keyframes glowing {
  0% { background-color: #B20000; box-shadow: 0 0 3px #B20000; }
  50% { background-color: #FF0000; box-shadow: 0 0 40px #FF0000; }
  100% { background-color: #B20000; box-shadow: 0 0 3px #B20000; }
}

@keyframes glowing {
  0% { background-color: #B20000; box-shadow: 0 0 3px #B20000; }
  50% { background-color: #FF0000; box-shadow: 0 0 40px #FF0000; }
  100% { background-color: #B20000; box-shadow: 0 0 3px #B20000; }
}
.progress-bar {
  background-color: #ffc107;
}
</style>


<div class="content-main flex-fill">
    <div class="container-fluid">        
        @if ($sessionData = Session::get('data'))
        @if($sessionData['status_code'] == 1)
        <div class="alert alert-success alert-block">
        @else
        <div class="alert alert-danger alert-block">
        @endif
            <button type="button" class="close" data-dismiss="alert">×</button>
            <strong>{{ $sessionData['message'] }}</strong>
        </div>
        @endif
        
        @if(Session::get('is_admin') == 1 && Session::get('sub_institute_id') == 0)
            <div class="px-4 bg-dark mb-3 pb-5 rounded mt-3">
                <div class="row">
                    <div class="col-12 text-white pt-4 pb-5">
                        <h4 class="page-title text-white">Dashboard</h4>
                        <h3 class="text-white">Welcome, <span class="js-greeting">{{ Session::get('name') }}</span></h3>
                       
                    </div>
                </div>
            </div>
        @else
            @if(isset($data['school_setup_data']) && $data['school_setup_data']['expire_date'] != "")
            <button type="submit" class="expire_button">{{$data['school_setup_data']['remaining_days']}} days left for your free trail version</button>
        @endif
        
        @if(isset($data['occupied_space_in_MB']) && $data['occupied_space_in_MB'] != "")
        <div class="progress" style="height: 10px;background-color: rgba(0,0,0,.12);">
          <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width:{{$data['used_space_in_MB']}}" aria-valuenow="{{$data['used_space_in_MB']}}" aria-valuemin="0" aria-valuemax="{{$data['occupied_space_in_MB']}}"></div>
        </div>

        <div class="card">
            <a href="{{ route('used_storage_graph.index') }}" class="js-greeting card-title">
                {{ number_format($data['used_space_in_MB'], 2) }} MB of {{ number_format($data['occupied_space_in_MB'], 2) }} MB used
            </a>
        </div>
        @endif

        <div class="px-4 bg-dark mb-3 pb-5 rounded mt-3">
            <div class="row">
                <div class="col-12 text-white pt-4 pb-5">
                    <h4 class="page-title text-white">Dashboard</h4>
                    <h3 class="text-white">Welcome, <span class="js-greeting">{{ Session::get('name') }}</span></h3>
                   
                </div>
            </div>
        </div>

        <div class="container-fluid pull-up1 mb-3">
            <div class="row">
                <div class="col-md-3">
                    @if(isset($data['totalStudent']))
                    <div class="card">
                        <div class="text-center card-body">
                            <div class=" text-center">
                                <h3>{{$data['totalStudent']}}</h3>
                            </div>
                            <div class="text-overline mb-3 font-weight-bolder">Total Students</div>
                            <div class="progress">
                                <div class="progress-bar bg-success" role="progressbar" style="width: 25%" aria-valuenow="{{$data['totalStudent']}}" aria-valuemin="0" aria-valuemax="100"></div>
                            </div>
                        </div>
                    </div>
                    @endif
                </div>
                <div class="col-md-3">
                    @if(isset($data['totalAdmission']))
                    <div class="card">
                        <div class="text-center card-body">
                            <div class=" text-center">
                                <h3>{{$data['totalAdmission']}}</h3>
                            </div>
                            <div class="text-overline mb-3 font-weight-bolder">Admission Inquiry</div>
                            <div class="progress">
                                <div class="progress-bar bg-info" role="progressbar" style="width: 78%" aria-valuenow="{{$data['totalAdmission']}}" aria-valuemin="0" aria-valuemax="100"></div>
                            </div>
                        </div>
                    </div>
                    @endif
                </div>
                <div class="col-md-3">
                    @if(isset($data['totalFees']))
                    <div class="card">
                        <div class="text-center card-body">
                        <a href="{{route('fees_collection_report.index')}}">
                            <div class=" text-center">
                                <h3>{{$data['totalFees']}}</h3>
                            </div>
                            <div class="text-overline mb-3 font-weight-bolder">Total Income</div>
                            <div class="progress">
                                <div class="progress-bar bg-danger" role="progressbar" style="width: 55%" aria-valuenow="{{$data['totalFees']}}" aria-valuemin="0" aria-valuemax="100"></div>
                            </div>
                        </div>
                        </a>
                    </div>
                    @endif
                </div>
                <div class="col-md-3">
                    @if(isset($data['totalUser']))
                    <div class="card">
                        <div class="text-center card-body">
                            <div class=" text-center">
                                <h3>{{$data['totalUser']}}</h3>
                            </div>
                            <div class="text-overline mb-3 font-weight-bolder">Total Employees</div>
                            <div class="progress">
                                <div class="progress-bar bg-warning" role="progressbar" style="width: 87%" aria-valuenow="{{$data['totalUser']}}" aria-valuemin="0" aria-valuemax="100"></div>
                            </div>
                        </div>
                    </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="container-fluid mb-2">
            <div class="row">
                @if(isset($data['standardsJson']))
                <div class="col-md-6 mb-4">
                    <div class="card h-100">
                        <div class="card-body">
                        <h3 class="card-title">Student Attendance</h3>
                        <div id="container" style="min-width: 310px; max-width: 800px; height: 400px; margin: 0 auto"></div>
                        </div>
                    </div>
                </div>
                @endif              

                @if(isset($data['admissionBlock']))
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

                @if(isset($data['visitorBlock']))
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

                @if(isset($data['smsNotificationBlock']))
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

                @if(isset($data['academicBlock']))
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
                
                @if(isset($data['recentFeesCollection']))
                <div class="col-md-6 mb-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <h3 class="card-title">Recent fees collection</h3>
                            <div class="row sales-report mb-3">
                                <div class="col-md-6 col-sm-6 col-xs-6">                                   
                                    <div class="mt-0 h4">{{date('M Y')}}</div>
                                    <p class="mb-0">FEES REPORT</p>
                                </div>
                                <div class="col-md-6 col-sm-6 col-xs-6">
                                    <div class="text-right text-info m-t-20 mb-0 h3">
                                        <i class="mdi mdi-currency-inr fa-fw"></i> {{$data['totalFees']}}
                                    </div>
                                </div>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
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
                                                <td>{{$value['payment_mode']}}</td>                                           
                                                <td><span class=""> 
                                                    <i class="mdi mdi-currency-inr fa-fw"></i>{{$value['total_fees']}}</span>
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
                        <div class="row sales-report mb-3">
                                <div class="col-md-6 col-sm-6 col-xs-6 mt-2">                                                               
                                <a href="{{route('fees_collection_report.index')}}">Fees Collection Report</a><BR>
                                <a href="{{route('fees_monthly_report.index')}}">Monthly Fees Report</a>                            
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
                @if(isset($data['teacherBirthdays']))
                <div class="col-md-12 col-lg-6 col-sm-12 mb-4">
                    <div class="card h-100">
                        <h3 class="card-title">Teachers Birthday (Up to 7 days)</h3>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                         <th>Teacher Name</th>
                                          <th>Designation </th>
                                         <th>Mobile</th>
                                         <th>BirthDate</th>
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
                @endif
                @if(isset($data['studentBirthdays']))
                <div class="col-md-12 col-lg-6 col-sm-12 mb-4">
                    <div class="card h-100">
                        <h3 class="card-title">Students Birthday (Up to 7 days)</h3>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Student Name</th>
                                        <th>Standard</th>
                                        <th>Division</th>
                                        <th>DOB</th>

                                    </tr>
                                </thead>
                                <tbody>
                                    @if(count($data['studentBirthdays']) > 0)
                                        @foreach($data['studentBirthdays'] as $key => $value)
                                        <tr>
                                            <td><span>{{$value->student_name}}</span> </td>
                                            <td><span>{{$value->standard_name}} - {{$value->division_name}}</span></td>
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
                @endif
                @if(isset($data['calendarEvents']))
                <div class="col-md-12 col-lg-6 col-sm-12 mb-4">
                    <div class="card h-100">
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
                                    @if(count($data['calendarEvents']) > 0)
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
                @endif
                @if(isset($data['studentLeaves']))
                <div class="col-md-12 col-lg-6 col-sm-12 mb-4">
                    <div class="card h-100">
                        <h3 class="card-title">Todays Student Leaves</h3>
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
                                            <td><span>{{$value->student_name}}</span> </td>
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
                @endif
                
                @if(isset($data['studentFeesChart']))
                <div class="col-md-12 col-lg-6 col-sm-12 mb-4">
                    <div class="card h-100">
                        <h3 class="card-title">Student Fees Chart</h3>
                        <div id="student_fees_chart"></div>
                    </div>
                </div>
                @endif

            </div>
        </div>      
    <!-- /.container-fluid -->
    <!-- ============================================================== -->
    <!-- End Page Content -->
    <!-- ============================================================== -->
        @endif 
    </div>    
</div>
@include('includes.footerJs')
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
    var a ={{ $data['standardsJson']}};    
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
        categories: <?php echo $data['standardsJson']; ?>//{{$data['standardsJson']}} //['CBSE-1', 'CBSE-2']
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
        data: <?php echo $data['presantsJson']; ?>//{{$data['presantsJson']}}//[5, 2]
    }, {
        name: 'Absent',
        color : '#e6346294',
        data: <?php echo $data['absentsJson']; ?>//{{$data['absentsJson']}}//[2, 3]
    }]
});
</script>
@endif

@if(isset($data['chartData']))
<script>
    var data = {{$data['chartData']}};
    Highcharts.getOptions().colors.splice(0, 0, 'transparent');


Highcharts.chart('student_fees_chart', {

    chart: {
        height: '300px'
    },

    title: {
        text: 'Fees Student Chart'
    },
    
    series: [{
        type: "sunburst",
        data: data,
        allowDrillToNode: true,
        cursor: 'pointer',
        dataLabels: {
            format: '{point.name}',
            filter: {
                property: 'innerArcLength',
                operator: '>',
                value: 16
            }
        },
        levels: [{
            level: 1,
            levelIsConstant: false,
            dataLabels: {
                filter: {
                    property: 'outerArcLength',
                    operator: '>',
                    value: 64
                }
            }
        }, {
            level: 2,
            colorByPoint: true
        },
        {
            level: 3,
            colorVariation: {
                key: 'brightness',
                to: -0.5
            }
        }, {
            level: 4,
            colorVariation: {
                key: 'brightness',
                to: 0.5
            }
        }]

    }],
    tooltip: {
        headerFormat: "",
        pointFormat: 'The Data of <b>{point.name}</b> is <b>{point.value}</b>'
    }
});
</script>
@endif


<!--  @if(Session::get('erpTour') == 0) //Session::get('erpTour')['dashboard']
<script src="../../../tooltip/bower_components/todomvc-common/base.js"></script>
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
<script>
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
</script> 
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
 @endif -->
@include('includes.footer')
