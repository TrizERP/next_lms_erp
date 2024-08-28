@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')
<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Employee Pay Roll History</h4>
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
                            <form action="{{route('payroll.show_employee_payroll_history')}}"
                                  enctype="multipart/form-data"
                                  method="post">
                                @csrf
                                <div class="row">
                                @php 
                                    $dep_id = $emp_id = '';
                                    $currentYear = date('Y');
                                    if(isset($data['selDept'])){
                                        $dep_id = $data['selDept'];
                                    }

                                    if(isset($data['selEmp'])){
                                        $emp_id = $data['selEmp'];
                                    }
                                @endphp

                                {!! App\Helpers\HrmsDepartments("","multiple",$dep_id,"multiple",$emp_id,"") !!}
                                    <div class="col-md-3 form-group">
                                        <label>Select Year</label>
                                        <select id='year' name="year" class="form-control">
                                            <option value="0">Select Year</option>
                                            @foreach($data['years'] as $year)
                                                <option @if(isset($data['selYear']) && $data['selYear'] == $year) selected @elseif($year==$currentYear) Selected @endif>{{$year}}</option>
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

                <div class="card">
                    <div class="table-responsive mt-20 tz-report-table">
                        <table id="example" class="table table-striped">
                            <thead>
                            <tr>
                                <th>Emp No</th>
                                <th>Emp Name</th>
                                <th>Month -Year </th>
                                <th>Total Day</th>
                                @foreach($data['header'] as $hkey => $col)
                                    <th>{{$col}} </th>
                                @endforeach
                                <th>Total Deduction</th>
                                <th class="text-left">Total Payment</th>
                            </tr>
                            </thead>
                            <form action="{{route('payroll.store_monthly_payroll_report')}}" method="post">
                                @csrf
                                <tbody>

                                    @foreach($data['currentYearemployeeDetails'] as $employee)
                                        <tr>
                                        <td>{{$employee['employee_no']}}</td>
                                        <td>{{$employee['employee_name']}}</td>
                                        <td>{{$employee['month'] .'/'. $employee['year']}}</td>
                                        <td>{{round($employee['total_day'],2)}}</td>
                                        @foreach($data['header'] as $hkey => $col)
                                            <td>{{$employee['data'][$hkey] ?? '0' }}</td>
                                        @endforeach
                                        <td>{{$employee['total_deduction']}}</td>
                                        <td>{{$employee['total_payment']}}</td>
                                        </tr>
                                    @endforeach


                               {{--     @foreach($data['nextYearemployeeDetails'] as $employee)
                                        <tr>
                                        <td>{{$employee['month'] .'/'. $employee['year']}}</td>
                                        <td>{{$employee['employee_id']}}</td>
                                        <td>{{$employee['total_day']}}</td>
                                        @foreach($data['header'] as $hkey => $col)
                                            <td>{{$employee['data'][$hkey] ?? '0' }}</td>
                                        @endforeach
                                        <td>{{$employee['total_deduction']}}</td>
                                        <td>{{$employee['total_payment']}}</td>
                                        </tr>
                                    @endforeach --}}

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
