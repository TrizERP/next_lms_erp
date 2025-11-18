@extends('layout')
@section('container')

<div id="page-wrapper">
    <div class="container-fluid">

        <div class="row bg-title">
            <div class="col-lg-3 col-sm-4">
                <h4 class="page-title">Circular Report</h4>
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
                <form action="{{ route('circular.report.index') }}" method="GET">
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
        @if(isset($result) && count($result) > 0)
        <div class="card mt-3">
            <div class="table-responsive">
                <table class="table table-bordered" id="example">
                    <thead>
                        <tr>
                            <th>Sr No</th>
                            <th>Syear</th>
                            <th>Type</th>
                            <th>Title</th>
                            <th>Message</th>
                            <th>Date</th>
                            <th>Standard</th>
                            <th>Division</th>
                            <th>File</th>
                             <th>Action</th>
                        </tr>
                    </thead>

                    <tbody>
                        @php $i = 1; @endphp

                        @foreach($result as $row)
                            <tr>
                                <td>{{ $i++ }}</td>
                                <td>{{ $row->syear }}</td>
                                <td>{{ $row->circular_type }}</td>
                                <td>{{ $row->title }}</td>
                                <td style="white-space: break-spaces;">{{ $row->message }}</td>
                                <td>{{ date('d-m-Y', strtotime($row->date_)) }}</td>
                                <td>{{ $row->std_name }}</td>
                                <td>{{ $row->div_name }}</td>
                                <td>
                                    @if($row->file_name)
                                        <a href="{{ asset('storage/circular/'.$row->file_name) }}" target="_blank">View</a>
                                    @else
                                        - <td>
                                    <form action="{{ route('circular.destroy', $row->id)}}" method="post">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" name="delete" onclick="return confirmDelete();" class="btn btn-info btn-outline-danger"><i class="mdi mdi-close"></i></button>
                                    </form>
                                </td>
                                    @endif
                                </td>
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
                    title: 'circular Report',
                    orientation: 'landscape',
                    pageSize: 'LEGAL',
                    pageSize: 'A0',
                    exportOptions: {
                        columns: ':visible'
                    },
                },
                {extend: 'csv', text: ' CSV', title: 'circular Report'},
                {extend: 'excel', text: ' EXCEL', title: 'circular Report'},
                {extend: 'print', text: ' PRINT', title: 'circular Report'},
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
