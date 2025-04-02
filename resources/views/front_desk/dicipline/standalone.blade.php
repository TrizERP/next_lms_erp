@include('includes.headcss')
<div class="row bg-title m-4">
			<div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
				<h4 class="page-title">Student Discipline</h4> 
			</div>
		</div> 
<div class="card" style="margin:0px 20px !important;">
    @if(Session::has('message'))
    <div class="alert  @if(Session::has('status') && Session::get('message')==0)  alert-danger @else alert-success @endif alert-block">
    <button type="button" class="close" data-dismiss="alert">×</button>
    <strong>{{  Session::get('message') }}</strong>
    </div>
    @endif
    <form action="{{route('dicipline_alone.create')}}">
        @csrf
        <input type="hidden" name="type" value="webForm">
        <input type="hidden" name="sub_institute_id" value="{{$_REQUEST['sub_institute_id']}}">

    <div class="row">
        <div class="col-md-3">
            <label for="syear">Select Year</label>
            <select name="syear" id="syear" class="form-control">
                @foreach($data['syears'] as $key=>$syear)
                    @php 
                        $selectedSyear = '';
                        if($data['syear']==$syear->syear){
                            $selectedSyear='selected';
                        }
                    @endphp
                    <option value="{{$syear->syear}}" {{$selectedSyear}}>{{$syear->syear}}</option>
                @endforeach
            </select>
        </div>   
        <div class="col-md-3">
        <label for="grade">Select Grade</label>
            <select name="grade" id="grade1" class="form-control" >
                <option value=''>Select Grade</option>
                @foreach($data['gradeList'] as $key=>$gradeList)
                    @php 
                        $selectedGarde = '';
                        if(isset($data['grade']) && $data['grade']==$gradeList->id){
                            $selectedGarde='selected';
                        }
                    @endphp
                    <option value="{{$gradeList->id}}" {{$selectedGarde}}>{{$gradeList->title}}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3">
        <label for="standard">Select standard</label>
            <select name="standard" id="standard1" class="form-control" >
                
            </select>
        </div>
        <div class="col-md-3">
        <label for="division">Select Division</label>
            <select name="division" id="division1" class="form-control">
             
            </select>
        </div>

        <div class="col-md-3">
            <label for="">GR No</label>
            <input type="text" name="grNo" class="form-control" @if(isset($data['grno'])) value="{{$data['grno']}}" @endif>
        </div>
        <div class="col-md-3">
            <label for="user">Select User</label>
            <select name="user_id" id="user_id" class="form-control" required>
                <option value=''>Select User</option>
                @foreach($data['profiles'] as $key=>$user)
                    @php 
                        $selecteduser = '';
                        if(isset($data['user_id']) && $data['user_id']==$user->id){
                            $selecteduser='selected';
                        }
                    @endphp
                    <option value="{{$user->id}}" {{$selecteduser}}>{{$user->name}}</option>
                @endforeach
            </select>
        </div>   

    </div>
    <div class="row">
        <div class="col-md-12 mt-4">
            <center>
                <input type="submit" value="Search" name="search" class="btn btn-success">
            </center>
        </div>
    </div>
    </form>
    @if(isset($data['stu_data']) && !empty($data['stu_data']))
            <form action="{{ route('dicipline_alone.store') }}" enctype="multipart/form-data" method="post" class="mt-4">
                        {{ method_field("POST") }}
                        {{csrf_field()}}
                        <input type="hidden" name="type" value="webForm">
                        <input type="hidden" name="sub_institute_id" value="{{$_REQUEST['sub_institute_id']}}">
                        <input type="hidden" name="syear" value="{{$_REQUEST['syear']}}">
                        <input type="hidden" name="grade" value="{{ $data['grade']}}">
                        <input type="hidden" name="standard" value="{{ $data['standard']}}">
                        <input type="hidden" name="division" value="{{ $data['division']}}">
                        <input type="hidden" name="user_id" value="{{ $data['user_id']}}">
                       
                         <div class="table-responsive ">
							<table id="example" class="table table-striped display">
							<thead>
								<tr>
									<th><input type="checkbox" name="all" id="ckbCheckAll" class="ckbox">  </th>
									<th>Sr No</th>
									<th>Student Name</th>
									<th>Standard</th>
									<th>Division</th>
									<th>Mobile</th>
									<th>Select</th>
									<th class="text-left">Message</th>
								</tr>
							</thead>
                            @php
                            $arr = $data['stu_data'];
                            @endphp
                            @foreach ($arr as $id=>$col_arr)                            
                            <tr>

                                <td><input type="checkbox" name="{{ 'values[stud_id]['.$col_arr['student_id'].']'}}" class="ckbox1">  </td>
                                <td>{{ $id+1}}</td>
                                <td>
                                    {{-- $col_arr['name'] --}}
                                    {{App\Helpers\sortStudentName($col_arr['name'])}}
                                </td>
                                <td>{{ $col_arr['standard_name']}}</td>
                                <td>{{ $col_arr['division_name']}}</td>
                                <td>{{ $col_arr['mobile']}}</td>
                                <td>
                                    <select name="{{ 'values[dd]['.$col_arr['student_id'].']'}}"
                                            class="form-control">
                                        <option value="">Select</option>
                                        @foreach ($data['dd'] as $id => $name)
                                            <option value="{{ $name}}" @if($name=="Bad") selected @endif>{{ $name}}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td>
                                    <textarea name="{{ 'values[text]['.$col_arr['student_id'].']'}}"
                                              class="form-control">Yout Ward is late Today.</textarea>
                                </td>
                            </tr>
                            @endforeach
                        </table>
						</div>

                        <div class="col-md-12 form-group mt-2">
                            <center>
                                <input type="submit" name="submit" value="Save" class="btn btn-success" >
                            </center>
                        </div>

                    </form>
                   
                    @endif
                </div>
                @if (count($errors) > 0)
                <div class="alert alert-danger">
                    <strong>Whoops!</strong> There were some problems with your input.<br><br>
                    <ul>
                        @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
    @endif
