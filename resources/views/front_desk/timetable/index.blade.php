@extends('layout') 
@section('container')
<div id="page-wrapper">
	<div class="container-fluid">
		<div class="row bg-title">
			<div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
				<h4 class="page-title">Automatic Timetable Generation</h4>
			</div>
		</div>
		@php 
        $grade_id = $standard_id = $division_id = '';
         if(isset($data['grade_id'])){ 
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
				<form action="{{ route('timetableAI.create') }}" enctype="multipart/form-data">
					@csrf
					<div class="row">
						{{ App\Helpers\SearchChain('4','single','grade,std,div',$grade_id,$standard_id,$division_id) }}
                        <!-- number of periods for teacher -->
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="">Number of Period</label>
                                <input type="number" name="number_period" class="form-control" id="number_period" value="{{ isset($data['number_period']) ? $data['number_period'] : '' }}" required>
                            </div>
                        </div>
                        <!-- subjects per periods -->
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="">Subject Per Period</label>
                                <input type="number" name="subject_per_period" class="form-control" id="subject_per_period" value="{{ isset($data['subject_per_period']) ? $data['subject_per_period'] : '' }}" required>
                            </div>
                        </div>
                        </div>                        
                        <div id="teacherSection" class="row">                        
                        <!-- select teacher -->
                        <div class="col-md-4">
                            <div class="form-group">
                            <label for="">Teacher</label>                            
                                <select name="select_teacher[0]" id="select_teacher" class="form-control" data-val="0" required>
                                <option>Select</option>
                                    @foreach($data['teachers'] as $key=>$value)
                                        <option value="{{$value->id}}" @if(isset($data['select_teacher']) && $value->id==$data['select_teacher']) selected @endif>{{$value->teacher_name}}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <!-- week load -->
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="">Week Load</label>
                                <input type="number" name="week_load[0]" class="form-control" id="week_load" data-val="0" value="{{ isset($data['week_load']) ? $data['week_load'] : '' }}" required>
                            </div>
                        </div>
                        <!-- days selections -->
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="">Days</label>                            
                                <select name="select_day[0][]" id="select_day" class="form-control" data-val="0" multiple required>
                                    @foreach($data['days'] as $key=>$value)
                                        <option value="{{$key}}"  @if(!empty($data['select_day']) && in_array($key,$data['select_day'])) selected @endif>{{$value}}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <!-- Periods selections -->
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="">Periods</label>                            
                                <select name="select_period[0][]" id="select_period" class="form-control" data-val="0" multiple required>
                                    @foreach($data['periods'] as $key=>$value)
                                        <option value="{{$value->id}}" @if(!empty($data['select_period']) && in_array($value->id,$data['select_period'])) selected @endif>{{$value->title}}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <!-- Subject selections -->
                        <div class="col-md-4">
                            <div class="form-group">
                            <label for="">Subject</label>                            
                                <select name="select_subject[0][]" id="select_subject" class="form-control" data-val="0" multiple required>
                                    <option>Please select standard first</option>
                                </select>
                            </div>
                        </div>  
                        <div class="col-md-4">
                        <div class="buttons-action d-block">
                                <div class="add m-2">
                                <input type="button" name="add_teacher" id="add_teacher0"  data-val="0" value="+" class="btn btn-primary" onclick="addNewTeacher(0)">
                                </div>
                                <div class="remove m-2">
                                <input type="button" name="remove_teacher" id="remove_teacher0" data-val="0" value="-" class="btn btn-primary" onclick="RemoveNewTeacher(0)">
                                </div>
                            </div>
                        </div>
                        <hr>
                        
                        </div>
                        <!-- for new teachers  -->
                        <div id="newAddedTeacher" class="col-md-12"></div>
                        <!-- search button  -->
						<div class="col-md-12 form-group">
							<center>
							
								<input type="submit" name="Search" value="Search" class="btn btn-success">
							</center>
						</div>
					</div>
				</form>
			</div>
	<!-- data from response  -->

 @if(isset($data['timetableData']['period_data']))
            <div class="card">
                <form action="{{route('timetableAI.store')}}" method="POST">  
					@csrf
                	<div class="row">
                        <div class="col-lg-12 col-sm-12 col-xs-12 p-0">
                        <input type="hidden" name="grade_id" value="{{$grade_id}}">
                        <input type="hidden" name="standard_id" value="{{$standard_id}}">
                        <input type="hidden" name="division_id" value="{{$division_id}}">
                        
                                <table id="example" class="table table-striped">
                                    <thead>
                                    <tr>
                                    <th><span class="label label-info">Days/Lectures</span></th>
                                      @foreach($data['timetableData']['period_data'] as $key => $value)
                                      <th><span class="label label-info">{{$value->title}}</span></th>
                                      @endforeach
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @php
                                        $j=1;
                                    @endphp
							        @if(isset($data['days']))
							        
									    @foreach($data['days'] as $shortday => $fullday)
									        <tr>
									            <td  style='display: table-cell;'><span class='label label-warning'>{{ $fullday }}</span></td>
									            @foreach($data['timetableData']['period_data'] as $key => $value)
									            <td class="text-center" style="font-size:10px;color: black;">
									            <!-- remove or delete timetable -->
									                @php 
									                $timetable_id =  $value->id ?? '';
									                $j=1;
									                @endphp
													<div id="{{$shortday.'-'.$timetable_id}}">

									            	@if(isset($data['timetableData']['timetable_data'][$shortday][$value->id]['SUBJECT_ID']))
   													
                                                        @foreach($data['timetableData']['timetable_data'][$shortday][$value->id]['SUBJECT_ID'] as $index => $lect)
                                                        <div style="display:flex">
                                                        <div class="timetabledata">
                                                        <!-- assigned subject  -->
                                                        @if(!empty($data['timetableData']['subject_data']))
                                                        <div class="form-group">
                                                        <select class="form-control mb-2" name="periods[{{$value->id}}][{{$shortday}}][subject][{{$index}}]">
                                                        <option value="-">--Subject--</option>
                                                            @foreach($data['timetableData']['subject_data'] as $sub => $subjects)
                                                            @php
                                                                $selected_sub=$data['timetableData']['timetable_data'][$shortday][$value->id]['SUBJECT_ID'][$index] ?? '';
                                                            @endphp
                                                            <option value="{{$subjects['subject_id']}}" @if(isset($selected_sub) && $selected_sub==$subjects['subject_id']) selected @endif>{{$subjects['display_name']}}</option>
                                                            @endforeach
                                                        </select>
                                                        </div>
                                                        @endif

                                                        <!-- assigned teachers  -->
                                                        @if(!empty($data['timetableData']['teacher_data']))
                                                        <div class="form-group">
                                                        <select class="form-control mb-2" name="periods[{{$value->id}}][{{$shortday}}][teachers][{{$index}}]">
                                                            <option value="-">--Teacher--</option>
                                                            @foreach($data['timetableData']['teacher_data'] as $teach => $teachers)
                                                            @php
                                                                $selected_teach=$data['timetableData']['timetable_data'][$shortday][$value->id]['TEACHER_ID'][$index] ?? '';
                                                            @endphp
                                                            <option value="{{$teachers['id']}}" @if(isset($selected_teach) && $selected_teach==$teachers['id']) selected @endif>{{$teachers['teacher_name']}}</option>
                                                            @endforeach
                                                            </select>
                                                        </div>
                                                        @endif

                                                        @if(!empty($data['timetableData']['stdData']))
                                                        <div class="form-group">
                                                        <select class="form-control mb-2" name="periods[{{$value->id}}][{{$shortday}}][standards][{{$index}}]">
                                                            <option value="-">--Stanadard--</option>
                                                            @foreach($data['timetableData']['stdData'] as $std => $values)
                                                            @php
                                                                $selected_std=$data['timetableData']['timetable_data'][$shortday][$value->id]['standard_id'][$index] ?? '';
                                                            @endphp
                                                            <option value="{{$values['id']}}" @if(isset($selected_std) && $selected_std==$values['id']) selected @endif>{{$values['name']}}</option>
                                                            @endforeach
                                                            </select>
                                                        </div>
                                                        @endif
                                                        <!-- // division -->
                                                         @if(!empty($data['timetableData']['divData']))
                                                        <div class="form-group">
                                                        <select class="form-control mb-2" name="periods[{{$value->id}}][{{$shortday}}][divisions][{{$index}}]">
                                                            <option value="-">--Division--</option>
                                                            @foreach($data['timetableData']['divData'] as $std => $values)
                                                            @php
                                                                $selected_div=$data['timetableData']['timetable_data'][$shortday][$value->id]['division_id'][$index] ?? '';
                                                            @endphp
                                                            <option value="{{$values['id']}}" @if(isset($selected_div) && $selected_div==$values['id']) selected @endif>{{$values['name']}}</option>
                                                            @endforeach
                                                            </select>
                                                        </div>
                                                        @endif

                                                    </div>

                                                    <div class="plus">
                                                    <a class="fas fa-window-close text-danger p-2" href="#" onclick="deleteTimetable('{{$shortday.'-'.$timetable_id}}');"></a>    
                                                    <a class='mdi mdi-source-merge fa-fw text-danger p-2' href='#' onclick=addNewStdandardDiv('{{$shortday.'-'.$timetable_id}}');></a>
                                                    </div>
                                                </div>

                                                    @endforeach
									            
                                                @else
									         <div style="display:flex">
                                                <div class="timetabledata">
										        	<!-- unassigned subject  -->
 												@if(!empty($data['timetableData']['subject_data']))
									                <div class="form-group">
										                <select class="form-control mb-2" name="periods[{{$value->id}}][{{$shortday}}][subject][0]">
											            <option value="-">--Subject--</option>
	    												@foreach($data['timetableData']['subject_data'] as $sub => $subjects)
	    												<option value="{{$subjects['subject_id']}}">{{$subjects['display_name']}}</option>
	        										     @endforeach
														</select>
													</div>
									            @endif
 												   
									            	<!-- unassigned teachers  -->
  												@if(!empty($data['timetableData']['teacher_data']))
									                <div class="form-group">
									                   <select class="form-control mb-2" name="periods[{{$value->id}}][{{$shortday}}][teachers][0]">
										              	<option value="-">--Teacher--</option>
    													@foreach($data['timetableData']['teacher_data'] as $teach => $teachers)
    													 <option value="{{$teachers['id']}}">{{$teachers['teacher_name']}}</option>
        										      @endforeach
													</select>
													</div>
									            @endif
                                                @if(!empty($data['timetableData']['stdData']))
                                                    <div class="form-group">
                                                    <select class="form-control mb-2" name="periods[{{$value->id}}][{{$shortday}}][standards][0]">
                                                        <option value="-">--Stanadard--</option>
                                                        @foreach($data['timetableData']['stdData'] as $std => $values)
                                                        <option value="{{$values['id']}}">{{$values['name']}}</option>
                                                        @endforeach
                                                        </select>
                                                    </div>
                                                    @endif
                                                    <!-- // division -->
                                                        @if(!empty($data['timetableData']['divData']))
                                                    <div class="form-group">
                                                    <select class="form-control mb-2" name="periods[{{$value->id}}][{{$shortday}}][divisions][{{$index}}]">
                                                        <option value="-">--Division--</option>
                                                        @foreach($data['timetableData']['divData'] as $std => $values)
                                                        <option value="{{$values['id']}}">{{$values['name']}}</option>
                                                        @endforeach
                                                        </select>
                                                    </div>
                                                    @endif
										        </div>
                                                <div class="plus">
									               <!-- add or delete timetable  -->
											           	<a class="fas fa-window-close text-danger" href="#" onclick="deleteTimetable('{{$shortday.'-'.$timetable_id}}');" id="delete-{{$shortday.'-'.$timetable_id}}"></a>
                                                    </div>
                                                </div>
									            @endif
									            </td>
									            @endforeach
									            
									        </tr>
									    @endforeach
									@endif
	
                                    </tbody>
                                </table>

                                 <div class="col-md-12 form-group mt-4">
                                <center>
                                    <input type="submit" name="submit" value="Create" class="btn btn-success">
                                </center>
                            </div>

                           
                        </div>
                    </div>
                </form>
            </div>
        @endif

        @if(isset($data['existed_data']) && !empty($data['existed_data']))
        <div class="card">
        <h4>This periods are already assigned OR Skipped periods.</h4>
            <div class="row">
                <table id="example" class="table table-striped">
                    <thead>
                        <tr>
                            <th>Sr no</th>
                            <th>Day</th>
                            <th>Period</th>
                            <th>Subject</th>
                            <th>Standard</th>
                            <th>Division</th>
                            <th class="text-left">Teacher</th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach($data['existed_data'] as $key=>$value)
                        <tr>
                            <td>{{$key+1}}</td>
                            <td>{{$data['days'][$value->week_day]}}</td>
                            <td>{{$value->period}}</td>  
                            <td>{{$value->subject_name}}</td>                            
                            <td>{{$value->standard}}</td>                            
                            <td>{{$value->division}}</td>                            
                            <td>{{$value->teacher}}</td>                            
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>        
        @endif
    <!-- end response -->
    </div>
</div>

@include('includes.footerJs')
<script>
    // api/get-subject-list standard_id
    $(document).ready(function() {
        @if(isset($data['standard_id']))
            getSubjects();        
        @endif
    })

    $("#standard").change(function(){       
        getSubjects();
    })
    function getSubjects() {
        $('#division').prop('required',true);
        var std_id = $("#standard").val();
        var subjects = [];
        @if(isset($data['select_subject']))
            subjects = @json($data['select_subject']);
        @endif

        var path = "/api/get-subject-list";
        $.ajax({
            url: path,
            data: 'standard_id=' + std_id,
            success: function(result) {
                // console.log(result);
                $('#select_subject').empty();
                $.each(result, function(index, title) {
                    var option = $('<option>', { value: index, text: title });
                    if ($.inArray(index, subjects) !== -1) {
                        option.prop('selected', true);
                    }
                    $('#select_subject').append(option);
                });
            }
        });
    }
@if($standard_id !='' && $division_id!='')
    function deleteTimetable(id) {
        var standard_id = {{$standard_id}};
        var division_id = {{$division_id}};
        var grade_id = {{$grade_id}};
        var path = "{{ route('Delete_Timetable') }}";
        $.ajax({url: path,data:'standard_id='+standard_id+'&division_id='+division_id+'&grade_id='+grade_id+'&id='+id,
        success: function(result){
            // Reload the current page
            location.reload();
        }
        });
    }

    function addNewRow(id){
        var standard_id = {{$standard_id}};
        var division_id = {{$division_id}};
        var path = "{{ route('add_remove_Batch_Timetable') }}";
            $.ajax({url: path,data:'mode=batchwise&standard_id='+standard_id+'&division_id='+division_id+'&id='+id,
        success: function(result){
            $("#"+id).html(result);
            $('#add-'+id).remove();
            $('#delete-'+id).remove();
        }
        });
    }

    function removeNewRow(id){
        var standard_id = {{$standard_id}};
        var division_id = {{$division_id}};
        var path = "{{ route('add_remove_Batch_Timetable') }}";
        $.ajax({
            url: path, data: 'mode=normal&standard_id=' + standard_id + '&division_id=' + division_id + '&id=' + id,
            success: function (result) {
                $("#" + id).html(result);
            }
        });
    }
        
    function addNewStdandardDiv(id){
        var standard_id = {{$standard_id}};
        var division_id = {{$division_id}};
        var path = "{{ route('ajax_New_Standard_Div') }}";
        $.ajax({
            url: path, data: 'mode=normal&standard_id=' + standard_id + '&division_id=' + division_id + '&id=' + id,
            success: function (result) {
                $("#" + id).append(result);
            }
        });
    }

@endif

 function addNewTeacher(nameVal){
     var standard = $('#standard').val();
     if(standard===''){
        alert('Before Clone please select standard');
        return false;
     }

    // Increment the maximum index by one
    var newIndex = nameVal + 1;
    // alert(newIndex);
    var newTeacherSection = $("#teacherSection").clone();
        
        // Update names of elements inside the cloned section
        newTeacherSection.find("[name^='select_teacher']").each(function(index) {
            $(this).attr("name", $(this).attr("name").replace(/\[\d+\]/, "[" + newIndex + "]"));
            $(this).attr("data-val", newIndex);
        });
        newTeacherSection.find("[name^='week_load']").each(function(index) {
            $(this).attr("name", $(this).attr("name").replace(/\[\d+\]/, "[" + newIndex + "]"));
            $(this).attr("data-val", newIndex);            
        });
        newTeacherSection.find("[name^='select_day']").each(function(index) {
            $(this).attr("name", $(this).attr("name").replace(/\[\d+\]/, "[" + newIndex + "]"));
            $(this).attr("data-val", newIndex);            
        });
        newTeacherSection.find("[name^='select_period']").each(function(index) {
            $(this).attr("name", $(this).attr("name").replace(/\[\d+\]/, "[" + newIndex + "]"));
            $(this).attr("data-val", newIndex);            
        });
        // Update names of select_subject elements
        newTeacherSection.find("[name^='select_subject']").each(function(index) {
            $(this).attr("name", $(this).attr("name").replace(/\[\d+\]/, "[" + newIndex + "]"));
            $(this).attr("data-val", newIndex);            
        });

       newTeacherSection.find("[name^='add_teacher']").each(function(index) {
            var currentOnClick = $(this).attr("onclick");
            var newIndex2 = "(" + newIndex + ")";
            var updatedOnClick = currentOnClick.replace(/\(\d+\)/, newIndex2);
            $(this).attr("onclick", updatedOnClick);
            $(this).attr("data-val", newIndex);                      
            $(this).attr("id", "add_teacher" + newIndex);

        });

        newTeacherSection.find("[name^='remove_teacher']").each(function(index) {
            var currentOnClick = $(this).attr("onclick");
            var newIndex2 = "(" + newIndex + ")";
            var updatedOnClick = currentOnClick.replace(/\(\d+\)/, newIndex2);
            $(this).attr("onclick", updatedOnClick);
            $(this).attr("data-val", newIndex);     
            $(this).attr("id", "remove_teacher" + newIndex);       
        });
        
        // Append the cloned section to the container
        $("#newAddedTeacher").append(newTeacherSection);
        $('#add_teacher'+nameVal).remove();
    }

 function RemoveNewTeacher(nameVal){
    if ($('#select_teacher').attr('data-val') == nameVal) {
        // Remove the element with id "select_teacher"
        $('#select_teacher').remove();
    }
    if ($('#week_load').attr('data-val') == nameVal) {
        // Remove the element with id "select_teacher"
        $('#week_load').remove();
    }
    if ($('#select_day').attr('data-val') == nameVal) {
        // Remove the element with id "select_teacher"
        $('#select_day').remove();
    }
    if ($('#select_period').attr('data-val') == nameVal) {
        // Remove the element with id "select_teacher"
        $('#select_period').remove();
    }
    if ($('#select_subject').attr('data-val') == nameVal) {
        // Remove the element with id "select_teacher"
        $('#select_subject').remove();
    }
    if ($('#add_teacher'+nameVal).attr('data-val') == nameVal) {             
        var newAdd = '<input type="button" name="add_teacher" id="add_teacher0" data-val="0" value="+" class="btn btn-primary" onclick="addNewTeacher('+(nameVal-1)+')">';
        $('#remove_teacher'+nameVal).before(newAdd);
        $('#add_teacher'+nameVal).remove();
    }

    if ($('#remove_teacher'+nameVal).attr('data-val') == nameVal) {
        // Remove the element with id "select_teacher"
        $('#remove_teacher'+nameVal).remove();
    }

     
 }    
</script>

@include('includes.footer')
@endsection