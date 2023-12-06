<!-- fees Account Details Modal -->
<div class="modal fade" id="exampleModal_fees" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header" style="align-items:center;padding:8px !important">
				<h3 class="modal-title fs-5 fcolor fees_step_title fcolor" id="exampleModalLabel"></h3>
				<button type="button" class="btn-close border-0" data-bs-dismiss="modal" aria-label="Close">X</button>
			</div>
			<div class="modal-body">
				<!-- step 1 start  -->
				<div id="fees_step_1">
					<div class="row">
						<div class="col-md-4 form-group">
							<label>Account Number </label>
							<input type="text" id='account_number' required @if(isset($data[ 'userdata'][ 'user_name'])) value="{{str_pad($data['userdata']['id'], 5, '0', STR_PAD_LEFT)}}"
							 @endif readonly="readonly" class="form-control">
						</div>
						<div class="col-md-4 form-group">
							<label>Creation Date </label>
							<input type="text" id='created_at' required @if(isset($data[ 'schooldata'][ 'created_at'])) value="{{date('d-m-Y',strtotime($data['schooldata']['created_at']))}}"
							 @endif readonly="readonly" class="form-control">
						</div>
						<div class="col-md-4 form-group">
							<label>School Name </label>
							<input type="text" id='school_name' @if(isset($data[ 'schooldata'][ 'SchoolName'])) value="{{$data['schooldata']['SchoolName']}}"
							 @endif required name='school_name' readonly="readonly" class="form-control">
						</div>
						<div class="col-md-4 form-group">
							<label>User Name </label>
							<input type="text" id='contact_person' @if(isset($data[ 'userdata'][ 'user_name'])) value="{{$data['userdata']['user_name']}}"
							 @endif required name='contact_person' readonly="readonly" class="form-control">
						</div>
						<div class="col-md-4 form-group">
							<label>Mobile </label>
							<input type="text" @if(isset($data[ 'userdata'][ 'mobile'])) value="{{$data['userdata']['mobile']}}" @endif readonly="readonly"
							 id='mobile' required name='mobile' class="form-control">
						</div>
						<div class="col-md-4 form-group">
							<label>Email </label>
							<input type="text" id='email' @if(isset($data[ 'userdata'][ 'email'])) value="{{$data['userdata']['email']}}" @endif required
							 name='email' class="form-control" readonly="readonly">
						</div>
						<div class="col-md-4 form-group">
							<label>First Name </label>
							<input type="text" id='first_name' @if(isset($data[ 'userdata'][ 'first_name'])) value="{{$data['userdata']['first_name']}}"
							 @endif required name='first_name' class="form-control" readonly="readonly">
						</div>
						<div class="col-md-4 form-group">
							<label>Last Name </label>
							<input type="text" id='last_name' @if(isset($data[ 'userdata'][ 'last_name'])) value="{{$data['userdata']['last_name']}}"
							 @endif required name='last_name' class="form-control" readonly="readonly">
						</div>
						<div class="col-md-12 form-group mb-0">
							<center>
								<button class="btn btn-primary fees-modulde-2">Continue</button>
							</center>
						</div>
					</div>
				</div>
				
				<!-- step 2 start  -->
				@php 
					$step2_titles = ["Fees Map Year","Fees Title","Fees Config Master","Fees Month Header","Fees Receipt Book Master","Fees Breakoff"];

					$step2_links=["map_year.create","fees_title.create","fees_config_master.create","fees_month_header.create","fees_receipt_book_master.create","fees_breackoff.create"];

					$step2_lists=["map_year.index","fees_title.index","fees_config_master.index","fees_month_header.index","fees_receipt_book_master.index","fees_breackoff.index"];

					$step2_tables=["fees_map_years","fees_title","fees_config_master","fees_month_header","fees_receipt_book_master","fees_breackoff"];
					$i=1;
					@endphp
				<div id="fees_step_2">
					<div class="row">
						<div class="col-md-4">
							<h4><b>Modules</b></h4>
						</div>
						<div class="col-md-4">
							<h4><b>Deatils</b></h4>	
						</div>
						<div class="col-md-4">
							<h4><b>Status</b></h4>	
						</div>										
					@foreach($step2_titles as $index=>$title)
					<div class="col-md-4">
							<label>
								<a class="text-primary" onclick="window.open('{{route($step2_links[$index])}}','scrollbars=yes,resizable=no,status=no,location=no,toolbar=no,menubar=no','width=1200,height=1200,left=100,top=100');"
								 href="javascript:void(0);"><b>{{$i++.") ".$title}}</b></a>
							</label>
						</div>

						<div class="col-md-4">
							@if(isset($data['table_name'][$step2_tables[$index]]) && $data['table_name'][$step2_tables[$index]] == 1)
							<a class="text-primary" onclick="window.open('{{route($step2_lists[$index])}}','scrollbars=yes,resizable=no,status=no,location=no,toolbar=no,menubar=no','width=1200,height=1200,left=100,top=100');"
								 href="javascript:void(0);">View Details</a>
                            @else
                                 <label class="text-danger">Incomplete Setup</label>
							@endif
						</div>

						<div class="col-md-4">
							@if(isset($data['table_name'][$step2_tables[$index]]) && $data['table_name'][$step2_tables[$index]] == 1)
                                 <img src="{{asset('/Images/square-check.svg')}}" title="complete">
                                 @else
                                 <img src="{{asset('/Images/close-square-icon.svg')}}" title="complete">
                                @endif
						</div>
					@endforeach
					</div>
					
					<div class="col-md-12 form-group mb-0">
						<center>
							<button class="btn btn-primary finish">Finish</button>
							<button class="btn btn-warning fees_back">Back</button>							
						</center>
					</div>
				</div>
				<!-- end of steps  -->

			</div>
		</div>
	</div>
</div>

<style>
	.modal-dialog {
		max-width: 1050px !important;
	}
</style>
<script>
	$(document).ready(function() {
		$('#fees_step_2').hide();
		$('.fees_step_title').append('<b>Step 1 : Check Account Details</b>');
		// Collapse toggle
		$('.fees-modulde-2').click(function() {
			$('.fees_step_title').empty();
			$('#fees_step_2').show();
			$('#fees_step_1').hide();
			$('.fees_step_title').append('<b>Step 2 : Fees Setup </b>');			
		});

		$('.fees_back').click(function() {
			$('#fees_step_2').hide();
			$('#fees_step_1').show();
			$('.fees_step_title').empty();
			$('.fees_step_title').append('<b>Step 1 : Check Account Details</b>');
		});

		$('.finish').click(function() {
			window.location.reload();
		});
	});
</script>