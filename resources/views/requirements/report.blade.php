@extends('layout')
@section('container')
<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Customre Requirements</h4>
            </div>
        </div>
      
        <div class="card">
            <div class="table-responsive mt-20 tz-report-table">
                <table id="example" class="table table-striped">
                    <thead>
                    <tr>
                        <th>Sr No</th>
                        <th>Menu Name</th>
                        <th>Requirements</th>
                        <th>Institute Name</th>
                        <th>Contact Person</th>
                        <th>Mobile</th>
                        <th>Email</th>
                        <th>Created By</th>
                        <th class="text-left">View</th>
                    </tr>
                    </thead>
                    <tbody>
                        @foreach($data['allRequirement'] as $key=>$value)
                        <tr>
                            <td>{{$key+1}}</td>
                            <td>{{$value['menu_name']}}</td>
                            <td>{!! substr(strip_tags($value['requirements']), 0, 50) !!}....</td>
                            <td>{{$value['SchoolName']}}</td>
                            <td>{{$value['ContactPerson']}}</td>
                            <td>{{$value['mobile']}}</td>
                            <td>{{$value['email']}}</td>
                            <td>{{$value['created_by_name'] ?? '-'}}</td>
                            <td>
                                <div class="d-inline">
                                    <a href="{{ route('requirements.edit',$value['id'])}}?view=1" class="btn btn-info btn-outline" target="_blank">
                                       View
                                    </a>
                                </div> 
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

    @include('includes.footerJs')
    <script>
        $(document).ready(function () {
            var table = $('#example').DataTable({
                ordering: false,
                select: true,
                lengthMenu: [
                    [100, 500, 1000, -1],
                    ['100', '500', '1000', 'Show All']
                ],
                dom: 'Bfrtip',
                buttons: [
                    {
                        extend: 'pdfHtml5',
                        title: 'Student Report',
                        orientation: 'landscape',
                        pageSize: 'LEGAL',
                        pageSize: 'A0',
                        exportOptions: {
                            columns: ':visible'
                        },
                    },
                    {extend: 'csv', text: ' CSV', title: 'Student Report'},
                    {extend: 'excel', text: ' EXCEL', title: 'Student Report'},
                    {extend: 'print', text: ' PRINT', title: 'Student Report'},
                    'pageLength'
                ],
            });
            //table.buttons().container().appendTo('#example_wrapper .col-md-6:eq(0)');

            $('#example thead tr').clone(true).appendTo('#example thead');
            $('#example thead tr:eq(1) th').each(function (i) {
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
    </script>
@include('includes.footer')
@endsection
