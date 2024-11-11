@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')
<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Monthly Pay Roll Reports</h4>
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
                            <form action="{{route('payroll.show_monthly_payroll_report')}}"
                                  enctype="multipart/form-data"
                                  method="post">
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
                                    <div class="col-md-3 form-group">
                                        <label>Select Month</label>
                                        <select id='year' name="month" class="form-control">
                                            <option value="0">Select Month</option>
                                            @foreach($data['months'] as $month)
                                                @if(isset($data['list']['month']) && $data['list']['month'] == $month)
                                                    <option selected>{{$month}}</option>
                                                @else
                                                    <option>{{$month}}</option>
                                                @endif
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-3 form-group">
                                        <label>Select Year</label>
                                        <select id='year' name="year" class="form-control">
                                            <option value="0">Select Year</option>
                                            @foreach($data['years'] as $year)
                                                @if(isset($data['list']['year']) && $data['list']['year'] == $year)
                                                    <option selected>{{$year}}</option>
                                                @else
                                                    <option>{{$year}}</option>
                                                @endif
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
            @if(isset($data['list']['employeeName']))
                @php
                    if(isset($data['list']['employeeName'])){
                        $employeeName = $data['list']['employeeName'];
                    }

                @endphp

                <div class="card">
                    <div class="table-responsive mt-20 tz-report-table">
                        <form action="{{route('payroll.store_monthly_payroll_report')}}" method="post">
                            <table id="example" class="table table-striped">
                                <thead>
                                <tr>
                                    <th>Emp No</th>
                                    <th>Employee Name</th>
                                    @foreach($data['header'] as $hkey => $col)
                                        <th class="text-left">{{$col}} </th>
                                    @endforeach
                                </tr>
                                </thead>

                                @csrf
                                <tbody>
                                <tr>
                                    <td>{{$employeeName['employee_no']}}</td>
                                    <td>{{$employeeName['first_name'] .' '.$employeeName['last_name']}}</td>
                                    <input type="hidden" name="emp_id" value="{{$employeeName['id']}}">
                                    <input type="hidden" name="department_id" value="{{$data['department_id']}}">
                                    <input type="hidden" name="month" value="{{$data['list']['month']}}">
                                    <input type="hidden" name="year" value="{{$data['list']['year']}}">
                                    @foreach($data['header'] as $hkey => $col)
                                        @if($hkey == 'total_day')
                                            <td class="d-flex"><input type="text" name="total_day" value="{{$data['total_day']}}" @if($data['total_day']!='') readonly @endif>
                                                @if($data['hide_button'])
                                                    <input type="submit" name="add" class="btn btn-primary" value="add">
                                                    <p style="color: red">{{isset($data['message']) ?$data['message'] : ''}}</p>
                                                @endif 
                                                @if(!$data['hide_button'])
                                                    <input type="submit" name="delete" class="btn btn-danger" value="X" style="padding: 0px;font-size: 0.6rem;width:20px;height:20px">
                                                @endif
                                            </td>
                                        @elseif(isset($data['employeeSalaryDetails'][$hkey]) && $hkey != 'total_deduction' && $hkey != 'total_payment' && $hkey != 'received_by')
                                            <input type="hidden" name="emp[id]" value="{{$employeeName['id']}}">
                                            <input type="hidden" name="emp[salary][{{$hkey}}]"
                                                   value="{{$data['employeeSalaryDetails'][$hkey]}}">
                                            <td>{{$data['employeeSalaryDetails'][$hkey]}}</td>
                                        @else
                                        
                                            @if($hkey == 'total_deduction')
                                                <input type="hidden" name="emp[{{$hkey}}]" value="{{$data['totaldeduction']}}">
                                                <td>{{$data['totaldeduction']}}</td>
                                            @elseif($hkey == 'total_payment')
                                                <input type="hidden" name="emp[{{$hkey}}]"
                                                       value="{{$data['totalallowance'] - $data['totaldeduction']}}">
                                                <td>{{$data['totalallowance'] - $data['totaldeduction']}}</td>
                                                @else
                                                <td>0</td>
                                            @endif
                                        @endif
                                    @endforeach
                                </tr>
                                </tbody>
                            </table>
                           
                            @if(isset($data['pdf_link']))
                                <a href="{{url('monthly-payroll-report/pdf').'/'.$employeeName['id'].'/'.$data['list']['month'].'/'.$data['list']['year']}}"
                                   class="btn btn-primary">pdf</a>
                            @else 
                            <input type="submit" name="save" value="save" class="btn btn-success" >
                            @endif
                        </form>
                    </div>
                </div>
        </div>
        @endif
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
                        title: 'Monthly Payroll Report',
                        orientation: 'landscape',
                        pageSize: 'LEGAL',
                        pageSize: 'A0',
                        exportOptions: {
                            columns: ':visible'
                        },
                    },
                    {extend: 'csv', text: ' CSV', title: 'Monthly Payroll Report'},
                    {extend: 'excel', text: ' EXCEL', title: 'Monthly Payroll Report'},
                    {extend: 'print', text: ' PRINT', title: 'Monthly Payroll Report'},
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
