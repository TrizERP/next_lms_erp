@extends('layout') @section('container')
<div id="page-wrapper">
	<div class="container-fluid">

		<div class="row bg-title">
			<div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
				<h4 class="page-title">Admission AI</h4>
			</div>
		</div>

		@if ($sessionData = Session::get('data')) @if (isset($sessionData['status_code']))
		<div class="card">
			<div class="row mb-2">
				<div class="alert alert-{{ $sessionData['status_code'] == 1 ? 'success' : 'danger' }} alert-block">
					<button type="button" class="close" data-dismiss="alert">×</button>
					<strong>{!! $sessionData['message'] !!}</strong>
				</div>
			</div>
		</div>
		@endif @endif

		<!-- data start  -->
		<div class="card">
            <div class="table-responsive" style="margin:10px !important">
                <table id="example" class="table table-box table-bordered">
                    <thead>
                        <tr>
                            <th>Sr No.</th>
                            <th>Standard</th>
                           @if(!empty($data['yearHeads']))
                           @php $date = date('Y'); @endphp
                           @foreach($data['yearHeads'] as $key=>$value)
                            <th class="text-left">{{$value}}@if($value > $date) (AI Predicted) @endif</th>
                           @endforeach
                           @endif
                        </tr>
                    </thead>
                    <tbody>
                    @if(!empty($data['admissionData']))
                    @foreach($data['admissionData'] as $key => $value)
                        <tr>
                            <td>{{$key+1}}</td>
                            <td>{{$value['standard_name']}}</td>
                           @foreach($data['yearHeads'] as $key2=>$value2)
                                @if(isset($value[$value2]))
                                    <td>{{$value[$value2]}}</td>
                                @else 
                                    <td>-</td>
                                @endif
                           @endforeach                
                        </tr>
                    @endforeach
                    @endif
                    </tbody>
                </table>
            </div>
		</div>
		<!-- data end  -->
	</div>
</div>

@include('includes.lmsfooterJs')
<script>
	  $(document).ready(function() {
        var table = $('#example').DataTable( {
         select: true,          
         lengthMenu: [ 
                        [100, 500, 1000, -1], 
                        ['100', '500', '1000', 'Show All'] 
        ],
        dom: 'Bfrtip', 
        buttons: [ 
            { 
                extend: 'pdfHtml5',
                title: 'Inactive Student Report',
                orientation: 'landscape',
                pageSize: 'LEGAL',                
                pageSize: 'A0',
                exportOptions: {                   
                     columns: ':visible'                             
                },
            }, 
            { extend: 'csv', text: ' CSV', title: 'Inactive Student Report' }, 
            { extend: 'excel', text: ' EXCEL', title: 'Inactive Student Report' }, 
            {
                extend: 'print',
                text: ' PRINT',
                title: 'Inactive Student Report',
                customize: function (win) {
                    $(win.document.body).prepend(`{!! App\Helpers\get_school_details() !!}`);
                    $(win.document.body).append(`<div style="text-align: right;margin-top:20px">Printed on: {{date('d-m-Y H:i:s')}}</div>`);
                }
            },
            'pageLength' 
        ], 
        }); 
        $('#example thead tr').clone(true).appendTo( '#example thead' );
        $('#example thead tr:eq(1) th').each( function (i) {
            var title = $(this).text();
            $(this).html( '<input type="text" placeholder="Search '+title+'" />' );

            $( 'input', this ).on( 'keyup change', function () {
                if ( table.column(i).search() !== this.value ) {
                    table
                        .column(i)
                        .search( this.value )
                        .draw();
                }
            } );
        } );
    } );
</script>
@include('includes.footer') @endsection