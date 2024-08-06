@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')
<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Leave Authorisation</h4>
            </div>
        </div>
        <div class="card">
            <div class="card-body">
                @if(session('success'))
                    <div class="alert alert-success alert-block">
                        <button type="button" class="close" data-dismiss="alert">×</button>
                        <strong>{{ session('success') }}</strong>
                    </div>
                @endif
                @php 
                    $leave_status = ['Approved_lwp','Cancelled','Rejected','Pending','Approved'];
                    $year = date('Y', strtotime($data['from_date_formatted']));
                @endphp
                <form action="{{ route('leave.authorisation.index') }}" enctype="multipart/form-data" method="post">
                @csrf
                    <div class="row">
                        <div class="col-md-4 form-group">
                            <label>From Date</label>
                            <div class="input-daterange input-group" id="date-range">
                                <input type="text" required class="form-control mydatepicker" placeholder="YYYY/MM/DD" name="from_date" id="from_date" value="{{ $data['from_date_formatted'] }}" autocomplete="off">
                                <span class="input-group-addon"><i class="icon-calender"></i></span>
                            </div>
                        </div>
                        <div class="col-md-4 form-group">
                            <label>End Date</label>
                            <div class="input-daterange input-group" id="date-range">
                                <input type="text" required class="form-control mydatepicker" placeholder="YYYY/MM/DD" name="to_date" id="to_date" value="{{ $data['to_date_formatted'] }}" autocomplete="off">
                                <span class="input-group-addon"><i class="icon-calender"></i></span>
                            </div>
                        </div>
                        <div class="col-md-4 form-group">
                            <label>Leave Status</label>
                            <select id='leave_status' name="leave_status[]" class="form-control" multiple required>
                                @foreach($leave_status as $leave)
                                    <option value="{{ $leave }}" @if(isset($data['get_leave_status']) && in_array($leave,$data['get_leave_status']) ) selected @elseif(isset($data['defaultSel']) && $data['defaultSel']==$leave) selected @endif>
                                        {{ $leave }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3 col-sm-offset-4 text-center form-group">
                            <input type="submit" name="submit" value="Search" class="btn btn-success">
                        </div>
                    </div>
                </form>
            </div>
        </div>
        @if(isset($data['get_employee_leave_lists']))
            <div class="card">
                <form action="{{ route('leave.authorisation.store') }}" method="post" onsubmit="return validateForm()">
                @csrf
                <div class="table-responsive mt-20 tz-report-table">
                    <table id="example" class="table table-striped">
                        <thead>
                            <tr>
                                <th><input type="checkbox" name="all" id="ckbCheckAll" class="ckbox">  </th>
                                <th>Sr No.</th>
                                <th>Applied Date</th>
                                <th>From Date</th>
                                <th>To Date</th>
                                <th>Employee Name</th>
                                <th>No of Days</th>
                                <th>Day Type</th>
                                <th>Leave Type</th>
                                <th>Reason</th>
                                <th>HOD's Comment</th>
                                <th>HOD's Comment Date</th>
                                <th>HR Remarks</th>
                                <th>HR Remark Date</th>
                                <th>Approved By</th>
                                <th class="text-left">Status</th>
                            </tr>
                        </thead>
                        @php
                        $j = 1;
                        $get_employee_leave_lists = $data['get_employee_leave_lists'];
                        @endphp
                        <tbody>
                            @foreach($get_employee_leave_lists as $key => $employee_leave_lists)
                            @php 
                                $from_date = \Carbon\Carbon::parse($employee_leave_lists->from_date);
                                $to_date = \Carbon\Carbon::parse($employee_leave_lists->to_date);
                                $dayType = $employee_leave_lists->day_type ?? 0;
                            @endphp
                                <tr>
                                    <td><input type="checkbox" name="checkedEmp[{{$employee_leave_lists->id}}]" class="ckbox1"></td>
                                    <td>{{ $j++ }}</td>
                                    <td>{{ \Carbon\Carbon::parse($employee_leave_lists->created_at)->format('d-M-Y') }}</td>
                                    <td>{{ \Carbon\Carbon::parse($employee_leave_lists->from_date)->format('d-M-Y') }}</td>
                                    <td>{{  \Carbon\Carbon::parse($employee_leave_lists->to_date)->format('d-M-Y') }}</td>
                                    <td><a style="text-decoration:underline !important" onclick="getEmpLeaveHistory('{{$employee_leave_lists->user_id}}','{{$employee_leave_lists->department_id}}')">{{ $employee_leave_lists->employee_name }}</a></td>
                                    <td>{{ App\Helpers\countDays($from_date,$to_date,$dayType) }}</td>
                                    <td>{{ ($employee_leave_lists->day_type=="0.5") ? 'Half Day' : 'Full Day' }}</td>
                                    <td>{{ $employee_leave_lists->leave_type }}</td>
                                    <td>
                                        {{ $employee_leave_lists->comment }}
                                    </td>
                                    <td>
                                        <input type="text" name="hod_comment[{{ $employee_leave_lists->id }}]" value="{{ $employee_leave_lists->hod_comment }}" style="width:150px;"></td>
                                    <td>{{ $employee_leave_lists->hod_comment_date ?? 'N/A' }}</td>
                                    <td>
                                        <input type="text" name="hr_remarks[{{ $employee_leave_lists->id }}]" value="{{ $employee_leave_lists->hr_remarks }}" style="width:150px;"></td>
                                    <td>{{ $employee_leave_lists->hr_remark_date ?? 'N/A' }}</td>
                                    <td>{{ $employee_leave_lists->approved_by }}</td>
                                    <td>
                                        <select id='single_leave_status' name="single_leave_status[{{ $employee_leave_lists->id }}]" class="form-control" style="width:140px;">
                                            @foreach($leave_status as $leave)
                                                <option value="{{ $leave }}" @if(isset($employee_leave_lists->status) && (lcfirst($leave) == $employee_leave_lists->status) ) selected @endif>
                                                    {{ $leave }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table><br>
                    <div class="col-md-3 col-sm-offset-4 text-center form-group">
                        <input type="submit" name="submit" value="Save" class="btn btn-success">
                    </div>
                </div>
                </form>
            </div>
        @endif
    </div>
</div>

<!-- end data modal  -->
@include('includes.footerJs')
<script>
    $(document).ready(function () {
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
                    title: 'Leave Authorization Report',
                    orientation: 'landscape',
                    pageSize: 'LEGAL',
                    pageSize: 'A0',
                    exportOptions: {
                        columns: ':visible'
                    },
                },
                {extend: 'csv', text: ' CSV', title: 'Leave Authorization Report'},
                {extend: 'excel', text: ' EXCEL', title: 'Leave Authorization Report'},
                {extend: 'print', text: ' PRINT', title: 'Leave Authorization Report'},
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

    // leave.summary.report.show
    function getEmpLeaveHistory(user_id,dep_id) {
        var year = "{{$year}}";
        window.open('leave-summary-report/create?department_id=' + dep_id + '&emp_id=' + user_id + '&years=' + year, '_blank', 'scrollbars=yes,resizable=no,status=no,location=no,toolbar=no,menubar=no,width=1200,height=1200,left=100,top=100');
    }

    function validateForm() {
        var checkboxes = document.querySelectorAll('input[name^="checkedEmp["]');
        var anyChecked = false;
        
        checkboxes.forEach(function(checkbox) {
            if (checkbox.checked) {
                anyChecked = true;
            }
        });
        
        if (!anyChecked) {
            alert("Please select at least one checkbox");
            return false; 
        }
        
        return true;
    }
    $(function () {
        var $tblChkBox = $("input:checkbox");
        $("#ckbCheckAll").on("click", function () {
            $($tblChkBox).prop('checked', $(this).prop('checked'));
        });
    });
</script>
@include('includes.footer')
