@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
            <div class="row bg-title">
                <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                    <h4 class="page-title">Fees Structure Report</h4> </div>
            </div>
        @php
        $grade_id = $standard_id = '';

            if(isset($data['grade_id'])){
                $grade_id = $data['grade_id'];
                $standard_id = $data['standard_id'];
            }
        @endphp

                <div class="card">
                    @if ($sessionData = Session::get('data'))
                    <div class="alert alert-success alert-block">
                        <button type="button" class="close" data-dismiss="alert">×</button>
                        <strong>{{ $sessionData['message'] }}</strong>
                    </div>
                    @endif
                    <form action="{{ route('fees_structure_report') }}" method="POST">
                        @csrf
                        <div class="row">
                            {{ App\Helpers\SearchChain('4','single','grade,std',$grade_id,$standard_id) }}

                            <div class="col-md-2 form-group mt-4">
                                <input type="submit" name="submit" value="Search" class="btn btn-success">
                            </div>
                        </div>
                    </form>
                </div>

        @if(isset($data['report_data']))

            <div class="card">

                <div class="col-lg-12 col-sm-12 col-xs-12">
                    <div class="table-responsive">
                        <table id="example" class="table table-striped table-bordered">
                            <thead>
                            <tr>
                                <th rowspan="2">Sr.No.</th>
                                <th rowspan="2">{{ App\Helpers\get_string('standard','request')}}</th>
                                <th rowspan="2">{{ App\Helpers\get_string('studentquota','request')}}</th>
                                <th colspan="13" style="text-align:center;">New Student</th>
                                <th colspan="13" style="text-align:center;">Old Student</th>
                            </tr>
                            <tr>
                                @foreach($data['months_arr'] as $key => $val)
                                    <th>{{$val}}</th>
                                @endforeach
                                <th>Total</th>
                                @foreach($data['months_arr'] as $key => $val)
                                    <th>{{$val}}</th>
                                @endforeach
                                <th>Total</th>
                            </tr>
                            </thead>
                            <tbody>
                            @php
                                $j=1;
                                if(count($data['report_data']) > 0)
                                {
                                    foreach($data['report_data'] as $std => $quota_data)
                                    {
                                        $colspan = count($quota_data) + 1;
                                        echo "<tr><td rowspan='$colspan'>".$j++."</td>";
                                        echo "<td rowspan='$colspan'>$std</td>";
                                        foreach($quota_data as $quota => $type_data)
                                        {
                                            echo "<tr><td>$quota</td>";
                                            foreach($type_data as $type => $amt_data)
                                            {
                                                $total = 0;
                                                foreach($data['months_arr'] as $month_id => $new_amt)
                                                {
                                                    if(isset($amt_data[$month_id]) )
                                                    {
                                                        echo "<td>$amt_data[$month_id]</td>";
                                                        $total += $amt_data[$month_id];
                                                    }else
                                                    {
                                                        echo "<td>-</td>";
                                                    }
                                                }
                                                echo "<td>$total</td>";
                                            }
                                            echo "</tr>";
                                        }
                                    }
                                }
                                else{
                                    echo "<tr><td colspan=30 align=center>No Records</td></tr>";
                                }
								@endphp

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
    // $(document).ready(function() {
    // // Setup - add a text input to each footer cell
    // $('#example thead tr').clone(true).appendTo( '#example thead' );
    // $('#example thead tr:eq(1) th').each( function (i) {
        // var title = $(this).text();
        // $(this).html( '<input type="text" placeholder="Search '+title+'" />' );

    // $( 'input', this ).on( 'keyup change', function () {
            // if ( table.column(i).search() !== this.value ) {
                // table
                    // .column(i)
                    // .search( this.value )
                    // .draw();
            // }
        // } );
    // } );

    // var table = $('#example').DataTable( {
        // orderCellsTop: true,
        // fixedHeader: true,
        // dom: 'Bfrtip',
        // buttons: [
            // 'copyHtml5',
            // 'excelHtml5',
            // 'csvHtml5',
            // 'pdfHtml5'
        // ]
    // } );
// } );
</script>

@include('includes.footer')
