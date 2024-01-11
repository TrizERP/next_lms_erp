@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')
<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Employee Leave Reports</h4>
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
                <form action="{{route('leave.report.show')}}" enctype="multipart/form-data" method="post">
                @csrf
                    <div class="row">
                        <div class="col-md-3 form-group">
                            <label>Department List</label>
                            <select id='department_id' name="department_id" class="form-control" required>
                                <option value="">Select Department</option>
                                @foreach($departments as $id => $department)
                                    <option value="{{$id}}"
                                    @if(isset($department_id))
                                        @if($department_id == $id)
                                        selected='selected'
                                        @endif
                                    @endif
                                    >{{ $department }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3 form-group">
                            <label>Employee List</label>
                            <select id='employee_id' name="employee_id" class="form-control">
                                <option value="">Select Employee</option>
                                
                            </select>
                        </div>
                        <div class="col-md-3 form-group">
                            <label>From Date</label>
                            <div class="input-daterange input-group" id="date-range">
                                <input type="text" required class="form-control mydatepicker" placeholder="YYYY/MM/DD" name="from_date" id="from_date" value="{{ $from_date_formatted }}" autocomplete="off">
                                <span class="input-group-addon"><i class="icon-calender"></i></span>
                            </div>
                        </div>
                        <div class="col-md-3 form-group">
                            <label>End Date</label>
                            <div class="input-daterange input-group" id="date-range">
                                <input type="text" required class="form-control mydatepicker" placeholder="YYYY/MM/DD" name="to_date" id="to_date" value="{{ $to_date_formatted }}" autocomplete="off">
                                <span class="input-group-addon"><i class="icon-calender"></i></span>
                            </div>
                        </div>
                        <div class="col-md-3 form-group">
                            <label>Leave Status</label>
                            <select id='leave_status' name="leave_status[]" class="form-control" multiple required>
                                <option value="Approved LWP" @if(isset($get_leave_status) && in_array("Approved LWP",$get_leave_status) ) selected @endif>Approved LWP</option>
                                <option value="Cancelled" @if(isset($get_leave_status) && in_array("Cancelled",$get_leave_status) ) selected @endif>Cancelled</option>
                                <option value="Rejected" @if(isset($get_leave_status) && in_array("Rejected",$get_leave_status) ) selected @endif>Rejected</option>
                                <option value="Pending" @if(isset($get_leave_status) && in_array("Pending",$get_leave_status) ) selected @endif>Pending Approval</option>
                                <option value="Approved" @if(isset($get_leave_status) && in_array("Approved",$get_leave_status) ) selected @endif>Approved</option>
                            </select>
                        </div>
                        <div class="col-md-3 col-sm-offset-4 text-center form-group">
                            <input type="submit" name="submit" value="Search" class="btn btn-success">
                        </div>
                    </div>
                </form>
            </div>
        </div>
        @if(isset($get_employee_leave_lists))
            <div class="card">
                <div class="table-responsive mt-20 tz-report-table">
                    <table id="example" class="table table-striped">
                        <thead>
                        <tr>
                            <th>Sr No.</th>
                            <th>Applied Date</th>
                            <th>From Date</th>
                            <th>To Date</th>
                            <th>Employee Name</th>
                            <th>No of Days</th>
                            <th>Leave Type</th>
                            <th>Status</th>
                            <th>Approved By</th>
                            <th>Leave Period</th>
                            <th>Comments</th>
                            <th>HR Remarks</th>
                            <th class="text-left">Principal Comments</th>
                        </tr>
                        </thead>
                        @php
                        $j = 1;
                        @endphp
                        <tbody>
                            @foreach($get_employee_leave_lists as $get_employee_leave_list)
                                <tr>
                                    <td>{{ $j++ }}</td>
                                    <td>{{ \Carbon\Carbon::parse($get_employee_leave_list->created_at)->format('d-M-Y') }}</td>
                                    <td>{{ \Carbon\Carbon::parse($get_employee_leave_list->from_date)->format('d-M-Y') }}</td>
                                    <td>{{ \Carbon\Carbon::parse($get_employee_leave_list->to_date)->format('d-M-Y') }}</td>
                                    <td>{{ $get_employee_leave_list->employee_name }}</td>
                                    <td>{{ $get_employee_leave_list->day_type }}</td>
                                    <td>{{ $get_employee_leave_list->leave_type }}</td>
                                    <td>{{ $get_employee_leave_list->status }}</td>
                                    <td>{{ $get_employee_leave_list->approved_by }}</td>
                                    <td></td>
                                    <td>{{ $get_employee_leave_list->comment }}</td>
                                    <td>{{ $get_employee_leave_list->hr_remarks }}</td>
                                    <td>{{ $get_employee_leave_list->hod_comment }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>
</div>

@include('includes.footerJs')

<script>
    $(document).ready(function () {
        var table = $('#example').DataTable({
            ordering: false,
            select: true,
            lengthMenu: [
                [100, 500, 1000, -1],
                ->100', '500', '1000', 'Show All
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
        
        // Ajax call to get employees based on the selected department
        $(document).on("change", "#department_id", function(e) {
            $('#employee_id').empty();
            var departmentId = $(this).val();
            
            $.ajax({
                type: "post",
                url: "{{ route('get-emp-list') }}",
                data: { department_id: departmentId },
                success: function(data) {
                    var options = '<option value="">Select Employee</option>';
                    $.each(data.employees, function(index, employee) {
                        // var selected = (employee.id == {{ json_encode($employee_id) }}) ? 'selected' : '';
                        options += '<option value="' + employee.id + '" >' + employee.first_name + ' ' + employee.last_name + '</option>';
                    });
                    $('#employee_id').html(options);
                },
                error: function(xhr) {
                    console.error(xhr.responseText);
                }
            });
        });
    });
</script>
@include('includes.footer')
