@include('includes.headcss')
@include('includes.header')
<style>
    .status-circle {
        display: inline-block;
        width: 20px;
        height: 20px;
        border-radius: 50%;
        margin-right: 0px;
        margin-bottom: -5px;
        font
    }

    .absent { background-color: #FFB2B2; }
    .latecomer { background-color: orange; }
    .halfday { background-color: yellow; }
    .ondutyleave { background-color: #9191c7; }
    .weekend { background-color: #99D699; }
    .holiday { background-color: #4591e0; }
    .punchsame { background-color: red; }
</style>
@include('includes.sideNavigation')
<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Hrms Attendance Reports</h4>
            </div>
        </div>
        <div class="card">
            <div class="card-body">
                @if ($sessionData = Session::get('data'))
                    @if($sessionData->status_code == 1)
                        <div class="alert alert-success alert-block">
                    @else
                        <div class="alert alert-danger alert-block">
                    @endif
                        <button type="button" class="close" data-dismiss="alert">×</button>
                        <strong>{{ $sessionData->message }}</strong>
                    </div>
                @endif
                <form action="{{route('hrms.show_hrms_attendance_report')}}" enctype="multipart/form-data" method="post">
                @csrf
                    <div class="row">
                    @php 
                                    $dep_id = $emp_id = '';
                                    if(isset($data['selDept'])){
                                        $dep_id = $data['selDept'];
                                    }

                                    if(isset($data['selEmp'])){
                                        $emp_id = $data['selEmp'];
                                    }
                                @endphp

                                {!! App\Helpers\HrmsDepartments("","multiple",$dep_id,"",$emp_id,"") !!}
                        <div class="col-md-3 form-group">
                            <label>From Date</label>
                            <div class="input-daterange input-group" id="date-range">
                                <input type="text" required class="form-control mydatepicker" placeholder="YYYY/MM/DD" name="from_date" id="from_date" value="{{ $data['from_date_formatted'] }}" autocomplete="off">
                                <span class="input-group-addon"><i class="icon-calender"></i></span>
                            </div>
                        </div>
                        <div class="col-md-3 form-group">
                            <label>End Date</label>
                            <div class="input-daterange input-group" id="date-range">
                                <input type="text" required class="form-control mydatepicker" placeholder="YYYY/MM/DD" name="to_date" id="to_date" value="{{ $data['to_date_formatted'] }}" autocomplete="off">
                                <span class="input-group-addon"><i class="icon-calender"></i></span>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-offset-4 text-center form-group">
                            <input type="submit" name="submit" value="Search" class="btn btn-success" onclick="checkEmp()">
                        </div>
                    </div>
                </form>
            </div>
        </div>
        @if(isset($data['report_data']))
            <div class="card">
                <div class="col-lg-12 col-md-4 col-sm-4 col-xs-12 page-title">
                    Colours Description =>
                    Absent: <span class="status-circle absent"></span> 
                    Latecomer: <span class="status-circle latecomer"></span> 
                    HalfDay: <span class="status-circle halfday"></span> 
                    On Duty Leave: <span class="status-circle ondutyleave"></span> 
                    Weekend: <span class="status-circle weekend"></span> 
                    Holiday: <span class="status-circle holiday"></span> 
                    Punch-in and Punch-out time are same: <span class="status-circle punchsame"></span> 
                </div><br>
                <div class="table-responsive mt-20 tz-report-table">
                    <table id="example" class="table table-striped">
                        <thead>
                        <tr>
                            <th>Sr No.</th>
                            <th>Date</th>
                            <th>Emp No</th>
                            <th>Employee Name</th>
                            <th>In Time</th>
                            <th>Out Time</th>
                            <th class="text-left">Duration</th>
                        </tr>
                        </thead>
                        @php
                        $j = 1;
                        $holidays = [];
                        $cl_leave = [];
                        $on_duty_leave = [];

                        if(isset($data['report_data']))
                        {
                            $report_data = $data['report_data'];
                        }

                       @endphp
                        <form action="{{route('payroll.store_monthly_payroll_report')}}" method="post">
                            @csrf
                            <tbody>
                                @foreach($report_data as $date => $hrmsAttendance)
                                    @php
                                        $att_status='';
                                        $get_format_punchin_time='';
                                        $day_name='';

                                        if (isset($hrmsAttendance[0]) && !empty($hrmsAttendance[0])) 
                                        {
                                            $hrmsAttendance = $hrmsAttendance[0];

                                            $get_format_punchin_time = \Carbon\Carbon::parse($hrmsAttendance->punchin_time)->format('H:i:s');

                                            $get_format_punchout_time = \Carbon\Carbon::parse($hrmsAttendance->punchout_time)->format('H:i:s');

                                            if ($get_format_punchin_time == $get_format_punchout_time) 
                                            {
                                                $att_status = 'background-color: red;';
                                            }
                                            else if ($hrmsAttendance->timestamp_diff <= "04:00:00") 
                                            {
                                                $att_status = 'background-color: yellow;';
                                            }
                                            else if ($hrmsAttendance->monday_in_date < $get_format_punchin_time || $hrmsAttendance->tuesday_in_date < $get_format_punchin_time || $hrmsAttendance->wednesday_in_date < $get_format_punchin_time || $hrmsAttendance->thursday_in_date < $get_format_punchin_time || $hrmsAttendance->friday_in_date < $get_format_punchin_time || $hrmsAttendance->saturday_in_date < $get_format_punchin_time)
                                            {
                                                $att_status = 'background-color: orange;';
                                            }
                                             
                                        }
                                        else if(isset($hrmsAttendance['leave'][0]) && !empty($hrmsAttendance['leave'][0]))
                                        {
                                            $leaveData = $hrmsAttendance['leave'][0];

                                            $from_date_cl = $leaveData->from_date;
                                            $to_date_cl = $leaveData->to_date;

                                            $from_date_on_duty_leave = $leaveData->from_date;
                                            $to_date_on_duty_leave = $leaveData->to_date;

                                            if ($leaveData->leave_type_id == 'LTY001' || $leaveData->leave_type_id == 'LTY002' || $leaveData->leave_type_id == 'LTY003' || $leaveData->leave_type_id == 'LTY005' || $leaveData->leave_type_id == 'LTY006') 
                                            {
                                                while (strtotime($from_date_cl) <= strtotime($to_date_cl)) 
                                                {
                                                    $cl_leave[] = $from_date_cl;
                                                    $from_date_cl = date("Y-m-d", strtotime("+1 day", strtotime($from_date_cl)));
                                                }
                                            } 
                                            else if ($leaveData->leave_type_id == 'LTY004') 
                                            {
                                                while (strtotime($from_date_on_duty_leave) <= strtotime($to_date_on_duty_leave)) 
                                                {
                                                    $on_duty_leave[] = $from_date_on_duty_leave;
                                                    $from_date_on_duty_leave = date("Y-m-d", strtotime("+1 day", strtotime($from_date_on_duty_leave)));
                                                }
                                            }
                                        }
                                        else if(isset($hrmsAttendance['holiday'][0]) && !empty($hrmsAttendance['holiday'][0]))
                                        {
                                            $holidayData = $hrmsAttendance['holiday'][0];
                                        
                                            $from_date_new = $holidayData->from_date;
                                            $to_date_new = $holidayData->to_date;

                                            while (strtotime($from_date_new) <= strtotime($to_date_new)) 
                                            {
                                                $holidays[] = $from_date_new;
                                                $from_date_new = date("Y-m-d", strtotime("+1 day", strtotime($from_date_new)));
                                            }
                                        }
                                        
                                        if (in_array($date, $holidays)) 
                                        {
                                            $att_status = 'background-color:#4591e0;';
                                        }
                                        if(in_array($date, $cl_leave)) 
                                        {
                                            $att_status = 'background-color:#FFB2B2;';
                                        }
                                        if(in_array($date, $on_duty_leave)) 
                                        {
                                            $att_status = 'background-color:#9191c7;';
                                        }

                                        $hrms_date = \Carbon\Carbon::createFromFormat('Y-m-d', $date);
                                        $day_name =lcfirst($hrms_date->format('l'));

                                        if($day_name ==  "sunday")
                                        {
                                            $att_status = 'background-color:#99D699;';
                                        }
                                    @endphp

                                    <tr style="{{ $att_status }}">
                                        <td>{{ $j++ }}</td>
                                        <td>{{date('d-m-Y',strtotime($date))}}</td>
                                        <td>{{ isset($hrmsAttendance->employee_no) ? $hrmsAttendance->employee_no : '' }}</td>
                                        <td>{{ isset($hrmsAttendance->employee_name) ? $hrmsAttendance->employee_name : '' }}</td>
                                        <td>{{ isset($hrmsAttendance->punchin_time) ? \Carbon\Carbon::parse($hrmsAttendance->punchin_time)->format('h:i A') : '-' }}</td>
                                        <td>{{ isset($hrmsAttendance->punchout_time) ? \Carbon\Carbon::parse($hrmsAttendance->punchout_time)->format('h:i A') : '-' }}</td>
                                        <td>{{ isset($hrmsAttendance->timestamp_diff) ? \Carbon\Carbon::parse($hrmsAttendance->timestamp_diff)->format('H:i') : '-' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </form>
                    </table>
                </div>
            </div>
        @endif
    </div>
</div>

@include('includes.footerJs')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/jquery-toast-plugin/1.3.2/jquery.toast.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-toast-plugin/1.3.2/jquery.toast.min.js"></script>
<script type="text/javascript">
    $(document).ready(function() {
        $('[data-toggle="tooltip"]').tooltip();

    });

    $(document).ready(function () {
        var table = $('#example').DataTable({
            ordering: false,
            select: true,
            lengthMenu: [
                [100, 500, 1000, -1],
                ['100', '500', '1000', 'Show All']
            ],
            dom: 'Bfrtip',
            buttons: [
                {
                    extend: 'pdfHtml5',
                    title: 'Student Report',
                    orientation: 'landscape',
                    pageSize: 'LEGAL',
                    pageSize: 'A0',
                    exportOptions: {
                        columns: ':visible'
                    },
                },
                {extend: 'csv', text: ' CSV', title: 'Student Report'},
                {extend: 'excel', text: ' EXCEL', title: 'Student Report'},
                {extend: 'print', text: ' PRINT', title: 'Student Report'},
                'pageLength'
            ],
        });
        //table.buttons().container().appendTo('#example_wrapper .col-md-6:eq(0)');

        $('#example thead tr').clone(true).appendTo('#example thead');
        $('#example thead tr:eq(1) th').each(function (i) {
            var title = $(this).text();
            $(this).html('<input type="text" placeholder="Search ' + title + '" />');

            $('input', this).on('keyup change', function () {
                if (table.column(i).search() !== this.value) {
                    table
                        .column(i)
                        .search(this.value)
                        .draw();
                }
            });
        });
    });
</script>
<script>
    function checkEmp(){
        var emp_id  = $('#emp_id').val();
        // alert(emp_id);
        if(emp_id===0){
            $('#emp_id').val('');
            alert('Please Select Atleast 1 Employee');
            return false;
        }else{
            return true;
        }
    }
$(document).ready(function () {
    // Ajax call to get employees based on the selected department
    $(document).on("change", "#department_id", function(e) {
        $('#employee_id').empty();
        var departmentId = $(this).val();
        
        $.ajax({
            type: "post",
            url: "{{ route('get.employees.list') }}",
            data: { department_id: departmentId },
            success: function(data) {
                var options = '';
                $.each(data.employees, function(index, employee) {
                    
                    options += '<option value="' + employee.id + '" >' + employee.first_name + ' ' + employee.last_name + '</option>';
                });
                $('#employee_id').append(options);
            },
            error: function(xhr) {
                console.error(xhr.responseText);
            }
        });
    });
});
</script>
@include('includes.footer')
