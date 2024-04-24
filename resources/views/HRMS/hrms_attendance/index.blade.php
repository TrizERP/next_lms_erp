@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<link rel="stylesheet" href="../../../plugins/bower_components/dropify/dist/css/dropify.min.css">


<style>
    .email_error {
        width: 80%;
        height: 35px;
        font-size: 1.1em;
        color: #D83D5A;
        font-weight: bold;
    }

    .email_success {
        width: 80%;
        height: 35px;
        font-size: 1.1em;
        color: green;
        font-weight: bold;
    }
</style>
<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Hrms Attendance</h4>
            </div>
        </div>
        <div class="card">
            <!-- @TODO: Create a saperate tmplate for messages and include in all tempate -->
            @if ($message = Session::get('message'))
                <div class="alert alert-success alert-block">
                    <button type="button" class="close" data-dismiss="alert">×</button>
                    <strong>{{ $message }}</strong>
                </div>
            @endif
            @if($data['button'] == 'in')
                <form action="{{ route('hrms_attendance_in_time.store') }}" method="post">
                @csrf
                    <div class="row">
                        <div class="col-md-4 form-group">
                            <label>Employee List</label>
                            <select id='employee_id' name="employee" class="form-control">
                                <option value="">Select Employee</option>
                                @foreach($data['employeeLists'] as $key => $employeeList)
                                    @if( $data['employee_id'] == $employeeList->id)
                                        <option value="{{$employeeList->id}}" selected>{{$employeeList->first_name .' '. $employeeList->last_name }}</option>
                                    @else
                                        <option value="{{$employeeList->id}}">{{$employeeList->first_name .' '. $employeeList->last_name }}</option>
                                    @endif
                                @endforeach
                            </select>
                            @error('employee')
                            <span style="color: red">{{$message}}</span>
                            @enderror
                        </div>
                        <div class="col-md-4 form-group">
                            <label>Date</label>
                            <div class="input-daterange input-group" id="date-range">
                                <input type="text" required class="form-control mydatepicker" placeholder="YYYY/MM/DD" name="indate" id="indate" value="{{ date('Y-m-d',strtotime($data['date'])) }}" autocomplete="off">
                                <span class="input-group-addon"><i class="icon-calender"></i></span>
                            </div>
                        </div>
                        <div class="col-md-4 form-group">
                            <label>Time</label>
                           {{-- <input type="text" id='' disabled name="" class="form-control"
                                   value="{{$data['time']}}">--}}
                            <input type="text" id='intime' name="intime" class="form-control"
                                   value="{{$data['time']}}">
                        </div>

                        <div class="col-md-4 form-group">
                            <label>Note</label>
                            <select id='employee_id' name="note" class="form-control">
                                @if($data['note'] ==1)
                                    <option value="1" selected>Day Start</option>
                                    
                                @else
                                    <option value="1">Day Start</option>
                                    <option value="2" selected>Day End</option>
                                @endif

                            </select>
                        </div>

                        <input type="hidden" name="id" value="{{$data['id']}}">
                        <div class="col-md-12 form-group">
                            <center>
                                <input type="submit" name="submit" id="Submit" value="In" class="btn btn-success">
                            </center>
                        </div>
                    </div>
                </form>
            @else
                <form action="{{ route('hrms_attendance_out_time.store') }}" method="post">
                    @csrf
                    <div class="row">
                        <div class="col-md-4 form-group">
                            <label>Employee List</label>
                            <select id='employee_id' name="employee" class="form-control">
                                <option value="">Select Employee</option>
                                @foreach($data['employeeLists'] as $key => $employeeList)
                                    @if( $data['employee_id'] == $employeeList->id)
                                        <option value="{{$employeeList->id}}" selected>{{$employeeList->first_name .' '. $employeeList->last_name }}</option>
                                    @else
                                        <option value="{{$employeeList->id}}">{{$employeeList->first_name .' '. $employeeList->last_name }}</option>
                                    @endif
                                @endforeach
                            </select>
                            @error('employee')
                            <span style="color: red">{{$message}}</span>
                            @enderror
                        </div>
                        <div class="col-md-4 form-group">
                            <label>Date</label>
                            <div class="input-daterange input-group" id="date-range">
                                <input type="text" required class="form-control mydatepicker" placeholder="YYYY/MM/DD" name="outdate" id="outdate" value="{{ date('Y-m-d',strtotime($data['date'])) }}" autocomplete="off">
                                <span class="input-group-addon"><i class="icon-calender"></i></span>
                            </div>
                        </div>
                        <div class="col-md-4 form-group">
                            <label>Time</label>
                           {{-- <input type="text" id='' disabled name="" class="form-control"
                                   value="{{$data['time']}}">--}}
                            <input type="text" id='outtime' name="outtime" class="form-control"
                                   value="{{$data['time']}}">
                        </div>

                        <div class="col-md-4 form-group">
                            <label>Note</label>
                            <select id='employee_id' name="note" class="form-control">
                                @if($data['note'] == 1)
                                    <option value="1" selected>Day Start</option>
                                    <option value="2">Day End</option>
                                @else
                                    
                                    <option value="2" selected>Day End</option>
                                @endif

                            </select>
                        </div>

                        <input type="hidden" name="employee_id" value="{{$data['employee_id']}}">
                        <div class="col-md-12 form-group">
                            @if(empty($data['hrms_attendance']->punchout_time))
                                <center>
                                    <input type="submit" name="submit" id="Submit" value="Out" class="btn btn-success">
                                </center>
                            @endif
                        </div>
                    </div>
                </form>
            @endif
        </div>
        <div class="card">
            <div class="table-responsive mt-20 tz-report-table">
                <table id="example" class="table table-striped">
                    <thead>
                    <tr>
                        <th>Sr No.</th>
                        <th>Date</th>
                        <th>Employee Name</th>
                        <th>In Time</th>
                        <th class="text-left">Out Time</th>
                    </tr>
                    </thead>
                    @php
                    $j = 1;
                    
                    if(isset($data['hrms_attendance'])){
                        $user_id = $data['hrms_attendance']->user_id ?? '';
                        $sub_institute_id = $data['hrms_attendance']->sub_institute_id ?? '';

                        $get_employe_name = DB::table('tbluser')->where(['id' => $user_id, 'sub_institute_id' => $sub_institute_id])->where('status',1)->first(); 
                    }
                    @endphp
                    <tbody>
                        <tr>
                            <td>{{ $j++ }}</td>
                            <td>{{ $data['hrms_attendance']->day ?? '' }}</td>
                            <td>{{ $get_employe_name->first_name ?? ''}} {{ $get_employe_name->last_name ?? ''}}</td>
                            <td>{{ isset($data['hrms_attendance']->punchin_time) ? \Carbon\Carbon::parse($data['hrms_attendance']->punchin_time)->format('h:i A') : 'N/A' }}</td>
                            <td>{{ isset($data['hrms_attendance']->punchout_time) ? \Carbon\Carbon::parse($data['hrms_attendance']->punchout_time)->format('h:i A') : 'N/A' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

@include('includes.footerJs')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/jquery-toast-plugin/1.3.2/jquery.toast.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-toast-plugin/1.3.2/jquery.toast.min.js"></script>
<script type="text/javascript">
    $(document).ready(function() {
        $('[data-toggle="tooltip"]').tooltip();
    });
</script>
<script src="../../../admin_dep/js/cbpFWTabs.js"></script>
<script>
    var indate = document.getElementById('indate');
    indate.addEventListener('change', function () {
        var employeeId = document.getElementById('employee_id').value;
        var date = document.getElementById('indate').value;
        console.log(employeeId);
        window.location.href = window.location.origin + '/hrms-attendance?employee_id=' + employeeId + '&date='+date;
    }, false);

    var select = document.getElementById('employee_id');
    select.addEventListener('change', function () {
        var employeeId = document.getElementById('employee_id').value;
        console.log(employeeId);
        window.location.href = window.location.origin + '/hrms-attendance?employee_id=' + employeeId;
    }, false);
    console.log("test")


</script>
<script type="text/javascript">
    (function () {
        [].slice.call(document.querySelectorAll('.sttabs')).forEach(function (el) {
            new CBPFWTabs(el);
        });
    })();
</script>
<script src="../../../plugins/bower_components/dropify/dist/js/drsopify.min.js"></script>
@include('includes.footer')
