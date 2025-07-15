@extends('layout')
@section('container')
    <style>
        .steps-wrapper .step h6 {
            font-weight: 600;
        }

        .steps-wrapper .step p {
            margin-left: 10px;
        }
    </style>
    <div id="page-wrapper">
        <div class="container-fluid">
            <div class="row bg-title">
                <div class="col-md-3 d-flex">
                    <h4 class="page-title">
                        Add Class Work
                    </h4>
                </div>
                <div class="col-md-9 d-flex justify-content-end">
                    <a class="mdi mdi-information" title="Click to view upload process"
                        style="font-size:24px;padding-right:10px;" data-toggle="modal" data-target="#exampleModal"></a>
                    <a href="{{ route('send_attachment.create') }}" class="mdi mdi-chart-bar" title="Click to view report"
                        target="_blank" style="font-size:24px;padding-right:10px;"></a>
                </div>
            </div>
            <div class="card">
                @if ($sessionData = Session::get('data'))
                    @if ($sessionData['status'] == 1)
                        <div class="alert alert-success alert-block">
                        @else
                            <div class="alert alert-danger alert-block">
                    @endif
                    <button type="button" class="close" data-dismiss="alert">×</button>
                    <strong>{{ $sessionData['message'] }}</strong>
            </div>
            @endif
            <form action="{{ route('send_attachment.store') }}" method="post" enctype="multipart/form-data" class="row"
                id="uploadForm">
                @csrf
                <div class="col-md-3">
                    <label for="title">Title</label>
                    <input type="text" name="title" class="form-control" placeholder="Enter Title" required>
                </div>
                <div class="col-md-3">
                    <label for="folderInput">Select Folder</label>
                    <input type="file" id="folderInput" name="files[]" webkitdirectory directory multiple required />
                </div>
                <div class="col-md-3">
                    <input type="submit" value="Submit" class="btn btn-success">
                </div>
            </form>
        </div>

        <div class="card">
            <div class="table-responsive">
                <table id="example" class="table table-striped">
                    <thead>
                        <tr>
                            <th>Sr No.</th>
                            <th>Title</th>
                            <th>{{ App\Helpers\get_string('grno') }}</th>
                            <th>{{ App\Helpers\get_string('studentname') }}</th>
                            <th>File</th>
                            <th>Date</th>
                            <th>Created By</th>
                            <th class="text-left">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @if (isset($data['sentData']) && !empty($data['sentData']))
                            @foreach ($data['sentData'] as $key => $value)
                                <tr>
                                    <td>{{ $key + 1 }}</td>
                                    <td>{{ $value['title'] }}</td>
                                    <td>{{ isset($value['student']) ? $value['student']['enrollment_no'] : 'N/A' }}</td>
                                    <th>{{ isset($value['student']) ? $value['student']['first_name'] . ' ' . $value['student']['last_name'] : 'N/A' }}
                                    </th>
                                    <td>
                                        @if (isset($value['file_path']))
                                            <a href="{{ $value['file_path'] }}" target="_blank">View File</a>
                                        @else
                                            No File
                                        @endif
                                    </td>
                                    <td>{{ date('d-m-Y', strtotime($value['created_at'])) }}</td>
                                    <td>{{ isset($value['created_by']) ? $value['created_by']['first_name'] . ' ' . $value['created_by']['last_name'] : 'N/A' }}
                                    </td>
                                    <td>
                                        <form action="{{ route('send_attachment.destroy', [$value['id']]) }}"
                                            method="post">
                                            @csrf
                                            @method('DELETE')
                                            <button onclick="return confirmDelete();" type="submit"
                                                class="btn btn-danger btn-outline"><i class="ti-trash"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        @endif
                    </tbody>
                </table>
            </div>
        </div>

    </div>
    </div>

    <!-- Modal -->
    <div class="modal fade" id="exampleModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel"
        aria-hidden="true">
        <div class="modal-dialog" role="document" style="max-width: 1000px;">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="exampleModalLabel">Folder Upload Process</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body" style="width:fit-content;">
                    <div class="container mb-4">
                        <div class="row">
                            <div class="col-md-12">
                                <div class="steps-wrapper" style="border-left: 3px solid #1d6b2f; padding-left: 15px;">
                                    <div class="step mb-4">
                                        <h6 class="text-primary mb-1">Step 1: Add Classwork Title</h6>
                                        <p class="mb-0">📝 Add Classwork Title in Input field.</p>
                                    </div>
                                    <div class="step mb-4">
                                        <h6 class="text-primary mb-1">Step 2: Click the Folder Picker</h6>
                                        <p class="mb-0">📁 Click on the file input below to begin.</p>
                                    </div>
                                    <div class="step mb-4">
                                        <h6 class="text-primary mb-1">Step 3: Select Folder or Sub-folder</h6>
                                        <p class="mb-0">📂 Choose a folder containing student files (subfolders allowed).
                                        </p>
                                    </div>
                                    <div class="step mb-4">
                                        <h6 class="text-primary mb-1">Step 4: File Format & Naming</h6>
                                        <p class="mb-0">📝 Files should be named with student enrollment numbers, e.g.
                                            <code>123456.png</code>, <code>654321.pdf</code>, etc.</p>
                                    </div>
                                    <div class="step mb-0">
                                        <h6 class="text-primary mb-1">Step 4: Send Notifications</h6>
                                        <p class="mb-0">🚀 After selecting, click <strong>Submit</strong> to notify the
                                            students.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>

    @include('includes.footerJs')
    <script>
        $(document).ready(function() {
            var table = $('#example').DataTable({
                select: true,
                lengthMenu: [
                    [100, 500, 1000, -1],
                    ['100', '500', '1000', 'Show All']
                ],
                dom: 'Bfrtip',
                buttons: [{
                        extend: 'pdfHtml5',
                        title: 'Classwork Attachments',
                        orientation: 'landscape',
                        pageSize: 'LEGAL',
                        pageSize: 'A0',
                        exportOptions: {
                            columns: ':visible'
                        },
                    },
                    {
                        extend: 'csv',
                        text: ' CSV',
                        title: 'Classwork Attachments'
                    },
                    {
                        extend: 'excel',
                        text: ' EXCEL',
                        title: 'Classwork Attachments'
                    },
                    {
                        extend: 'print',
                        text: ' PRINT',
                        title: 'Classwork Attachments'
                    },
                    'pageLength'
                ],
            });

            $('#example thead tr').clone(true).appendTo('#example thead');
            $('#example thead tr:eq(1) th').each(function(i) {
                var title = $(this).text();
                $(this).html('<input type="text" placeholder="Search ' + title + '" />');

                $('input', this).on('keyup change', function() {
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
