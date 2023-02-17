@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Student Transfer</h4>
            </div>
        </div>
        @php
        $grade_id = $standard_id = $division_id = $from_institute_name = $from_client_id = $from_syear = $to_sub_institute_id = $to_syear = $to_academic_section = $to_standard = $to_division = '';
            if(isset($data['grade'])){
                $grade_id = $data['grade'];
                $standard_id = $data['standard'];
                $division_id = $data['division'];
            }
            if(isset($data['from_institute_name']))
            {
                $from_institute_name = $data['from_institute_name'];
            }
            if(isset($data['from_client_id']))
            {
                $from_client_id = $data['from_client_id'];
            }
            if(isset($data['from_syear']))
            {
                $from_syear = $data['from_syear'];
            }
            if(isset($data['to_sub_institute_id']))
            {
                $to_sub_institute_id = $data['to_sub_institute_id'];
            }
            if(isset($data['to_syear']))
            {
                $to_syear = $data['to_syear'];
            }
            if(isset($data['to_academic_section']))
            {
                $to_academic_section = $data['to_academic_section'];
            }
            if(isset($data['to_standard']))
            {
                $to_standard = $data['to_standard'];
            }
            if(isset($data['to_division']))
            {
                $to_division = $data['to_division'];
            }
            
        @endphp
        <div class="card">            
             @if ($sessionData = Session::get('data'))
                @if($sessionData['status'] == 1)
                <div class="alert alert-success alert-block">
                @else
                <div class="alert alert-danger alert-block">
                @endif
                    <button type="button" class="close" data-dismiss="alert">×</button>
                    <strong>{{ $sessionData['message'] }}</strong>
                </div>
            @endif
            <form action="{{ route('student_transfer.create') }}">  
                <div class="row"> 
                    <div class="col-md-6"> 
                        <div class="col-md-12">
                            <label>From Institute Name:</label>
                            <input type="text" id="from_institute_name" value="{{$from_institute_name}}" name="from_institute_name" class="form-control" readonly required="required">
                            <input type="hidden" id="from_sub_institute_id" value="{{Session::get('sub_institute_id')}}" name="from_sub_institute_id" class="form-control">
                            <input type="hidden" id="from_client_id" value="{{$from_client_id}}" name="from_client_id" class="form-control">
                        </div>
                        <div class="col-md-12">
                            <label>From Academic Year:</label>
                            <select class="cust-select form-control year-sel" required="required" name="from_syear" id="from_syear">
                                <option>Select Academic Year</option>
                                @foreach(Session::get('academicYears') as $kay => $vay)
                                    <option value="{{$vay->syear}}"
                                    @if($from_syear == $vay->syear)
                                    selected="selected"
                                    @endif
                                    >{{$vay->syear}}</option>
                                @endforeach
                            </select>
                        </div>              
                        {{ App\Helpers\SearchChain('12','single','grade,std,div',$grade_id,$standard_id,$division_id) }}
                    </div>
                    <div class="col-md-6"> 
                        <div class="col-md-12">
                            <label>To Institute Name:</label>
                            <select name="to_sub_institute_id" id="to_sub_institute_id" class="form-control" required="required" onchange="getToAcademicSection(this.value);">
                                <option value="">Select To Institute Name</option>
                                @if(isset($data['to_institute_details']))
                                    @foreach($data['to_institute_details'] as $key => $value)
                                        <option value="{{$value['Id']}}"
                                            @if($to_sub_institute_id == $value['Id'])
                                            selected='selected'
                                            @endif
                                        >{{$value['SchoolName']}}</option>
                                    @endforeach
                                @endif    
                            </select>
                        </div>
                        <div class="col-md-12">
                            <label>To Academic Year:</label>
                            <select class="cust-select form-control year-sel" required="required" name="to_syear" id="to_syear">
                                <option>Select Academic Year</option>
                                @foreach(Session::get('academicYears') as $kay => $vay)
                                    <option value="{{$vay->syear}}"
                                    @if($to_syear == $vay->syear)
                                    selected="selected"
                                    @endif
                                    >{{$vay->syear}}</option>
                                @endforeach
                            </select>
                        </div> 
                        <div class="col-md-12">
                            <label>To Academic Section:</label>                            
                            <select name="to_academic_section" id="to_academic_section" class="form-control" required="required" onchange="getToStandard(this.value);">
                                <option value="">Select</option>
                                @if(isset($data['to_academic_sections']))
                                    @foreach($data['to_academic_sections'] as $k => $v)
                                        <option value="{{$v['id']}}" 
                                        @if($to_academic_section == $v['id'])
                                        selected='selected'
                                        @endif >{{$v['title']}}</option>
                                    @endforeach
                                @endif
                            </select>               
                        </div>
                        <div class="col-md-12">
                            <label>To Standard:</label>                            
                            <select name="to_standard" id="to_standard" class="form-control" required="required" onchange="getToDivision(this.value);">
                                <option value="">Select</option>
                                @if(isset($data['to_standards']))
                                    @foreach($data['to_standards'] as $k1 => $v1)
                                        <option value="{{$v1['id']}}" 
                                        @if($to_standard == $v1['id'])
                                        selected='selected'
                                        @endif>{{$v1['name']}}</option>
                                    @endforeach
                                @endif
                            </select>               
                        </div>
                        <div class="col-md-12">
                            <label>To Division:</label>                            
                            <select name="to_division" id="to_division" class="form-control" required="required">
                                <option value="">Select</option>
                                @if(isset($data['to_divisions']))
                                    @foreach($data['to_divisions'] as $k2 => $v2)
                                        <option value="{{$v2['id']}}" 
                                        @if($to_division == $v2['id'])
                                        selected='selected'
                                        @endif>{{$v2['name']}}</option>
                                    @endforeach
                                @endif
                            </select>               
                        </div>
                    </div>
                    <div class="col-sm-12 form-group">
                        <center>                            
                            <input type="submit" name="submit" value="Search" class="btn btn-success" >
                        </center>
                    </div>
                </div>              
            </form>            
        </div>
        @if(isset($data['student_data']))
        @php
            if(isset($data['student_data'])){
                $student_data = $data['student_data'];
                $finalData = $data;
            }
        @endphp

        <div class="card">                
            <form method="POST" action="{{ route('student_transfer.store') }}" id="submit_form">
                @if(isset($data['modules_array']))
                    @foreach($data['modules_array'] as $module_key => $module_name)
                        <div class="form-group col-md-2 ml-0 mr-0">
                            <div class="custom-control custom-checkbox d-flex align-items-center">
                                @php
            
                                $checked = $disabled = '';
                                if($module_key == 'general_information')
                                {
                                    $checked = 'checked=checked';
                                    $disabled = ' disabled="disabled" ';
                                }
                                
                                @endphp
                                <input type="checkbox" id="{{$module_key}}" value="{{$module_key}}" class="custom-control-input" name="modules[]" {{$checked}} {{$disabled}}>
                                <label class="custom-control-label mb-0 pt-1" for="{{$module_key}}">{{$module_name}}</label>
                            </div>
                        </div>
                    @endforeach
                @endif
                                  
                <div class="row mt-5">                    
                    <div class="col-lg-12 col-sm-12 col-xs-12">
                        <div class="table-responsive">
                            <table class="table table-box table-bordered">
                                <thead>
                                    <tr>
                                        <th><input id="checkall" name="checkall" onchange="checkAll(this);" type="checkbox"></th>
                                        <th>Sr.No.</th>
                                        <th>Student Name</th>
                                        <th>Enrollment No.</th>
                                        <th>Standard</th>
                                        <th>Division</th>
                                        <th>Mobile</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @php
                                    $j=1;
                                    @endphp
                                    @foreach($student_data as $key => $data)
                                    <tr>
                                        <td>
                                            <input id="students" value="{{$data['student_id']}}" name="students[]" type="checkbox">
                                        </td>
                                        <td>{{$j}}</td>
                                        <td>{{$data['first_name']}} {{$data['middle_name']}} {{$data['last_name']}}</td>
                                        <td>{{$data['enrollment_no']}}</td>
                                        <td>{{$data['standard_name']}}</td>
                                        <td>{{$data['division_name']}}</td>
                                        <td>{{$data['mobile']}}</td>                                        
                                    </tr>
                                        @php
                                        $j++;
                                        @endphp
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>    
                    <div class="col-md-12 form-group">
                        <center>
                            <input type="hidden" name="from_institute_name" @if(isset($finalData['from_institute_name'])) value="{{$finalData['from_institute_name']}}" @endif>
                        	<input type="hidden" name="from_syear" @if(isset($finalData['from_syear'])) value="{{$finalData['from_syear']}}" @endif>
                            <input type="hidden" name="grade" @if(isset($finalData['grade'])) value="{{$finalData['grade']}}" @endif>
                            <input type="hidden" name="standard" @if(isset($finalData['standard'])) value="{{$finalData['standard']}}" @endif>                            
                            <input type="hidden" name="division" @if(isset($finalData['division'])) value="{{$finalData['division']}}" @endif>                            
                            <input type="hidden" name="to_sub_institute_id" @if(isset($finalData['to_sub_institute_id'])) value="{{$finalData['to_sub_institute_id']}}" @endif>                            
                            <input type="hidden" name="to_syear" @if(isset($finalData['to_syear'])) value="{{$finalData['to_syear']}}" @endif>                            
                            <input type="hidden" name="to_academic_section" @if(isset($finalData['to_academic_section'])) value="{{$finalData['to_academic_section']}}" @endif>                            
                            <input type="hidden" name="to_standard" @if(isset($finalData['to_standard'])) value="{{$finalData['to_standard']}}" @endif>                            
                            <input type="hidden" name="to_division" @if(isset($finalData['to_division'])) value="{{$finalData['to_division']}}" @endif>                            
                            <input type="submit" name="submit" value="Move to Other Institute" class="btn btn-success mt-3" >
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
    $('#grade').attr('required',true);
    $('#standard').attr('required',true);
    $('#division').attr('required',true);

    $('#submit_form').submit(function(){ 
        var selected_stud = $("input[name='students[]']:checked").length;
        if(selected_stud == 0)
        {
            alert("Please Select Atleast One Student");
            return false;
        }
        else{
            return true;
        }   
    });

    function getToAcademicSection(to_sub_institute_id)
    {        
        var to_sub_institute_id = $("#to_sub_institute_id").val();
        var path = "{{ route('ajax_toAcademicSections') }}";
        $('#to_academic_section').find('option').remove().end().append('<option value="">Select</option>').val('');
        $.ajax({url: path,data:'to_sub_institute_id='+to_sub_institute_id, success: function(result){
            for(var i=0;i < result.length;i++){
                $("#to_academic_section").append($("<option></option>").val(result[i]['id']).html(result[i]['title']));
            }
        }
        });
    }
    
    function getToStandard(to_academic_section)
    {        
        var to_academic_section = $("#to_academic_section").val();
        var path = "{{ route('ajax_toStandards') }}";
        $('#to_standard').find('option').remove().end().append('<option value="">Select</option>').val('');
        $.ajax({url: path,data:'to_academic_section='+to_academic_section, success: function(result){
            for(var i=0;i < result.length;i++){
                $("#to_standard").append($("<option></option>").val(result[i]['id']).html(result[i]['name']));
            }
        }
        });
    }

    function getToDivision(to_standard)
    {        
        var to_standard = $("#to_standard").val();
        var path = "{{ route('ajax_toDivisions') }}";
        $('#to_division').find('option').remove().end().append('<option value="">Select</option>').val('');
        $.ajax({url: path,data:'to_standard='+to_standard, success: function(result){
            for(var i=0;i < result.length;i++){
                $("#to_division").append($("<option></option>").val(result[i]['id']).html(result[i]['name']));
            }
        }
        });
    }

	function checkAll(ele) {
         // var checkboxes = $("input[name='checkall']");
         // alert(checkboxes);
	     var checkboxes_new = document.getElementsByTagName('input');
         // alert(checkboxes);
         
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

</script>
@include('includes.footer')
