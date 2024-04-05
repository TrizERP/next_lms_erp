@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')
<style>
    .title {
        font-weight: 200;
    }
</style>
<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Proxy Report</h4>
            </div>
        </div>
        <div class="card">
            @if ($message = Session::get('data'))
                <div class="alert alert-success alert-block">
                    <button type="button" class="close" data-dismiss="alert">×</button>
                    <strong>{{ $message['message'] }}</strong>
                </div>
                @endif
                <div class="col-lg-12 col-sm-12 col-xs-12">
                    <form action="{{ route('ajax_getproxyreport') }}" enctype="multipart/form-data" method="post">
                        @csrf
                        <div class="row">
                            <div class="col-md-4 form-group">
                            <label>From Date</label>
                                <div class="input-daterange input-group" id="date-range">
                                    <input value="@if(isset($data['from_date'])){{ $data['from_date'] }}@endif"
                                           type="text" required class="form-control mydatepicker"
                                           placeholder="YYYY/MM/DD" name="from_date" id="from_date" autocomplete="off">
                                    <span class="input-group-addon"><i class="icon-calender"></i></span>
                                </div>
                            </div>
                            <div class="col-md-4 form-group">
                                <label>To Date</label>
                                <div class="input-daterange input-group" id="date-range">
                                    <input value="@if(isset($data['to_date'])){{ $data['to_date'] }}@endif" type="text"
                                           required class="form-control mydatepicker" placeholder="YYYY/MM/DD"
                                           name="to_date" id="to_date" autocomplete="off">
                                    <span class="input-group-addon"><i class="icon-calender"></i></span>
                                </div>
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Proxy Teacher</label>
                                <select class="selectpicker form-control" name="teacher_id" id="teacher_id">
                                    <option value="">Select Teacher</option>
                                    @if(isset($data['teacher_data']))
                                        @foreach($data['teacher_data'] as $key =>$val)
                                            @php
                                                $selected = '';
                                                if( isset($data['teacher']) && $data['teacher'] == $val->id )
                                                {
                                                    $selected = 'selected';
                                                }
                                            @endphp
                                            <option {{$selected}} value="{{$val->id}}">{{$val->teacher_name}}</option>
                                        @endforeach
                                    @endif
                                </select>
                            </div>
                            <div class="col-md-12 form-group">
                                <br>
                                <input type="submit" name="submit" value="Submit" class="btn btn-success"
                                       onclick="return validate_dates();">
                            </div>
                        </div>
                    </form>
                </div>
            <div class="col-lg-12 col-sm-12 col-xs-12">
                <div class="alert alert-danger alert-dismissable" id='showerr' style="display:none;">
                    <div id='err'></div>
                </div>
            </div>
            @if( isset($data['proxydata']) )
                <div class="col-lg-12 col-sm-12 col-xs-12">
				<div class="table-responsive">
                    {!! App\Helpers\get_school_details("","","") !!}
                    <table id="proxy_list" class="table table-striped">
                        <thead>
                        <tr>
                            <th>Sr. No.</th>
                            <th>Date</th>
                            <th>Standard</th>
                            <th>Division</th>
                            <th>Absent Teacher</th>
                            <th>Proxy Teacher</th>
                            <th>Period</th>
                            <th>Subject</th>
                        </tr>
                        </thead>
                        <tbody>
                        @php $i=1; @endphp
                        @foreach($data['proxydata'] as $key =>$val)
                            <tr>
                                <td>{{$i++}}</td>
                                <td>{{$val->proxy_date}}</td>
                                <td>{{$val->standard_name}}</td>
                                <td>{{$val->division_name}}</td>
                                <td>{{$val->teacher_name}}</td>
                                <td>{{$val->proxy_teacher_name}}</td>
                                <td>{{$val->period_name}}</td>
                                <td>{{$val->sub_name}}</td>
                            </tr>
                        @endforeach
                        </tbody>
                        @if( count($data['proxydata']) == 0 )
                            <tr align="center">
                                <td colspan="10">
                                    No Records Found!
                                </td>
                            </tr>
                        @endif
                    </table>
                </div>
                </div>
            @endif

                @if (count($errors) > 0)
                <div class="alert alert-danger">
                    <strong>Whoops!</strong> There were some problems with your input.<br><br>
                    <ul>
                        @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
                @endif
        </div>
    </div>
</div>

@include('includes.footerJs')
<script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.1/moment.min.js"></script>
<script>
    $(document).ready(function () {
        var table = $('#proxy_list').DataTable({
            select: true,
            lengthMenu: [
                [100, 500, 1000, -1],
                ['100', '500', '1000', 'Show All']
            ],
            dom: 'Bfrtip',
            buttons: [
                {
                    extend: 'pdfHtml5',
                    title: 'Proxy Report',
                    orientation: 'landscape',
                    pageSize: 'LEGAL',
                    pageSize: 'A0',
                    exportOptions: {
                        columns: ':visible'
                    },
                },
                {extend: 'csv', text: ' CSV', title: 'Proxy Report'},
                {extend: 'excel', text: ' EXCEL', title: 'Proxy Report'},
                {
                    extend: 'print',
                    text: ' PRINT',
                    title: 'Student Report',
                    customize: function (win) {
                        $(win.document.body).prepend(`{!! App\Helpers\get_school_details("", "", "") !!}`);
                        $(win.document.body).append(`<div style="text-align: right;margin-top:20px">Printed on: {{date('d-m-Y H:i:s')}}</div>`);
                    }
                },
                'pageLength'
            ],
        });

        $('#proxy_list thead tr').clone(true).appendTo('#proxy_list thead');
        $('#proxy_list thead tr:eq(1) th').each(function (i) {
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
        });
    });

    function validate_dates() {
    var from_date = $("#from_date").val();
    var to_date = $("#to_date").val();

    // Convert input date strings to Moment.js objects with the correct format
    var momentFrom = moment(from_date, "DD-MM-YYYY");
    var momentTo = moment(to_date, "DD-MM-YYYY");

    // Format the dates in "MM/DD/YYYY" format for comparison
    var formattedFrom = momentFrom.format("MM/DD/YYYY");
    var formattedTo = momentTo.format("MM/DD/YYYY");

    // Perform the date comparison
    if (momentFrom.isBefore(momentTo)) {
        return true;
    } else {
        $("#showerr").css("display", "block");
        $("#err").html("Please select Proper Dates");
        return false;
    }
}


</script>

@include('includes.footer')
