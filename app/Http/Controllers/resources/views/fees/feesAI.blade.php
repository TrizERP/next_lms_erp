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

            @if(!empty($data['standard_data']))
                <div class="table-responsive mt-20 tz-report-table">
                    {!! App\Helpers\get_school_details() !!}
                    <table id="example" class="table">
                        <thead>
                            <tr>
                                <th class="text-center">Standard</th>
                                <th class="text-center" colspan="2">Months</th>
                            </tr>
                        </thead>
                        @foreach($data['standard_data'] as $std_name => $values)
                        <tr style="background:#ddd !important">
                            <td class="text-center" >{{$std_name}}</td>
                            <td class="text-left" colspan="2">{{$values['total_prediction']}}</td>
                        </tr>
                            @foreach($values['all_data'] as $key => $value)
                            @if($std_name == $value['standard_name'])
                            <tr>
                                <td class="border-none"></td>
                                <td>{{$value['month_name']}}</td>
                                <td>{{$value['Prediction']}}</td>     
                            </tr>
                            @endif
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