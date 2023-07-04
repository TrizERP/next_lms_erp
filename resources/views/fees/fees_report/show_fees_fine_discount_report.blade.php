@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
            <div class="row bg-title">
                <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                    <h4 class="page-title">Fees Fine Discount Report</h4> </div>
            </div>
        @php
        $grade_id = $standard_id = $division_id =  $from_date = $to_date = '';

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
                    <form action="{{ route('fees_fine_discount_report') }}" method="POST">
                        @csrf
                         <div class="row">
                            {{ App\Helpers\SearchChain('4','single','grade,std,div',$grade_id,$standard_id,$division_id) }}

                            <div class="col-md-4 form-group ml-0 mr-0">
                                <label>From Date</label>
                                <input type="text" id="from_date" @if(isset($data['from_date'])) value="{{$data['from_date']}}"  @endif name="from_date" class="form-control mydatepicker" required="required" autocomplete="off">
                            </div>

                            <div class="col-md-4 form-group ml-0">
                                <label>To Date</label>
                                <input type="text" id="to_date" @if(isset($data['to_date'])) value="{{$data['to_date']}}" @endif name="to_date"
                                       class="form-control mydatepicker" required="required" autocomplete="off">
                            </div>

                             <div class="col-md-12 form-group">
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
                                    <th>{{ App\Helpers\get_string('grno','request')}}</th>
                                    <th>{{ App\Helpers\get_string('studentname','request')}}</th>
                                    <th>Gender</th>
                                    <th>{{ App\Helpers\get_string('standard','request')}}</th>
                                    <th>{{ App\Helpers\get_string('division','request')}}</th>
                                    <th>Remark</th>
                                    <th>Rec.No</th>
                                    <th>Paid Date</th>
                                    <th>Fees Mafi</th>
                                    <th>Fine</th>
                                </tr>
                            </thead>
                            <tbody>
                                    @php
                                    $j=1;
                                    @endphp
                                @foreach($report_data as $key => $data)
                                <tr>
                                    <td>{{$j}}</td>
                                    <td>{{$data['enrollment_no']}}</td>
                                    <td>{{$data['STUDENT_NAME']}}</td>
                                    <td>{{$data['GENDER']}}</td>
                                    <td>{{$data['std']}}</td>
                                    <td>{{$data['div_name']}}</td>
                                    <td>{{$data['COMMENT']}}</td>
                                    <td>{{$data['receipt_no']}}</td>
                                    <td>{{$data['RECEIVED_DATE']}}</td>
                                    <td>{{$data['FEES_MAFI']}}</td>
                                    <td>{{$data['FINE']}}</td>
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
                    title: 'Fees Fine Discount Report',
                    orientation: 'landscape',
                    pageSize: 'LEGAL',
                    pageSize: 'A0',
                    exportOptions: {
                        columns: ':visible'
                    },
                },
                {extend: 'csv', text: ' CSV', title: 'Fees Fine Discount Report'},
                {extend: 'excel', text: ' EXCEL', title: 'Fees Fine Discount Report'},
                {extend: 'print', text: ' PRINT', title: 'Fees Fine Discount Report'},
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
