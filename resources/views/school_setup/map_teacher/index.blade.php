@extends('layout') 
@section('container')
<div id="page-wrapper">
	<div class="container-fluid">
		<div class="row bg-title">
			<div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
				<h4 class="page-title">Map Teacher Load</h4>
			</div>
		</div>
		@php 
        $grade_id = $standard_id = '';
         if(isset($data['grade_id'])){ 
            $grade_id = $data['grade_id']; 
            $standard_id= $data['standard_id'];
            }
        @endphp
		<div class="card">
			@if(!empty($data['message'])) @if($data['status_code'] == 1)
			<div class="alert alert-success alert-block">
				@else
				<div class="alert alert-danger alert-block">
					@endif
					<button type="button" class="close" data-dismiss="alert">×</button>
					<strong>{{ $data['message'] }}</strong>
				</div>
				@endif
				<div class="col-lg-3 col-sm-3 col-xs-3">
                    <a href="{{ route('map_teacher.create') }}" class="btn btn-info add-new"><i class="fa fa-plus"></i> Map New Teacher</a>
                </div>
			</div>

        @if(isset($data['details']) && !empty($data['details']))
        <div class="card">
            <div class="row">
                <table id="example" class="table table-striped">
                    <thead>
                        <tr>
                            <th>Sr no</th>
                            <th>Teacher</th>
                            <th>Standard</th>
                            <th>Subject</th>
                            <th>Load</th>
                            <th>Added By</th>
                            <th class="text-left">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach($data['details'] as $key=>$value)
                        <tr>
                            <th>{{$key + 1}}</th>
                            <th>{{$value->teacher_name}}</th>
                            <th>{{$value->standard_name}}</th>
                            <th>{{$value->subject_name}}</th>
                            <th>{{$value->load}}</th>
                            <th>{{$value->created_by}}</th>
                            <th class="text-left">
                                <div class="d-inline">
                                    <a href="{{ route('map_teacher.edit',$value->id)}}" class="btn btn-info btn-outline">
                                        <i class="ti-pencil-alt"></i>
                                    </a>
                                </div>
                                <form action="{{ route('map_teacher.destroy', $value->id)}}" method="post" class="d-inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-outline-danger"><i class="ti-trash"></i></button>
                                </form>
                            </th>                 
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>        
        @endif
    <!-- end response -->
    </div>
</div>

@include('includes.footerJs')
<script>
   $(document).ready(function () {
        var table = $('#proxy_list').DataTable({
            select: true,
            lengthMenu: [
                [100, 500, 1000, -1],
                ['100', '500', '1000', 'Show All']
            ],
            dom: 'Bfrtip',
            buttons: [
                {
                    extend: 'pdfHtml5',
                    title: 'Proxy Report',
                    orientation: 'landscape',
                    pageSize: 'LEGAL',
                    pageSize: 'A0',
                    exportOptions: {
                        columns: ':visible'
                    },
                },
                {extend: 'csv', text: ' CSV', title: 'Proxy Report'},
                {extend: 'excel', text: ' EXCEL', title: 'Proxy Report'},
                {
                    extend: 'print',
                    text: ' PRINT',
                    title: 'Student Report',
                    customize: function (win) {
                        $(win.document.body).prepend(`{!! App\Helpers\get_school_details("", "", "") !!}`);
                        $(win.document.body).append(`<div style="text-align: right;margin-top:20px">Printed on: {{date('d-m-Y H:i:s')}}</div>`);
                    }
                },
                'pageLength'
            ],
        });

        $('#proxy_list thead tr').clone(true).appendTo('#proxy_list thead');
        $('#proxy_list thead tr:eq(1) th').each(function (i) {
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
        });
    });
</script>

@include('includes.footer')
@endsection