@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')
<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Department Wise Attendance Report</h4>
            </div>
        </div>
        @php 
            $fromdate = isset($data['selectedFromDate']) ? $data['selectedFromDate'] : $data['start_date'];
            $todate =isset($data['selectedToDate']) ? $data['selectedToDate'] : $data['end_date'];
            $dep_id = $emp_id = "";
            if(isset($data['selDepartments'])){
                $dep_id = $data['selDepartments'];
            }
            if(isset($data['emp_id'])){
                $emp_id = $data['emp_id'];
            }
        @endphp
        <div class="card">
        @if ($sessionData = Session::get('data')) @if($sessionData['status_code'] == 1)
        <div class="alert alert-success alert-block">
            @else
            <div class="alert alert-danger alert-block">
                @endif
                <button type="button" class="close" data-dismiss="alert">×</button>
                <strong>{{ $sessionData['message'] }}</strong>
            </div>
            @endif
           
            <form action="{{route('department_attendance_report.create')}}" method="get" class="row">

                <div class="col-md-3 form-group">
                    <label>From Date</label>
                    <div class="input-daterange input-group" id="date-range">
                        <input type="text" required class="form-control mydatepicker" name="from_date" id="from_date" value="{{$fromdate}}" autocomplete="off">
                        <span class="input-group-addon"><i class="icon-calender"></i></span>
                    </div>
                </div>

                <div class="col-md-3 form-group">
                    <label>To Date</label>
                    <div class="input-daterange input-group" id="date-range">
                        <input type="text" required class="form-control mydatepicker" name="to_date" id="to_date" value="{{$todate}}" autocomplete="off">
                        <span class="input-group-addon"><i class="icon-calender"></i></span>
                    </div>
                </div>
                {!! App\Helpers\HrmsDepartments("","multiple",$dep_id,"",$emp_id,"") !!}
                
                <div class="col-md-12 form-group">
                    <center>
                        <input type="submit" value="Search" class="btn btn-primary">
                    </center>
                </div>
            </form>

        @if(isset($data['empData']) &&!empty($data['empData']))
            <div class="table-responsive mt-20 tz-report-table">
                <table id="example" class="table table-striped">
                    <thead>
                    <tr>
                        <th>Sr No.</th>
                        <th>Emp Code</th>
                        <th>Department Name</th>
                        <th>Employee Name</th>
                        <th>Total Days</th>
                        <th>Week off</th>
                        <th>Holiday</th>
                        <th>Total Working Days</th>
                        <th>Total Present Days</th>
                        <th>Absent Days</th>
                        <th>Half Days</th>
                        <th class="text-left">Late Comes</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($data['empData'] as $key=>$value)
                        @php 
                            $abDays = ($value->total_ab_day);
                            $holidays = $value->holidays ?? 0;
                            $removedDays = ($value->total_att_day+$abDays+$holidays);
                            $lateArr = !empty($value->lateAtt)  ? implode(',',$value->lateAtt) : '';
                        @endphp
                        <tr>
                            <td>{{$key+1}}</td>
                            <td>{{$value->employee_no}}</td>
                            <td>{{$value->department}}</td>
                            <td>{{$value->full_name}}</td>
                            <td>@if($value->totalDays != 0) <a class="text-body" style="text-decoration:underline !important" onclick="getDetails('{{$value->user_id}}','totalDays')">{{$value->totalDays}}</a> @else 0 @endif</td>

                            <td>@if($value->weekday_off != 0) <a class="text-body" style="text-decoration:underline !important" onclick="getDetails('{{$value->user_id}}','weekday_off')">{{$value->weekday_off}}</a> @else 0 @endif</td>

                            <td>@if($holidays != 0) <a class="text-body" style="text-decoration:underline !important" onclick="getDetails('{{$value->department_id}}','holidays')">{{$holidays}}</a> @else 0 @endif</td>

                            <td>@if($value->workingDays != 0) <a class="text-body" style="text-decoration:underline !important" onclick="getDetails('{{$value->user_id}}','workingDays')">{{$value->workingDays}}</a> @else 0 @endif</td>

                            <td>@if($value->total_att_day != 0) <a class="text-body" style="text-decoration:underline !important" onclick="getDetails('{{$value->user_id}}','total_att_day')">{{$value->total_att_day}}</a> @else 0 @endif</td>

                            <td>@if($abDays != 0) <a class="text-body" style="text-decoration:underline !important" onclick="getDetails('{{$value->user_id}}','absent_days')">{{$abDays}}</a> @else 0 @endif</td>

                            <td>@if($value->half_day != 0) <a class="text-body" style="text-decoration:underline !important" onclick="getDetails('{{$value->user_id}}','half_day')">{{$value->half_day}}</a> @else 0 @endif</td>

                            <td>@if($value->late != 0) <a class="text-body" style="text-decoration:underline !important" onclick="getDetails('{{$value->user_id}}','late','{!! $lateArr !!}')">{{$value->late}}</a> @else 0 @endif</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
        </div>
    </div>

    <!-- total Modal -->
    <div class="modal fade" id="totalDaysModel" tabindex="-1" role="dialog" aria-labelledby="dateRangeModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document" style="max-width:1000px !important">
        <div class="modal-content">
        <div class="modal-header">
            <h5 class="modal-title" id="dateRangeModalLabel"></h5>
            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
            </button>
        </div>
        <div class="modal-body" id="dateRangeInfo">
            <!-- Date and day name information will be displayed here -->
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
        </div>
        </div>
    </div>
    </div>

    @include('includes.footerJs')
    <script>
        $(document).ready(function () {
            $('#totalDaysModel').hide();
            var table = $('#example').DataTable({
                select: true,
                lengthMenu: [
                    [100, 500, 1000, -1],
                    ['100', '500', '1000', 'Show All']
                ],
                dom: 'Bfrtip',
                buttons: [
                    {
                        extend: 'pdfHtml5',
                        title: 'Department Wise Attendance Report',
                        orientation: 'landscape',
                        pageSize: 'LEGAL',
                        pageSize: 'A0',
                        exportOptions: {
                            columns: ':visible'
                        },
                    },
                    {extend: 'csv', text: ' CSV', title: 'Department Wise Attendance Report'},
                    {extend: 'excel', text: ' EXCEL', title: 'Department Wise Attendance Report'},
                    {extend: 'print', text: ' PRINT', title: 'Department Wise Attendance Report'},
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

    function getDetails(user_id,DayDetails,AttIdArr='') {
            var fromDate = new Date("{{$fromdate}}");
            var toDate = new Date("{{$todate}}");
            var modalContent = '<table class="table table-bordered">';
            var i = 1;
            // total attendance 
            if (DayDetails=="totalDays") {
                modalContent += "<tr><th>Sr</th><th>Date</th><th>Days</th></tr>";
                while (fromDate <= toDate) {

                    modalContent += '<tr><td>' + (i++) + '</td><td>' + formatDate(fromDate) + '</td><td>' + getDayName(fromDate.getDay()) + '</td></tr>';
                    fromDate.setDate(fromDate.getDate() + 1);
                }
                modalContent += '</table>';
                $('#dateRangeModalLabel').text('Total Days');
                $('#dateRangeInfo').html(modalContent);
                $('#totalDaysModel').modal('show');
            }
            // week off days 
            else if (DayDetails=="weekday_off") {
                modalContent += "<tr><th>Sr</th><th>Date</th><th>Days</th></tr>";
                while (fromDate <= toDate) {
                    if (fromDate.getDay() === 0) { 
                        modalContent += '<tr><td>' + (i++) + '</td><td>' + formatDate(fromDate) + '</td><td>' + getDayName(fromDate.getDay()) + '</td></tr>';
                    }
                    fromDate.setDate(fromDate.getDate() + 1);
                }
                $('#dateRangeModalLabel').text('Week Days Off');
                $('#dateRangeInfo').html(modalContent);
                $('#totalDaysModel').modal('show');
            }
            // working days
            else if (DayDetails=="workingDays") {
                modalContent += "<tr><th>Sr</th><th>Date</th><th>Days</th></tr>";
                while (fromDate <= toDate) {
                    if (fromDate.getDay() !== 0) { 
                        modalContent += '<tr><td>' + (i++) + '</td><td>' + formatDate(fromDate) + '</td><td>' + getDayName(fromDate.getDay()) + '</td></tr>';
                    }
                    fromDate.setDate(fromDate.getDate() + 1);
                }
                $('#dateRangeModalLabel').text('Total Working Days');
                $('#dateRangeInfo').html(modalContent);
                $('#totalDaysModel').modal('show');
            }
            // late comes 
            else if(DayDetails=='late'){
                $.ajax({
                    url : '{{route("attendance_by_id")}}',
                    type: 'Get',
                    data : {attId:AttIdArr},
                    success : function(result){
                        if (Array.isArray(result)) {
                            modalContent += "<tr><th>Sr No.</th><th>Emp Code.</th><th>Emp Name</th><th>Date</th><th>Day</th><th>Punch-In</th><th>Punch-Out</th></tr>"
                            var i =1;
                            result.forEach(value=>{
                                var fdate = new Date(value.day);
                                var punchin = getTimeFromTimestamp(value.punchin_time);
                                var punchout = getTimeFromTimestamp(value.punchout_time);

                                modalContent +='<tr><td>'+(i++)+'</td><td>'+value.employee_no+'</td><td>'+value.full_name+'</td><td>' + formatDate(fdate) + '</td><td>' + getDayName(fdate.getDay()) + '</td><td>'+punchin+'</td><td>'+punchout+'</td></tr>';
                            });
                            modalContent += '</table>';
                            $('#dateRangeModalLabel').text('Late Come Days');
                            $('#dateRangeInfo').html(modalContent);
                            $('#totalDaysModel').modal('show');     
                        }else{
                            console.log('empty response');        
                        }                
                    },
                    error: function(xhr, status, error) {
                        console.error('Error:', error);
                    }
                })
            }
            
                var fromDate2 ="{{$fromdate}}";
                var toDate2 = "{{$todate}}";
                // holidays 
                 if (DayDetails=="holidays") {
                    var url = "/get-holidays?department_id="+user_id+'&from_date='+fromDate2+'&to_date='+toDate2;
                    $('#dateRangeModalLabel').text('Holidays');
                 }
                 if (DayDetails=="total_att_day") {
                    var url = "/get-present-days?user_id="+user_id+'&from_date='+fromDate2+'&to_date='+toDate2;
                        $.ajax({
                            url : url,
                            type: 'Get',
                            success : function(result){
                                if (Array.isArray(result)) {
                                    modalContent += "<tr><th>Sr No.</th><th>Emp Code.</th><th>Emp Name</th><th>Date</th><th>Day</th><th>Punch-In</th><th>Punch-Out</th></tr>"
                                    var i =1;
                                    result.forEach(value=>{
                                        var fdate = new Date(value.day);
                                        var punchin = getTimeFromTimestamp(value.punchin_time);
                                        var punchout = getTimeFromTimestamp(value.punchout_time);

                                        modalContent +='<tr><td>'+(i++)+'</td><td>'+value.employee_no+'</td><td>'+value.full_name+'</td><td>' + formatDate(fdate) + '</td><td>' + getDayName(fdate.getDay()) + '</td><td>'+punchin+'</td><td>'+punchout+'</td></tr>';
                                    });
                                    modalContent += '</table>';
                                    $('#dateRangeModalLabel').text('Total Attendance Days');
                                    $('#dateRangeInfo').html(modalContent);
                                    $('#totalDaysModel').modal('show');
                                }
                                
                            },
                            error: function(xhr, status, error) {
                                console.error('Error:', error);
                            }
                        })
                    }
                    
                //  absent days 
                 if (DayDetails=="absent_days") {
                    var url = "/get-absent-days?user_id="+user_id+'&from_date='+fromDate2+'&to_date='+toDate2;
                    $.ajax({
                            url : url,
                            type: 'Get',
                            success : function(result){
                                if (Array.isArray(result)) {
                                    modalContent += "<tr><th>Sr No.</th><th>Emp Code.</th><th>Emp Name</th><th>Department</th><th>Leave Day Type</th><th>Leave Type</th><th>Date</th><th>Day</th></tr>"
                                    var i =1;
                                    result.forEach(value=>{
                                        var fdate = new Date(value.from_date);
                                        modalContent +='<tr><td>'+(i++)+'</td><td>'+value.employee_no+'</td><td>'+value.full_name+'</td><td>'+value.department+'</td><td>'+value.day_type+'</td><td>'+value.leave_type+'</td><td>' + formatDate(fdate) + '</td><td>' + getDayName(fdate.getDay()) + '</td></tr>';
                                    });
                                    modalContent += '</table>';
                                    $('#dateRangeModalLabel').text('Absent Days');
                                    $('#dateRangeInfo').html(modalContent);
                                    $('#totalDaysModel').modal('show');
                                }
                                
                            },
                            error: function(xhr, status, error) {
                                console.error('Error:', error);
                            }
                        })
                 }

                 if (DayDetails=="half_day") {
                    var url = "/get-half-day?user_id="+user_id+'&from_date='+fromDate2+'&to_date='+toDate2;
                    $.ajax({
                            url : url,
                            type: 'Get',
                            success : function(result){
                                if (Array.isArray(result)) {
                                    modalContent += "<tr><th>Sr No.</th><th>Emp Code.</th><th>Emp Name</th><th>Leave Day Type</th><th>Leave Type</th><th>Date</th><th>Day</th><th>Punch-In</th><th>Punch-Out</th></tr>"
                                    var i =1;
                                    result.forEach(value=>{
                                        var fdate = new Date(value.day);
                                        var punchin = getTimeFromTimestamp(value.punchin_time);
                                        var punchout = getTimeFromTimestamp(value.punchout_time);

                                        modalContent +='<tr><td>'+(i++)+'</td><td>'+value.employee_no+'</td><td>'+value.full_name+'</td><td>'+value.day_type+'</td><td>'+value.leave_type+'</td><td>' + formatDate(fdate) + '</td><td>' + getDayName(fdate.getDay()) + '</td><td>'+punchin+'</td><td>'+punchout+'</td></tr>';
                                    });
                                    modalContent += '</table>';
                                    $('#dateRangeModalLabel').text('Half Days');
                                    $('#dateRangeInfo').html(modalContent);
                                    $('#totalDaysModel').modal('show');
                                }
                                
                            },
                            error: function(xhr, status, error) {
                                console.error('Error:', error);
                            }
                        })
                 }
        }
    
    function formatDate(date) {
        var day = date.getDate();
        var month = date.getMonth() + 1;
        var year = date.getFullYear();
        return (day < 10 ? '0' : '') + day + '-' + (month < 10 ? '0' : '') + month + '-' + year;
    }

    function getDayName(dayIndex) {
        var days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        return days[dayIndex];
    }
    function getTimeFromTimestamp(timestamp) {
        if (!timestamp) {
            return "-";
        }

        const date = new Date(timestamp);
        let day = date.getDate();
        let month = date.getMonth() + 1; // Months are zero-based
        const year = date.getFullYear();
        let hours = date.getHours();
        const minutes = date.getMinutes();
        const ampm = hours >= 12 ? 'PM' : 'AM';

        // Ensure day and month are always two digits
        day = day < 10 ? '0' + day : day;
        month = month < 10 ? '0' + month : month;

        hours = hours % 12;
        hours = hours ? hours : 12; // the hour '0' should be '12'
        const minutesFormatted = minutes < 10 ? '0' + minutes : minutes;

        return `${day}-${month}-${year} ${hours}:${minutesFormatted} ${ampm}`;
    }
    </script>
@include('includes.footer')
