@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')
<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Leave Sumary for Designation</h4>
            </div>
        </div>
    
        <div class="card">
            @if(isset($data['status_code']) && $data['status_code']==0)
                <div class="alert alert-danger alert-block">
                    <button type="button" class="close" data-dismiss="alert">×</button>
                    <strong>{{ $data['message'] }}</strong>
                </div>
            @endif
            <form action="{{route('designation_leave.store')}}" class="row" method="post">
                @csrf
                <div class="col-md-4 form-group">
                    <label for="">Select Departments</label>
                    <select name="department_ids" id="department_ids" class="form-control" required>
                        <option value="All">All</option>
                        @foreach($data['departments'] as $key=>$value)
                        <option value="{{$key}}">{{$value}}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-4 form-group">
                    <label for="">Select Leave Type</label>
                    <select name="leave_type_ids[]" id="leave_type_ids" class="form-control" multiple required>
                        @foreach($data['leave_types'] as $key=>$value)
                        <option value="{{$key}}">{{$value}}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-4 form-group">
                    <label for="">Select Year</label>
                    <select name="year" id="year" class="form-control" required>
                        @foreach($data['years'] as $key=>$value)
                        <option value="{{$value}}">{{$value}}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-4 form-group">
                    <label for="">Days</label>
                   <input type="number" name="days" id="days" class="form-control" required>
                </div>

                <div class="col-md-12">
                     <center>
                     <input type="submit" name="save" value="Add" class="btn btn-primary">
                     </center>
                </div>

            </form>
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
