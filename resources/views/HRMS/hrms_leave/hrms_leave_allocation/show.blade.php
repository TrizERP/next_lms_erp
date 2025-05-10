@extends('layout')
@section('container')
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

                <div class="sttabs tabs-style-linemove triz-verTab bg-white style2 text-center">
                    <ul class="nav nav-tabs tab-title mb-4">
                        <li class="nav-item"><a href="#section-linemove-1" class="nav-link active" aria-selected="true" data-toggle="tab"><span>Department wise Leave</span></a></li>
                        <li class="nav-item"><a href="#section-linemove-2" class="nav-link" aria-selected="false" data-toggle="tab"><span>Employee wise Leave</span></a></li>
                    </ul>
                        
                        
                        <div class="tab-content">
                            {{-- department leave starts  --}}
                            <div class="tab-pane p-3 active" id="section-linemove-1" role="tabpanel"> 
                                <div class="col-lg-12 col-sm-12 col-xs-12 text-right">
                                    <a href="{{ route('designation_leave.create') }}?view=department" class="btn btn-primary add-new mb-3">
                                        <i class="fa fa-plus"></i> Add Department Leave
                                    </a>
                                </div>
                            
                                    <div class="table-responsive mt-20 tz-report-table">
                                        <table id="example" class="table table-striped">
                                            <thead>
                                            <tr>
                                                <th>Sr No.</th>
                                                <th>Designation</th>
                                                <th>Leave Type</th>
                                                <th>Leave Entitled (days)</th>
                                                <th class="text-left">Action</th>
                                            </tr>
                                            </thead>
                                            @if(isset($data['departmentData']) &&!empty($data['departmentData']))
                                            <tbody>
                                            @foreach($data['departmentData'] as $key=>$value)
                                                <tr>
                                                    <td>{{$key+1}}</td>
                                                    <td>{{$value->department}}</td>
                                                    <td>{{$value->leave_type}}</td>
                                                    <td>{{$value->value}}</td>
                                                    <td class="d-flex justify-content-around">
                                                        <a href="{{ route('designation_leave.edit',$value->id)}}?view=department" class="btn btn-info btn-outline"><i class="ti-pencil-alt"></i></a>
                                                        <form action="{{ route('designation_leave.destroy', $value->id)}}" method="post" class="d-inline">
                                                        @csrf
                                                        @method('DELETE')
                                                            <button type="submit" onclick="return confirmDelete();" class="btn btn-outline-danger d-block" style="display:block !important"><i class="mdi mdi-delete"></i>
                                                            </button>
                                                        </form>
                                                    </td>
                                                </tr>
                                            @endforeach
                                            </tbody>
                                            @endif
                                        </table>
                                    </div>
                            </div>
                            {{-- department leave ends  --}}
                            {{-- employee leave starts  --}}
                            <div class="tab-pane p-3" id="section-linemove-2" role="tabpanel"> 
                                <div class="col-lg-12 col-sm-12 col-xs-12 text-right">
                                    <a href="{{ route('designation_leave.create') }}?view=employee" class="btn btn-primary add-new mb-3">
                                        <i class="fa fa-plus"></i> Add Employee Leave
                                    </a>
                                </div>
                                    <div class="table-responsive mt-20 tz-report-table">
                                        <table id="example2" class="table table-striped">
                                            <thead>
                                            <tr>
                                                <th>Sr No.</th>
                                                <th>Employee No</th>
                                                <th>Employee Name</th>
                                                <th>Designation</th>
                                                <th>Leave Type</th>
                                                <th>Leave Entitled (days)</th>
                                                <th class="text-left">Action</th>
                                            </tr>
                                            </thead>
                                            @if(isset($data['employeeData']) &&!empty($data['employeeData']))
                                            <tbody>
                                                @foreach($data['employeeData'] as $key=>$value)
                                                <tr>
                                                    <td>{{$key+1}}</td>
                                                    <td>{{$value->employee_no}}</td>
                                                    <td>{{$value->employee_name}}</td>
                                                    <td>{{$value->department}}</td>
                                                    <td>{{$value->leave_type}}</td>
                                                    <td>{{$value->value}}</td>
                                                    <td class="d-flex justify-content-around"> 
                                                        <a href="{{ route('designation_leave.edit',$value->id)}}?view=employee" class="btn btn-info btn-outline"><i class="ti-pencil-alt"></i></a>
                                                        <form action="{{ route('designation_leave.destroy', $value->id)}}" method="post" class="d-inline">
                                                        @csrf
                                                        @method('DELETE')
                                                            <button type="submit" onclick="return confirmDelete();" class="btn btn-outline-danger d-block" style="display:block !important"><i class="mdi mdi-delete"></i>
                                                            </button>
                                                        </form>
                                                    </td>
                                                </tr>
                                                @endforeach
                                            </tbody>
                                            @endif
                                        </table>
                                    </div>
                            </div>
                             {{-- employee leave ends  --}}
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
                        title: 'Department Leave Allocation Report',
                        orientation: 'landscape',
                        pageSize: 'LEGAL',
                        pageSize: 'A0',
                        exportOptions: {
                            columns: ':visible'
                        },
                    },
                    {extend: 'csv', text: ' CSV', title: 'Department Leave Allocation Report'},
                    {extend: 'excel', text: ' EXCEL', title: 'Department Leave Allocation Report'},
                    {extend: 'print', text: ' PRINT', title: 'Department Leave Allocation Report'},
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
        $(document).ready(function () {
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
                        title: 'Employee Leave Allocation Report',
                        orientation: 'landscape',
                        pageSize: 'LEGAL',
                        pageSize: 'A0',
                        exportOptions: {
                            columns: ':visible'
                        },
                    },
                    {extend: 'csv', text: ' CSV', title: 'Employee Leave Allocation Report'},
                    {extend: 'excel', text: ' EXCEL', title: 'Employee Leave Allocation Report'},
                    {extend: 'print', text: ' PRINT', title: 'Employee Leave Allocation Report'},
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
        });
    </script>
@include('includes.footer')
@endsection
