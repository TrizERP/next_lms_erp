@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
            <div class="row bg-title">
                <div class="col-lg-6 col-md-4 col-sm-4 col-xs-12">
                    <h4 class="page-title">Institute Wise Fees Paid Report</h4> </div>
            </div>

                <div class="card">
                    @if ($sessionData = Session::get('data'))
                    <div class="alert alert-success alert-block">
                        <button type="button" class="close" data-dismiss="alert">×</button>
                        <strong>{{ $sessionData['message'] }}</strong>
                    </div>
                    @endif
                    <form action="{{ route('institute_wise_fees_paid_report') }}" method="POST">
                        @csrf
                        <div class="row">
                            <div class="col-md-4 form-group">
                                <label>From Date</label>
                                <input type="text" id="from_date" @if(isset($data['from_date'])) value="{{$data['from_date']}}"  @endif name="from_date" class="form-control mydatepicker" required="required" autocomplete="off">
                            </div>

                            <div class="col-md-4 form-group">
                                <label>To Date</label>
                                <input type="text" id="to_date" @if(isset($data['to_date'])) value="{{$data['to_date']}}" @endif name="to_date"
                                       class="form-control mydatepicker" required="required" autocomplete="off">
                            </div>

                            <div class="col-md-4 form-group mt-4">
                                <center>
                                    <input type="submit" name="submit" value="Search" class="btn btn-success">
                                </center>
                            </div>
                        </div>
                    </form>
                </div>

        @if(isset($data['report_data']))
        @php
            if(isset($data['report_data'])){
                $report_data = $data['report_data'];
                $finalData = $data;
            }
        @endphp

            <div class="card">

            <div class="col-lg-12 col-sm-12 col-xs-12">
                    <div class="table-responsive">
                        <table id="example" class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Sr.No.</th>
                                    <th>School Name</th>
                                    <th>Title</th>
                                    <th>Mobile</th>
                                    <th>Email Address</th>
                                    <th>Total Students</th>
                                    <th>Paid Students</th>
                                    <th>Total Fees Collected</th>
                                </tr>
                            </thead>
                            <tbody>
                                    @php
                                    $j=1;
                                    @endphp
                                @foreach($report_data as $key => $data)

                                <tr>
                                    <td>{{$j}}</td>
                                    <td>{{$data['SchoolName']}}</td>
                                    <td>{{$data['ShortCode']}}</td>
                                    <td>{{$data['Mobile']}}</td>
                                    <td>{{$data['Email']}}</td>
                                    <td>{{$data['TOTAL_STUDENT']}}</td>
                                    <td>{{$data['TOOTAL_PAID']}}</td>
                                    <td>{{$data['Total_Fees_Collected']}}</td>
                                </tr>
                                    @php
                                    $j++;
                                    @endphp
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    </div>
                </div>

        @endif
    </div>
</div>

@include('includes.footerJs')

<script>
    $(document).ready(function () {
        // Setup - add a text input to each footer cell

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
                    title: 'Institute Wise Fees Paid Report',
                    orientation: 'landscape',
                    pageSize: 'LEGAL',
                    pageSize: 'A0',
                    exportOptions: {
                        columns: ':visible'
                    },
                },
                {extend: 'csv', text: ' CSV', title: 'Institute Wise Fees Paid Report'},
                {extend: 'excel', text: ' EXCEL', title: 'Institute Wise Fees Paid Report'},
                {extend: 'print', text: ' PRINT', title: 'Institute Wise Fees Paid Report'},
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
                        .search( this.value )
                        .draw();
                }
            } );
        } );
    } );
</script>

@include('includes.footer')
