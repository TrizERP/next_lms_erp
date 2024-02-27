@extends('layout') 
@section('container')

<div id="page-wrapper">
	<div class="container-fluid">
		<div class="row bg-title">
			<div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
				<h4 class="page-title">
					Subject Elective
				</h4>
			</div>
		</div>
		<div class="card">
            <div class="row">
                <div class="col-lg-3 col-sm-3 col-xs-3 m-30">
                    <a href="{{ route('subject_elective.create') }}" class="btn btn-info add-new">
                        <i class="fa fa-plus"></i> Add New
                    </a>
                </div>
            </div>
			<div class="panel-body">
            @if (isset($data['status_code']))
                @if($data['status_code'] == 1)
                <div class="alert alert-success alert-block">
                @else
                <div class="alert alert-danger alert-block">
                @endif
                    <button type="button" class="close" data-dismiss="alert">×</button>
                    <strong>{{ $data['message'] }}</strong>
                </div>
            @endif
				<div class="col-lg-12 col-sm-12 col-xs-12">
                    <div class="table-responsive">
                        <table id="example" class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Sr. No.</th>
                                    <th>Subject</th>
                                    <th>Standard</th>
                                    <th>Division</th>
                                    <th class="text-left">Action</th>                                    
                                </tr>
                            </thead>
                            <tbody>
                            @foreach($data['subject_elective'] as $key=>$value)
                            <tr>
                                <th>{{$key+1}}</th>
                                <th>{{$value->subject_name}}</th>
                                <th>{{$value->std_name}}</th>
                                <th>{{$value->div_name}}</th>
                                <th>
                                    <a href="{{ route('subject_elective.edit',$value->id)}}?standard_id={{$value->standard_id}}" class="btn btn-info btn-outline btn m-r-5"><i class="ti-pencil-alt"></i></a>
                                </th>                                    
                            </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
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
                    title: 'Fees Monthly Report',
                    orientation: 'landscape',
                    pageSize: 'LEGAL',
                    pageSize: 'A0',
                    exportOptions: {
                        columns: ':visible'
                    },
                },
                {extend: 'csv', text: ' CSV', title: 'Fees Monthly Report'},
                {extend: 'excel', text: ' EXCEL', title: 'Fees Monthly Report'},
                {extend: 'print', text: ' PRINT', title: 'Fees Monthly Report'},
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
@endsection