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
                                        <option
                                                value="{{$employeeList->id}}" selected>{{$employeeList->first_name .' '. $employeeList->last_name }}</option>
                                    @else
                                        <option
                                                value="{{$employeeList->id}}">{{$employeeList->first_name .' '. $employeeList->last_name }}</option>
                                    @endif
                                @endforeach
                            </select>
                            @error('employee')
                            <span style="color: red">{{$message}}</span>
                            @enderror
                        </div>
                        <div class="col-md-4 form-group">
                            <label>Date</label>
                            <input type="date" placeholder="{{date('d/m/Y',strtotime($data['date']))}}" value="{{ date('Y-m-d',strtotime($data['date'])) }}" id="indate" name="indate" class="form-control">
                            @error('indate')
                            <span style="color: red">{{$message}}</span>
                            @enderror
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
                                    <option value="2">Day End</option>
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
                                        <option
                                                value="{{$employeeList->id}}" selected>{{$employeeList->first_name .' '. $employeeList->last_name }}</option>
                                    @else
                                        <option
                                                value="{{$employeeList->id}}">{{$employeeList->first_name .' '. $employeeList->last_name }}</option>
                                    @endif
                                @endforeach
                            </select>
                            @error('employee')
                            <span style="color: red">{{$message}}</span>
                            @enderror
                        </div>
                        <div class="col-md-4 form-group">
                            <label>Date </label>
                            <input type="date" placeholder="{{date('d/m/Y',strtotime($data['date']))}}" value="{{ date('Y-m-d',strtotime($data['date'])) }}" id="indate" name="outdate" class="form-control">
                            @error('indate')
                            <span style="color: red">{{$message}}</span>
                            @enderror
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
                                @if($data['note'] ==1)
                                    <option value="1" selected>Day Start</option>
                                    <option value="2">Day End</option>
                                @else
                                    <option value="1">Day Start</option>
                                    <option value="2" selected>Day End</option>
                                @endif

                            </select>
                        </div>

                        <input type="hidden" name="employee_id" value="{{$data['employee_id']}}">
                        <div class="col-md-12 form-group">
                            <center>
                                <input type="submit" name="submit" id="Submit" value="Out"
                                       class="btn btn-success">
                            </center>
                        </div>
                    </div>
                </form>
            @endif
        </div>
    </div>
</div>

@include('includes.footerJs')
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
