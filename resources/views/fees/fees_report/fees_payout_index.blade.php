{{--
@include('includes.headcss') @include('includes.header') @include('includes.sideNavigation')
--}}
@extends('layout')
@section('container')
<div id="page-wrapper">
	<div class="container-fluid">
		<div class="row bg-title">
			<div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
				<h4 class="page-title">Fees Payout Report</h4>
			</div>
		</div>
		@php
		$grade_id = $standard_id = $division_id = $enrollment_no = $receipt_no = $from_date = $to_date = $name = $mb_no ='';
		
		if(isset($data['from_date'])) { $from_date = $data['from_date']; }
		if(isset($data['to_date'])) { $to_date = $data['to_date'];
		} @endphp
		<div class="card">
			@if ($sessionData = Session::get('data'))
				@if($sessionData['status_code'] == 1)
					<div class="alert alert-success alert-block">
				@else
					<div class="alert alert-danger alert-block">
				@endif
					<button type="button" class="close" data-dismiss="alert">×</button>
					<strong>{{ $sessionData['message'] }}</strong>
				</div>
			@endif
			<form action="{{ route('show_fees_payout_report') }}" enctype="multipart/form-data" class="row" method="post">
				{{ method_field("POST") }} @csrf
					<div class="col-md-4 form-group">
						<label>From Date</label>
						<input type="text" id="from_date" name="from_date" value="{{$from_date}}" class="form-control mydatepicker" autocomplete="off">
					</div>
					<div class="col-md-4 form-group">
						<label>To Date</label>
						<input type="text" id="to_date" name="to_date" value="{{$to_date}}" class="form-control mydatepicker" autocomplete="off">
					</div>
					<div class="col-md-12 form-group">
						<center>
							<input type="submit" name="submit" value="Search" class="btn btn-success">
						</center>
					</div>

				</form>
			</div>
			@if(isset($data['fees_data']))
				<div class="card">
					<div class="table-responsive">
						@php
						echo App\Helpers\get_school_details("","","");
						echo '<br><center><span style=" font-size: 14px;font-weight: 600;font-family: Arial, Helvetica, sans-serif !important">From Date : '.date('d-m-Y',strtotime($data['from_date'])) .' - </span><span style=" font-size: 14px;font-weight: 600;font-family: Arial, Helvetica, sans-serif !important">To Date : '.date('d-m-Y',strtotime($data['to_date'])) .'</span></center><br>';
						@endphp
						<table id="example" class="table table-border text-center">
							@if(count($data['fees_data']) > 0)
							<thead>
								<tr>
									<th>No.</th>
									<th>Sports</th>
									<th>Coach Name</th>
									<th>Batch</th>
									<th colspan="6">Students</th>
									<th>Total</th>
									<th colspan="2">Income</th>
									<th>Total Income</th>
								</tr>
								<tr>
									<th></th>
									<th></th>
									<th></th>
									<th></th>
									<th colspan="3">CN</th>
									<th colspan="3">Other Student</th>
									<th></th>
									<th>CN</th>
									<th>Other</th>
									<th></th>
								</tr>
								<tr>
									<th></th>
									<th></th>
									<th></th>
									<th></th>
									<th>G</th>
									<th>B</th>
									<th>To</th>
									<th>G</th>
									<th>B</th>
									<th>To</th>
									<th></th>
									<th></th>
									<th></th>
									<th></th>
								</tr>
							</thead>
							<tbody>
							@php

							$i = 1;
							@endphp

							@foreach($data['fees_data'] as $key => $value)
								@foreach($data['fees_data'][$key] as $key1 => $value1)
									@foreach($data['fees_data'][$key][$key1] as $key2 => $value2)
										@foreach($data['fees_data'][$key][$key1][$key2] as $key3 => $value3)
												@php
												
												$otherSchoolMaleCount = 0;
												$otherSchoolFemaleCount = 0;
												$cnFemaleCount = 0;
												$cnMaleCount = 0;
												$other_total_amount = 0;
												$cn_total_amount = 0;
												$total_amount = 0;
												$cn_total_girls = 0;
												@endphp
												@if($key3 == "Other School")
													@php 
													@endphp
													@foreach($value3 as $entry)
														@php 
															$other_total_amount += $entry['tot'];
														@endphp
														@if($key2 == $entry['batch_name'])
															@if ($entry['gender'] == 'M' || $entry['gender'] == 'Male')
																@php
																$otherSchoolMaleCount++;
																@endphp
															@elseif ($entry['gender'] == 'F' || $entry['gender'] == 'Female')
																@php
																$otherSchoolFemaleCount++;
																@endphp
															@endif
														@endif
													@endforeach
												@elseif($key3 != "Other School")
													@foreach($value3 as $entry)
														@php 
															$cn_total_amount += $entry['tot'];
														@endphp
														@if($key2 == $entry['batch_name'])
															@if ($entry['gender'] == 'M' || $entry['gender'] == 'Male')
																@php
																$cnMaleCount++;
																@endphp
															@elseif ($entry['gender'] == 'F' || $entry['gender'] == 'Female')
																@php
																$cnFemaleCount++;
																$cn_total_girls += $cnFemaleCount++;
																@endphp
															@endif
														@endif
													@endforeach
												@endif
											<tr>
												<td>{{ $i++ }}</td>
												<td>{{ $key }}</td>
												<td>{{ $key1 }}</td>
												<td>{{ $key2 }}</td>
												<td>{{ $cnFemaleCount }}</td>
												<td>{{ $cnMaleCount }}</td>
												<td>{{ $cnFemaleCount + $cnMaleCount }}</td>
												<td>{{ $otherSchoolFemaleCount }}</td>
												<td>{{ $otherSchoolMaleCount }}</td>
												<td>{{ $otherSchoolFemaleCount + $otherSchoolMaleCount }}</td>
												<td>{{ ($otherSchoolFemaleCount + $otherSchoolMaleCount) + ($cnFemaleCount + $cnMaleCount) }}</td>
												<td>{{ $cn_total_amount }}</td>
												<td>{{ $other_total_amount }}</td>
												<td>{{ $cn_total_amount + $other_total_amount }}</td>
											</tr>
										@endforeach
									@endforeach
								@endforeach
							@endforeach
							</tbody>
							<tfoot>
								<tr>
									<th colspan="4">Total</th>
									<th>{{ $cn_total_girls }}</th>
									<th></th>
									<th></th>
									<th></th>
									<th></th>
									<th></th>
									<th></th>
									<th></th>
									<th></th>
									<th>{{ $total_amount }}</th>
								</tr>
							</tfoot>
							@else
							<tbody>
								<tr>
									<th class="text-center">No Data Found</th>
								</tr>
							</tbody>
							@endif
						</table>
					</div>
				</div>
			@endif
		</div>
	</div>

@include('includes.footerJs')

@include('includes.footer')
@endsection
