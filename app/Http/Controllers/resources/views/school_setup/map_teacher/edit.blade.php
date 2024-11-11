@extends('layout') 
@section('container')
<div id="page-wrapper">
	<div class="container-fluid">
		<div class="row bg-title">
			<div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
				<h4 class="page-title">Map Teacher Load</h4>
			</div>
		</div>
		@php 
        $grade_id = $standard_id = '';
         if(isset($data['grade_id'])){ 
            $grade_id = $data['grade_id']; 
            $standard_id= $data['standard_id'];
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
				<form action="{{ route('map_teacher.update', $data['mapData']->id) }}" enctype="multipart/form-data" method="post">
                {{ method_field("PUT") }}       
					@csrf
                    <div class="main_div">
                        <div class="row cloneDiv">
                            <div class="col-md-4 form-group">
                                <label for="">Select Teacher</label>
                                <select name="teacher_id" id="teacher_id" class="form-control" data-val="0" required>
                                <option value="">Select</option>
                                @foreach($data['users'] as $key=>$value)
                                <option @if($data['mapData']->teacher_id == $value->id) selected @endif value="{{$value->id}}">{{$value->name}}</option>
                                @endforeach
                                </select>
                            </div>

                            <div class="col-md-4 form-group">
                                <label for="">Standard</label>
                                <input type="text" name="standard" id="standard" value="{{$data['mapData']->standard_name}}" class="form-control"  data-val="0"  disabled>
                            </div>
                            
                            <div class="col-md-4 form-group">
                                <label for="">Subject</label>
                                <input type="text" name="subject" id="subject" value="{{$data['mapData']->subject_name}}" class="form-control"  data-val="0"  disabled>
                            </div>

                            <div class="col-md-4 form-group">
                                <label for="">Week Load</label>
                                <input type="number" name="load" id="load" class="form-control"  value="{{$data['mapData']->load}}"  required>
                            </div>

                        </div>
                        <!-- append new div  -->
                    </div>

                    <!-- // submit button  -->
                    <div class="col-md-12 form-group">
							<center>
								<input type="submit" name="Update" value="Update" class="btn btn-success">
							</center>
						</div>

				</form>
			</div>

       
    </div>
</div>

@include('includes.footerJs')
<script>
$(document).ready(function(){
    $(".btn-primary").click(function(){
        var clone = $(".cloneDiv").first().clone(); 
        clone.find("select").val(""); 
        clone.find("input[type='number']").val("");
        $(".main_div").append(clone);
    });

})

function getSubject(element){
    var data_val = $(element).attr('data-val');
    alert(data_val);
}
</script>

@include('includes.footer')
@endsection