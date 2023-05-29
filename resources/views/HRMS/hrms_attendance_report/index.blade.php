@include('includes.headcss')
@include('includes.header')
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
                    @if($sessionData['status_code'] == 1)
                        <div class="alert alert-success alert-block">
                            @else
                                <div class="alert alert-danger alert-block">
                                    @endif
                                    <button type="button" class="close" data-dismiss="alert">×</button>
                                    <strong>{{ $sessionData['message'] }}</strong>
                                </div>
                            @endif
                            <form action="{{route('hrms.show_hrms_attendance_report')}}"
                                  enctype="multipart/form-data"
                                  method="post">
                                @csrf
                                <div class="col-md-3 form-group">
                                    <label>Employee List</label>

                                    <select id='employee_id' name="employee_id" class="form-control">
                                        <option value="0">Select Employee</option>
                                        @foreach($employeeLists as $key => $employeeList)

                                            @if(is_array($employees) && count($employees) == 1 && $employees[0]['id'] == $employeeList->id)
                                                <option
                                                    value="{{$employeeList->id}}"
                                                    selected>{{$employeeList->first_name .' '. $employeeList->last_name }}</option>
                                            @else
                                                <option
                                                    value="{{$employeeList->id}}">{{$employeeList->first_name .' '. $employeeList->last_name }}</option>
                                            @endif
                                        @endforeach

                                    </select>
                                </div>
                                <div class="col-md-3 form-group">
                                    <label>From Date</label>
                                    <input type="date" name="from_date" class="form-control"
                                           value="{{ date('Y-m-d',strtotime($from_date)) }}">
                                </div>
                                <div class="col-md-3 form-group">
                                    <label>To Date</label>
                                    <input type="date" name="end_date" class="form-control"
                                           value="{{ date('Y-m-d',strtotime($end_date)) }}">
                                </div>
                                <div class="col-md-3 col-sm-offset-4 text-center form-group">
                                    <input type="submit" name="submit" value="Search" class="btn btn-success">
                                </div>
                        </div>
                        <!-- Modal -->
                        <div class="modal fade bd-example-modal-lg" id="exampleModal" tabindex="-1"
                             role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
                            <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title" id="exampleModalLabel">Choose Field</h5>
                                        <button type="button" class="close" data-dismiss="modal"
                                                aria-label="Close">
                                            <span aria-hidden="true">x</span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        </form>
            </div>
        </div>

        <div class="card">
            <div class="table-responsive mt-20 tz-report-table">
                <table id="example" class="table table-striped">
                    <thead>
                    <tr>
                        <th>Sr No.</th>
                        <th>Employee Id</th>
                        <th>Employee Name</th>
                        <th>In Time</th>
                        <th>Out Time</th>
                        <th>Duration</th>
                    </tr>
                    </thead>
                    <?php
                    $j = 1;
                    ?>
                    <form action="{{route('payroll.store_monthly_payroll_report')}}" method="post">
                        @csrf
                        <tbody>
                        @foreach($hrmsList as $hrmsAttendance)
                            <tr>
                                <td>{{$j++}}</td>
                                <td>{{$hrmsAttendance['user_id']}}</td>
                                <td>{{$hrmsAttendance->getUser['first_name'] .' '. $hrmsAttendance->getUser['last_name']}}</td>
                                <td>{{  date('h:i',strtotime($hrmsAttendance['punchin_time']))}}</td>
                                <td>{{date('h:i',strtotime($hrmsAttendance['punchout_time']))}}</td>
                                <td>{{$hrmsAttendance['timestamp_diff']}}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </form>
                </table>
            </div>
        </div>
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
@include('includes.footer')
