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
                    <form action="{{ route('hrms_attendance_in_time.store') }}" method="post" id="inForm">
                    @csrf
                        <div class="row">
                            <div class="col-md-4 form-group">
                                <label>Employee List</label>
                                <select id='employee_id' name="employee" class="form-control employee_id">
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
                                <input type="text" id='intime' name="intime" class="form-control" value="{{$data['time']}}">
                            </div>

                            <div class="col-md-4 form-group">
                                <label>Note</label>
                                <select name="note" class="form-control" id="note_in">
                                    <option value="1">Day Start</option>
                                    <option value="2">Day End</option>
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
                    <form action="{{ route('hrms_attendance_out_time.store') }}" method="post" id="outForm">
                        @csrf
                        <div class="row">
                            <div class="col-md-4 form-group">
                                <label>Employee List</label>
                                <select id='employee_id' name="employee" class="form-control employee_id">
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
                                <input type="text" id='outtime' name="outtime" class="form-control" value="{{$data['time']}}">
                            </div>

                            <div class="col-md-4 form-group">
                                <label>Note</label>
                                <select name="note" class="form-control" id="note_out">
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
                                
                                    <center>
                                        <input type="submit" name="submit" id="Submit" value="Out" class="btn btn-success btn-hide">
                                    </center>
                                
                            </div>
                        </div>                                                              
                    </form>
            </div>
            @if(isset($data['hrms_attendance']))
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
                            <th>Actions</th>
                        </tr>                               
                        </thead>
                        @php                                                                    
                        $j = 1;
                            $user_id = $data['hrms_attendance']->user_id ?? '';
                            $sub_institute_id = $data['hrms_attendance']->sub_institute_id ?? '';

                            $get_employe_name = DB::table('tbluser')->where(['id' => $user_id, 'sub_institute_id' => $sub_institute_id])->where('status',1)->first(); 
                        
                        @endphp
                        <tbody>                        
                            <tr>
                                <td>{{ $j++ }}</td>
                                <td>{{ $data['hrms_attendance']->day ?? '' }}</td>
                                <td>{{ $get_employe_name->first_name ?? ''}} {{ $get_employe_name->last_name ?? ''}}</td>
                                <td class="in-time">{{ isset($data['hrms_attendance']->punchin_time) ? \Carbon\Carbon::parse($data['hrms_attendance']->punchin_time)->format('h:i A') : '-' }}</td>
                                <td class="out-time">{{ isset($data['hrms_attendance']->punchout_time) ? \Carbon\Carbon::parse($data['hrms_attendance']->punchout_time)->format('h:i A') : '-' }}</td>
                                <td>
                                    <button type="button" class="btn btn-info btn-sm in-btn">In</button>
                                    <button type="button" class="btn btn-warning btn-sm out-btn">Out</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    @endif

    @include('includes.footerJs')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/jquery-toast-plugin/1.3.2/jquery.toast.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-toast-plugin/1.3.2/jquery.toast.min.js"></script>
    <script type="text/javascript">
        $(document).ready(function() {
            @if($data['button'] == 'in')
                $('#inForm').show();
                $('#outForm').hide();
            @else 
                $('#inForm').hide();
                $('#outForm').show();
            @endif

            $('.employee_id').on('change', function () {
                const employeeId = $(this).val();
                if (employeeId) {
                    window.location.href = window.location.origin + '/hrms-attendance?employee_id=' + employeeId;
                }
            });

            @if(isset($data['hrms_attendance']->punchout_time))
                $('.btn-hide').hide();
            @endif
                $('[data-toggle="tooltip"]').tooltip();
                
                // In button click handler
                $('.in-btn').on('click', function() {
                    var row = $(this).closest('tr');
                    var inTime = row.find('.in-time').text().trim();
                    
                    // Convert time to 24-hour format if needed
                    var time24 = convertTo24Hour(inTime);
                    
                    // Set values in the In form
                    $('#inForm #intime').val(time24);
                    $('#inForm #note_in').val('1'); // Day Start
                    
                    // Show the In form and hide the Out form if both are present
                    $('#inForm').show();
                    $('#outForm').hide();
                });
                
                // Out button click handler
                $('.out-btn').on('click', function() {
                    var row = $(this).closest('tr');
                    var outTime = row.find('.out-time').text().trim();
                    
                    // Convert time to 24-hour format if needed
                    var time24 = convertTo24Hour(outTime);
                    
                    // Set values in the Out form
                    $('#outForm #outtime').val(time24);
                    $('#outForm #note_out').val('2'); // Day End
                    
                    // Show the Out form and hide the In form if both are present
                    $('#outForm').show();
                    $('#inForm').hide();
                    $('.btn-success').show();
                });
            
            // Function to convert 12-hour time to 24-hour format
            function convertTo24Hour(time12h) {
                if(time12h === '-') return '';
                
                const [time, modifier] = time12h.split(' ');
                let [hours, minutes] = time.split(':');
                
                if (hours === '12') {
                    hours = '00';
                }
                
                if (modifier === 'PM') {
                    hours = parseInt(hours, 10) + 12;
                }
                
                return hours + ':' + minutes;
            }
        });

        // var indate = document.getElementById('indate');
        // indate.addEventListener('change', function () {
        //     var employeeId = document.getElementById('employee_id').value;
        //     var date = document.getElementById('indate').value;
        //     console.log(employeeId);
        //     window.location.href = window.location.origin + '/hrms-attendance?employee_id=' + employeeId + '&date='+date;
        // }, false);

        // var select = document.getElementById('employee_id');
        // select.addEventListener('change', function () {
        //     var employeeId = document.getElementById('employee_id').value;
        //     console.log(employeeId);
        //     window.location.href = window.location.origin + '/hrms-attendance?employee_id=' + employeeId;
        // }, false);
        // console.log("test")
      

    </script>
    <script src="../../../admin_dep/js/cbpFWTabs.js"></script>
    <script type="text/javascript">
        (function () {
            [].slice.call(document.querySelectorAll('.sttabs')).forEach(function (el) {
                new CBPFWTabs(el);
            });
        })();
    </script>
    <script src="../../../plugins/bower_components/dropify/dist/js/drsopify.min.js"></script>
    @include('includes.footer')