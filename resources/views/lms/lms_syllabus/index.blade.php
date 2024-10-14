@extends('layout')
@section('container')
<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">LMS Syllabus</h4>
            </div>
        </div>
        <div class="col-lg-3 col-sm-3 col-xs-3 m-30">
            <a href="{{ route('lms_syllabus.create') }}" class="btn btn-info add-new">
                <i class="fa fa-plus"></i> Add New
            </a>
        </div>
        <div class="card">
            <div class="card-body">
                @if ($sessionData = Session::get('data'))
                    @if($sessionData['status_code'] == 1)
                        <div class="alert alert-success alert-block">
                    @else
                        <div class="alert alert-danger alert-block">
                            @endif
                            <button type="button" class="close" data-dismiss="alert">×</button>
                            <strong>{{ $sessionData['message'] }}</strong>
                        </div>
                    @endif
            </div>
                <div class="table-responsive">
                    <table id="example" class="table table-striped">
                        <thead>
                        <tr>
                            <th>Sr No.</th>
                            <th>Standard</th>
                            <th>Subject</th>
                            <th>Curriculum Title</th>
                            <th>Syllabus Title</th>
                            <th>Objectives</th>
                            <th>Learning Outcomes</th>
                            <th>Suggested Materials</th>
                            <th>Assesment Plans</th>
                            <th>Progressing Tracks</th>
                            <th class="text-left">Action</th>
                        </tr>
                        </thead>
                        <tbody>
                            @foreach($data['allData'] as $key=>$value)
                            <tr>
                                <td>{{$key+1}}</td>
                                <td>{{$value->standard_name}}</td>
                                <td>{{$value->subject_name}}</td>
                                <td>{{$value->curriculum_name}}</td>
                                <td>{{$value->title}}</td>
                                <td>{{$value->objectives}}</td>
                                <td>{{$value->learning_outcomes}}</td>
                                <td>{{$value->suggested_materials}}</td>
                                <td>{{$value->assessment_plan}}</td>
                                <td>{{$value->progress_tracking}}</td>
                                <td>
                                    <div class="d-inline">
                                        <a href="{{ route('lms_syllabus.edit',$value->id)}}" class="btn btn-info btn-outline">
                                            <i class="ti-pencil-alt"></i>
                                        </a>
                                    </div>
                                    <form action="{{ route('lms_syllabus.destroy', $value->id)}}" method="post" class="d-inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" onclick="return confirmDelete();" class="btn btn-outline-danger">
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
                select: true,
                lengthMenu: [
                    [100, 500, 1000, -1],
                    ['100', '500', '1000', 'Show All']
                ],
                dom: 'Bfrtip',
                buttons: [
                    {
                        extend: 'pdfHtml5',
                        title: 'LMS Curriculum Report',
                        orientation: 'landscape',
                        pageSize: 'LEGAL',
                        pageSize: 'A0',
                        exportOptions: {
                            columns: ':visible'
                        },
                    },
                    {extend: 'csv', text: ' CSV', title: 'LMS Curriculum Report'},
                    {extend: 'excel', text: ' EXCEL', title: 'LMS Curriculum Report'},
                    {
                        extend: 'print',
                        text: ' PRINT',
                        title: 'LMS Curriculum Report',
                    },
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