@extends('layout')
@section('container')
<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">fees AI</h4>
            </div>
        </div>        
        <div class="card">
            @if (isset($data['status_code']))
                @if($data['status_code'] == 1)
                    <div class="alert <!-- alert -->-success alert-block">
                @else
                    <div class="alert alert-danger alert-block">
                @endif
                <button type="button" class="close" data-dismiss="alert">×</button>
                <strong>{{ $data['message'] }}</strong>
                </div>
            @endif

            @if(!empty($data['student_details']))
                <div class="table-responsive mt-20 tz-report-table">
                    {!! App\Helpers\get_school_details() !!}
                    <table id="example" class="table table-striped">
                        <thead>
                            <tr>
                                <th>Sr No.</th>
                                <th>{{ App\Helpers\get_string('studentname')}}</th>
                                <th>{{ App\Helpers\get_string('grno')}}</th>
                                <th>Mobile</th>                                
                                <th>{{ App\Helpers\get_string('std/div')}}</th>
                                <th>AI Pre-Process Amount</th>
                                <th>AI Prediction Amount</th>
                            </tr>
                        </thead>
                        @foreach($data['student_details'] as $key => $student_val)
                            @foreach($student_val as $student_id => $value)
                            <tr>
                                <td>{{$key+1}}</td>
                                <td>{{$value['student_name']}}</td>  
                                <td>{{$value['enrollment_no']}}</td>
                                <td>{{$value['mobile']}}</td>
                                <td>{{$value['standard_name'].'/'.$value['division_name']}}</td>
                                <td>{{$value['prediction']}}</td>     
                                <td>{{$value['true_label']}}</td>
                            </tr>
                            @endforeach
                        @endforeach
                    </table>
                </div>
            @endif
        </div>
        <!-- card over  -->
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