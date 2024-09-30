@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')
<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Pay Roll Reports</h4>
            </div>
        </div>
        <div class="card">
            <div class="card-body">
            @if(!empty($data['message']))
                @if(!empty($data['status_code']) && $data['status_code'] == 1)
                <div class="alert alert-success alert-block">
                @else
                <div class="alert alert-danger alert-block">
                @endif
                    <button type="button" class="close" data-dismiss="alert">×</button>
                    <strong>{{ $data['message'] }}</strong>
                </div>
                @endif
                            <form action="{{route('payroll.show_payroll_report')}}" enctype="multipart/form-data" method="post" class="row">
                                @csrf
                                @php 
                                    $dep_id = '';
                                    if(isset($data['department_id']))
                                    {
                                        $dep_id = $data['department_id'];
                                    }
                                @endphp 
                                {!! App\Helpers\HrmsDepartments("","multiple",$dep_id,"none","","") !!}
                                <div class="col-md-3 form-group">
                                    <label>Select Month</label>
                                    <select id='year' name="month" class="form-control">
                                        <option value="0">Select Month</option>
                                        @foreach($data['months'] as $month)
                                            <option @if((isset($data['month']) && $data['month'] == $month)) selected
                                            @endif > {{$month}}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-3 form-group">
                                    <label>Select Year</label>
                                    <select id='year' name="year" class="form-control">
                                        <option value="0">Select Year</option>
                                        @foreach($data['years'] as $year)
                                        <option @if((isset($data['year']) && $data['year'] == $year)) selected @endif>{{$year}}</option>
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
            @if(isset($data['employeeDetails']) && !empty($data['employeeDetails']))
                <div class="card">
                    <div class="table-responsive mt-20 tz-report-table">
                        <table id="example" class="table table-striped">
                            <thead>
                            <tr>
                                <th>Emp No</th>
                                <th>Employee Name</th>
                                <th>Total Days</th>
                                <!-- added on 20-09-2024 -->
                                <th>Absent Days</th>
                                <th>Leave Days</th>
                                <th>LWP Days</th>
                                <!-- end on 20-09-2024 -->
                                <th>Total</th>
                                <th>Total Deduction</th>
                                <th class="text-left">Total Payment</th>
                            </tr>
                            </thead>
                            <form action="{{route('payroll.store_monthly_payroll_report')}}" method="post">
                                @csrf
                                <tbody>
                                @foreach($data['employeeDetails'] as $employeeDetail)
                                <tr>
                                    <td>{{ $employeeDetail['employee_no'] }}</td>
                                    <td>{{ $employeeDetail['full_name'] }}</td>
                                    <td>{{ round($employeeDetail['total_day'],2) }}</td>
                                    <!-- added on 20-09-2024 -->
                                    <td>{{ $employeeDetail['absent_days'] }}</td>
                                    <td>{{ $employeeDetail['leave_days'] }}</td>
                                    <td>{{ $employeeDetail['lwp_days'] }}</td>
                                    <!-- end on 20-09-2024 -->
                                    <td>{{ $employeeDetail['total_payment'] + $employeeDetail['total_deduction'] }}</td>
                                    <td>{{ $employeeDetail['total_deduction'] }}</td>
                                    <td>{{ $employeeDetail['total_payment'] }}</td>
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
                        title: 'Payroll report',
                        orientation: 'landscape',
                        pageSize: 'LEGAL',
                        pageSize: 'A0',
                        exportOptions: {
                            columns: ':visible'
                        },
                    },
                    {extend: 'csv', text: ' CSV', title: 'Payroll report'},
                    {extend: 'excel', text: ' EXCEL', title: 'Payroll report'},
                    {extend: 'print', text: ' PRINT', title: 'Payroll report'},
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
