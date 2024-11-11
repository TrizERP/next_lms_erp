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
				<form action="{{ route('map_teacher.store') }}" enctype="multipart/form-data" method="post">
					@csrf
                    <div class="main_div">
                        <div class="row cloneDiv" data-val="0">
                            <div class="col-md-2 form-group">
                                <label for="">Select Teacher</label>
                                <select name="teacher_id[]" id="teacher_id" class="form-control teacher_id" data-val="0" required>
                                <option value="">Select</option>
                                @foreach($data['users'] as $key=>$value)
                                <option value="{{$value->id}}">{{$value->name}}</option>
                                @endforeach
                                </select>
                            </div>

                            <div class="col-md-2 form-group">
                                <label for="">Select Standard</label>
                                <select name="standard_id[]" id="standard_id" class="form-control standard_id" data-val="0" onchange="getSubject(this);" required>
                                <option value="">Select</option>
                                @foreach($data['standard'] as $key=>$value)
                                <option value="{{$value->id}}">{{$value->name}}</option>
                                @endforeach
                                </select>
                            </div>
                            
                            <div class="col-md-2 form-group">
                                <label for="">Select Subject</label>
                                <select name="subject_id[]" id="subject_id" class="form-control subject_id"  data-val="0"  required>

                                </select>
                            </div>

                            <div class="col-md-2 form-group">
                                <label for="">Week Load</label>
                                <input type="number" name="load[]" id="load" class="form-control load"  data-val="0"  required>
                            </div>

                            <div class="col-md-2 form-group">
                                <a class="btn btn-primary plus" onclick="addRow(this)" data-val="0">+</a>
                                <a class="btn btn-primary minus" onclick="removeRow(this)" data-val="0">-</a>
                            </div>

                        </div>
                        <!-- append new div  -->
                    </div>

                    <!-- // submit button  -->
                    <div class="col-md-12 form-group">
                        <center>
                            <input type="submit" name="Add" value="Add" class="btn btn-success">
                        </center>
                    </div>

				</form>
			</div>
       
    </div>
</div>

@include('includes.footerJs')
<script>

function addRow(element){
    var data = parseInt($(element).attr('data-val')); 
    var data_val = (data+1);
    // alert(data_val);
    var clone = $(".cloneDiv:last").clone();
        clone.attr("data-val", data_val);
        clone.find("select").attr("data-val", data_val);
        clone.find("a").attr("data-val",data_val);
        clone.find("select").val(""); 
        clone.find("input[type='number']").attr("data-val",data_val);
        clone.find("input[type='number']").val("");
        $(".main_div").append(clone);
    
    $('.plus[data-val="'+data+'"]').prop('disabled',true);
}

function removeRow(element){
    var data = parseInt($(element).attr('data-val'));
    $(".cloneDiv[data-val='"+data+"']").remove();
    $(".plus:last").prop('disabled',false);
}

function getSubject(element){
    var data_val = $(element).attr('data-val');
    var standard = $(element).val();
    var subjects = [];
    $('.subject_id[data-val="'+data_val+'"]').empty();
    var path = "/api/get-subject-list";
    $.ajax({
        url: path,
        data: 'standard_id=' + standard,
        success: function(result) {
            // console.log(result);
            $.each(result, function(index, title) {
                var option = $('<option>', { value: index, text: title });
                if ($.inArray(index, subjects) !== -1) {
                    option.prop('selected', true);
                }
                $('.subject_id[data-val="'+data_val+'"]').append(option);
            });
        }
    });
}
</script>

@include('includes.footer')
@endsection