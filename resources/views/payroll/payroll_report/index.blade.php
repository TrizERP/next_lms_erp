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
                            <form action="{{route('payroll.show_payroll_report')}}"
                                  enctype="multipart/form-data"
                                  method="post" class="row">
                                @csrf
                                @php 
                                $dep_id = '';
                                if(isset($data['department_id']))
                                {
                                    $dep_id = $data['department_id'];
                                }
                                @endphp 
                                {!! App\Helpers\HrmsDepartments("","",$dep_id,"none","","") !!}
                                <div class="col-md-3 form-group">
                                    <label>Select Month</label>
                                    <select id='year' name="month" class="form-control">
                                        <option value="0">Select Month</option>
                                        @foreach($data['months'] as $month)
                                            @if(isset($data['month']) && $data['month'] == $month)
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
                                            @if(isset($data['year']) && $data['year'] == $year)
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
            @if(isset($data['employeeDetails']) && !empty($data['employeeDetails']))
                <div class="card">
                    <div class="table-responsive mt-20 tz-report-table">
                        <table id="example" class="table table-striped">
                            <thead>
                            <tr>
                                <th>Emp No</th>
                                <th>Employee Name</th>
                                <th>Total Day</th>
                                <th>Total</th>
                                <th>Total Deduction</th>
                                <th>Total Payment</th>
                            </tr>
                            </thead>
                            <form action="{{route('payroll.store_monthly_payroll_report')}}" method="post">
                                @csrf
                                <tbody>
                                @foreach($data['employeeDetails'] as $employeeDetail)
                                <tr>
                                    <td>{{$employeeDetail->employee_no}}</td>
                                    <td>{{$employeeDetail->first_name .' '. $employeeDetail->last_name}}</td>
                                    <td>{{$employeeDetail->total_day}}</td>
                                    <td>{{$employeeDetail->total_payment + $employeeDetail->total_deduction}}</td>
                                    <td>{{$employeeDetail->total_deduction}}</td>
                                    <td>{{$employeeDetail->total_payment}}</td>
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
