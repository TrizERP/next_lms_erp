{{--@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')--}}
@extends('layout')
@section('container')
<link rel="stylesheet" href="../../../plugins/bower_components/dropify/dist/css/dropify.min.css">
<link href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.3/css/select2.min.css" rel="stylesheet"/>

<style>
.select2-dropdown.select2-dropdown--below {
  width: 460px !important;
}

.select2-selection.select2-selection--single 
{
  width: 87% !important;
  height: 50% !important;
  border-radius: 2px !important;
  border: 2px solid #d4d4d4 !important;
  transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out !important;
  line-height: 20px !important;
  padding: 5px !important;
  font-size: 13px !important;
  font-weight: 400 !important;
}
.email_error {
  width: 80%;
  height: 35px; 
  font-size: 1.1em;
  color: #D83D5A;
  font-weight: bold;
}
.email_success {
    width: 80%;
    height: 35px;    
    font-size: 1.1em;
    color: green;
    font-weight: bold;
}
.division_error {
  width: 80%;
  height: 35px; 
  font-size: 1.1em;
  color: red;
  font-weight: bold;
}
.division_success {
    width: 80%;
    height: 35px;    
    font-size: 1.1em;
    color: green;
    font-weight: bold;
}
</style>
<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Add New Student</h4> </div>
        </div>
        <div class="card">
            @if ($message = Session::get('success'))
            <div class="alert alert-success alert-block">
                <button type="button" class="close" data-dismiss="alert">×</button>
                <strong>{{ $message }}</strong>
            </div>
            @endif
            <!-- <section> -->
            <!-- <div class="sttabs tabs-style-linemove"> -->
                    <!--  <nav>
                        <ul>
                            <li><a href="#section-linemove-1" class="sticon  ti-layout-media-overlay"><span>Student Information</span></a></li>
                            <li><a href="#section-linemove-2" class="sticon  ti-new-window"><span>Past Education</span></a></li>
                            <li><a href="#section-linemove-3" class="sticon  ti-stack-overflow"><span>Family History</span></a></li>
                            <li><a href="#section-linemove-4" class="sticon ti-layout-tab"><span>Siblings Details</span></a></li>
                            <li><a href="#section-linemove-5" class="sticon ti-star"><span>Parent Feedback</span></a></li>
                                
                        </ul>
                    </nav> -->
                    @if(Session::get('user_profile_name') != 'Student')
                    <div class="content-wrap text-center">
                        <!-- <section id="section-linemove-1"> -->
                        <form action="{{ route('add_student.store') }}" enctype="multipart/form-data" class="row" method="post">
                        {{ method_field("POST") }}
                            @csrf
                                    <!--  <div class="col-md-4 form-group text-left">
                                <label>U ID </label>
                                <input type="text" id='U ID' required name="U ID" class="form-control">
                            </div>-->

                            <div class="col-md-4 form-group text-left">
                                <label>{{App\Helpers\get_string('studentname','request')}}<span style="color: red;">*</span></label>
                                <input type="text" id='first_name' required name="first_name" class="form-control">
                            </div>
                            <div class="col-md-4 form-group text-left">
                                <label>Middle Name</label>
                                <input type="text" id='middle_name' name="middle_name" class="form-control">
                            </div>
                            <div class="col-md-4 form-group text-left">
                                <label>Sur Name<span style="color: red;">*</span></label>
                                <input type="text" onchange="getUsername();" id='last_name' required name="last_name" class="form-control">
                            </div>
                            <div class="col-md-4 form-group text-left" style="display: none;">
                                <label>Username<span style="color: red;">*</span></label>
                                <input type="text" id='username' required name="username" class="form-control">
                            </div>
                            <div class="col-md-4 form-group text-left">
                                <label>{{ App\Helpers\get_string('grno','request')}}<span style="color: red;">*</span></label>
                                <input type="text" id='enrollment_no' required name="enrollment_no" class="form-control" value="@if(isset($data['new_enrollment_no'])){{$data['new_enrollment_no']}}@endif">
                            </div>
                            <div class="col-md-4 form-group text-left">
                                <label>Mother Name<span style="color: red;">*</span></label>
                                <input type="text" id='mother_name' name="mother_name" class="form-control" require>
                            </div>
                            @if(session()->get('sub_institute_id')!=257)
                            <div class="col-md-4 form-group text-left">
                                <label>{{ App\Helpers\get_string('fathername','request')}}</label>
                                <input type="text" id='father_name' name="father_name" class="form-control">
                            </div>
                            @endif
                            <div class="col-md-4 form-group text-left">
                                <label>SMS Number<span style="color: red;">*</span></label>
                                <input type="text" id='mobile' pattern="[1-9]{1}[0-9]{9}" required name="mobile" class="form-control">
                            </div>
                            <div class="col-md-4 form-group text-left">
                                <label>{{ App\Helpers\get_string('studentmobile','request')}}</label>
                                <input type="text" id='student_mobile' pattern="[1-9]{1}[0-9]{9}" name="student_mobile" class="form-control">
                            </div>
                            <div class="col-md-4 form-group text-left">
                                <label>Birthdate<span style="color: red;">*</span></label>
                                <input type="text" id='dob' required name="dob" class="form-control mydatepicker birthdate_picker" autocomplete="off">
                            </div>

                           <!--  <div class="col-md-4 form-group text-left" >
                               <label>Birthdate</label>
                                    <div class="input-daterange input-group" >
                                        <input type="date" required class="form-control" placeholder="yyyy-mm-dd" name="dob" id="dob" autocomplete="off">
                                        <span class="input-group-addon"><i class="icon-calender"></i></span>
                                    </div> 
                                        <label>Birthdate</label>
                                <div class="input-daterange input-group" id="date-range">
                                <input type="text" required class="form-control mydatepicker" placeholder="dd/mm/yyyy" name="birthdate" autocomplete="off"><span class="input-group-addon"><i class="icon-calender"></i></span> </div>
                            
                            </div> -->
                            <div class="col-md-4 form-group text-left">
                                <label>Mother Mobile<span style="color: red;">*</span></label>
                                <input type="text" id='mother_mobile'  pattern="[1-9]{1}[0-9]{9}" required name="mother_mobile" class="form-control">
                            </div>                            
                            <div class="col-md-4 form-group text-left">
                                <label>Email / Username<span style="color: red;">*</span></label>                                
                                <input type="email" id='email' required name="email" class="form-control">
                                <span id="email_error_span"></span>
                            </div>
                            <div class="col-md-4 form-group text-left">
                                <label>Fees Year</label>
                                <select id='admission_year' name="admission_year" class="form-control">
                                @if(isset($data['admission_year']))
                                    @foreach($data['admission_year'] as $key => $value)
                                        @php
                                            $year = is_array($value) ? $value['year'] : $value->year;
                                        @endphp
                                        <option value="{{ $year }}">{{ $year }}</option>
                                    @endforeach
                                @endif
                                </select>
                            </div>
                            <div class="col-md-4 form-group text-left" >
                                <label>Admission Date<span style="color: red;">*</span></label>
                                <div class="input-daterange input-group" id="date-range">
                                <input type="text" required class="form-control mydatepicker" placeholder="dd/mm/yyyy" name="admission_date" autocomplete="off" value="{{date('Y-m-d'); }}">
                                <span class="input-group-addon"><i class="icon-calender"></i></span> 
                            </div>
                            </div>
                            <div class="col-md-4 form-group text-left">
                                <label>Address</label>
                                <input type="text" id='address' name="address" class="form-control">
                            </div>
                            <div class="col-md-4 form-group text-left">                   
                                <label>State</label>
                                <select class="form-control" name="state" id="state" onchange="getStatewiseCity(this.value);">
                                @if(empty($data['city_data']) && session()->get('sub_institute_id')==257)
                                    <option value="Gujarat">Gujarat</option>
                                    @endif

                                @if(!empty($data['state_data']))  
                                <option value="">Select State</option> 
                                @foreach($data['state_data'] as $key => $value)
                                    <option value="{{ $value['state_name'] }}" @if(isset($data->state)) {{ $data->state == $value['state_name'] ? 'selected' : '' }} @endif> {{ $value['state_name'] }} </option>
                                @endforeach
                                @endif
                                </select>
                            </div>
                            <div class="col-md-4 form-group">                   
                                <label>City</label>
                                @if(session()->get('sub_institute_id')==195)
                                    <input list="city" name="city" id="exampleDataList" class="form-control"/>
                                    <datalist id="city" height="100" style="height:100px">
                                    @if(!empty($data['city_data']))  
                                    @foreach($data['city_data'] as $k1 => $v1)
                                        <option value="{{ $v1['city_name'] }}" @if(isset($student_data->city)) {{ $student_data->city == $v1['city_name'] ? 'selected' : '' }} @endif> {{ $v1['city_name'] }} </option>
                                    @endforeach
                                    @endif
                                    </datalist>
                                @else
                                    <select class="form-control" name="city" id="city">
                                    @if(empty($data['city_data']) && session()->get('sub_institute_id')==257)
                                        <option value="Ahmedabad">Ahmedabad</option>
                                        @endif
                                    @if(!empty($data['city_data'])) 
                                    <option value="">Select City</option> 
                                    @foreach($data['city_data'] as $k1 => $v1)
                                        <option value="{{ $v1['city_name'] }}" @if(isset($data->city)) {{ $data->city == $v1['city_name'] ? 'selected' : '' }} @endif> {{ $v1['city_name'] }} </option>
                                    @endforeach
                                    @endif
                                @endif
                                </select>
                            </div>
                            <!--<div class="col-md-4 form-group text-left">
                                <label>City</label>
                                <input type="text" id='city' name="city" class="form-control">
                            </div>
                             <div class="col-md-4 form-group text-left">
                                <label>State</label>
                                <input type="text" id='state' name="state" class="form-control">
                            </div> -->                            
                            <div class="col-md-4 form-group text-left">
                                <label>Pincode</label>
                                <input type="text" id='pincode' name="pincode" class="form-control">
                            </div>
                            
                            {{ App\Helpers\SearchChain('4','single','grade,std,div') }}
                            <div class="col-md-4 form-group">
                                <span></span>
                            </div>
                            <div class="col-md-4 form-group">
                                <span></span>
                            </div> 
                            <div class="col-md-4 form-group">
                                <span id="division_error_span"></span>
                            </div>  
                               
                            <div class="col-md-4 form-group text-left">
                                <label>{{App\Helpers\get_string('studentquota','request')}}<span style="color: red;">*</span></label>
                                <select id='student_quota' required name="student_quota" class="form-control">
                                    <option value="">--Select--</option>
                                    @if(isset($data['student_quota']))
                                        @foreach($data['student_quota'] as $key => $value)
                                            <option value="{{ $value['id'] }}">{{ $value['title'] }}</option>
                                        @endforeach
                                    @endif
                                </select>
                            </div> 

                            <div class="col-md-4 form-group">
                                <label>{{ App\Helpers\get_string('house','request')}} @if(session()->get('sub_institute_id')==257) <span style="color: red;">*</span>@endif</label>
                                <select id='house' name="house" class="form-control" @if(session()->get('sub_institute_id')==257) required @endif>
                                    <option value="">--Select--</option>  
                                    @if(isset($data['house_data']))
                                        @foreach($data['house_data'] as $key => $value)
                                            <option value="{{ $value['id'] }}">{{ $value['house_name'] }}</option>
                                        @endforeach
                                    @endif                                                  
                                </select>
                            </div>

                            <div class="col-md-4 form-group text-left">
                                <label class="control-label">Gender<span style="color: red;">*</span></label>
                                <div class="radio-list">
                                    <label class="radio-inline p-0">
                                        <div class="radio radio-success">
                                            <input type="radio" name="gender" id="male" value="M" required>
                                            <label for="male">Male</label>
                                        </div>
                                    </label>
                                    <label class="radio-inline">
                                        <div class="radio radio-success">
                                            <input type="radio" name="gender" id="female" value="F" required>
                                            <label for="female">Female</label>
                                        </div>
                                    </label>
                                    <label class="radio-inline p-0">
                                        <div class="radio radio-success">
                                            <input type="radio" name="gender" id="other" value="O" required>
                                            <label for="other">Other</label>
                                        </div>
                                    </label>
                                </div>
                            </div>                                        
                            <div class="col-md-4">        
                                <label for="input-file-now">User Image</label>
                                <input type="file" accept="image/png, image/jpg, image/jpeg" name="student_image" id="input-file-now" class="dropify" /> 
                            </div>
                            @if(session()->get('sub_institute_id')!=257)
                            <div class="col-md-4 form-group text-left">
                                <label>Optional Subject</label>
                                <select id='optional_subject' name="optional_subject[]" multiple class="form-control">
                                    <option value="">--Select--</option>                                                    
                                </select>
                            </div>
                            @endif
                            <div class="col-md-4 form-group text-left">
                                <label>Student Batch</label>
                                <select id='studentbatch' name="studentbatch" class="form-control">
                                    <option value="">--Select--</option>                                                    
                                </select>
                            </div>
                            @if(session()->get('sub_institute_id')!=257)
                            <div class="col-md-4 form-group text-left">
                                <label>Student Religion</label>
                                <select id='religion' name="religion" class="form-control">
                                    <option value="">--Select--</option>  
                                    @if(isset($data['religion_data']))
                                        @foreach($data['religion_data'] as $key => $value)
                                            <option value="{{ $value['id'] }}">{{ $value['religion_name'] }}</option>
                                        @endforeach
                                    @endif                                                  
                                </select>
                            </div>
                            
                            <div class="col-md-4 form-group text-left">
                                <label>Student Caste</label>
                                <select id='cast' name="cast" class="form-control">
                                    <option value="">--Select--</option>  
                                    @if(isset($data['caste_data']))
                                        @foreach($data['caste_data'] as $key => $value)
                                            <option value="{{ $value['id'] }}">{{ $value['caste_name'] }}</option>
                                        @endforeach
                                    @endif                                                  
                                </select>
                            </div>

                            <div class="col-md-4 form-group text-left">
                                <label>Sub Caste</label>
                                <input type="text" id='subcast' name="subcast" class="form-control">
                            </div>
                            @endif
                            <div class="col-md-4 form-group text-left">
                                <label>Roll No.</label>
                                <input type="text" id='roll_no' name="roll_no" class="form-control">
                            </div>                                          
                            
                            <div class="col-md-4 form-group text-left">
                                <label>Student Blood Group</label>
                                <select id='bloodgroup' name="bloodgroup" class="form-control">
                                    <option value="">--Select--</option>
                                    @if(isset($data['bloodgroup_data']))
                                        @foreach($data['bloodgroup_data'] as $key => $value)
                                            <option value="{{ $value['id'] }}">{{ $value['bloodgroup'] }}</option>
                                        @endforeach
                                    @endif                                                  
                                </select>
                            </div>
                            @if(session()->get('sub_institute_id')!=257)
                            <div class="col-md-4 form-group text-left">
                                <label>Aadhar Number</label>
                                <input type="text" id='adharnumber' name="adharnumber" class="form-control" onblur="AadharValidate();">
                            </div>
                            
                            <div class="col-md-4 form-group text-left">
                                <label>{{ App\Helpers\get_string('annualincome','request')}}</label>
                                <input type="number" id='anuualincome' name="anuualincome" class="form-control">
                            </div>
                            @endif
                             {{--  For Euro School --}}
                        @if (Session::get('sub_institute_id') != '195')
                        
                            <div class="col-md-4 form-group text-left">
                                <label>{{ App\Helpers\get_string('uniqueid','request')}}</label>
                                <input type="text" id='uniqueid' name="uniqueid" class="form-control">
                            </div>
                         @endif
                         <div class="col-md-4 form-group">
                            <label>{{ App\Helpers\get_string('nationality','request')}}<i class="mdi mdi-lead-pencil"></i></label>
                            <input type="text" id='nationality' name="nationality" class="form-control">
                        </div>    
                            @if(isset($data['custom_fields']))
                            @foreach($data['custom_fields'] as $key => $value)
                            <div class="col-md-4 form-group text-left">
                                <label>{{ $value['field_label'] }}</label>
                                @if($value['field_type'] == 'file')
                                <input type="{{ $value['field_type'] }}" accept="image/*" id="input-file-now" @if($value['required'] == 1) required @endif name="{{ $value['field_name'] }}" class="dropify">
                                @elseif($value['field_type'] == 'date')
                                <div class="input-daterange input-group" >
                                <input type="date" class="form-control" placeholder="dd/mm/yyyy" autocomplete="off" id="{{ $value['field_name'] }}" @if($value['required'] == 1) required @endif name="{{ $value['field_name'] }}" class="form-control"><span class="input-group-addon"><i class="icon-calender"></i></span>
                                </div>
                                @elseif($value['field_type'] == 'checkbox')
                                <div class="checkbox-list">
                                    @if(isset($data['data_fields'][$value['id']]))
                                    @foreach($data['data_fields'][$value['id']] as $keyData => $valueData )
                                        <label class="checkbox-inline">
                                            <div class="checkbox checkbox-success">
                                                <input type="checkbox" name="{{ $value['field_name'] }}[]" value="{{ $valueData['display_value'] }}" id="{{ $valueData['display_value'] }}" @if($value['required'] == 1) required @endif>
                                                <label for="{{ $valueData['display_value'] }}">{{ $valueData['display_text'] }}</label>
                                            </div>
                                        </label>
                                        @endforeach
                                    @endif
                                </div>
                                @elseif($value['field_type'] == 'dropdown')
                                        <select name="{{ $value['field_name'] }}" class="form-control" @if($value['required'] == 1) required @endif id="{{ $value['field_name'] }}">
                                            <option value=""> SELECT {{ strtoupper($value['field_label']) }} </option>
                                        @if(isset($data['data_fields'][$value['id']]))
                                            @foreach($data['data_fields'][$value['id']] as $keyData => $valueData)
                                            <option value="{{ $valueData['display_value'] }}"> {{ $valueData['display_text'] }} </option>
                                            @endforeach
                                        @endif
                                        </select>
                                @elseif($value['field_type'] == 'textarea')
                                <textarea id="{{ $value['field_name'] }}" class="form-control" @if($value['required'] == 1) required @endif name="{{ $value['field_name'] }}" placeholder="{{ $value['field_message'] }}">
                                </textarea>
                                @else
                                <input type="{{ $value['field_type'] }}" id="{{ $value['field_name'] }}" placeholder="{{ $value['field_message'] }}" @if($value['required'] == 1) required @endif name="{{ $value['field_name'] }}" class="form-control">
                                @endif
                            </div>
                            @endforeach
                            @endif
                            <div class="col-md-12 form-group">
                                <input type="submit" name="submit" id="Submit" value="Save" class="btn btn-success" >
                            </div>
                            </form>
                        <!-- </section> -->
                        <!-- <section id="section-linemove-2">
                            <form action="{{ route('past_education.store') }}" enctype="multipart/form-data" method="post">
            {{ method_field("POST") }}
                @csrf       <div id="past_og">
                                <div class="col-md-2 form-group">
                                    <label>Course </label>
                                    <input type="text" id='course'  name="courses[]" class="form-control">
                                </div>
                                <div class="col-md-1 form-group">
                                    <label>Medium </label>
                                    <input type="text" id='medium'  name="mediums[]" class="form-control">
                                </div>
                                <div class="col-md-2 form-group">
                                    <label>Name of board </label>
                                    <input type="text" id='name_of_board'  name="name_of_boards[]" class="form-control">
                                </div>
                                <div class="col-md-1 form-group">
                                    <label>Year</label>
                                    <input type="text" id='year_of_passing'  name="year_of_passings[]" class="form-control">
                                </div>
                                <div class="col-md-1 form-group">
                                    <label>Percentage </label>
                                    <input type="text" id='percentage'  name="percentages[]" class="form-control">
                                </div>
                                <div class="col-md-2 form-group">
                                    <label>School Name </label>
                                    <input type="text" id='school_name'  name="school_names[]" class="form-control">
                                </div>
                                <div class="col-md-1 form-group">
                                    <label>Place </label>
                                    <input type="text" id='place'  name="places[]" class="form-control">
                                </div>
                                <div class="col-md-1 form-group">
                                    <label>Trial </label>
                                    <input type="text" id='trial'  name="trials[]" class="form-control">
                                </div>
                            </div>
                                <div class="col-md-1 form-group">
                                    <label>Add </label>
                                    <a href="javascript:void(0);" onclick="addNewRow();"><span class="circle circle-sm bg-success di form-control"><i class="ti-plus"></i></span></a>
                                </div>
                            <div id="past_add">
                            </div>
                                <div class="col-md-12 form-group">
                                    <input type="submit" name="submit" value="Save" class="btn btn-success" >
                                </div>
                            
                            
                            </form>
                        </section> -->                       
                    </div>
                @endif    
                    <!-- /content -->
                <!-- </div> -->
                <!-- /tabs -->
            <!-- </section> -->
        </div>
    </div>
</div>


<!-- check student Model  -->
<div class="modal fade" id="studentModal" tabindex="-1" role="dialog" aria-labelledby="studentModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="studentModalLabel">Student Details</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <table class="table table-bordered">
                    <tbody id="studentData"></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

@include('includes.footerJs')
<script src="../../../admin_dep/js/cbpFWTabs.js"></script>
<script type="text/javascript">
    (function() {
        [].slice.call(document.querySelectorAll('.sttabs')).forEach(function(el) {
            new CBPFWTabs(el);
        });
    })();
    
    $(document).ready(function() {      
        $('#dob').datepicker('setEndDate', new Date());     
    });
    
</script>
<script>
    function getUsername(){
        
        var first_name = document.getElementById("first_name").value;
        var last_name = document.getElementById("last_name").value;
        var username = first_name.toLowerCase()+"_"+last_name.toLowerCase();
        document.getElementById("username").value = username;
    }
    function addNewRow(){
        var divHtml = document.getElementById("past_og").innerHTML;
        $('#past_add:last').after(divHtml);
    }

    function removeNewRow() {     
        // $(".addButtonDrop:last" ).remove();    
    }
</script>
<script src="../../../plugins/bower_components/dropify/dist/js/dropify.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.3/js/select2.min.js"></script>
    <script>
    $(document).ready(function() {
        // Basic
        $('.dropify').dropify();
        // Translated
        $('.dropify-fr').dropify({
            messages: {
                default: 'Glissez-déposez un fichier ici ou cliquez',
                replace: 'Glissez-déposez un fichier ou cliquez pour remplacer',
                remove: 'Supprimer',
                error: 'Désolé, le fichier trop volumineux'
            }
        });
        // Used events
        var drEvent = $('#input-file-events').dropify();
        drEvent.on('dropify.beforeClear', function(event, element) {
            return confirm("Do you really want to delete \"" + element.file.name + "\" ?");
        });
        drEvent.on('dropify.afterClear', function(event, element) {
            alert('File deleted');
        });
        drEvent.on('dropify.errors', function(event, element) {
            console.log('Has Errors');
        });
        var drDestroy = $('#input-file-to-destroy').dropify();
        drDestroy = drDestroy.data('dropify')
        $('#toggleDropify').on('click', function(e) {
            e.preventDefault();
            if (drDestroy.isDropified()) {
                drDestroy.destroy();
            } else {
                drDestroy.init();
            }
        })
    });
    
    //START Bind Batch
    $("#division").change(function(){
        var div_id = $("#division").val();         
        var std_id = $("#standard").val();              
        var path = "{{ route('ajax_getBatch') }}";
        $('#studentbatch').find('option').remove().end();
        $.ajax({
            url:path,
            data:'div_id='+div_id+'&std_id='+std_id,
            success:function(result){               
                for(var i=0;i < result.length ;i++)
                {
                    $("#studentbatch").append($("<option></option>").val(result[i]['id']).html(result[i]['title']));
                }
            },error: function(xhr, status, error) {
                 alert(JSON.parse(xhr.responseText).message);
            }
        });
    })
    // check student lists
    $("#mobile").change(function(){
      checkStudentExists();
    })
    $('#first_name').change(function(){
      checkStudentExists();
    })
    $('#last_name').change(function(){
      checkStudentExists();
    })
    //END Bind Batch
    
    //START Bind Optional Subject
    $("#standard").change(function(){       
        var std_id = $("#standard").val();              
        var path = "{{ route('ajax_getOptionalSubject') }}";
        $('#optional_subject').find('option').remove().end();
        $.ajax({
            url:path,
            data:'std_id='+std_id,
            success:function(result){               
                for(var i=0;i < result.length ;i++)
                {
                    $("#optional_subject").append($("<option></option>").val(result[i]['subject_id']).html(result[i]['subject_name']));
                }
            }
        });
    })
    //END Bind Optional Subject

    $("#division").attr('required',true);

    $('document').ready(function(){
        //START Unique Email Validation        
        var email_state = false;        
        $("#email").on( "blur", function( event ) {
            email_val = this.value;
            var path = "{{ route('ajax_checkEmailExist') }}";
            $.ajax({
                url:path,
                data:'email='+email_val,
                success:function(result){
                    if(result == 1)
                    {                                                
                        $("#email_error_span").removeClass().addClass("email_error").text('Email already taken');                        
                        email_state = true;
                    }
                    else
                    {
                        $("#email_error_span").removeClass().addClass("email_success").text('Email available');                        
                        email_state = false;
                    }
                }
            });
        });
        //END Unique Email Validation

        //START Check Division Capacity Validation - 18/11/2021
        var division_check = false;
        document.getElementById('division').addEventListener('change', function(){
            var selected_division_id = $("#division").val();
            var selected_std_id = $("#standard").val();
            
            var path = "{{ route('ajax_checkDivisionCapacity') }}";
            $.ajax({
                url:path,
                data:'std_id='+selected_std_id+'&division_id='+selected_division_id,
                success:function(result){
                    var capacity = result.split("/");
                    if(capacity[1] != 0)
                    {                                                
                        
                        $("#division_error_span").removeClass().addClass("division_success").text('Total Capacity : '+capacity[0]+' / Remaining Capacity : '+capacity[1]);
                        division_check = true;
                    }
                    else if(capacity[1] == '')
                    {
                        division_check = true;
                    }
                    else
                    {
                        $("#division_error_span").removeClass().addClass("division_error").text('Total Capacity : '+capacity[0]+' / Remaining Capacity : '+capacity[1]);
                        division_check = false;
                    }
                }
            });
            
        });
        //END Check Division Capacity Validation - 18/11/2021

        $('#Submit').on('click', function(){

            if(email_state == true)
            { 
                alert('Fix the errors in the form first');
                return false;
            }
            if(division_check == false)
            { 
                alert('Please select other division.');
                return false;
            }

        });

        jQuery('.mydatepicker, .birthdate_picker').datepicker({
          autoclose: true,
          startDate: '01-01-1970',
          endDate: '+0d',
          format: 'dd-mm-yyyy',
          orientation: 'bottom'
        });

    });

    function getStatewiseCity(state_name){    
        var path = "{{ route('ajax_StatewiseCity') }}";
        //alert('dk');
        $('#city').find('option').remove().end().append('<option value="">Select City</option>').val('');
        $.ajax({url: path,data:'state_name='+state_name, success: function(result){  
            for(var i=0;i < result.length;i++){  
                $("#city").append($("<option></option>").val(result[i]['city_name']).html(result[i]['city_name']));  
            } 
        }
        });
    }
</script>
<script type="text/javascript">
     jQuery('#dob').datepicker({
        changeMonth: true,
        changeYear: true,
        maxDate: 0,
        yearRange: "-40:+10",
        inline: true,
        autoclose: true,
        format: 'dd-mm-yyyy',
        orientation: 'bottom',
        forceParse: false
      });
    function AadharValidate() {
      var aadhar = document.getElementById("adharnumber").value;
      var adharcardTwelveDigit = /^\d{12}$/;
      var adharSixteenDigit = /^\d{16}$/;
      if (aadhar != '') 
      {
        if (aadhar.match(adharcardTwelveDigit))
        {
          return true;
        }else{
          alert("Enter valid Aadhar Number");
          document.getElementById("adharnumber").value = "";
          return false;
        }
      }
    }

    $("#city").select2({
      tags: true,
      createTag: function (params) {
        return {
          id: params.term,
          text: params.term,
          newOption: true
        }
      },
       templateResult: function (data) {
        var $result = $("<span></span>");

        $result.text(data.text);

        if (data.newOption) {
          $result.append(" <em>(new)</em>");
        }

        return $result;
      }
    });
 function checkStudentExists(){
        var first_name = $('#first_name').val();
        var last_name = $('#last_name').val();
        var mobile = $('#mobile').val();
        // alert(first_name);
       $.ajax({
        url : "{{route('checkExists')}}",
        data : {first_name:first_name,last_name:last_name,mobile:mobile},
        type:'GET',
        success: function (response) {
            console.log(response);

            if (response && Object.keys(response).length !== 0) {
                $('#studentData').empty();

                var grno = "{{ App\Helpers\get_string('grno','request')}}";
                var house = "{{ App\Helpers\get_string('house','request')}}";
                var grade = "{{ App\Helpers\get_string('searchsection','request')}}";
                var std = "{{ App\Helpers\get_string('searchstandard','request')}}";
                var div = "{{ App\Helpers\get_string('searchdivision','request')}}";
                var uniqueid = "{{ App\Helpers\get_string('uniqueid','request')}}";
                var nationality = "{{ App\Helpers\get_string('nationality','request')}}";

                var dobParts = response.dob.split('-');
                var formattedDate = dobParts[2] + '-' + dobParts[1] + '-' + dobParts[0];

                $('#studentData').append(`
                    <tr>
                        <td><b>Full Name : </b>${response.student_name}</td>
                        <td><b>SMS Number : </b>${response.mobile}</td>
                        <td><b>Birthdate : </b>${formattedDate}</td>
                    </tr>
                    <tr>
                        <td><b>${grno} : </b>${response.enrollment_no}</td>
                        <td><b>${house} : </b>${response.house}</td>
                        <td><b>Batch : </b>${response.batch}</td>
                    </tr>
                    <tr>
                        <td><b>${grade} : </b>${response.grade}</td>
                        <td><b>${std} : </b>${response.standard}</td>
                        <td><b>${div} : </b>${response.division}</td>
                    </tr>
                    <tr>
                        <td><b>${uniqueid} : </b>${response.uniqueid}</td>
                        <td><b>${nationality} : </b>${response.nationality}</td>
                        <td><b>Status : </b>${response.status}</td>
                    </tr>`);

                $('#studentModal').modal('show');
                console.log(response.student_name); // Assuming 'student_name' is a field in your 'students' table
            } else {
                console.error("Student not found");
            }
        },
        error: function(xhr, status, error) {
            console.error(xhr.responseText);
         }
       })
 }
</script>
@include('includes.footer')
@endsection