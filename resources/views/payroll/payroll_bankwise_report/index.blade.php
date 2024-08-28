@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')
<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Payroll Bank Wise Reports</h4>
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
                            <form action="{{route('payroll.show_payroll_bankwise_report')}}"
                                  enctype="multipart/form-data"
                                  method="post">
                                @csrf
                                <div class="row">
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
            <div class="card">
                <div class="table-responsive mt-20 tz-report-table">
                {!! App\Helpers\get_school_details("","","") !!}
                    <table id="example" class="table table-striped">
                        <thead>
                        <tr>
                            <th>Emp No</th>
                            <th>Employee Name</th>
                            <th>Bank Name</th>
                            <th>A/C No.</th>
                            <th>IFSC Code</th>
                            <th class="text-left">Net Payable Amount</th>
                        </tr>
                        </thead>
                        <tbody>
                        @php $allTotal = $empTotal= 0; @endphp
                        @foreach($employees as $key=> $employee)
                        <tr>
                            <td>{{$employee->employee_no}}</td>
                            <td>{{$employee->first_name .' '. $employee->last_name}}</td>
                            <td>{{$employee->bank_name}}</td>
                            <td>{{$employee->account_no}}</td>
                            <td>{{$employee->ifsc_code}}</td>
                            <td>{{$employee->total_payment}}</td>
                        </tr>
                        @php 
                            $allTotal += $employee->total_payment;
                            $empTotal = ($key+1);
                        @endphp
                        @endforeach
                        @if(!empty($employees))
                        <tr>
                            <td><b>Total</b></td>
                            <td><b>{{$empTotal}}</b></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td><b>{{$allTotal}}</b></td>
                        </tr>
                        @endif  
                        </tbody>
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
                        title: 'Bankwise Payroll Report',
                        orientation: 'landscape',
                        pageSize: 'LEGAL',
                        pageSize: 'A0',
                        exportOptions: {
                            columns: ':visible'
                        },
                    },
                    {extend: 'csv', text: ' CSV', title: 'Bankwise Payroll Report'},
                    {extend: 'excel', text: ' EXCEL', title: 'Bankwise Payroll Report'},
                    {
                        extend: 'print',
                        text: ' PRINT',
                        title: 'Enquiry Followup Report',
                        customize: function (win) {
                            $(win.document.body).prepend(`{!! App\Helpers\get_school_details("", "", "") !!}`);
                            $(win.document.body).append(`<div style="text-align: right;margin-top:20px">Printed on: {{date('d-m-Y H:i:s')}}</div>`);                                       
                        }
                    },
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
