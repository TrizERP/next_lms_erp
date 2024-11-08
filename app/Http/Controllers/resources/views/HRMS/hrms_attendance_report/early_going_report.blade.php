@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')
<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Early Going Attendance Reports</h4>
            </div>
        </div>
        <div class="card">
            <div class="card-body">
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
                    <form action="{{route('hrms.show_early_going_hrms_attendance_report')}}"
                          enctype="multipart/form-data"
                          method="post" class="row">
                        @csrf
                        @php 
                            $dep_id = $emp_id = '';
                            if(isset($data['department_id'])){
                                $dep_id = $data['department_id'];
                            }

                            if(isset($data['selEmp'])){
                                $emp_id = $data['selEmp'];
                            }
                            $date = now();
                            if(isset($data['date_formatted'])){
                                $date = $data['date_formatted'];
                            }
                        @endphp

                        {!! App\Helpers\HrmsDepartments("","multiple",$dep_id,"multiple",$emp_id,"") !!}
                        <div class="col-md-3 form-group">
                            <label>Date</label>
                            <div class="input-daterange input-group" id="date-range">
                                <input type="text" required class="form-control mydatepicker" placeholder="YYYY/MM/DD" name="date" id="date" value="{{ $date }}" autocomplete="off" required>
                                <span class="input-group-addon"><i class="icon-calender"></i></span>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-offset-4 text-center form-group">
                            <input type="submit" name="submit" value="Search" class="btn btn-success">
                        </div>
                    </div>
                </form>
            </div>
        </div>
        @if(isset($data['hrmsList']))
            <div class="card">
                <div class="table-responsive mt-20 tz-report-table">
                    <table id="example" class="table table-striped">
                        <thead>
                        <tr style="text-align:center;">
                            <th>Sr No.</th>
                            <th>Emp No</th>
                            <th>Employee Name</th>
                            <th>Department Name</th>
                            <th>Out Time</th>
                            <th style="text-align:center;">Expected Out Time</th>
                        </tr>
                        </thead>
                        <?php
                        $j = 1;
                        if(isset($data['hrmsList']))
                        {
                            $hrmsList = $data['hrmsList'];
                        }
                        ?>
                        <form method="post">
                            @csrf
                            <tbody>
                            @foreach($hrmsList as $hrmsAttendance)
                                @php 
                                    $get_hrms_department = DB::table('hrms_departments')
                                    ->where('sub_institute_id',session()->get('sub_institute_id'))
                                    ->where('id', $hrmsAttendance['getUser']['department_id'])
                                    ->first();
                                @endphp
                                <tr style="text-align:center;">
                                    <td>{{$j++}}</td>
                                    <td>{{ isset($hrmsAttendance['getUser']) ? $hrmsAttendance['getUser']['employee_no'] : '' }}</td>
                                    <td>{{isset($hrmsAttendance['getUser']) ? $hrmsAttendance['getUser']['first_name'] .'-'.$hrmsAttendance['getUser']['last_name'] : ''}}</td>
                                    <td>{{ isset($get_hrms_department->department) ? $get_hrms_department->department : '-' }}</td>
                                    <td>{{ isset($hrmsAttendance->punchout_time) ? \Carbon\Carbon::parse($hrmsAttendance->punchout_time)->format('h:i A') : '-' }}</td>
                                    <td>{{ isset($hrmsAttendance['getUser']['monday_out_date']) ? \Carbon\Carbon::parse($hrmsAttendance['getUser']['monday_out_date'])->format('h:i A') : '-' }}</td>
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
</script>
@include('includes.footer')
