@include('includes.headcss') @include('includes.header') @include('includes.sideNavigation')

<div id="page-wrapper">
	<div class="container-fluid">
		<div class="row bg-title">
			<div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
				<h4 class="page-title">Student Results</h4>
			</div>
		</div>
        @php 
        $grade_id = $standard_id = $division_id = ''; 
        if(isset($data['grade_id']))
        { 
            $grade_id = $data['grade_id']; 
            $standard_id= $data['standard_id']; 
            $division_id = $data['division_id']; 
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
				<form action="{{ route('student-result.create') }}">
					<div class="row">
                    <div class="col-md-4 form-group">
							<label>Result Template</label>
							<select class="form-control" name="template" required="required">
                                <option value="">Select Result Template</option>
                                @if(isset($data['data']))
                                @foreach($data['data'] as $key=>$value)
                                <option value="{{$value['id']}}" @if(isset($data['template']) && $value['id']==$data['template']) selected @endif>{{$value['module_name']}}</option>
                                @endforeach
								@endif
							</select>
						</div>
						
						<div class="col-md-4 form-group">
							<label>Result Format</label>
							<select class="form-control" name="format" required="required">
								@foreach($data['terms'] as $key=>$value)
									<option value="{{$value->term_id}}" @if(isset($data['format']) && $data['format']==$value->term_id) selected @endif>{{$value->title}}</option>
								@endforeach
                                <option value="yearly" @if(isset($data['format']) && $data['format']=="yearly") selected @endif>Yearly</option>                              
							</select>
                        </div>

                        {{ App\Helpers\SearchChain('4','single','grade,std,div',$grade_id,$standard_id,$division_id) }}
                        
						<div class="col-md-12 form-group">
							<center>
								<input type="submit" name="submit" value="Search" class="btn btn-success">
							</center>
						</div>
					</div>
				</form>
			</div>
			@if(isset($data['student_data'])) @php if(isset($data['student_data'])){ $student_data = $data['student_data']; } @endphp

			<div class="card">
				<form method="POST" action="{{route('student-result.store')}}">
					@csrf
					<div class="row">
						<div class="col-lg-12 col-sm-12 col-xs-12">
							<div class="table-responsive">
							<input type="hidden" value="{{$data['template']}}" name="template_id">
							<input type="hidden" value="{{$data['format']}}" name="format">	
                            <input type="hidden" value="{{$data['grade_id']}}" name="grade_id">							
                            <input type="hidden" value="{{$data['standard_id']}}" name="standard_id">							
                            <input type="hidden" value="{{$data['division_id']}}" name="division_id">							
													
								<table id="example" class="table table-striped">
									<thead>
										<tr>
											<th>
												<input id="checkall" onchange="checkAll(this);" type="checkbox">
											</th>
											<th>{{App\Helpers\get_string('grno','request')}}</th>
											<th>{{App\Helpers\get_string('studentname','request')}}</th>
											<th>{{App\Helpers\get_string('standard','request')}}</th>
											<th class="text-left">{{App\Helpers\get_string('division','request')}}</th>
										</tr>
									</thead>
									<tbody>
                                        @php 
                                        $i=1;
                                        @endphp
                                        @foreach($data['student_data'] as $key => $value)
                                        <tr>
                                        <td><input id="{{$value['id']}}" value="{{$value['id']}}" name="students[]" type="checkbox"></td>
                                            <td>{{$value['enrollment_no']}}</td>
                                            <td>{{$value['first_name'].' '.$value['middle_name'].' '.$value['last_name']}}</td>
                                            <td>{{$value['standard_name']}}</td>
                                            <td>{{$value['division_name']}}</td>
                                        </tr>
                                        @endforeach
									</tbody>
								</table>
							</div>
						</div>
						<div class="col-md-12 form-group">
							<center>
							
								<input type="submit" name="submit" value="Submit" class="btn btn-success" onclick="check_validation()">
							</center>
						</div>
					</div>
				</form>
			</div>
			@endif
		</div>
	</div>

	@include('includes.footerJs')
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

		function check_validation() {
			var checked_questions = err = 0;

			$("input[name='students[]']:checked").each(function() {
				checked_questions = checked_questions + 1;
			});
			if (checked_questions == 0) {
				alert("Please Select Atleast one Student from search");
				err = 1;
				return false;
			} else {
				return true;
			}
		}
	</script>

	<!-- <script>
$(document).ready(function () {
    $('#example').DataTable();
});

</script> -->
	@include('includes.footer')