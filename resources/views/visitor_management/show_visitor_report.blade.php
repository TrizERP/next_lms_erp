@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Visitor Report</h4></div>
        </div>
        <div class="card">
            @if(!empty($data['message']))
                <div class="alert alert-success alert-block">
                    <button type="button" class="close" data-dismiss="alert">×</button>
                    <strong>{{ $data['message'] }}</strong>
                </div>
                @endif

				<div class="col-lg-12 col-sm-12 col-xs-12">
                    <form action="{{ route('show_visitor_report_data') }}" enctype="multipart/form-data" method="post">
                        @csrf
                        <div class="row">
                            <div class="col-md-4 form-group">
                            <label>From Date</label>
                                <div class="input-daterange input-group" id="date-range">
                                    <input value="@if(isset($data['from_date'])){{ $data['from_date'] }}@endif"
                                           type="text" required class="form-control mydatepicker text-left"
                                           placeholder="YYYY/MM/DD" name="from_date" id="from_date" autocomplete="off">
                                    <span class="input-group-addon"><i class="icon-calender"></i></span>
                                </div>
                            </div>
                            <div class="col-md-4 form-group">
                                <label>To Date</label>
                                <div class="input-daterange input-group" id="date-range">
                                    <input value="@if(isset($data['to_date'])){{ $data['to_date'] }}@endif" type="text"
                                           required class="form-control mydatepicker text-left" placeholder="YYYY/MM/DD"
                                           name="to_date" id="to_date" autocomplete="off">
                                    <span class="input-group-addon"><i class="icon-calender"></i></span>
                                </div>
                            </div>
                            <div class="col-md-4 form-group mt-2">
                                <br>
                                <input type="submit" name="submit" value="Submit" class="btn btn-success"
                                       onclick="return validate_dates();">
                            </div>
                        </div>
                    </form>
                </div>

            @if( isset($data['data']) )
				<div class="col-lg-12 col-sm-12 col-xs-12">
                <div class="table-responsive">
                    <table id="example" class="table table-striped" >
                        <thead>
                            <tr>
                                <th>Appointment Type</th>
                                <th>Visitor Type</th>
                                <th>Visitor Name</th>
                                <th>Visitor Contact</th>
                                <th>Visitor Email</th>
                                <th>Visitor Photo</th>
                                <th>Visitor ID Card</th>
                                <th>Coming From</th>
                                <th>To Meet</th>
                                <th>Relation</th>
                                <th>Purpose</th>
                                <th>Date</th>
                                <th>Check In Time</th>
                                <th>Check Out Time</th>
                            </tr>
                        </thead>
                        <tbody>
                        @foreach($data['data'] as $key => $data)
                            <tr>
                                <td>{{$data->appointment_type}} Appointment</td>
                                <td>{{$data->visitor_type_name}}</td>
                                <td>{{$data->name}}</td>
                                <td>{{$data->contact}}</td>
                                <td>{{$data->email}}</td>
                                <td><a target="_blank"
                                       href="/storage/visitor_photo/{{$data->photo}}">{{$data->photo}}</a></td>
                                <td>{{$data->visitor_idcard}}</td>
                                <td>{{$data->coming_from}}</td>
                                <td>{{$data->staff_name}}</td>
                                <td>{{$data->relation}}</td>
                                <td>{{$data->purpose}}</td>
                                <td>{{date('d-m-Y',strtotime($data->meet_date))}}</td>
                                <td>{{$data->in_time}}</td>
                                <td>{{$data->out_time}}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                </div>
            @endif
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
                    title: 'Visitor Report',
                    orientation: 'landscape',
                    pageSize: 'LEGAL',
                    pageSize: 'A0',
                    exportOptions: {
                        columns: ':visible'
                    },
                },
                {extend: 'csv', text: ' CSV', title: 'Visitor Report'},
                {extend: 'excel', text: ' EXCEL', title: 'Visitor Report'},
                {extend: 'print', text: ' PRINT', title: 'Visitor Report'},
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
