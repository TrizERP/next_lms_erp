@include('includes.headcss') @include('includes.header') @include('includes.sideNavigation')
<div id="page-wrapper">
	<div class="container-fluid">
		<div class="row bg-title">
			<div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
				<h4 class="page-title">Student Result Remarks</h4>
			</div>
		</div>
		<div class="card">
			@if(session('success'))
				<div class="alert alert-success alert-block">
					<button type="button" class="close" data-dismiss="alert">×</button>
					<strong>{{ session('success') }}</strong>
				</div>
			@endif
			<form action="{{ route('student-result-remarks.create') }}">
				<div class="row">
					<div class="col-md-4 form-group">
						<label>Standard </label>
						<select id='standard_id' name="standard_id" required class="form-control">
							<option value=""> Select Standard </option>
							@foreach($data['get_standards'] as $key => $value)
								<option value="{{$value['id']}}" @if(isset($data['standard_id'])) @if($data['standard_id'] == $value['id']) selected="selected" @endif @endif> {{$value['name']}} </option>
							@endforeach
						</select>
					</div>
					<div class="col-md-4 form-group">
						<label>Division </label>
						<select id='division_id' required="required" name="division_id" class="form-control">
							<option value=""> Select Division </option>
							@if(isset($data['get_divisions']))
								@foreach($data['get_divisions'] as $key => $value)
									<option value="{{$value->id}}" @if(isset($data['division_id'])) @if($data['division_id'] == $value->id) selected="selected" @endif @endif>{{$value->name}}</option>
								@endforeach
							@endif
						</select>
					</div>
					<div class="col-md-4 form-group">
						<label>Term</label>
						<select class="form-control" name="all_term">
							<option value=""> Select Term </option>
							@if($data['all_term'])
									@foreach($data['all_term'] as $id=>$term)
										<option value="{{$term->term_id}}"  @if(isset($data['term_id'])) @if($data['term_id'] == $term->term_id) selected="selected" @endif @endif>{{$term->title}}</option>
									@endforeach
							@endif
						</select>
					</div>
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
											$remarks = ['Passed Promoted to class 10','Promoted with condition of improvement','Detained in class 9','*Passed with grace marks','Failed','Conditionally Promoted','Needs improvement'];
										@endphp

										@foreach($data['get_students'] as $key => $value)
											<tr>
												<input type="hidden" value="{{ $value->id }}" name="student_id[]">
												<input type="hidden" value="{{ $data['term_id'] }}" name="term_id">
												<td>{{ $i++ }}</td>
												<td>{{ $value->roll_no }}</td>
												<td>{{ $value->gr_number }}</td>
												<td>{{ $value->first_name.' '.$value->middle_name.' '.$value->last_name }}</td>
												<td>
													<select id='result_remarks' name="result_remarks[{{ $value->id }}]" class="form-control" style="width:300px;">	
														<option value=""> Result Remarks </option>
														@foreach($remarks as $remark)
															<option value="{{ $remark }}" @if(isset($value->result_remarks)) @if($value->result_remarks == $remark) selected="selected" @endif @endif>{{ $remark }}</option>
														@endforeach
													</select>
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
@include('includes.footer')
