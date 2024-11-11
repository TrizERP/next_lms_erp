@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Roll Over Employee Salary Structure</h4>
            </div>
        </div>
        <div class="card">
            @if ($sessionData = Session::get('data'))
                <div class="alert alert-success alert-block">
                    <button type="button" class="close" data-dismiss="alert">×</button>
                    <strong>{{ $sessionData['message'] }}</strong>
                </div>
            @endif
            <form action="{{ route('rollover_employee_salary_structure.store') }}" enctype="multipart/form-data" method="post">
                {{ method_field("POST") }}
                @csrf
                <div class="row">
                    <div class="col-lg-12 col-sm-12 col-xs-12">
                        <div class="table-responsive">
                            <table id="example" class="table table-striped">
                                <thead>
                                <tr>
                                    <th>Emp.No</th>
                                    <th>Emp Name</th>
                                    <th>Gender</th>
                                    @foreach ($payrollTypes as $payrollType)
                                        <th class="text-left">{{$payrollType->payroll_name}}</th>
                                    @endforeach
                                </tr>
                                </thead>
                                <tbody>
                                @php
                                    $j=1;
                                @endphp
                                @foreach($employees as $key => $data)
                                    <tr>
                                        <td>{{$data->employee_no}}</td>
                                        <td>{{$data->first_name .' '. $data->middle_name .' '.$data->last_name}}</td>
                                        <td>{{$data->gender}}</td><input type="hidden" name="emp[{{$key}}][]" value="{{$data->id}}">
                                        <input type="hidden" name="emp[{{$key}}][year] ?? ''" value="{{$employeeSalaryStructures[$key]['year'] ?? ''}}">
                                        @foreach ($payrollTypes as $payrollType)
                                            <input type="hidden" name="emp[{{$key}}][{{$payrollType->id}}][]"
                                                   value="{{$payrollType->id}}">
                                            <td><input type="text" name="emp[{{$key}}][{{$payrollType->id}}][]" value="{{$employeeSalaryStructures[$key]['employee_salary_data'][$payrollType->id] ?? 0}}">
                                            </td>
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
                            <input type="submit" name="submit" id="Submit" value="Roll Over" class="btn btn-success">
                        </center>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

@include('includes.footerJs')

<script src="{{ asset("/plugins/bower_components/datatables/datatables.min.js") }}"></script>
<script>
    $(document).ready(function () {
        $('#example').DataTable();
    });

</script>
@include('includes.footer')
