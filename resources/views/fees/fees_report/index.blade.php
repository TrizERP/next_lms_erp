@include('includes.headcss') @include('includes.header') @include('includes.sideNavigation')

<div id="page-wrapper">
	<div class="container-fluid">
		<div class="row bg-title">
			<div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
				<h4 class="page-title">Fees Collection Report</h4>
			</div>
		</div>
		@php 
		$grade_id = $standard_id = $division_id = $enrollment_no = $receipt_no = $from_date = $to_date = $name = $mb_no ='';
		if(isset($data['grade_id'])){ $grade_id = $data['grade_id']; $standard_id = $data['standard_id']; $division_id = $data['division_id'];
		} 
		if(isset($data['enrollment_no'])) { $enrollment_no = $data['enrollment_no']; } 
		if(isset($data['name'])) { $name = $data['name'];
		} 
		if(isset($data['mb_no'])) { $mb_no = $data['mb_no']; } 
		if(isset($data['receipt_no'])) { $receipt_no = $data['receipt_no'];
		} 
		if(isset($data['from_date'])) { $from_date = $data['from_date']; } 
		if(isset($data['to_date'])) { $to_date = $data['to_date'];
		} @endphp
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
				<form action="{{ route('show_fees_collection_report') }}" enctype="multipart/form-data" class="row" method="post">
					{{ method_field("POST") }} @csrf
					<div class="col-md-4 form-group">
						<label>{{App\Helpers\get_string('grno','request')}}</label>
						<input type="text" id="enrollment_no" name="enrollment_no" value="{{$enrollment_no}}" class="form-control" placeholder="Gr No">
					</div>
					<div class="col-md-4 form-group">
						<label>{{App\Helpers\get_string('studentname','request')}}</label>
						<input type="text" id="name" name="name" class="form-control" placeholder="Name" value="{{$name}}">
					</div>
					<div class="col-md-4 form-group">
						<label>Mobile Number</label>
						<input type="text" id="mb_no" name="mb_no" class="form-control" value="{{$mb_no}}" placeholder="Mobile Number">
					</div>
					<!--<div class="col-md-4 form-group">
                        <label>Receipt No</label>
                        <input type="text" id="receipt_no" value="{{$receipt_no}}" name="receipt_no" class="form-control">
                    </div>-->
					{{ App\Helpers\SearchChain('4','multiple','grade,std,div',$grade_id,$standard_id,$division_id) }}
					<div class="col-md-4 form-group">
						<label>From Date</label>
						<input type="text" id="from_date" name="from_date" value="{{$from_date}}" class="form-control mydatepicker" autocomplete="off">
					</div>
					<div class="col-md-4 form-group">
						<label>To Date</label>
						<input type="text" id="to_date" name="to_date" value="{{$to_date}}" class="form-control mydatepicker" autocomplete="off">
					</div>
					@php
						$payment_mode = '';
						if(isset($data['payment_mode'])){ 
							$payment_mode = $data['payment_mode'];
						}
					@endphp
					<div class="col-md-4 form-group">
						<label>Payment Mode</label>
						<select class="form-control" name="payment_mode" id="payment_mode">
							<option value="">Select Payment Mode</option>
							<option @if($payment_mode == 'Cash') selected="selected"
                                                    @endif value="Cash">Cash</option>
							<option @if($payment_mode == 'Cheque') selected="selected"
                                                    @endif value="Cheque">Cheque</option>
							<option @if($payment_mode == 'DD') selected="selected"
                                                    @endif value="DD">DD</option>
							<option @if($payment_mode == 'Online') selected="selected"
                                                    @endif value="Online">Online</option>
							<option @if($payment_mode == 'NACH') selected="selected"
                                                    @endif value="NACH">NACH</option>
							<option @if($payment_mode == 'UPI') selected="selected"
                                                    @endif value="UPI">UPI</option>
							<option @if($payment_mode == 'Swipe1') selected="selected"
                                                    @endif value="Swipe1">Swipe1</option>
							<option @if($payment_mode == 'Swipe2') selected="selected"
                                                    @endif value="Swipe2">Swipe2</option>
							<option @if($payment_mode == 'Swipe3') selected="selected"
                                                    @endif value="Swipe3">Swipe3</option>
						</select>
					</div>
					@php 
						if(isset($data['get_users'])){ 
							$get_users = $data['get_users']; 
						} 

						$selected_user_name ='';
						if(isset($data['selected_user_name'])){ 
							$selected_user_name = $data['selected_user_name']; 
						}
					@endphp
					<div class="col-md-4 form-group">
						<label>Collected By</label>
						<select class="form-control" name="user_name" id="user_name">
							<option value="">Select</option>
							@foreach($get_users as $key => $value)
								<option @if($value->id == $selected_user_name) selected="selected"
                                                    @endif value="{{$value->id}}">{{$value->user_name}}</option>
							@endforeach
						</select>
					</div>
					<div class="col-md-12 form-group">
						<center>
							<input type="submit" name="submit" value="Search" class="btn btn-success">
						</center>
					</div>

				</form>
			</div>
			@if(isset($data['fees_data'])) 
			@php 
			if(isset($data['fees_data'])){ $fees_data = $data['fees_data']; } 
			@endphp
			<div class="card">
				<div class="table-responsive">
					<table id="example" class="table table-striped">
						<thead>
							<tr>
								<th>Sr No.</th>
								<th>{{App\Helpers\get_string('grno','request')}}</th>
								<th>{{App\Helpers\get_string('studentname','request')}}</th>
								<th>{{App\Helpers\get_string('std/div','request')}}</th>
								<th>{{App\Helpers\get_string('studentquota','request')}}</th>
								<th>{{App\Helpers\get_string('uniqueid','request')}}</th>
								<th>Month</th>
								<th>Receipt No</th>
								<th>Payment Mode</th>
								<th>Bank Details</th>
								<th>Receipt Date</th>
								<th>Collected By</th>
								<th>Amount</th>
							</tr>
						</thead>
						<tbody>
							@php $j=1; $amount = 0; @endphp 
							@if(isset($data['fees_data'])) 
							@foreach($fees_data as $key => $value) 
							@php
							if($value['cheque_date']
								!= '' && $value['cheque_date'] != '0000-00-00') { $cheque_date = date('d-m-Y',strtotime($value['cheque_date'])); 
							}
							else{
								$cheque_date = ''; 
								} 
							@endphp
							<tr>
								<td>{{$j}}</td>
								<td>{{$value['enrollment_no']}}</td>
								<td>{{$value['student_name']}}</td>
								<td>{{$value['standard_name']}} - {{$value['division_name']}} {{$value['batch']}}
							@if (Session::get('sub_institute_id') == '257')
							  {{$value['place_of_birth']}}
							@endif
								</td>
								<td>{{$value['quota']}}</td>
								<td>{{$value['uniqueid']}}</td>
								<td>{{$data['months'][$value['term_id']]}}</td>
								<td>{{$value['receipt_no']}}</td>
								<td>{{$value['payment_mode']}}</td>
								<td>{{$value['cheque_no']}} {{$value['cheque_bank_name']}} {{$value['bank_branch']}}</td>
								<!--<td>{{$cheque_date}}</td>-->
								<td>{{date('d-m-Y',strtotime($value['receiptdate']))}}</td>
								<td>{{$value['user_name']}}</td>
								<!--<td>{{date('d-m-Y h:i:s',strtotime($value['created_date']))}}</td>-->
								<td>{{$value['actual_amountpaid']}}</td>
							</tr>
							@php $amount += $value['actual_amountpaid']; $j++; @endphp @endforeach
							<tr>
								<th>Total</th>
								<td></td>
								<td></td>
								<td></td>
								<td></td>
								<td></td>								
								<td></td>
								<!--<td></td>-->
								<td></td>
								<td></td>
								<td></td>
								<td></td>
								<td></td>
								<!--<td></td>-->
								<th>{{$amount}}</th>
							</tr>
							@endif
						</tbody>
					</table>
				</div>
                <div class="mt-4" style="display:inline-grid;justify-content:center;width:100%">
				<div class="table-responsive">
						<table id="example" class="table table-striped">
							@php 
                            $printedModes = []; // Track the printed payment modes 
                            $j=1;
                            $tot_amounts = 0;
                            $tot_stu = 0;                                     
                            
                            @endphp 
                            <tr>
                            <th style="font-weight: 600;">Payment Modes</th>
                            <th style="font-weight: 600;">Total Student</th>
                            <th style="font-weight: 600;">Amounts</th>                            
                            </tr>
                            @foreach($fees_data as $key => $value)
                            @php
                                $tot_amounts += $value['actual_amountpaid'];
                                if (!in_array($value['payment_mode'], $printedModes)) {
                                    $printedModes[] = $value['payment_mode'];
                                    $amount = 0; 
                                    $studentCount = 0; 
                                    $studentNames = [];

                                    foreach ($fees_data as $fee) {
                                        if ($fee['payment_mode'] === $value['payment_mode']) {
                                            $amount += $fee['actual_amountpaid'];

                                            if (!in_array($fee['student_name'], $studentNames)) {
                                                $studentNames[] = $fee['student_name'];
                                                if (count(array_keys($studentNames, $fee['student_name'])) < 2) {
                                                    $studentCount++;
                                                }
                                            }
                                        }
                                    }
                                } else {
                                    continue;
                                }
                                $tot_stu+= $studentCount;                                                                                                                                    
                            @endphp

                            <tr>
                                <td>{{ $value['payment_mode'] }}</td>
                                <td>{{ $studentCount }}</td>        
                                <td>{{ $amount }}</td>
                            </tr>
                        @endforeach
                        <tr>
                            <th style="font-weight: 600;">Total</th>
                            <th style="font-weight: 600;">{{ $tot_stu }}</th>                                                                                                       
                            <th style="font-weight: 600;">{{ $tot_amounts }}</th>
                        </tr>
						</table>
				</div>
                </div>

			</div>
			@endif
		</div>
	</div>

	@include('includes.footerJs')
	<link href="https://code.jquery.com/ui/1.10.4/themes/ui-lightness/jquery-ui.css" rel="stylesheet">
	<script src="https://code.jquery.com/jquery-1.10.2.js"></script>
	<script src="https://code.jquery.com/ui/1.10.4/jquery-ui.js"></script>
	<script>
		function checkAll(ele) {
			var checkboxes = document.getElementsByTagName('input');
			if (ele.checked) {
				for (var i = 0; i < checkboxes.length; i++) {
					if (checkboxes[i].type == 'checkbox') {
						checkboxes[i].checked = true;
					}
				}
			} else {
				for (var i = 0; i < checkboxes.length; i++) {
					console.log(i)
					if (checkboxes[i].type == 'checkbox') {
						checkboxes[i].checked = false;
					}
				}
			}
		}
	</script>
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
						title: 'Fees Collection Report',
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
						title: 'Fees Collection Report'
					},
					{
						extend: 'excel',
						text: ' EXCEL',
						title: 'Fees Collection Report'
					},
					{
                        extend: 'print',
                        text: ' PRINT',
                        title: 'Fees Collection Report',
                        customize: function (win) {
                            $(win.document.body).prepend(`{!! App\Helpers\get_school_details("", "", "") !!}`);
                        }
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