@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')
<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Employee Leave Summary Reports</h4>
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
                        <button type="button" class="close" data-dismiss="alert">Ã—</button>
                        <strong>{{ $sessionData->message }}</strong>
                    </div>
                @endif
                <form action="{{route('leave.summary.report.show')}}" enctype="multipart/form-data" method="post">
                @csrf
                    <div class="row">
                        @php
                            $dep_id = $emp_id = '';
                            if(isset($data['department_id'])){
                                $dep_id = $data['department_id'];
                            }

                            if(isset($data['employee_id'])){
                                $emp_id = $data['employee_id'];
                            }
                        @endphp

                        {!! App\Helpers\HrmsDepartments("","",$dep_id,"",$emp_id,"") !!}

                        <div class="col-md-4 form-group">
                            <label>Year</label>
                            <select id='years' name="years" class="form-control">
                                <option value="2024" @if(isset($data['years']) && "2024" == $data['years']) ) selected  @endif>2024-2025</option>
                                <option value="2023" @if(isset($data['years']) && "2023" == $data['years']) ) selected  @endif>2023-2024</option>
                                <option value="2022" @if(isset($data['years']) && "2022" == $data['years']) ) selected  @endif>2022-2023</option>
                                <option value="2021" @if(isset($data['years']) && "2021" == $data['years']) ) selected  @endif>2021-2022</option>
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
                <div class="table-responsive mt-20 tz-report-table">
                    <h4><font style="color:red;">Note: Red color indicates employee under probation period</font></h4>
                    <table id="example" class="table table-striped">
                        <thead style="text-align:center;">
                            <tr>
                                <th rowspan="3" style="padding-top:40px;">Sr.No</th>
                                <th rowspan="3" style="padding-top:40px;">Emp.No</th>
                                <th rowspan="3" style="padding-top:40px;">Employee Name</th>
                                <th rowspan="3" style="padding-top:40px;">Designation</th>
                                <th rowspan="3" style="padding-top:40px;">Joining Date</th>
                                <th colspan="{{count($data['get_hrms_leave_types']) * 3}}" style="text-align:center; !importanat">Taken</th>
                            </tr>
                            <tr>
                                @foreach($data['get_hrms_leave_types'] as $get_hrms_leave_type)
                                    <th colspan="3" style="text-align:center; !importanat">{{ $get_hrms_leave_type->leave_type }}</th>
                                @endforeach
                            </tr>
                            <tr>
                                @foreach($data['get_hrms_leave_types'] as $get_hrms_leave_type)
                                    <th>Op Balance</th>
                                    <th>Taken</th>
                                    <th>Balance</th>
                                @endforeach
                            </tr>
                        </thead>
                        @php
                        $j = 1;
                        @endphp
                        <tbody>
                            @foreach($data['get_employee_leave_lists'] as $key => $get_employee_leave_list)
                                @php 
                                    $att_status = '';
                                    $get_probation_period_from = \Carbon\Carbon::parse($get_employee_leave_list->probation_period_from)->format('Y-m-d');

                                    $get_probation_period_to = \Carbon\Carbon::parse($get_employee_leave_list->probation_period_to)->format('Y-m-d');
                                @endphp

                                @if($get_probation_period_from <= now() && now() <= $get_probation_period_to) 
                                    @php 
                                        $att_status = 'background-color: red;'; 
                                    @endphp
                                @endif

                                <tr style="text-align:center; !importanat; {{ $att_status }}">
                                    <td>{{ $j++ }}</td>
                                    <td>{{ $get_employee_leave_list->employee_no }}</td>
                                    <td>
                                        @if(is_array($get_employee_leave_list) && isset($get_employee_leave_list['employee_name']))
                                            {{ $get_employee_leave_list['employee_name'] }}
                                        @elseif(is_object($get_employee_leave_list) && isset($get_employee_leave_list->employee_name))
                                            {{ $get_employee_leave_list->employee_name }}
                                        @endif
                                    </td>
                                    <td>{{ $get_employee_leave_list->department_name }}</td>
                                    <td>
                                        @if(is_array($get_employee_leave_list) && isset($get_employee_leave_list['joined_date']))
                                            {{ \Carbon\Carbon::parse($get_employee_leave_list['joined_date'])->format('d-M-Y') }}
                                        @elseif(is_object($get_employee_leave_list) && isset($get_employee_leave_list->joined_date))
                                            {{ \Carbon\Carbon::parse($get_employee_leave_list->joined_date)->format('d-M-Y') }}
                                        @endif
                                    </td>

                                    @foreach($data['get_hrms_leave_types'] as $get_hrms_leave_type)
                                        @php 
                                            $total_op = 0; $total_taken = 0; $total_remain = 0;
                                        @endphp
                                        <td>
                                            @if($get_probation_period_from <= now() && now() <= $get_probation_period_to)
                                                @if(isset($data['op_data'][$get_hrms_leave_type->leave_type]) && $data['op_data'][$get_hrms_leave_type->leave_type] != '')
                                                    @php 
                                                        $total_op = 0;
                                                    @endphp

                                                    {{ $total_op }}
                                                @endif
                                            @else
                                                @if(isset($data['op_data'][$get_hrms_leave_type->leave_type][$get_employee_leave_list->department_id]) && $data['op_data'][$get_hrms_leave_type->leave_type][$get_employee_leave_list->department_id] != '')
                                                    @php 
                                                        $total_op = $data['op_data'][$get_hrms_leave_type->leave_type][$get_employee_leave_list->department_id];
                                                    @endphp

                                                    {{ $total_op }}
                                                @else
                                                    0
                                                @endif
                                            @endif
                                        </td>
                                        <td>
                                            @if(isset($data['new_data'][$get_hrms_leave_type->leave_type]) && $data['new_data'][$get_hrms_leave_type->leave_type] != '')
                                                @php 
                                                    $total_taken = $data['new_data'][$get_hrms_leave_type->leave_type][$get_employee_leave_list->id] ?? 0; 
                                                @endphp

                                                {{ $total_taken }} 
                                            @else
                                                0
                                            @endif
                                        </td>
                                        <td>
                                            @php
                                                $total_remain = ($total_op - $total_taken);
                                            @endphp

                                            {{ $total_remain }}
                                        </td>
                                    @endforeach
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
    });
</script>
<script>
	$(document).on("change", "#department_id", function(e) {
        $('#employee_id').empty().append('<option value="">--Select Employee--</option>');
        var departmentId = $(this).val();
        
        $.ajax({
            type: "post",
            url: "{{ route('emp-list') }}",
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
