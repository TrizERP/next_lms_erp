@extends('layout')
@section('container')
<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Add Triz Process</h4>
            </div>
        </div>
      
        <div class="card">
            <div class="col-lg-3 col-sm-3 col-xs-3">
                <a href="{{ route('requirements.create') }}" class="btn btn-info add-new"><i class="fa fa-plus"></i> Add New Process</a>
            </div>

            <div class="table-responsive mt-20 tz-report-table">
                <table id="example" class="table table-striped">
                    <thead>
                    <tr>
                        <th>Sr No</th>
                        <th>Menu Name</th>
                        <th>Process</th>
                        <th>Created By</th>
                        <th class="text-left">Action</th>
                    </tr>
                    </thead>
                    <tbody>
                        @foreach($data['TrizProcess'] as $key=>$value)
                        <tr>
                            <td>{{$key+1}}</td>
                            <td>{{$value['menu_name']}}</td>
                            <td>{!! substr(strip_tags($value['requirements']), 0, 50) !!}....</td>
                            <td class="text-left">{{$value['created_by_name']}}</td>
                            <td>
                                <div class="d-inline">
                                    <a href="{{ route('requirements.edit',$value['id'])}}" class="btn btn-info btn-outline">
                                        <i class="ti-pencil-alt"></i>
                                    </a>
                                </div> 
                                <form action="{{ route('requirements.destroy', $value['id'])}}" method="post" class="d-inline">
                                @csrf
                                @method('DELETE')
                                    <button type="submit" onclick="return confirmDelete();" class="btn btn-info btn-outline-danger">
                                        <i class="ti-trash"></i>
                                    </button>
                                </form>
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
