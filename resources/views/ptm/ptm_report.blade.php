@extends('layout')
@section('container')

    <div id="page-wrapper">
        <div class="container-fluid">
            <div class="row bg-title">
                <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                    <h4 class="page-title">PTM Report</h4>
                </div>
            </div>

            <div class="card">
                @if ($sessionData = Session::get('data'))
                    <div class="@if($sessionData['status_code']==1) alert alert-success alert-block @else alert alert-danger alert-block @endif ">
                        <button type="button" class="close" data-dismiss="alert">×</button>
                        <strong>{{ $sessionData['message'] }}</strong>
                    </div>
                @endif

                @if ($message = Session::get('success'))
                    <div class="alert alert-success alert-block">
                        <button type="button" class="close" data-dismiss="alert">×</button>
                        <strong>{{ $message }}</strong>
                    </div>
                @endif
                @php
                    $grade_id = $standard_id = $division_id = '';
                    if(isset($data['grade_id'])){
                        $grade_id = $data['grade_id'];
                    }
                    if(isset($data['standard_id'])){
                        $standard_id = $data['standard_id'];
                    }
                    if(isset($data['division_id'])){
                        $division_id = $data['division_id'];
                    }
                @endphp
                <form action="{{ route('ptm_report.index') }}" method="GET">
                    {{ method_field("POST") }}
                    @csrf
                    <div class="row">
                        <div class="col-md-12 form-group">
                            <div class="row">
                                {{ App\Helpers\SearchChain('4','single','grade,std,div',$grade_id,$standard_id,$division_id) }}                          
                            </div>
                        </div> 
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
                                <input type="submit" name="Search" value="Search" class="btn btn-success">
                            </center>
                        </div>
                    </div>
                </form>

            </div>

            @php
                $j = 1;
            @endphp
            @if(isset($data['details']) && !empty($data['details']))
            <div class="card">
                <div class="table-responsive">
                    <table class="table table-box table-bordered" id="example">
                        <thead>
                        <tr>
                            <th>Sr No.</th>
                            <th>{{ App\Helpers\get_string('studentname','request')}}</th>
                            <th>{{ App\Helpers\get_string('std/div','request')}}</th>
                            <th>Mobile</th>
                            <th>PTM Title</th>
                            <th>PTM Date</th>
                            <th>PTM Time</th>
                            <th>Attend Status</th>
                            <th class="text-left">Attend Remarks</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($data['details'] as $key => $val)
                            <tr>
                                <td>{{$key+1}}</td>
                                <td>{{$val->student_name}}</td>
                                <td>{{$val->standard.'/'.$val->division}}</td>
                                <td>{{$val->mobile}}</td>
                                <td>{{$val->slot_title}}</td>
                                <td>{{date($val->DATE,strtotime('d-m-Y'))}}</td>
                                <td>{{ date('h:i:s a', strtotime($val->from_time)) . ' - ' . date('h:i:s a', strtotime($val->to_time)) }}</td>
                                <td>{{$val->PTM_ATTENDED_STATUS}}</td>
                                <td>{{($val->PTM_ATTENDED_REMARKS) ? $val->PTM_ATTENDED_REMARKS : '-'}}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
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
                    title: 'PTM Report',
                    orientation: 'landscape',
                    pageSize: 'LEGAL',
                    pageSize: 'A0',
                    exportOptions: {
                        columns: ':visible'
                    },
                },
                {extend: 'csv', text: ' CSV', title: 'PTM Report'},
                {extend: 'excel', text: ' EXCEL', title: 'PTM Report'},
                {extend: 'print', text: ' PRINT', title: 'PTM Report'},
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
@endsection
