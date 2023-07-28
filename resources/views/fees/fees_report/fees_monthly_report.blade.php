@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Fees Monthly Report</h4>
            </div>
        </div>
        @php
            $grade_id = $standard_id = $division_id = '';
                if(isset($data['grade_id'])){
                    $grade_id = $data['grade_id'];
                    $standard_id = $data['standard_id'];
                    $division_id = $data['division_id'];
                }
        @endphp

                <div class="card">
                    @if ($sessionData = Session::get('data'))
                    <div class="alert alert-success alert-block">
                        <button type="button" class="close" data-dismiss="alert">×</button>
                        <strong>{{ $sessionData['message'] }}</strong>
                    </div>
                    @endif
                    <form action="{{ route('getfeesMonthlyReport') }}" method="POST" onsubmit="return check_dates();">
                        @csrf
                        <div class="row">
                            {{ App\Helpers\SearchChain('4','single','grade,std,div',$grade_id,$standard_id,$division_id) }}

                            <div class="col-md-4 form-group ml-0 mr-0">
                                <label>From Date</label>
                                <div class="input-daterange input-group" id="date-range">
                                    <input value="@if(isset($data['from_date'])){{ $data['from_date'] }}@endif"
                                           type="text"
                                           required class="form-control mydatepicker" placeholder="YYYY/MM/DD"
                                           name="from_date" id="from_date" autocomplete="off">
                                    <span class="input-group-addon"><i class="icon-calender"></i></span>
                                </div>
                            </div>
                            <div class="col-md-4 form-group ml-0">
                                <label>To Date</label>
                                <div class="input-daterange input-group" id="date-range">
                                    <input value="@if(isset($data['to_date'])){{ $data['to_date'] }}@endif" type="text"
                                           required class="form-control mydatepicker" placeholder="YYYY/MM/DD"
                                           name="to_date" id="to_date" autocomplete="off">
                                    <span class="input-group-addon"><i class="icon-calender"></i></span>
                                </div>
                            </div>
                            <div class="col-md-12 form-group mt-4">
                                <center>
                                    <input type="submit" name="submit" value="Search" class="btn btn-success">
                                </center>
                            </div>
                        </div>
                    </form>
                </div>


        @if(isset($data['report_data']))

            <div class="card">

                <div class="col-lg-12 col-sm-12 col-xs-12">
                    <div class="table-responsive">
                        <table id="example" class="table table-striped">
                            <thead>
                            <tr>
                                <th>Sr.No.</th>
                                <th>Date</th>
                                @foreach($data['heading_arr'] as $key => $val)
                                    @php $$key = 0; @endphp
                                    <th>{{$val}}</th>
                                @endforeach
                                <th>Total</th>
                            </tr>
                            </thead>
                            <tbody>
                            @php
                                $i=1;
                                $rowtotal = array();
                                $grand_total = 0;
                            @endphp
                            @foreach($data['report_data'] as $rkey =>$rval)
                                @php
                                    $total = 0;
                                @endphp
                                <tr>
                                    <td>{{$i++}}</td>
                                    <td>{{date('d-m-Y',strtotime($rkey))}}</td>
                                    @php
                                        foreach($data['heading_arr'] as $hkey => $hval)
                                        {
                                            $hname = 'total_'.$hkey;
                                            if(isset($rval[$hname]) )
                                            {
                                                echo "<th>".$rval[$hname]."</th>";
                                                $total += $rval[$hname];
                                                $$hkey += $rval[$hname];
                                            }
                                            else
                                            {
                                                echo "<th>0</th>";
                                            }
                                        }
                                        $grand_total += $total;
                                    @endphp
                                    <td>{{$total}}</td>
                                </tr>
                            @endforeach
                            <tr>
                                <td>{{$i++}}</td>
                                <td align="right"><b>Total :</b></td>
                                @php
                                    foreach($data['heading_arr'] as $h1key => $h1val)
                                    {
                                        echo "<td><b>".$$h1key."</b></td>";
                                    }
                                @endphp
                                <td><b>{{$grand_total}}</b></td>
                            </tr>

                            @if( count($data['report_data']) == 0 )
                                <tr align="center">
                                    <td colspan="20">No Records Found!</td>
                                </tr>
                            @endif
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
    function check_dates() {
        var start = new Date($('#from_date').val());
        var end = new Date($('#to_date').val());

        var diff = new Date(end - start);
        var days = 1;
        days = diff / 1000 / 60 / 60 / 24;
        days = days + 1;
        if (days > 31) {
            alert("Maximum Days should be 31");
            return false;
        } else {
            return true;
        }
    }
</script>
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
                    title: 'Fees Monthly Report',
                    orientation: 'landscape',
                    pageSize: 'LEGAL',
                    pageSize: 'A0',
                    exportOptions: {
                        columns: ':visible'
                    },
                },
                {extend: 'csv', text: ' CSV', title: 'Fees Monthly Report'},
                {extend: 'excel', text: ' EXCEL', title: 'Fees Monthly Report'},
                {extend: 'print', text: ' PRINT', title: 'Fees Monthly Report'},
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
