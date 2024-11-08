@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')
<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Pay Roll Type Reports</h4>
            </div>
        </div>
    
        <div class="card">
            @if(isset($data['status_code']) && $data['status_code']==0)
                <div class="alert alert-danger alert-block">
                    <button type="button" class="close" data-dismiss="alert">×</button>
                    <strong>{{ $data['message'] }}</strong>
                </div>
            @endif
            <div class="card-body">
                <form class="row" action="{{route('payrollTypeReport.create')}}" enctype="multipart/form-data" method="get">
                    @csrf
                    <div class="col-md-3 form-group">
                        <label>Select Month</label>
                        <select id='year' name="month" class="form-control" required>
                            @foreach($data['months'] as $month)
                            @php
                                $selected = '';
                                if(isset($data['selectedMonth'])) {
                                    if($data['selectedMonth']==$month) {
                                    $selected = 'selected'; 
                                    }
                                }
                                elseif($month==date('M')) {
                                    $selected = 'selected'; 
                                }
                            @endphp
                                <option {{$selected}}>{{$month}}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3 form-group">
                        <label>Select Year</label>
                        <select id='year' name="year" class="form-control" required>
                            @foreach($data['years'] as $year)
                            @php
                                $selected = '';
                                if(isset($data['selectedYear'])) {
                                    if($data['selectedYear']==$year) {
                                    $selected = 'selected'; 
                                    }
                                }
                                elseif($year==date('Y')) {
                                    $selected = 'selected'; 
                                }
                            @endphp
                                <option {{$selected}}>{{$year}}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3 form-group">
                        <label>Select Type</label>
                        <select id='payroll_type' name="payroll_type[]" class="form-control" multiple>
                            @foreach($data['py_types'] as $key=>$value)
                            @php
                                $selected = '';
                                if(isset($data['selectedPayrollType'])) {
                                    if(in_array($value['id'],$data['selectedPayrollType'])) {
                                    $selected = 'selected'; 
                                    }
                                }
                                else{
                                    $selected = 'selected'; 
                                }
                            @endphp
                                <option value="{{$value['id']}}" {{$selected}}>{{$value['payroll_name']}}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-12 col-sm-offset-4 text-center form-group">
                        <input type="submit" name="submit" value="Search" class="btn btn-success">
                    </div>
                
                </form>
            </div>
        </div>
    @if(isset($data['payrollData']) &&!empty($data['payrollData']))
        <div class="card">
            <div class="table-responsive mt-20 tz-report-table">
                <table id="example" class="table table-striped">
                    <thead>
                    <tr>
                        <th>Emp No</th>
                        <th>Employee Name</th>
                        @foreach($data['payrollHeads'] as $key=>$value)
                            <th>{{$value['payroll_name']}}</th>
                        @endforeach
                        <th>Total</th>
                        <th>Total Deduction</th>
                        <th class="text-left">Total Payment</th>
                    </tr>
                    </thead>
                    <form action="{{route('payroll.store_monthly_payroll_report')}}" method="post">
                        @csrf
                        <tbody>
                        @foreach($data['payrollData'] as $key=>$value)
                            <tr>
                                <td>{{$value->employee_no}}</td>
                                <td>{{$value->emp_name}} ({{$value->profile_name}})</td>
                                @php 
                                    $jsonSalaryDecode = json_decode($value->employee_salary_data,true);
                                    $jsonSalary=[];
                                    if(!empty($jsonSalaryDecode)){
                                        foreach($jsonSalaryDecode as $k=>$v){
                                            $jsonSalary[$k] = $v; 
                                        }
                                    }
                                    $total = $totalDeduction = $totalPayment = 0;
                                @endphp
                                @foreach($data['payrollHeads'] as $key2=>$value2)
                                    @if(isset($jsonSalary[$value2['id']]))
                                        <td>{{$jsonSalary[$value2['id']]}}</td>
                                        @php  
                                            $total +=$jsonSalary[$value2['id']];
                                            if($value2['payroll_name']=="PF" || $value2['payroll_name']=="PT"){
                                                $totalDeduction += $jsonSalary[$value2['id']];
                                            }else{
                                                $totalPayment += $jsonSalary[$value2['id']];
                                            }
                                        @endphp
                                    @else
                                        <td>0</td>
                                    @endif
                                @endforeach
                                <td>{{$total}}</td>
                                <td>{{$totalDeduction}}</td>
                                <td>{{ ($totalPayment > 0) ? ($totalPayment - $totalDeduction) : $value->total_payment}}</td>
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
@include('includes.footer')
