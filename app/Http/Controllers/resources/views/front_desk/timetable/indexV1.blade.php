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
				<form action="{{ route('timetableAIV1.create') }}" enctype="multipart/form-data">
					@csrf
					<div class="row">
						{{ App\Helpers\SearchChain('4','multiple','grade,std,div',$grade_id,$standard_id,$division_id) }}
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
                                                    <select class="form-control mb-2" name="periods[{{$value->id}}][{{$shortday}}][divisions][0]">
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
   function deleteTimetable(id) {
      
        var path = "{{ route('Delete_Timetable') }}";
        $.ajax({url: path,data:'id='+id,
        success: function(result){
            // Reload the current page
            location.reload();
        }
        });
    }
</script>

@include('includes.footer')
@endsection