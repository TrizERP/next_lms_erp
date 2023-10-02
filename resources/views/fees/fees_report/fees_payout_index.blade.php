@include('includes.headcss') @include('includes.header') @include('includes.sideNavigation')
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
					<div class="col-md-4 form-group">
						<label>Select Month</label>
						<select id="selected_month" name="selected_month" class="form-control" required>
							<option value="">Select Month</option>
							@foreach($data['months'] as $monthKey => $monthValue)
								<option value="{{ $monthKey }}" {{ $data['selected_month'] == $monthKey ? 'selected' : '' }}>
									{{ $monthValue }}
								</option>
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
				<div class="card">
					<div class="table-responsive">
						@php
						echo App\Helpers\get_school_details("","","");
						echo '<br><center><span style=" font-size: 14px;font-weight: 600;font-family: Arial, Helvetica, sans-serif !important">From Date : '.date('d-m-Y',strtotime($data['from_date'])) .' - </span><span style=" font-size: 14px;font-weight: 600;font-family: Arial, Helvetica, sans-serif !important">To Date : '.date('d-m-Y',strtotime($data['to_date'])) .'</span></center><br>';
						@endphp
						<table id="fees_payout_report" class="table table-border text-center table-striped">
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
								$cn_total_girls = 0;
								$cn_total_boys = 0;
								$cn_total_boys_and_girls = 0;
								$other_total_girls = 0;
								$other_total_boys = 0;
								$other_total_boys_and_girls = 0;
								$cn_and_other_totals = 0;
								$cn_total_income = 0;
								$other_total_income = 0;
								$total_income = 0;
							@endphp
							@foreach($data['fees_data'] as $fees_data)
								<tr>
									<td>{{ $i++ }}</td>
									<td>{{ $fees_data['standard_name'] }}</td>
									<td>{{ $fees_data['coach_name'] }}</td>
									<td>{{ $fees_data['batch_name'] ? $fees_data['batch_name'] : '-'  }}</td>
									<td>{{ $fees_data['cn_female_count'] }}</td>
									<td>{{ $fees_data['cn_male_count'] }}</td>
									<td>{{ $fees_data['cn_male_count'] + $fees_data['cn_female_count'] }}</td>
									<td>{{ $fees_data['other_female_count'] }}</td>
									<td>{{ $fees_data['other_male_count'] }}</td>
									<td>{{ $fees_data['other_male_count'] + $fees_data['other_female_count'] }}</td>
									<td>{{ $fees_data['tot_count'] }}</td>
									<td>{{ $fees_data['cn_tot'] }}</td>
									<td>{{ $fees_data['other_tot'] }}</td>
									<td>{{ $fees_data['tot'] }}</td>
								</tr>
								@php
									$cn_total_girls += $fees_data['cn_female_count']; 
									$cn_total_boys += $fees_data['cn_male_count'];
									$cn_total_boys_and_girls += $fees_data['cn_female_count'] + $fees_data['cn_male_count'];
									$other_total_girls += $fees_data['other_female_count'];
									$other_total_boys += $fees_data['other_male_count'];
									$other_total_boys_and_girls += $fees_data['other_female_count'] + $fees_data['other_male_count'];
									$cn_and_other_totals += $fees_data['tot_count'];
									$cn_total_income += $fees_data['cn_tot'];
									$other_total_income += $fees_data['other_tot'];
									$total_income += $fees_data['tot'];
								@endphp
							@endforeach			
							</tbody>
							<tfoot>
								<tr>
									<th colspan="4">Total</th>
									<th>{{ $cn_total_girls }}</th>
									<th>{{ $cn_total_boys }}</th>
									<th>{{ $cn_total_boys_and_girls }}</th>
									<th>{{ $other_total_girls }}</th>
									<th>{{ $other_total_boys }}</th>
									<th>{{ $other_total_boys_and_girls }}</th>
									<th>{{ $cn_and_other_totals }}</th>
									<th>{{ $cn_total_income }}</th>
									<th>{{ $other_total_income }}</th>
									<th>{{ $total_income }}</th>
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
					<center>
						<button
							onclick="exportTableToExcel('fees_payout_report', 'Fees Payout Report')"
							class="btn btn-success mt-2">Excel Export
						</button>
					</center>
				</div>
			</div>
		@endif
	</div>
</div>

@include('includes.footerJs')
<script>
	function exportTableToExcel(tableID, filename = '')
    {
        var downloadLink;
        var dataType = 'application/vnd.ms-excel';
        var tableSelect = document.getElementById(tableID);
        var tableHTML = tableSelect.outerHTML.replace(/ /g, '%20');

        // Specify file name
        filename = filename?filename+'.xls':'excel_data.xls';

        // Create download link element
        downloadLink = document.createElement("a");

        document.body.appendChild(downloadLink);

        if(navigator.msSaveOrOpenBlob){
            var blob = new Blob(['\ufeff', tableHTML], {
                type: dataType
            });
            navigator.msSaveOrOpenBlob( blob, filename);
        }else{
            // Create a link to the file
            downloadLink.href = 'data:' + dataType + ', ' + tableHTML;

            // Setting the file name
            downloadLink.download = filename;

            //triggering the function
            downloadLink.click();
        }
    }
</script>
@include('includes.footer')
