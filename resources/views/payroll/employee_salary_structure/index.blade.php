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
                            <form action="{{route('employee_salary_structure.index')}}"
                                  enctype="multipart/form-data"
                                  method="post">
                                @csrf
                                <div class="row">
                                @php 
                                    $dep_id = $emp_id = '';
                                    if(isset($data['department_id'])){
                                        $dep_id = $data['department_id'];
                                    }

                                    if(isset($data['selected_emp'])){
                                        $emp_id = $data['selected_emp'];
                                    }
                                @endphp

                                {!! App\Helpers\HrmsDepartments("","multiple",$dep_id,"multiple",$emp_id,"") !!}

                                    <div class="col-md-3 form-group">
                                        <label>Employee Status</label>
                                        <select id='emp_status' name="emp_status" class="form-control">
                                            <option @if(isset($data['emp_status']) && $data['emp_status']==1) selected @endif value="1">Active</option>
                                            <option @if(isset($data['emp_status']) && $data['emp_status']==0) selected @endif value="0">In-active</option>
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
            <form class="card" action="{{ route('employee_salary_structure.store') }}" enctype="multipart/form-data" method="post">
                {{ method_field("POST") }}
                @csrf
                <div class="row">
                    <div class="col-lg-12 col-sm-12 col-xs-12">
                        <div class="table-responsive">
                            <table id="example" class="table table-striped">
                                <thead>
                                <tr>
                                    <th>Sr No.</th>
                                    <th>Emp.No</th>
                                    <th>Emp.Name</th>
                                    <th>Department</th>
                                    <th>Gender</th>
                                    @foreach ($data['payrollTypes'] as $payrollType)
                                        <th class="text-left">{{$payrollType->payroll_name}}</th>
                                    @endforeach
                                </tr>
                                </thead>
                                <tbody>
                                @php $j=1; @endphp
                                @foreach($data['employees'] as $key => $value)
                                    <tr>
                                        <td>{{$key+1}}</td>
                                        <td>{{$value['employee_no']}}</td>
                                        <td>{{$value['first_name'] .' '. $value['middle_name'] .' '.$value['last_name']}}</td>
                                        <td>{{$value['department']}}</td>
                                        <td>{{$value['gender']}}<input type="hidden" name="emp[{{$value['id']}}][]" value="{{$value['gender']}}"></td>
                                         
                                        @foreach ($data['payrollTypes'] as $payrollType)
                                            @if(($payrollType->payroll_name == 'PF' || $payrollType->payroll_name == 'PT') && Session::get('sub_institute_id') != '195')
                                                
                                                <td>
                                                    <input type="hidden" name="emp[{{$value['id']}}][{{$payrollType->id}}][]" value="{{$payrollType->id}}">

                                                    <span id="all_values" style="display:none">{{$data['employeeSalaryStructures'][$value['id']][$payrollType->id] ?? 0}}</span>

                                                    <input type="text" disabled value="{{$data['employeeSalaryStructures'][$value['id']][$payrollType->id] ?? 0}}" class="form-control" style="width:80px !important">

                                                    <input type="hidden" name="emp[{{$value['id']}}][{{$payrollType->id}}][]" value="{{$data['employeeSalaryStructures'][$value['id']][$payrollType->id] ?? 0}}">

                                                    <input type="hidden" name="emp[{{$value['id']}}][{{$payrollType->id}}][]" value="{{$payrollType->payroll_name}}">

                                                    <input type="hidden" name="emp[{{$value['id']}}][{{$payrollType->id}}][]" value="{{$payrollType->payroll_type}}">
                                                </td>
                                            @else
                                                
                                                <td>
                                                    <input type="hidden" name="emp[{{$value['id']}}][{{$payrollType->id}}][]" value="{{$payrollType->id}}">

                                                    <span id="all_values" style="display:none">{{$data['employeeSalaryStructures'][$value['id']][$payrollType->id] ?? 0}}</span>

                                                    <input type="text" name="emp[{{$value['id']}}][{{$payrollType->id}}][]" value="{{$data['employeeSalaryStructures'][$value['id']][$payrollType->id] ?? 0}}" class="form-control" style="width:80px !important">

                                                    <input type="hidden" name="emp[{{$value['id']}}][{{$payrollType->id}}][]" value="{{$payrollType->payroll_name}}"> 

                                                    <input type="hidden" name="emp[{{$value['id']}}][{{$payrollType->id}}][]" value="{{$payrollType->payroll_type}}">
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
<style>
    @media print {
    .flex-on-print {
        display: flex !important;
    }
}
</style>
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
                    title: 'Salary Structure Report',
                    orientation: 'landscape',
                    pageSize: 'LEGAL',
                    pageSize: 'A0',
                    exportOptions: {
                        columns: ':visible'
                    },
                },
                {extend: 'csv', text: ' CSV', title: 'Salary Structure Report'},
                {extend: 'excel', text: ' EXCEL', title: 'Salary Structure Report'},
                {
                    extend: 'print', 
                    text: ' PRINT', 
                    title: 'Salary Structure Report',
                    customize: function (win) {
                        $(win.document.body).append(`<div style="text-align: right;margin-top:20px">Printed on: {{date('d-m-Y H:i:s')}}</div>`);
                        $('#all_values').addClass('flex-on-print');
                    }
                },
                'pageLength'
            ],
        });

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