</div>

@include('includes.footerJs')
<script>
    $(document).ready(function(){
        var syear = $('#syear').val();
        var sub_institute_id = "{{$_REQUEST['sub_institute_id']}}";

        @if(isset($data['grade']) && isset($data['standard']))
            getStandard("{{$data['grade']}}",sub_institute_id,syear,"{{$data['standard']}}");
        @endif
        @if(isset($data['standard']) && isset($data['division']))
            getDivision("{{$data['standard']}}",sub_institute_id,syear,"{{$data['division']}}");
        @endif

        $('#syear').change(function () {
           $('11').val('');
           $('#standard1').empty();
           $('#division1').empty();
        })

        $('#grade1').change(function () {
            getStandard($(this).val(),sub_institute_id,syear);
        })

        $('#standard1').change(function () {
            getDivision($(this).val(),sub_institute_id,syear);
        })
    })

    function getStandard(grade,sub_institute_id,syear,selVal=''){

        $.ajax({
                url : '/api/get-standard-list?grade_id='+grade+'&type=webForm&sub_institute_id='+sub_institute_id+'&syear='+syear,
                type : 'GET',
                success : function(res){
                    if (res) {
                        $("#standard1").empty();
                        $("#standard1").append('<option value="">Select</option>');
                        $.each(res, function (key, value) {
                            var selected = '';
                            if(selVal!='' && selVal==key){
                                selected = 'selected';
                            }
                            $("#standard1").append('<option value="' + key + '" '+selected+'>' + value + '</option>');
                        });
                        $("#division1").empty();

                    } else {
                        $("#standard1").empty();
                        alert('failed to fetch standard');
                    }
                }
            })
    }

    function getDivision(standard,sub_institute_id,syear,selVal=''){
        $.ajax({
                url : '/api/get-division-list?standard_id='+standard+'&type=webForm&sub_institute_id='+sub_institute_id+'&syear='+syear,
                type : 'GET',
                success : function(res){
                    if (res) {
                        $("#division1").empty();
                        $("#division1").append('<option value="">Select</option>');
                        $.each(res, function (key, value) {
                            var selected = '';
                            if(selVal!='' && selVal==key){
                                selected = 'selected';
                            }
                            $("#division1").append('<option value="' + key + '" '+selected+'>' + value + '</option>');
                        });

                    } else {
                        $("#division1").empty();
                    }
                }
            })
    }
</script>
@include('includes.footer')