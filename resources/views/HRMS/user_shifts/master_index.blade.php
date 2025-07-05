@extends('layout')
@section('container')
    <link rel="stylesheet" href="/plugins/bower_components/clockpicker/dist/jquery-clockpicker.min.css">

    <div id="page-wrapper">
        <div class="container-fluid">
            <div class="row bg-title">
                <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                    <h4 class="page-title">Shift Master</h4>
                </div>
            </div>

            <div class="card" id="shiftForm">
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
            <form action="{{ route('user_shift_master.store') }}" method="post" id="shiftFormElement">
                @csrf
                <input type="hidden" name="id" id="editId">
                <div id="shiftRows">
                    <div class="row mt-2 cloneRaw">
                        <div class="col-md-4">
                            <label for="shiftTitle">Shift Title</label>
                            <input type="text" name="shift_name" id="shiftName" class="form-control" placeholder="Enter Shift Title" required>
                        </div>
                        <div class="col-md-4">
                            <label for="startTime">Shift Start Time</label>
                            <input type="text" name="start_time" id="startTime" class="form-control clockpicker" placeholder="Enter Time H:i" required autocomplete="off">
                            <span class="input-group-addon"><span class="glyphicon glyphicon-time"></span></span>
                        </div>
                        <div class="col-md-4">
                            <label for="endTime">Shift End Time</label>
                            <input type="text" name="end_time" id="endTime" class="form-control clockpicker" placeholder="Enter Time H:i" required autocomplete="off">
                            <span class="input-group-addon"><span class="glyphicon glyphicon-time"></span></span>
                        </div>
                    </div>
                </div>
                <div class="col-md-12 mt-2">
                    <center>
                        <button type="submit" id="submitBtn" class="btn btn-primary">Submit</button>
                        <button type="button" id="cancelEdit" class="btn btn-secondary" style="display:none;">Cancel</button>
                    </center>
                </div>
            </form>
        </div>

        @if (isset($data['shift_data']) && !empty($data['shift_data']))
            <div class="card">
                <div class="table-responsive mt-20 tz-report-table">
                    <table id="example2" class="table table-striped">
                        <thead>
                            <tr>
                                <th>Sr No.</th>
                                <th>Shift Title</th>
                                <th>Shift Start Time</th>
                                <th>Shift End Time</th>
                                <th class="text-left">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($data['shift_data'] as $key => $value)
                                <tr>
                                    <td>{{ $key + 1 }}</td>
                                    <td>{{ $value['shift_name'] }}</td>
                                    <td>{{ \Carbon\Carbon::parse($value['start_time'])->format('h:i A') }}</td>
                                    <td>{{ \Carbon\Carbon::parse($value['end_time'])->format('h:i A') }}</td>
                                    <td>
                                        <div class="d-inline">
                                            <a class="btn btn-info btn-outline edit-btn" data-id="{{ $value['id'] }}" 
                                               data-shift-name="{{ $value['shift_name'] }}" 
                                               data-start-time="{{ $value['start_time'] }}" 
                                               data-end-time="{{ $value['end_time'] }}">
                                                <i class="ti-pencil-alt"></i>
                                            </a>
                                        </div>
                                        <form action="{{ route('user_shift_master.destroy', $value['id']) }}" method="post" class="d-inline">
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
        @endif
    </div>

    @include('includes.footerJs')
    <script>
        $(document).ready(function() {
            // Initialize DataTable
            var table = $('#example2').DataTable({
                select: true,
                lengthMenu: [
                    [100, 500, 1000, -1],
                    ['100', '500', '1000', 'Show All']
                ],
                dom: 'Bfrtip',
                buttons: [
                    {
                        extend: 'pdfHtml5',
                        title: 'Add Shift',
                        orientation: 'landscape',
                        pageSize: 'LEGAL',
                        pageSize: 'A0',
                        exportOptions: {
                            columns: ':visible'
                        },
                    },
                    {extend: 'csv', text: ' CSV', title: 'Add Shift'},
                    {extend: 'excel', text: ' EXCEL', title: 'Add Shift'},
                    {extend: 'print', text: ' PRINT', title: 'Add Shift'},
                    'pageLength'
                ],
            });
            //table.buttons().container().appendTo('#example_wrapper .col-md-6:eq(0)');

            $('#example2 thead tr').clone(true).appendTo('#example2 thead');
            $('#example2 thead tr:eq(1) th').each(function (i) {
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

            // Initialize clockpicker
            $('.clockpicker').clockpicker({
                donetext: 'Done',
            }).find('input').change(function() {
                console.log(this.value);
            });

            // Edit button click handler
            $(document).on('click', '.edit-btn', function() {
                var id = $(this).data('id');
                var shiftName = $(this).data('shift-name');
                var startTime = $(this).data('start-time');
                var endTime = $(this).data('end-time');
                // alert('startTime='+startTime+' endTime'+endTime);
                // Set form values
                $('#editId').val(id);
                $('#shiftName').val(shiftName);
                $('#startTime').val(startTime);
                $('#endTime').val(endTime);
                
                // Change submit button text
                $('#submitBtn').text('Update');
                $('#cancelEdit').show();
                
                // Scroll to form
                $('html, body').animate({
                    scrollTop: $('#shiftForm').offset().top - 20
                }, 500);
                
                // Focus on first field
                $('#shiftName').focus();
            });

            // Cancel edit button
            $('#cancelEdit').click(function() {
                resetForm();
            });

            // Form submit handler
            $('#shiftFormElement').submit(function(e) {
                // You can add additional validation here if needed
                return true;
            });

            // Reset form function
            function resetForm() {
                $('#editId').val('');
                $('#shiftFormElement')[0].reset();
                $('#submitBtn').text('Submit');
                $('#cancelEdit').hide();
            }

            // Confirm delete function
            function confirmDelete() {
                return confirm('Are you sure you want to delete this shift?');
            }
        });
    </script>
    @include('includes.footer')
@endsection