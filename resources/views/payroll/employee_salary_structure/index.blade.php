@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Employee Salary Structure</h4>
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
                            <form action="{{route('payroll.show_employee_salary_structure')}}"
                                  enctype="multipart/form-data"
                                  method="post">
                                @csrf
                                <div class="row">
                                    <div class="col-md-3 form-group">
                                        <label>Employee List</label>

                                        <select id='employee_id' name="employee_id" class="form-control">
                                            <option value="0">Select Employee</option>
                                            @foreach($data['employeeLists'] as $key => $employeeList)
                                                @if(is_array($data['employees']) && count($data['employees']) == 1 || $data['selected_emp'] == $employeeList->id)
                                                    <option
                                                        value="{{$employeeList->id}}"
                                                        @if(isset($data['selected_emp'])==$employeeList->id) selected @endif>{{$employeeList->first_name .' '. $employeeList->last_name }}</option>
                                                @else
                                                    <option
                                                        value="{{$employeeList->id}}" >{{$employeeList->first_name .' '. $employeeList->last_name }}</option>
                                                @endif
                                            @endforeach

                                        </select>
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
            <form action="{{ route('employee_salary_structure.store') }}" enctype="multipart/form-data" method="post">
                {{ method_field("POST") }}
                @csrf
                <div class="row">
                    <div class="col-lg-12 col-sm-12 col-xs-12">
                        <div class="table-responsive">
                            <table id="example" class="table table-striped">
                                <thead>
                                <tr>
                                    <th>Emp Id</th>
                                    <th>Emp Name</th>
                                    <th>Gender</th>
                                    @foreach ($data['payrollTypes'] as $payrollType)
                                        <th>{{$payrollType->payroll_name}}</th>
                                    @endforeach
                                </tr>
                                </thead>
                                <tbody>
                                @php
                                    $j=1;
                                @endphp
                                @foreach($data['employees'] as $key => $value)
                                    <tr>
                                        <td>{{$value->id}}</td>
                                        <td>{{$value->first_name .' '. $value->middle_name .' '.$value->last_name}}</td>
                                        <td>{{$value->gender}}</td>
                                        <input type="hidden" name="emp[{{$value->id}}][]" value="{{$value->id}}"> 
                                        @foreach ($data['payrollTypes'] as $payrollType)
                                            @if($payrollType->payroll_name == 'PF' || $payrollType->payroll_name == 'Pro.Tax')
                                                <input type="hidden" name="emp[{{$value->id}}][{{$payrollType->id}}][]"
                                                       value="{{$payrollType->id}}">
                                                <td><input type="text" disabled
                                                           value="{{$data['employeeSalaryStructures'][$value->id][$payrollType->id] ?? 0}}">
                                                    <input type="hidden" name="emp[{{$value->id}}][{{$payrollType->id}}][]"
                                                           value="{{$data['employeeSalaryStructures'][$value->id][$payrollType->id] ?? 0}}">
                                                    <input type="hidden" name="emp[{{$value->id}}][{{$payrollType->id}}][]"
                                                           value="{{$payrollType->payroll_name}}">
                                                    <input type="hidden" name="emp[{{$value->id}}][{{$payrollType->id}}][]"
                                                           value="{{$payrollType->payroll_type}}">
                                                </td>
                                            @else
                                                <input type="hidden" name="emp[{{$value->id}}][{{$payrollType->id}}][]"
                                                       value="{{$payrollType->id}}">
                                                <td><input type="text" name="emp[{{$value->id}}][{{$payrollType->id}}][]"
                                                           value="{{$data['employeeSalaryStructures'][$value->id][$payrollType->id] ?? 0}}">
                                                    <input type="hidden" name="emp[{{$value->id}}][{{$payrollType->id}}][]"
                                                           value="{{$payrollType->payroll_name}}"> <input type="hidden" name="emp[{{$value->id}}][{{$payrollType->id}}][]"
                                                           value="{{$payrollType->payroll_type}}">
                                                </td>
                                            @endif

                                        @endforeach
                                    </tr>
                                    @php
                                        $j++;
                                    @endphp
                                @endforeach
                                </tbody>
                            </table>
                        </div>

                    </div>
                    <div class="col-md-12 form-group">
                        <center>
                            <input type="submit" name="submit" id="Submit" value="Save" class="btn btn-success">
                        </center>
                    </div>
                </div>
            </form>
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
