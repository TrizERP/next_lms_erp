@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')
<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Pal Report</h4>
            </div>
        </div>
        <div class="card">
            @if(!empty($data['message']))
                <div class="alert alert-success alert-block">
                    <button type="button" class="close" data-dismiss="alert">×</button>
                    <strong>{{ $data['message'] }}</strong>
                </div>
                @endif

            @if( isset($data['data']) )
				<div class="col-lg-12 col-sm-12 col-xs-12">
                <div class="table-responsive">
                    <table id="example" class="table table-striped" >
                        <thead>
                            <tr>
                                <th>Student Name</th>
                                <th>Std/Div</th>
                                <th>Started</th>
                                <th>Grade</th>
                            </tr>
                        </thead>
                        <tbody>
                        @foreach($data['data'] as $key => $data)
                            <tr>
                                <td>{{$data->first_name}} {{$data->middle_name}} {{$data->last_name}}</td>
                                <td>{{$data->standard}}/{{$data->division}}</td>
                                <td>{{$data->start_time}}</td>
                                <td>{{$data->grade}}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                </div>
            @endif
        </div>
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
                    title: 'Pal Report',
                    orientation: 'landscape',
                    pageSize: 'LEGAL',
                    pageSize: 'A0',
                    exportOptions: {
                        columns: ':visible'
                    },
                },
                {extend: 'csv', text: ' CSV', title: 'Pal Report'},
                {extend: 'excel', text: ' EXCEL', title: 'Pal Report'},
                {extend: 'print', text: ' PRINT', title: 'Pal Report'},
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