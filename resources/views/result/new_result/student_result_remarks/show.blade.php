@include('includes.headcss') @include('includes.header') @include('includes.sideNavigation')
<div id="page-wrapper">
	<div class="container-fluid">
		<div class="row bg-title">
			<div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
				<h4 class="page-title">Student Result Remarks</h4>
			</div>
		</div>
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
			@php
            	$grade_id = $standard_id = $division_id = $term_id = '';
				if(isset($data['grade_id'])){
					$grade_id = $data['grade_id'];
					$standard_id = $data['standard_id'];
					$division_id = $data['division_id'];
				}
				if(isset($data['term_id'])){
					$term_id = $data['term_id'];
				}
			@endphp
			<form action="{{ route('student-result-remarks.create') }}">
				<div class="row">
					{{ App\Helpers\SearchChain('4','single','grade,std,div',$grade_id,$standard_id,$division_id) }}
                    {{ App\Helpers\TermDD($term_id) }}

					<div class="col-md-12 form-group">
						<center>
							<input type="submit" name="submit" value="Search" class="btn btn-success">
						</center>
					</div>
				</div>
			</form>
		</div>
		@if(isset($data['get_students'])) 
			<div class="card">
				<form method="POST" action="{{route('student-result-remarks.store')}}">
				@csrf
					<div class="row">
						<div class="col-lg-12 col-sm-12 col-xs-12">
							<div class="table-responsive">	
								<table id="example" class="table table-striped">
									<thead>
										<tr>
											<th>Sr No.</th>
											<th>Roll No.</th>
											<th>Gr No.</th>
											<th>Student Name</th>
											<th style="text-align:left;">Result Remarks</th>
										</tr>
									</thead>
									<tbody>
										@php 
											$i=1;
											$remarks = [
												'Passed & Promoted',
												'Promoted with condition of improvement',
												'Detained in class 9',
												'*Passed with grace marks',
												'Failed',
												'Conditionally Promoted',
												'Needs improvement',
												'Passed Promoted to class 10',
												'Detain',
												'Essential repeat',
												'Promoted as per RTE Guidelines (Conditional Promotion)'
												];
										@endphp

										@foreach($data['get_students'] as $key => $value)
										@php 
											$explodeVal = explode('||',$value->result_remarks);
											$remarks_val = (isset($explodeVal[0]) && $explodeVal[0]!='') ? $explodeVal[0] : '';
											$remarks_input = (isset($explodeVal[1]) && $explodeVal[1]!='') ? $explodeVal[1] : '';
										@endphp 
											<tr>
												<input type="hidden" value="{{ $value->id }}" name="student_id[]">
												<input type="hidden" value="{{ $data['term_id'] }}" name="term_id">
												<td>{{ $i++ }}</td>
												<td>{{ $value->roll_no }}</td>
												<td>{{ $value->gr_number }}</td>
												<td>{{ $value->first_name.' '.$value->middle_name.' '.$value->last_name }}</td>
												<td>
													<div class="row">
													<div class="col-md-6">
														<select id='result_remarks' name="result_remarks[{{ $value->id }}]" class="form-control">	
															<option value="">--Select Result Remarks--</option>
															@foreach($remarks as $remark)
																<option value="{{ $remark }}" @if(isset($remarks_val) && $remarks_val == $remark) selected="selected" @endif>{{ $remark }}</option>
															@endforeach
														</select>
													</div>
													@if(session()->get('sub_institute_id')==47)
													<div class="col-md-6">
													<!-- for mmis display division in result remarks  -->
														<input type="text" class="form-control" name="result_remarks_input[{{ $value->id }}]" @if(isset($remarks_input) && $remarks_input !='' && $remarks_input!=null) value="{{$remarks_input}}" @endif>
													</div>
													@endif
													</div>
												</td>
											</tr>
										@endforeach
										@php
											$i++
										@endphp
									</tbody>
								</table><br>
								<div class="col-md-3 col-sm-offset-4 text-center form-group">
									<input type="submit" name="submit" value="Save" class="btn btn-success">
								</div>
							</div>
						</div>
					</div>
				</form>
			</div>
		@endif
	</div>
</div>
@include('includes.footerJs')
<script>
	$(document).ready(function(){
		$('#term').prop('required',true);
	})
</script>
@include('includes.footer')
