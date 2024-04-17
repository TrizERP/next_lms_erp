@extends('layout')
@section('container')
<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Whatsapp Send Messages</h4>
            </div>
        </div>
        <div class="card">
            <div class="row">
                <div class="col-lg-3 col-sm-3 col-xs-3">
                    <a href="{{ route('whatsapp_send_message.create') }}" class="btn btn-info add-new"><i
                            class="fa fa-plus"></i> Whatsapp Send Message </a>
                </div>
            </div>
            <div class="col-lg-12 col-sm-12 col-xs-12">
                <div class="table-responsive">
                    <table id="example" class="table table-striped">
                        <thead>
                        <tr>
                            <th>Id</th>
                            <th>Standard</th>
                            <th>Division</th>
                            <th>Student Id</th>
                            <th>Created By</th>
                            <th>Date</th>
                            <th>Message</th>
                        </tr>
                        </thead>
                        <tbody>
                        @php
                            $j=1;
                        @endphp
                        @foreach($data['data'] as $key => $data)
                            <tr>
                                <td>{{$j}}</td>
                                <td>{{$data->standard_id}}</td>
                                <td>{{$data->division_id}}</td>
                                <td>{{$data['student_id']}}</td>
                                <td>{{$data->created_by_name ?? '-'}}</td>
                                <td>{{$data->created_at ?? '-'}}</td>
                                <td>{{$data->message ?? '-'}}</td>
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
        </div>

    </div>
</div>

@include('includes.footerJs')

<script src="{{ asset("/plugins/bower_components/datatables/datatables.min.js") }}"></script>

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
@endsection
