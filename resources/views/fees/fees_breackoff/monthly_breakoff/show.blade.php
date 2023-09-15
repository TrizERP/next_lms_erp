@include('includes.headcss') @include('includes.header') @include('includes.sideNavigation')

<div id="page-wrapper">
	<div class="container-fluid">
		<div class="row">
			<div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
				<h4 class="page-title">Fees Structure</h4>
			</div>
		</div>
		<div class="card">
        @php $fields = Session::get('data') @endphp
			@if ($sessionData = Session::get('data')) 
            @if( $sessionData['status_code'] == 1)
			<div class="alert alert-success alert-block">
				@else
				<div class="alert alert-danger alert-block">
					@endif
					<button type="button" class="close" data-dismiss="alert">×</button>
					<strong>{{ $sessionData['message'] }}</strong>
				</div>
				@endif
				<form action="{{ route('monthly_breakoff.store') }}" method="POST">
					@csrf
					<div class="row">
						<div class="col-md-4 form-group">
							<label>Select Breakoff Month</label>
							<select id='bk_month' name="bk_month" class="form-control">
								<option>--Select BK Month--</option>
								@if(isset($data['bk_month'])) 
                                @foreach($data['bk_month'] as $key => $value)
								<option value="{{$key}}" @if(isset($fields['bk_months']) && $fields['bk_months']==$key) selected @endif>{{$value}}</option>
								@endforeach 
                                @endif
							</select>
						</div>

						<div class="col-md-4 form-group">
							<label>Select Next Month</label>
							<select id='next_bk' name="next_bk[]" class="form-control" multiple>
								<option>--Select Next BK Month--</option>
                                @if(isset($data['next_month'])) 
                                @foreach($data['next_month'] as $key => $value)
								<option value="{{$key}}" @if(isset($fields['next_bk_months']) && in_array($key,$fields['next_bk_months'])) selected @endif>{{$value}}</option>
								@endforeach 
                                @endif
							</select>
						</div>

					</div>
                    
                    <div class="col-md-4 form-group">
                        <center>
                        <input type="submit" vlaue="Save" name="Submit" class="btn btn-success">
                        </center>
                        </div>
				</form>
                @if(isset($data['today_data']))
                <div class="col-lg-12 col-sm-12 col-xs-12">
                <h5>Today Added Breakoff</h5>
                    <div class="table-responsive">
                        <table id="example" class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Sr No</th>
                                    <th>Syear</th>
                                    <th>Fee Head</th>
                                    <th>Admission</th>
                                    <th>{{ App\Helpers\get_string('studentquota','request')}}</th>
                                    <th>Grade</th>
                                    <th>{{ App\Helpers\get_string('standard','request')}}</th>
                                    {{-- <th>Division</th> --}}
                                    <th>Month</th>
                                    <th>Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                            @php
                                $j=1;
                                @endphp
                                @foreach($data['today_data'] as $key => $data)
                                <tr>
                                    <td>{{$j}}</td>
                                    <td>{{$data->syear}}</td>
                                    <td>{{$data->fees_head}}</td>
                                    <td>{{$data->admission_year}}</td>
                                    <td>{{$data->quota}}</td>
                                    <td>{{$data->grade_name}}</td>
                                    <td>{{$data->sta_name}}</td>
                                    <td>{{$data->month_id}}</td>
                                    <td>{{$data->amount}}</td>
                                </tr>
                                @php
                                $j++;
                                @endphp
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
                @endif
			</div>
           
		</div>
	</div>
</div>

@include('includes.footerJs')
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
                title: 'Admission Confirmation Report',
                orientation: 'landscape',
                pageSize: 'LEGAL',                
                pageSize: 'A0',
                exportOptions: {                   
                     columns: ':visible'                             
                },
            }, 
            { extend: 'csv', text: ' CSV', title: 'Admission Confirmation Report' }, 
            { extend: 'excel', text: ' EXCEL', title: 'Admission Confirmation Report'}, 
            { extend: 'print', text: ' PRINT', title: 'Admission Confirmation Report'}, 
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
 @include('includes.footer')