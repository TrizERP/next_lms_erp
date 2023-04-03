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
                <h4 class="page-title">Petty Cash Report</h4>
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
                    <form action="{{ route('ajax_getpettycashreport') }}" enctype="multipart/form-data" method="post">
                        @csrf
                        <div class="row">
                            <div class="col-md-3 form-group">
                            <label>From Date</label>
                                <div class="input-daterange input-group" id="date-range">
                                    <input value="@if(isset($data['from_date'])){{ $data['from_date'] }}@endif"
                                           type="text" required class="form-control mydatepicker"
                                           placeholder="YYYY/MM/DD" name="from_date" id="from_date" autocomplete="off">
                                    <span class="input-group-addon"><i class="icon-calender"></i></span>
                                </div>
                            </div>
                            <div class="col-md-3 form-group">
                                <label>To Date</label>
                                <div class="input-daterange input-group" id="date-range">
                                    <input value="@if(isset($data['to_date'])){{ $data['to_date'] }}@endif" type="text"
                                           required class="form-control mydatepicker" placeholder="YYYY/MM/DD"
                                           name="to_date" id="to_date" autocomplete="off">
                                    <span class="input-group-addon"><i class="icon-calender"></i></span>
                                </div>
                            </div>
                            <div class="col-sm-3 form-group">
                                <label>Title</label>
                                <select required id="title_id" name="title_id" class="selectpicker form-control">
                                    <option value="">Select Title</option>
                                    @foreach($data['Title_Arr'] as $key => $val)
                                        <option @if(isset($data['title_id']) && $data['title_id'] == $val->id) selected
                                                @endif value="{{$val->id}}">{{$val->title}}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3 form-group mt-4">
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
            @if( isset($data['pettycashdata']) )
                <div class="col-lg-12 col-sm-12 col-xs-12">
                    <div class="table-responsive">
                    <table id="proxy_list" class="table table-striped">
                        <thead>
                            <tr>
                                <th>Sr. No.</th>
                                <th>Date</th>
                                <th>User</th>
                                <th>Title</th>
                                <th>Amount</th>
                                <th>Description</th>
                            </tr>
                        </thead>
                        <tbody>
                        @php $i=1; @endphp
                        @foreach($data['pettycashdata'] as $key =>$val)
                            <tr>
                                <td>{{$i++}}</td>
                                <td>{{$val->bill_date}}</td>
                                <td>{{$val->user_name}}</td>
                                <td>{{$val->title_name}}</td>
                                <td>{{$val->amount}}</td>
                                <td>{{$val->description}}</td>
                            </tr>
                        @endforeach
                        </tbody>
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
                    title: 'Petty Cash Report',
                    orientation: 'landscape',
                    pageSize: 'LEGAL',
                    pageSize: 'A0',
                    exportOptions: {
                        columns: ':visible'
                    },
                },
                {extend: 'csv', text: ' CSV', title: 'Petty Cash Report'},
                {extend: 'excel', text: ' EXCEL', title: 'Petty Cash Report'},
                {extend: 'print', text: ' PRINT', title: 'Petty Cash Report'},
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
                        .search(this.value)
                        .draw();
                }
            });
        });
    });

    function validate_dates() {
        var from_date = $("#from_date").val();
        var to_date = $("#to_date").val();

        if (Date.parse(from_date) < Date.parse(to_date)) {
            return true;
        } else {
            $("#showerr").css("display", "block");
            $("#err").html("Please select Proper Dates");
            //alert("Please select Proper Dates");
            return false;
        }
    }

</script>

@include('includes.footer')
