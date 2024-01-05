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
                                    <div class="col-md-3 form-group">
                                        <label>Employee List</label>
                                        <select id='employee_id' name="employee_id" class="form-control">
                                            <option value="0">Select Employee</option>
                                            @foreach($employees as $employee)
                                                @if(isset($list['employeeName']))

                                                    <option
                                                        value="{{$employee->employee_id}}" {{$list['employeeName']['id'] == $employee->employee_id ? 'selected' :''}}>{{$employee->getUser ? $employee->getUser->first_name .' '. $employee->getUser->last_name : ' '}}</option>
                                                @else
                                                    <option
                                                        value="{{$employee->employee_id}}">{{$employee->getUser ? $employee->getUser->first_name .' '. $employee->getUser->last_name : ' '}}</option>
                                                @endif
                                            @endforeach

                                        </select>
                                    </div>
                                    <div class="col-md-3 form-group">
                                        <label>Select Month</label>
                                        <select id='year' name="month" class="form-control">
                                            <option value="0">Select Month</option>
                                            @foreach($months as $month)
                                                @if(isset($list['month']) && $list['month'] == $month)
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
                                            @foreach($years as $year)
                                                @if(isset($list['year']) && $list['year'] == $year)
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
            @if(isset($list['employeeName']))
                @php
                    if(isset($list['employeeName'])){
                        $employeeName = $list['employeeName'];
                    }

                @endphp

                <div class="card">
                    <div class="table-responsive mt-20 tz-report-table">
                        <form action="{{route('payroll.store_monthly_payroll_report')}}" method="post">
                            <table id="example" class="table table-striped">
                                <thead>
                                <tr>
                                    <th>Employee Id</th>
                                    <th>Employee Name</th>
                                    @foreach($header as $hkey => $col)
                                        <th>{{$col}} </th>
                                    @endforeach
                                </tr>
                                </thead>

                                @csrf
                                <tbody>
                                <tr>
                                    <td>{{$employeeName['id']}}</td>
                                    <td>{{$employeeName['first_name'] .' '.$employeeName['last_name']}}</td>
                                    <input type="hidden" name="employee_id" value="{{$employeeName['id']}}">
                                    <input type="hidden" name="month" value="{{$list['month']}}">
                                    <input type="hidden" name="year" value="{{$list['year']}}">
                                    @foreach($header as $hkey => $col)
                                        @if($hkey == 'total_day')
                                            <td><input type="text" name="total_day" value="{{$total_day}}">
                                                @if($hide_button)
                                                    <input type="submit" name="add" class="btn btn-primary" value="add">
                                                    <p style="color: red">{{isset($message) ?$message : ''}}</p>
                                                @endif
                                            </td>
                                        @elseif(isset($employeeSalaryDetails[$hkey]) && $hkey != 'total_deduction' && $hkey != 'total_payment' && $hkey != 'received_by')
                                            <input type="hidden" name="emp[id]" value="{{$employeeName['id']}}">
                                            <input type="hidden" name="emp[salary][{{$hkey}}]"
                                                   value="{{$employeeSalaryDetails[$hkey]}}">
                                            <td>{{$employeeSalaryDetails[$hkey]}}</td>
                                        @else
                                            @if($hkey == 'total_deduction')
                                                <input type="hidden" name="emp[{{$hkey}}]" value="{{$totaldeduction}}">
                                                <td>{{$totaldeduction}}</td>
                                            @elseif($hkey == 'total_payment')
                                                <input type="hidden" name="emp[{{$hkey}}]"
                                                       value="{{$totalallowance - $totaldeduction}}">
                                                <td>{{$totalallowance - $totaldeduction}}</td>
                                            @endif
                                        @endif
                                    @endforeach
                                </tr>
                                </tbody>
                            </table>
                            @if($hide_button)
                                <input type="submit" name="save" value="save" class="btn btn-success">
                            @endif
                            @if(!$hide_button)
                                <a href="{{url('monthly-payroll-report/pdf').'/'.$employeeName['id'].'/'.$list['month'].'/'.$list['year']}}"
                                   class="btn btn-primary">pdf</a> 
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
