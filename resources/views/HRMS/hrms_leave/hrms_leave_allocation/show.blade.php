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
        @if ($sessionData = Session::get('data')) @if($sessionData['status_code'] == 1)
			<div class="alert alert-success alert-block">
				@else
				<div class="alert alert-danger alert-block">
					@endif
					<button type="button" class="close" data-dismiss="alert">×</button>
					<strong>{{ $sessionData['message'] }}</strong>
				</div>
				@endif
            <div class="col-lg-3 col-sm-3 col-xs-3">
                <a href="{{ route('designation_leave.create') }}" class="btn btn-info add-new mb-3">
                    <i class="fa fa-plus"></i> Add Leave
                </a>
            </div>
       
    @if(isset($data['allData']) &&!empty($data['allData']))
       
            <div class="table-responsive mt-20 tz-report-table">
                <table id="example" class="table table-striped">
                    <thead>
                    <tr>
                        <th>Sr No.</th>
                        <th>Job Title</th>
                        <th>Leave Type</th>
                        <th>Leave Entitled (days)</th>
                        <th class="text-left">Action</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($data['allData'] as $key=>$value)
                        <tr>
                            <td>{{$key+1}}</td>
                            <td>{{$value->department}}</td>
                            <td>{{$value->leave_type}}</td>
                            <td>{{$value->value}}</td>
                            <td>
                                <a href="{{ route('designation_leave.edit',$value->id)}}" class="btn btn-info btn-outline"><i class="ti-pencil-alt"></i></a>
                                <form action="{{ route('designation_leave.destroy', $value->id)}}" method="post" class="d-inline">
                                @csrf
                                @method('DELETE')
                                    <button type="submit" onclick="return confirmDelete();" class="btn btn-info btn-outline-danger">    <i class="ti-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
      
        @endif
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
