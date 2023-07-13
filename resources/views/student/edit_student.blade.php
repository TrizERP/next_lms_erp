@include('includes.headcss')
<link rel="stylesheet" href="../../../plugins/bower_components/dropify/dist/css/dropify.min.css">
<link href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.3/css/select2.min.css" rel="stylesheet"/>
@include('includes.header')
@include('includes.sideNavigation')
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
#overlay {
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    right: 0;
    background: #000;
    opacity: 0.8;
    filter: alpha(opacity=80);
    display: none;
}
#loading {
    width: 50px;
    height: 57px;
    position: absolute;
    top: 50%;
    left: 50%;
    margin: -28px 0 0 -25px;
}
br {
     display: block;
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
                <h4 class="page-title">Edit Student Details</h4> 
            </div>
        </div>

        <div class="card">
                @if (session()->has('Warning'))
                <div class="alert alert-danger alert-block">
                    <button type="button" class="close" data-dismiss="alert">x</button>
                    <strong>{{ session()->get('Warning') }}</strong>
                </div>
                @endif
                @if ($message = Session::get('success'))
                <div class="alert alert-success alert-block">
                    <button type="button" class="close" data-dismiss="alert">�</button>
                    <strong>{{ $message }}</strong>
                </div>
                @endif
            <div class="row">
                <div class="col-lg-12 col-sm-12 col-xs-12">
                    <div class="sttabs tabs-style-linemove triz-verTab bg-white style2">
                        <ul class="nav nav-tabs tab-title mb-4">
                            <li class="nav-item"><a href="#section-linemove-1" class="nav-link active" aria-selected="true" data-toggle="tab"><span>Student Information</span></a></li>
                            <li class="nav-item"><a href="#section-linemove-2" class="nav-link" aria-selected="false" data-toggle="tab"><span>Past Education</span></a></li>
                            <li class="nav-item"><a href="#section-linemove-3" class="nav-link" aria-selected="false" data-toggle="tab"><span>Family History</span></a></li>
                            <li class="nav-item"><a href="#section-linemove-4" class="nav-link" aria-selected="false" data-toggle="tab"><span>Siblings Details</span></a></li>
                            <li class="nav-item"><a href="#section-linemove-5" class="nav-link" aria-selected="false" data-toggle="tab"><span>Parent Feedback</span></a></li>
                            <li class="nav-item"><a href="#section-linemove-6" class="nav-link" aria-selected="false" data-toggle="tab"><span>Infirmary</span></a></li>
                            <li class="nav-item"><a href="#section-linemove-7" class="nav-link" aria-selected="false" data-toggle="tab"><span>Vaccination</span></a></li>
                            <li class="nav-item"><a href="#section-linemove-8" class="nav-link" aria-selected="false" data-toggle="tab"><span>Height & Weight</span></a></li>
                            <li class="nav-item"><a href="#section-linemove-9" class="nav-link" aria-selected="false" data-toggle="tab"><span>Health</span></a></li>
                            <li class="nav-item"><a href="#section-linemove-10" data-id="document" class="nav-link" aria-selected="false" data-toggle="tab"><span>Documents</span></a></li>
                            <li class="nav-item"><a href="#section-linemove-11" class="nav-link" aria-selected="false" data-toggle="tab"><span>Fees Details</span></a></li>
                            <li class="nav-item"><a href="#section-linemove-12" class="nav-link" aria-selected="false" data-toggle="tab"><span>TC Information</span></a></li>
                            <li class="nav-item"><a href="#section-linemove-13" class="nav-link" aria-selected="false" data-toggle="tab"><span>Attendance</span></a></li>
                            <li class="nav-item"><a href="#section-linemove-14" class="nav-link" aria-selected="false" data-toggle="tab"><span>Parent Communication</span></a></li>
                            <li class="nav-item"><a href="#section-linemove-15" class="nav-link" aria-selected="false" data-toggle="tab"><span>Leave Application</span></a></li>
                        </ul>
                            
                            @if(isset($data['data']))
                                @php
                                    if(isset($data['data'])){
                                        $student_data = $data['data'];
                                    }
                                @endphp
                            @endif
                            
                            <div class="tab-content">
                                
                                <!-- START STUDENT INFORMATION -->
                                <div class="tab-pane p-3 active" id="section-linemove-1" role="tabpanel">                                
                                    <form action="{{ route('add_student.update', $student_data->id) }}" enctype="multipart/form-data" method="post">
                                        <div class="row equal">
                                        {{ method_field("PUT") }}
                                            @csrf
                                        <div class="col-md-4 form-group">
                                            <label>Student Name </label>
                                            <input type="text" id='first_name' required name="first_name" value="{{ $student_data->first_name }}" class="form-control">
                                        </div>
                                        <div class="col-md-4 form-group">
                                            <label>Middle Name</label>
                                            <input type="text" id='middle_name' name="middle_name" value="{{ $student_data->middle_name }}" class="form-control">
                                        </div>
                                        <div class="col-md-4 form-group">
                                            <label>Sur Name </label>
                                            <input type="text" onchange="getUsername();" id='last_name' required name="last_name" value="{{ $student_data->last_name }}" class="form-control">
                                        </div>
                                        <div class="col-md-4 form-group">
                                            <label>Username</label>
                                            <input type="text" id='username' required name="username" value="{{ $student_data->username }}"  class="form-control">
                                        </div>
                                        <div class="col-md-4 form-group">
                                            <label>{{ App\Helpers\get_string('grno','request')}}<i class="mdi mdi-lead-pencil"></i></label>
                                            <input type="text" id='enrollment_no' required value="{{ $student_data->enrollment_no }}" name="enrollment_no" class="form-control">
                                        </div>
                                        <div class="col-md-4 form-group">
                                            <label>Mother Name</label>
                                            <input type="text" value="{{ $student_data->mother_name ? $student_data->mother_name : '-' }}" id='mother_name' name="mother_name" class="form-control" require>
                                        </div>
                                        <div class="col-md-4 form-group">
                                            <label>{{ App\Helpers\get_string('fathername','request')}}<i class="mdi mdi-lead-pencil"></i></label>
                                            <input type="text" id='father_name' name="father_name" value="{{ $student_data->father_name }}" class="form-control">
                                        </div>
                                        <div class="col-md-4 form-group">
                                            <label>Mobile</label>
                                            <input type="text" id='mobile' required  pattern="[1-9]{1}[0-9]{9}" name="mobile" value="{{ $student_data->mobile }}" class="form-control">
                                        </div>
                                        <div class="col-md-4 form-group">
                                            <label>{{ App\Helpers\get_string('studentmobile','request')}}<i class="mdi mdi-lead-pencil"></i></label>
                                            <input type="text" id='student_mobile' pattern="[1-9]{1}[0-9]{9}" name="student_mobile" value="{{ $student_data->student_mobile }}" class="form-control">
                                        </div>
                                        <div class="col-md-4 form-group" >
                                            <label>Birthdate</label>
                                                <div class="input-daterange input-group" >
                                                    <input type="text" required class="form-control mydatepicker" placeholder="yyyy-mm-dd" value="{{ $student_data->dob }}" name="dob" id="dob" autocomplete="off">
                                                    <span class="input-group-addon"><i class="icon-calender"></i></span>
                                                </div>
                                        </div>
                                        <div class="col-md-4 form-group">
                                            <label>Mother Mobile</label>
                                            <input type="text" id='mother_mobile' pattern="[1-9]{1}[0-9]{9}" name="mother_mobile" value="{{ $student_data->mother_mobile }}" class="form-control">
                                        </div>                                       
                                        <div class="col-md-4 form-group">
                                            <label>Email</label>
                                            <!--<span><br><b>{{ $student_data->email }}</b></span>-->
                                            <input type="email" value="{{ $student_data->email }}" id='email' required name="email" class="form-control">
                                        </div>
                                        <div class="col-md-4 form-group">
                                            <label>Admission Year</label>
                                            <select id='admission_year' name="admission_year" class="form-control" >
                                                <option value="">--Select--</option>  
                                                @if(isset($data['admission_year']))
                                                    @foreach($data['admission_year'] as $key => $value)
                                                        <option @if($student_data->admission_year == $value->year) selected="selected" @endif value="{{ $value->year }}">{{ $value->year }}</option>
                                                    @endforeach
                                                @endif   
                                            </select>                                           
                                        </div>
                                        <div class="col-md-4 form-group" >
                                            <label>Admission Date</label>
                                            <div class="input-daterange input-group" >
                                                <input type="text" value="{{ $student_data->admission_date }}" required class="form-control mydatepicker" placeholder="yyyy-mm-dd" name="admission_date" autocomplete="off">
                                                <span class="input-group-addon"><i class="icon-calender"></i></span>
                                            </div>
                                        </div>

                                        <div class="col-md-4 form-group text-left">                   
                                            <label>State</label>
                                            <select class="form-control" name="state" id="state" onchange="getStatewiseCity(this.value);">
                                                <option value="">Select State</option>
                                            @if(!empty($data['state_data']))  
                                            @foreach($data['state_data'] as $key => $value)
                                                <option value="{{ $value['state_name'] }}" @if(isset($student_data->state)) {{ $student_data->state == $value['state_name'] ? 'selected' : '' }} @endif> {{ $value['state_name'] }} </option>
                                            @endforeach
                                            @endif
                                            </select>
                                        </div>
                                        <div class="col-md-4 form-group">                   
                                            <label>City</label>
                                            <select class="form-control" name="city" id="city">
                                               @if(empty($data['city_data']))
                                                <option value="">Select City</option>
                                                @endif
                                            @if(!empty($data['city_data']))  
                                            @foreach($data['city_data'] as $k1 => $v1)
                                                <option value="{{ $v1['city_name'] }}" @if(isset($student_data->city)) {{ $student_data->city == $v1['city_name'] ? 'selected' : '' }} @endif> {{ $v1['city_name'] }} </option>
                                            @endforeach
                                            @endif
                                            </select>
                                        </div>

                                        <!-- <div class="col-md-4 form-group">
                                            <label>City</label>
                                            <input type="text" value="{{ $student_data->city }}" id='city' name="city" class="form-control">
                                        </div>
                                        <div class="col-md-4 form-group">
                                            <label>State</label>
                                            <input type="text" value="{{ $student_data->state }}" id='state' name="state" class="form-control">
                                        </div> -->
                                        <div class="col-md-4 form-group">
                                            <label>Address</label>
                                            <input type="text" value="{{ $student_data->address }}" id='address' name="address" class="form-control">
                                        </div>
                                        <div class="col-md-4 form-group">
                                            <label>Pincode</label>
                                            <input type="text" value="{{ $student_data->pincode }}" id='pincode' name="pincode" class="form-control">
                                        </div>

                                        {{ App\Helpers\SearchChain('4','single','grade,std,div',$student_data->grade_id,$student_data->standard_id,$student_data->section_id) }}
                                        <div class="col-md-4 form-group">
                                            <span></span>
                                        </div>
                                        <div class="col-md-4 form-group">
                                            <span></span>
                                        </div> 
                                        <div class="col-md-4 form-group">
                                            <span id="division_error_span"></span>
                                        </div> 
                                        
                                        <div class="col-md-4 form-group">
                                            <label>{{App\Helpers\get_string('studentquota','request')}}<i class="mdi mdi-lead-pencil"></i></label>
                                            <select id='student_quota' required name="student_quota" class="form-control" >
                                                <option value="">--Select--</option>
                                                @if(isset($data['student_quota']))

                                                    @foreach($data['student_quota'] as $key => $value)
                                                            @php
                                                                $selected = '';
                                                            @endphp
                                                        @if($student_data['student_quota']== $value['id'])
                                                            @php
                                                                $selected = 'selected';
                                                            @endphp
                                                        @endif
                                                        <option {{ $selected }} value="{{ $value['id'] }}">{{ $value['title'] }}</option>
                                                    @endforeach
                                                @endif
                                            </select>
                                        </div>    

                                        <div class="col-md-4 form-group">
                                            <label>{{ App\Helpers\get_string('house','request')}}<i class="mdi mdi-lead-pencil"></i></label>
                                            <select id='house' name="house" class="form-control">
                                                <option value="">--Select--</option>  
                                                @if(isset($data['house_data']))
                                                    @foreach($data['house_data'] as $key => $value)
                                                        <option @if($student_data->house_id == $value['id'] ) selected="selected" @endif value="{{ $value['id'] }}">{{ $value['house_name'] }}</option>
                                                    @endforeach
                                                @endif                                                  
                                            </select>
                                        </div>

                                        <div class="col-md-4 form-group">
                                            <label class="control-label">Gender</label>
                                            <div class="radio-list">
                                                <label class="radio-inline p-0">
                                                    <div class="radio radio-success">
                                                        <input type="radio" @if("M" == $student_data->gender) checked @endif name="gender" id="male" value="M" required>
                                                        <label for="male">Male</label>
                                                    </div>
                                                </label>
                                                <label class="radio-inline">
                                                    <div class="radio radio-success">
                                                        <input type="radio" @if("F" == $student_data->gender) checked @endif name="gender" id="female" value="F" required>
                                                        <label for="female">Female</label>
                                                    </div>
                                                </label>
                                                <label class="radio-inline">
                                                    <div class="radio radio-success">
                                                        <input type="radio" @if("O" == $student_data->gender) checked @endif name="gender" id="other" value="O" required>
                                                        <label for="other">Other</label>
                                                    </div>
                                                </label>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <label for="input-file-now">User Image</label>
                                            <input type="file" data-default-file="/storage/student/{{ $student_data->image }}" accept="image/png, image/jpg, image/jpeg" name="student_image" id="input-file-now" class="dropify" />
                                        </div>                                          
                                        <div class="col-md-4 form-group">
                                            <label>Optional Subject</label>
                                            <select id='optional_subject' name="optional_subject[]" multiple class="form-control">
                                                @if(isset($data['optional_subject_data']))
                                                    @foreach($data['optional_subject_data'] as $key => $value)
                                                        <option @php if( in_array($value['subject_id'],$data['student_optional_subject_data']) ){ echo "selected"; }@endphp value="{{ $value['subject_id'] }}">{{ $value['subject_name'] }}</option>
                                                    @endforeach
                                                @endif                                                   
                                            </select>
                                        </div>
                                        
                                        <div class="col-md-4 form-group">
                                            <label>Student Batch</label>
                                            <select id='studentbatch' name="studentbatch" class="form-control">
                                                <option value="">--Select--</option>
                                                @if(isset($data['batch_data']))
                                                    @foreach($data['batch_data'] as $key => $value)
                                                        <option @if($student_data->studentbatch == $value['id'] ) selected="selected" @endif value="{{ $value['id'] }}">{{ $value['title'] }}</option>
                                                    @endforeach
                                                @endif                                                    
                                            </select>
                                        </div>
                                        
                                        <div class="col-md-4 form-group">
                                            <label>Student Religion</label>
                                            <select id='religion' name="religion" class="form-control">
                                                <option value="">--Select--</option>  
                                                @if(isset($data['religion_data']))
                                                    @foreach($data['religion_data'] as $key => $value)
                                                        <option @if($student_data->religion == $value['id'] ) selected="selected" @endif value="{{ $value['id'] }}">{{ $value['religion_name'] }}</option>
                                                    @endforeach
                                                @endif                                                  
                                            </select>
                                        </div>
                                        
                                        <div class="col-md-4 form-group">
                                            <label>Student Caste Category</label>
                                            <select id='cast' name="cast" class="form-control">
                                                <option value="">--Select--</option>  
                                                @if(isset($data['caste_data']))
                                                    @foreach($data['caste_data'] as $key => $value)
                                                        <option @if($student_data->cast == $value['id'] ) selected="selected" @endif value="{{ $value['id'] }}">{{ $value['caste_name'] }}</option>
                                                    @endforeach
                                                @endif                                                  
                                            </select>
                                        </div>

                                        <div class="col-md-4 form-group">
                                            <label>{{ App\Helpers\get_string('nationality','request')}}<i class="mdi mdi-lead-pencil"></i></label>
                                            <input type="text" value="{{ $student_data->nationality }}" id='nationality' name="nationality" class="form-control">
                                        </div>

                                        <div class="col-md-4 form-group">
                                            <label>{{ App\Helpers\get_string('cast','request')}}<i class="mdi mdi-lead-pencil"></i></label>
                                            <input type="text" id='subcast' value="{{ $student_data->subcast }}" name="subcast" class="form-control">
                                        </div>

                                        <div class="col-md-4 form-group">
                                            <label>Roll Number</label>
                                            <input type="text" id='roll_no' value="{{ $student_data->roll_no }}" name="roll_no" class="form-control">
                                        </div>                                          
                                        
                                        <div class="col-md-4 form-group">
                                            <label>Student Blood Group</label>
                                            <select id='bloodgroup' name="bloodgroup" class="form-control">
                                                <option value="">--Select--</option>  
                                                @if(isset($data['bloodgroup_data']))
                                                    @foreach($data['bloodgroup_data'] as $key => $value)
                                                        <option @if($student_data->bloodgroup == $value['id'] ) selected="selected" @endif value="{{ $value['id'] }}">{{ $value['bloodgroup'] }}</option>
                                                    @endforeach
                                                @endif                                                  
                                            </select>
                                        </div>
                                        
                                        <div class="col-md-4 form-group">
                                            <label>Aadhar Number</label>
                                            <input type="text" id='adharnumber' value="{{ $student_data->adharnumber }}"  name="adharnumber" class="form-control" onblur="AadharValidate();">
                                        </div>
                                        
                                        <div class="col-md-4 form-group">
                                            <label>{{ App\Helpers\get_string('annualincome','request')}}<i class="mdi mdi-lead-pencil"></i></label>
                                            <input type="number" id='anuualincome' value="{{ $student_data->anuualincome }}" name="anuualincome" class="form-control">
                                        </div>
                                        {{--  For Euro School --}}
                                        @if (Session::get('sub_institute_id') != '195')
                                         <div class="col-md-4 form-group">
                                            <label>{{ App\Helpers\get_string('uniqueid','request')}}</label>
                                            <input type="text" id='uniqueid' value="{{ $student_data->uniqueid }}" name="uniqueid" class="form-control">
                                        </div> 
                                        @endif  
                                        <div class="col-md-4 form-group">
                                            <label>Dise Uid</label>
                                            <input type="text" id='dise_uid' value="{{ $student_data->dise_uid }}" name="dise_uid" class="form-control">
                                        </div>                                                          
                                        <div class="col-md-4 form-group">
                                            <label>Admission Docket No.</label>
                                            <input type="text" id='admission_docket_no' value="{{ $student_data->admission_docket_no }}"  name="admission_docket_no" class="form-control">
                                        </div>
                                        <div class="col-md-4 form-group">
                                            <label>{{ App\Helpers\get_string('birthplace','request')}}<i class="mdi mdi-lead-pencil"></i></label>
                                            <input type="text" id='place_of_birth' value="{{ $student_data->place_of_birth }}"  name="place_of_birth" class="form-control">
                                        </div>

                                        <div class="col-md-4 form-group">
                                            <label>Registration No.</label>
                                            <input type="text" id='registration_no' value="{{ $student_data->registration_no }}"  name="registration_no" class="form-control">
                                        </div> 

                                        <div class="col-md-4 form-group">
                                            <label>Admission under GENERAL/RTE</label>
                                            <select id='admission_under' name="admission_under" class="form-control">
                                                <option>Select Admission Under</option>
                                                <option value="GENERAL" @if(isset($student_data->admission_under) && $student_data->admission_under == 'GENERAL') selected="selected" @endif>GENERAL</option>
                                                <option value="RTE" @if(isset($student_data->admission_under) && $student_data->admission_under == 'RTE') selected="selected" @endif>RTE</option>
                                            </select>
                                        </div>

                                        <div class="col-md-4 form-group">
                                            <label>Distance From School</label>
                                            <select id='distance_from_school' name="distance_from_school" class="form-control">
                                                <option value="">Select Transport Kilometer</option>  
                                                @if(isset($data['transport_kilometer_data']))
                                                    @foreach($data['transport_kilometer_data'] as $key => $value)
                                                        <option @if($student_data->distance_from_school == $value['id'] ) selected="selected" @endif value="{{ $value['id'] }}">{{ $value['distance_from_school'] }}</option>
                                                    @endforeach
                                                @endif                                                  
                                            </select>
                                        </div>

                                        <div class="col-md-4 form-group">
                                            <label>Inactive Status</label>
                                            <select id='inactive_satus' name="inactive_satus" onchange="showInactive(this.value);" class="form-control">
                                            <option value="0" @if(!isset($student_data->end_date)) selected="selected" @endif> No </option>
                                            <option value="1" @if(isset($student_data->end_date)) selected="selected" @endif> Yes </option>
                                            </select>
                                        </div>

                                        <div class="col-md-4 form-group" id="end_date_div" @if(isset($student_data->end_date)) style="display: block;" @else style="display: none;" @endif>
                                            <label>Inactive Date</label>
                                            <div class="input-daterange input-group" >
                                                <input type="text" value="{{ $student_data->end_date }}" class="form-control mydatepicker" placeholder="yyyy-mm-dd" name="end_date" autocomplete="off">
                                                <span class="input-group-addon"><i class="icon-calender"></i></span>
                                            </div>
                                        </div>

                                        <div class="col-md-4 form-group" id="remarks_div" @if(isset($student_data->end_date)) style="display: block;" @else style="display: none;" @endif>
                                            <label>Inactive Remarks </label>
                                            <textarea type="text" id='remarks' name="remarks" class="form-control">@if(isset($student_data->remarks)) {{$student_data->remarks}} @endif</textarea>
                                        </div>

                                        @if(isset($data['custom_fields']))
                                        @foreach($data['custom_fields'] as $key => $value)
                                        <div class="col-md-4 form-group">
                                            <label>{{ $value['field_label'] }}</label>
                                            @if($value['field_type'] == 'file')
                                            <input type="{{ $value['field_type'] }}" id="input-file-now"  @if($value['required'] == 1) required @endif data-default-file="/storage/student/{{ $student_data[$value['field_name']] }}" name="{{ $value['field_name'] }}" class="dropify">
                                            <a href="/storage/student/{{ $student_data[$value['field_name']] }}" download="{{$student_data->username.'_'.$student_data[$value['field_name']]}}"><label>Download</label></a>
                                            @elseif($value['field_type'] == 'date')
                                            <div class="input-daterange input-group" >
                                            <input type="text" class="form-control mydatepicker" placeholder="dd/mm/yyyy" autocomplete="off" id="{{ $value['field_name'] }}" @if($value['required'] == 1) required @endif value="{{ $student_data[$value['field_name']] }}" name="{{ $value['field_name'] }}" class="form-control"><span class="input-group-addon"><i class="icon-calender"></i></span>
                                            </div>
                                            @elseif($value['field_type'] == 'checkbox')
                                            <div class="checkbox-list">
                                                @if(isset($data['data_fields'][$value['id']]))
                                                @foreach($data['data_fields'][$value['id']] as $keyData => $valueData )
                                                    <label class="checkbox-inline">
                                                        <div class="checkbox checkbox-success">
                                                            <input type="checkbox" @if($valueData['display_value'] == $student_data[$value['field_name']]) checked @endif name="{{ $value['field_name'] }}[]" value="{{ $valueData['display_value'] }}"  id="{{ $valueData['display_value'] }}" @if($value['required'] == 1) required @endif>
                                                            <label for="{{ $valueData['display_value'] }}">{{ $valueData['display_text'] }}</label>
                                                        </div>
                                                    </label>
                                                    @endforeach
                                                @endif
                                            </div>
                                            @elseif($value['field_type'] == 'dropdown')

                                                    <!-- <div class="custom-select"> -->
                                                    <select name="{{ $value['field_name'] }}" class="form-control" @if($value['required'] == 1) required @endif id="{{ $value['field_name'] }}">
                                                        <option value=""> SELECT {{ strtoupper($value['field_label']) }} </option>

                                                    @if(isset($data['data_fields'][$value['id']]))
                                                        @foreach($data['data_fields'][$value['id']] as $keyData => $valueData)
                                                        @php
                                                            $selected = '';
                                                        @endphp
                                                        @if($student_data[$value['field_name']]== $valueData['display_value'])
                                                            @php
                                                                $selected = 'selected';
                                                            @endphp
                                                        @endif
                                                        <option value="{{ $valueData['display_value'] }}" {{$selected}} > {{ $valueData['display_text'] }} </option>
                                                        @endforeach
                                                    @endif
                                                    </select>
                                                    <!-- </div> -->
                                                    
                                            @elseif($value['field_type'] == 'textarea')
                                            <textarea id="{{ $value['field_name'] }}" class="form-control" @if($value['required'] == 1) required @endif name="{{ $value['field_name'] }}">
                                            {{ $student_data[$value['field_name']] }}
                                            </textarea>
                                            @else
                                            <input type="{{ $value['field_type'] }}" id="{{ $value['field_name'] }}" placeholder="{{ $value['field_message'] }}" value="{{ $student_data[$value['field_name']] }}" @if($value['required'] == 1) required @endif name="{{ $value['field_name'] }}" class="form-control">
                                            @endif
                                        </div>
                                        @endforeach
                                        @endif

                                        @if(Session::get('user_profile_name') != 'Student')
                                        <div class="col-md-12 form-group">
                                            <input type="submit" name="submit" value="Update" class="btn btn-success triz-btn" >
                                        </div>
                                        @endif
                                        <div class="clearfix"></div>
                                        </div>
                                        </form>                                
                                </div>
                                <!-- END STUDENT INFORMATION -->
                                
                                
                                <!-- START STUDENT EDUCATION -->
                                <div class="tab-pane p-3" id="section-linemove-2" role="tabpanel">                                
                                <form action="{{ route('past_education.store') }}" enctype="multipart/form-data" method="post">
                                {{ method_field("POST") }}
                                @csrf
                                @php
                                    if(isset($data['past_education'])){
                                        $past_education = $data['past_education'];
                                    }else{
                                        $past_education = array();
                                    }
                                @endphp
                                @foreach($past_education as $pkey => $pvalue)
                                    <div id="entered_og">
                                        <div class="row">
                                            <div class="col-md-4 form-group">
                                                <label>Course </label>
                                                <input type="text" required id='course' value="{{$pvalue['course']}}" name="courses[]" class="form-control">
                                            </div>
                                            <div class="col-md-4 form-group">
                                                <label>Medium </label>
                                                <input type="text" id='medium' value="{{$pvalue['medium']}}" name="mediums[]" class="form-control">
                                            </div>
                                            <div class="col-md-4 form-group">
                                                <label>Name of board </label>
                                                <input type="text" id='name_of_board' value="{{$pvalue['name_of_board']}}" name="name_of_boards[]" class="form-control">
                                            </div>
                                            <div class="col-md-4 form-group">
                                                <label>Year</label>
                                                <input type="text" id='year_of_passing' value="{{$pvalue['year_of_passing']}}" name="year_of_passings[]" class="form-control">
                                            </div>
                                            <div class="col-md-4 form-group">
                                                <label>Percentage </label>
                                                <input type="text" id='percentage' value="{{$pvalue['percentage']}}" name="percentages[]" class="form-control">
                                            </div>
                                            <div class="col-md-4 form-group">
                                                <label>School Name </label>
                                                <input type="text" id='school_name' value="{{$pvalue['school_name']}}" name="school_names[]" class="form-control">
                                            </div>
                                            <div class="col-md-4 form-group ml-0 mr-0">
                                                <label>Place </label>
                                                <input type="text" id='place' value="{{$pvalue['place']}}" name="places[]" class="form-control">
                                            </div>
                                            <div class="col-md-4 form-group ml-0">
                                                <label>Trial </label>
                                                <input type="text" id='trial' value="{{$pvalue['trial']}}" name="trials[]" class="form-control">
                                            </div>
                                            <div style="height:60px; width:100%; clear:both;"></div>
                                        </div>
                                    </div>
                                    @endforeach
                                    <input type="hidden" name="student_id" value="{{$student_data['id']}}">
                                        <div id="past_og">

                                        <div class="row">
                                            <div class="col-md-4 form-group">
                                                <label>Course </label>
                                                <input type="text" required id='course'  name="courses[]" class="form-control">
                                            </div>
                                            <div class="col-md-4 form-group">
                                                <label>Medium </label>
                                                <input type="text" id='medium'  name="mediums[]" class="form-control">
                                            </div>
                                            <div class="col-md-4 form-group">
                                                <label>Name of board </label>
                                                <input type="text" id='name_of_board'  name="name_of_boards[]" class="form-control">
                                            </div>
                                            <div class="col-md-4 form-group">
                                                <label>Year</label>
                                                <input type="text" id='year_of_passing'  name="year_of_passings[]" class="form-control">
                                            </div>
                                            <div class="col-md-4 form-group">
                                                <label>Percentage </label>
                                                <input type="text" id='percentage'  name="percentages[]" class="form-control">
                                            </div>
                                            <div class="col-md-4 form-group">
                                                <label>School Name </label>
                                                <input type="text" id='school_name'  name="school_names[]" class="form-control">
                                            </div>
                                            <div class="col-md-4 form-group ml-0 mr-0">
                                                <label>Place </label>
                                                <input type="text" id='place'  name="places[]" class="form-control">
                                            </div>
                                            <div class="col-md-4 form-group ml-0">
                                                <label>Trial </label>
                                                <input type="text" id='trial'  name="trials[]" class="form-control">
                                            </div>                                              
                                        </div>                                              
                                    </div>
                                    <div class="col-md-12 form-group">
                                        <label>Add </label>
                                        <a href="javascript:void(0);" class="triz-add-btn" onclick="addNewRow('past_og','past_add');">
                                            <span class="btn btn-outline-success"><i class="fa fa-plus"></i></span>
                                        </a>
                                    </div>
                                    <div id="past_add">
                                    </div>
                                    @if(Session::get('user_profile_name') != 'Student')
                                    <div class="col-md-12 form-group">
                                        <input type="submit" name="submit" value="Save" class="btn btn-success triz-btn" >
                                    </div>
                                    @endif
                                    </form>                                
                                </div>
                                <!-- END STUDENT EDUCATION -->


                                
                                <!-- START STUDENT HISTORY -->
                                <div class="tab-pane p-3" id="section-linemove-3" role="tabpanel">                                   
                                    <form action="{{ route('family_history.store') }}" enctype="multipart/form-data" method="post">
                                        {{ method_field("POST") }}
                                    @csrf
                                    @php
                                        if(isset($data['family_history'])){
                                            $family_history = $data['family_history'];
                                        }else{
                                            $family_history = array();
                                        }
                                    @endphp
                                @foreach($family_history as $fkey => $fvalue)
                                    <div id="entered_og_family">
                                            <div class="row">
                                                <div class="col-md-4 form-group">
                                                    <label>Name </label>
                                                    <input type="text" required id='name' placeholder="Name" value="{{$fvalue['name']}}" name="names[]" class="form-control">
                                                </div>
                                                <div class="col-md-4 form-group">
                                                    <label>Institute Name </label>
                                                    <input type="text" id='institute_name' placeholder="Institute Name" value="{{$fvalue['institute_name']}}" name="institute_names[]" class="form-control">
                                                </div>
                                                <div class="col-md-4 form-group">
                                                    <label>Course </label>
                                                    <input type="text" id='course' placeholder="Course" value="{{$fvalue['course']}}" name="courses[]" class="form-control">
                                                </div>
                                                <div class="col-md-4 form-group">
                                                    <label>Year</label>
                                                    <input type="text" id='year' placeholder="Year" value="{{$fvalue['year']}}" name="years[]" class="form-control">
                                                </div>
                                                <div class="col-md-4 form-group">
                                                    <label>Percentage </label>
                                                    <input type="text" id='percentage' placeholder="Percentage" value="{{$fvalue['percentage']}}" name="percentages[]" class="form-control">
                                                </div>
                                                <div class="col-md-4 form-group">
                                                    <label>Relation With Student </label>
                                                    <input type="text" id='relation_with_student' placeholder="Relation With Student" value="{{$fvalue['relation_with_student']}}" name="relation_with_students[]" class="form-control">
                                                </div>
                                                <div style="height:60px; width:100%; clear:both;"></div>
                                            </div>
                                    </div>
                                    @endforeach
                                    <input type="hidden" name="student_id" value="{{$student_data['id']}}">
                                    <div id="family_og">
                                        <div class="row">
                                            <div class="col-md-4 form-group">
                                                <label>Name </label>
                                                <input type="text" id='name' required placeholder="Name" name="names[]" class="form-control">
                                            </div>
                                            <div class="col-md-4 form-group">
                                                <label>Institute Name </label>
                                                <input type="text" id='institute_name' placeholder="Institute Name" name="institute_names[]" class="form-control">
                                            </div>
                                            <div class="col-md-4 form-group">
                                                <label>Course </label>
                                                <input type="text" id='course' placeholder="Course" name="courses[]" class="form-control">
                                            </div>
                                            <div class="col-md-4 form-group">
                                                <label>Year</label>
                                                <input type="text" id='year' placeholder="Year" name="years[]" class="form-control">
                                            </div>
                                            <div class="col-md-4 form-group">
                                                <label>Percentage </label>
                                                <input type="text" id='percentage' placeholder="Percentage" name="percentages[]" class="form-control">
                                            </div>
                                            <div class="col-md-4 form-group">
                                                <label>Relation With Student </label>
                                                <input type="text" id='relation_with_student' placeholder="Relation With Student" name="relation_with_students[]" class="form-control">
                                            </div>
                                            <div class="clearfix"></div>
                                        </div>
                                    </div>
                                        <div class="col-md-12 form-group">
                                            <label>Add </label>
                                            <a href="javascript:void(0);" class="triz-add-btn" onclick="addNewRow('family_og','family_add');">                                                
                                                <span class="btn btn-outline-success"><i class="fa fa-plus"></i></span>
                                            </a>
                                        </div>
                                    <div id="family_add">
                                    </div>
                                    @if(Session::get('user_profile_name') != 'Student')
                                        <div class="col-md-12 form-group">
                                            <input type="submit" name="submit" value="Save" class="btn btn-success triz-btn" >
                                        </div>
                                    @endif    
                                    </form>                                
                                </div>
                                <!-- END STUDENT HISTORY -->
                                
                                

                                <!-- START STUDENT SIBLINGS -->
                                <div class="tab-pane p-3" id="section-linemove-4" role="tabpanel">                                
                                    @php
                                        if(isset($data['student_siblings'])){
                                            $student_siblings = $data['student_siblings'];
                                        }else{
                                            $student_siblings = array();
                                        }
                                    @endphp
                                    <div class="table-responsive">
                                        <table id="example" class="table table-striped">
                                            <thead>
                                                <tr>
                                                    <th>Sr No.</th>                                                            
                                                    <th>Gr No.</th>
                                                    <th>Student Name</th>
                                                    <th>Standard</th>
                                                    <th>Division</th>
                                                    <th>Mobile</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                            @php
                                            $j=1;
                                            @endphp
                                            @if(isset($data['data']))
                                                @foreach($student_siblings as $pkey => $pvalue)
                                                <tr>
                                                    <td>{{$j}}</td>                                                            
                                                    <td>{{$pvalue['enrollment_no']}}</td>
                                                    <td>{{$pvalue['student_name']}}</td>
                                                    <td>{{$pvalue['std_name']}}</td>
                                                    <td>{{$pvalue['div_name']}}</td>
                                                    <td>{{$pvalue['mobile']}}</td>
                                                </tr>
                                            @php
                                            $j++;
                                            @endphp
                                                @endforeach
                                            @endif
                                            </tbody>
                                        </table>
                                    </div>                                
                                </div>
                                <!-- END STUDENT SIBLINGS -->


                                <!-- START STUDENT FEEDBACK -->
                                <div class="tab-pane p-3" id="section-linemove-5" role="tabpanel">                                
                                    <form action="{{ route('parent_feedback.store') }}" enctype="multipart/form-data" method="post">
                                        {{ method_field("POST") }}
                                    @csrf
                                    @php
                                        if(isset($data['parent_feedback'])){
                                            $parent_feedback = $data['parent_feedback'];
                                        }else{
                                            $parent_feedback = array();
                                        }
                                    @endphp
                                    @foreach($parent_feedback as $pkey => $pvalue)
                                    <div id="entered_og_parent">
                                        <div class="row">
                                            <div class="col-md-4 form-group">
                                                <label>Name of person on phone</label>
                                                <input type="text" required id='person_name' value="{{$pvalue['person_name']}}" name="person_names[]" class="form-control">
                                            </div>
                                            <div class="col-md-4 form-group">
                                                <label>Purpose of phone </label>
                                                <input type="text" id='purpose' value="{{$pvalue['purpose']}}" name="purposes[]" class="form-control">
                                            </div>
                                            <div class="col-md-4 form-group">
                                                <label>Response </label>
                                                <input type="text" id='response' value="{{$pvalue['response']}}" name="responses[]" class="form-control">
                                            </div>
                                            <div class="col-md-4 form-group ml-0 mr-0">
                                                <label>Comments</label>
                                                <input type="text" id='comments' value="{{$pvalue['comments']}}" name="commentss[]" class="form-control">
                                            </div>
                                            <div class="col-md-4 form-group ml-0">
                                                <label>Date </label>
                                                <input type="text" id='date' value="{{$pvalue['date']}}" name="dates[]" class="form-control mydatepicker">
                                            </div>
                                            <div style="height:60px; width:100%; clear:both;"></div>
                                        </div>
                                    </div>
                                @endforeach
                                    <input type="hidden" name="student_id" value="{{$student_data['id']}}">
                                    <div id="parent_og">
                                            <div class="row">
                                                <div class="col-md-4 form-group">
                                                    <label>Name of person on phone</label>
                                                    <input type="text" required id='person_name' name="person_names[]" class="form-control">
                                                </div>
                                                <div class="col-md-4 form-group">
                                                    <label>Purpose of phone </label>
                                                    <input type="text" id='purpose' name="purposes[]" class="form-control">
                                                </div>
                                                <div class="col-md-4 form-group">
                                                    <label>Response </label>
                                                    <input type="text" id='response' name="responses[]" class="form-control">
                                                </div>
                                                <div class="col-md-4 form-group ml-0 mr-0">
                                                    <label>Comments</label>
                                                    <input type="text" id='comments' name="commentss[]" class="form-control">
                                                </div>
                                                <div class="col-md-4 form-group ml-0">
                                                    <label>Date </label>
                                                    <input type="text" id='date' name="dates[]" class="form-control mydatepicker" autocomplete="off">
                                                </div>
                                            </div>
                                    </div>
                                    <div class="col-md-12 form-group">
                                            <label>Add </label>
                                            <a href="javascript:void(0);" class="triz-add-btn" onclick="addNewRow('parent_og','parent_add');">
                                                <span class="btn btn-outline-success"><i class="fa fa-plus"></i></span>
                                            </a>
                                        </div>
                                    <div id="parent_add">
                                    </div>
                                    @if(Session::get('user_profile_name') != 'Student')
                                        <div class="col-md-12 form-group">
                                            <input type="submit" name="submit" value="Save" class="btn btn-success triz-btn" >
                                        </div>
                                    @endif    
                                    </form>                                
                                </div>
                                <!-- END STUDENT FEEDBACK -->
                                


                                <!-- START STUDENT INFIRMARY -->
                                <div class="tab-pane p-3" id="section-linemove-6" role="tabpanel">
                                    @if(Session::get('user_profile_name') != 'Student') 
                                    <div class="row">                                        
                                        <div class="col-md-3 mb-4"> 
                                            <a href="{{ route('student_infirmary.create') }}" class="btn btn-info add-new" target="_blank">
                                                <i class="fa fa-plus"></i> Add Infirmary
                                            </a>
                                        </div>
                                    </div>
                                    @endif                               
                                    @php
                                        if(isset($data['student_infirmary'])){
                                            $student_infirmary = $data['student_infirmary'];
                                        }else{
                                            $student_infirmary = array();
                                        }
                                    @endphp
                                    <div class="table-responsive">
                                        <table id="example" class="table table-striped">
                                            <thead>
                                                <tr>
                                                    <th>SrNo.</th>
                                                    <th>MedicalCaseNo</th>
                                                    <th>DoctorName</th>
                                                    <th>DoctorContact</th>
                                                    <th>OpenDate</th>
                                                    <th>Complaint</th>
                                                    <th>Symptoms</th>
                                                    <th>Disease</th>
                                                    <th>Treatments</th>
                                                    <th>MedicalCloseDate</th>
                                                    <th>Health Center</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                            @php
                                            $j=1;
                                            @endphp
                                            @if(isset($data['data']))
                                                @foreach($student_infirmary as $pkey => $pvalue)
                                                <tr>
                                                    <td>{{$j}}</td>
                                                    <td>{{$pvalue['medical_case_no']}}</td>
                                                    <td>{{$pvalue['doctor_name']}}</td>
                                                    <td>{{$pvalue['doctor_contact']}}</td>
                                                    <td>{{$pvalue['date']}}</td>
                                                    <td>{{$pvalue['complaint']}}</td>
                                                    <td>{{$pvalue['symptoms']}}</td>
                                                    <td>{{$pvalue['disease']}}</td>
                                                    <td>{{$pvalue['treatments']}}</td>
                                                    <td>{{$pvalue['medical_close_date']}}</td>
                                                    <td>{{$pvalue['health_center']}}</td>
                                                </tr>
                                            @php
                                            $j++;
                                            @endphp
                                                @endforeach
                                            @endif
                                            </tbody>
                                        </table>
                                    </div>                                
                                </div>
                                <!-- END STUDENT INFIRMARY -->


                                <!-- START STUDENT VACCINATION -->
                                <div class="tab-pane p-3" id="section-linemove-7" role="tabpanel">
                                    @if(Session::get('user_profile_name') != 'Student')
                                    <div class="row">                                        
                                        <div class="col-md-3 mb-4"> 
                                            <a href="{{ route('student_vaccination.create') }}" class="btn btn-info add-new" target="_blank">
                                                <i class="fa fa-plus"></i> Add Vaccination 
                                            </a>
                                        </div>
                                    </div> 
                                    @endif
                                    @php
                                        if(isset($data['student_vaccination'])){
                                            $student_vaccination = $data['student_vaccination'];
                                        }else{
                                            $student_vaccination = array();
                                        }
                                    @endphp
                                    <div class="table-responsive">
                                        <table id="example" class="table table-striped">
                                            <thead>
                                                <tr>
                                                    <th>Sr No.</th>                                                            
                                                    <th>Doctor Name</th>
                                                    <th>Doctor Contact</th>
                                                    <th>Vaccination Type</th>
                                                    <th>Note</th>
                                                    <th>Date</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                            @php
                                            $j=1;
                                            @endphp
                                            @if(isset($data['data']))
                                                @foreach($student_vaccination as $pkey => $pvalue)
                                                <tr>
                                                    <td>{{$j}}</td>                                                            
                                                    <td>{{$pvalue['doctor_name']}}</td>
                                                    <td>{{$pvalue['doctor_contact']}}</td>
                                                    <td>{{$pvalue['vaccination_type']}}</td>
                                                    <td>{{$pvalue['note']}}</td>
                                                    <td>{{$pvalue['date']}}</td>
                                                </tr>
                                            @php
                                            $j++;
                                            @endphp
                                                @endforeach
                                            @endif
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                                <!-- END STUDENT VACCINATION -->

                               
                                <!-- START STUDENT HEALTH WEIGHT-->
                                <div class="tab-pane p-3" id="section-linemove-8" role="tabpanel">
                                    @if(Session::get('user_profile_name') != 'Student')
                                    <div class="row">                                        
                                        <div class="col-md-3 mb-4"> 
                                            <a href="{{ route('student_hw.create') }}" class="btn btn-info add-new" target="_blank">
                                                <i class="fa fa-plus"></i> Add Height Weight 
                                            </a>
                                        </div>
                                    </div>                           
                                    @endif
                                    @php
                                        if(isset($data['student_height_weight'])){
                                            $student_height_weight = $data['student_height_weight'];
                                        }else{
                                            $student_height_weight = array();
                                        }
                                    @endphp

                                    <div class="table-responsive">
                                        <table id="example" class="table table-striped">
                                            <thead>
                                                <tr>
                                                    <th>Sr No.</th>                                                            
                                                    <th>Doctor Name</th>
                                                    <th>Doctor Contact</th>
                                                    <th>Height</th>
                                                    <th>Weight</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                            @php
                                            $j=1;
                                            @endphp
                                            @if(isset($data['data']))
                                                @foreach($student_height_weight as $pkey => $pvalue)
                                                <tr>
                                                    <td>{{$j}}</td>
                                                    <td>{{$pvalue['doctor_name']}}</td>
                                                    <td>{{$pvalue['doctor_contact']}}</td>
                                                    <td>{{$pvalue['height']}}</td>
                                                    <td>{{$pvalue['weight']}}</td>
                                                </tr>
                                            @php
                                            $j++;
                                            @endphp
                                                @endforeach
                                            @endif
                                            </tbody>
                                        </table>
                                    </div>                                
                                </div>
                                <!-- END STUDENT HEALTH WEIGHT-->


                                <!-- START STUDENT HEALTH -->
                                <div class="tab-pane p-3" id="section-linemove-9" role="tabpanel">
                                    @if(Session::get('user_profile_name') != 'Student')
                                    <div class="row">                                        
                                        <div class="col-md-3 mb-4"> 
                                            <a href="{{ route('student_health.create') }}" class="btn btn-info add-new" target="_blank">
                                                <i class="fa fa-plus"></i> Add Health
                                            </a>
                                        </div>
                                    </div>
                                    @endif                           
                                    @php
                                        if(isset($data['student_health'])){
                                            $student_health = $data['student_health'];
                                        }else{
                                            $student_health = array();
                                        }
                                    @endphp
                                    <div class="table-responsive">
                                        <table id="example" class="table table-striped">
                                            <thead>
                                                <tr>
                                                    <th>Sr No.</th>                                                            
                                                    <th>Doctor Name</th>
                                                    <th>Doctor Contact</th>
                                                    <th>Date</th>
                                                    <th>File</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                            @php
                                            $j=1;
                                            @endphp
                                            @if(isset($data['data']))
                                                @foreach($student_health as $pkey => $pvalue)
                                                <tr>
                                                    <td>{{$j}}</td>                                                            
                                                    <td>{{$pvalue['doctor_name']}}</td>
                                                    <td>{{$pvalue['doctor_contact']}}</td>
                                                    <td>{{$pvalue['date']}}</td>
                                                    <td><a target="_blank" href="../../../../storage/frontdesk/{{$pvalue['file']}}">{{$pvalue['file']}}</a></td>
                                                </tr>
                                            @php
                                            $j++;
                                            @endphp
                                                @endforeach
                                            @endif
                                            </tbody>
                                        </table>
                                    </div>                                
                                </div>
                                <!-- END STUDENT HEALTH -->


                                <!-- START STUDENT DOCUMENT -->                                
                                <div class="tab-pane p-3" id="section-linemove-10" role="tabpanel">                                
                                
                                @php
                                    if(isset($data['student_document'])){
                                        $student_document = $data['student_document'];
                                    }else{
                                        $student_document = array();
                                    }
                                @endphp                                
                                   
                                    <form name="student_document_form" id="student_document_form" enctype="multipart/form-data" method="post">
                                    {{ method_field("POST") }}
                                    @csrf
                                        <input type="hidden" name="student_id" id="student_id" value="{{$student_data['id']}}">
                                            <div id="past_document">
                                            <div class="row">
                                                <div class="col-md-4 form-group">
                                                     <label>Document Type</label>
                                                    <select id='document_type_id' name="document_type_id" class="form-control" required>
                                                        <option value="">Select</option>  
                                                        @if(isset($data['document_type_data']))
                                                            @foreach($data['document_type_data'] as $key => $value)
                                                                <option value="{{ $value['id'] }}">{{ $value['document_type'] }}</option>
                                                            @endforeach
                                                        @endif                                                  
                                                    </select>
                                                </div>                                            
                                                <div class="col-md-4 form-group">
                                                    <label>Document Title</label>
                                                    <input type="text" id='document_title' name="document_title" class="form-control" required>
                                                </div>                                            
                                                <div class="col-md-4 form-group">
                                                    <label>File </label>
                                                    <input type="file" id='file_name' name="file_name" class="form-control" required>
                                                </div>  
                                            </div>                                              
                                        </div>
                                    @if(Session::get('user_profile_name') != 'Student')                                
                                        <div class="col-md-12 form-group">
                                            <input type="submit" name="submit" value="Save" class="btn btn-success triz-btn">
                                        </div>
                                    @endif    
                                    </form> 
                                    <div class="table-responsive">
                                        <table id="example" class="table table-striped">
                                            <thead>
                                                <tr>
                                                    <th>Sr No.</th>                                                            
                                                    <th>Document Type</th>
                                                    <th>Document Title</th>
                                                    <th>Date</th>
                                                    <th>File Name</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                            @php
                                            $j=1;
                                            @endphp
                                            @if(isset($data['data']))
                                               @foreach($student_document as $pkey => $docdata)
                                                <tr>
                                                    <td>{{$j}}</td>                                                            
                                                    <td>{{$docdata['document_type']}}</td>
                                                    <td>{{$docdata['document_title']}}</td>
                                                    <td>{{$docdata['created_on']}}</td>
                                                    <td><a target="_blank" href="../../../../storage/student_document/{{$docdata['file_name']}}">{{$docdata['file_name']}}</a></td>
                                                </tr>
                                            @php
                                            $j++;
                                            @endphp
                                                @endforeach
                                            @endif
                                            </tbody>
                                        </table>
                                    </div>                                
                                </div>
                                <!-- END STUDENT DOCUMENT -->


                                <!-- START FEES DETAILS -->                                
                                <div class="tab-pane p-3" id="section-linemove-11" role="tabpanel">                                                                
                                    <form name="student_fees_form" id="student_fees_form" enctype="multipart/form-data" method="post" action="{{ route('student_fees_detail.store') }}">
                                    {{ method_field("POST") }}
                                    @csrf
                                        <input type="hidden" name="student_id" id="student_id" value="{{$student_data['id']}}">
                                            <div class="h4 text-primary">Fees Detail</div>
                                            <div class="row border rounded mb-3 mb-md-4 mt-3 p-4">                                                                                            
                                                <div class="col-md-3 form-group">
                                                    <label>Account Type</label>
                                                    <select id='ac_type' name="ac_type" class="form-control" required>
                                                        <option value="">Select</option>                                                                                                  
                                                        @foreach($data['ac_type_arr'] as $ackey => $acval)
                                                            @php
                                                            $ac_selected = "";
                                                            if(isset($data['studentfeesdetails']['ac_type']) && $data['studentfeesdetails']['ac_type'] == $acval['type_id'])
                                                            {
                                                                $ac_selected = "selected";
                                                            }
                                                            @endphp
                                                            <option value="{{$acval['type_id']}}" {{$ac_selected}}>{{$acval['type_name']}}</option>
                                                        @endforeach
                                                        
                                                        <!-- <option value="saving" @if(isset($data['studentfeesdetails']['ac_type']) && $data['studentfeesdetails']['ac_type'] == "saving") selected @endif> Saving Account </option>
                                                        <option value="current" @if(isset($data['studentfeesdetails']['ac_type']) && $data['studentfeesdetails']['ac_type'] == "current") selected @endif> Current Account </option>
                                                        <option value="cash" @if(isset($data['studentfeesdetails']['ac_type']) && $data['studentfeesdetails']['ac_type'] == "cash") selected @endif> Cash / Credit </option> -->
                                                    </select>
                                                </div>                                            
                                                <div class="col-md-3 form-group">
                                                    <label>Account Holder Name</label>                                                    
                                                    <input type="text" id='ac_holder_name' name="ac_holder_name" value="@if(isset($data['studentfeesdetails']['ac_holder_name'])) {{$data['studentfeesdetails']['ac_holder_name']}} @endif" class="form-control" required>
                                                </div>
                                                <div class="col-md-3 form-group">
                                                    <label>Account No.</label>
                                                    <input type="text" id='ac_number' name="ac_number" value="@if(isset($data['studentfeesdetails']['ac_number'])) {{$data['studentfeesdetails']['ac_number']}} @endif" class="form-control" required>
                                                </div>
                                                <div class="col-md-3 form-group">
                                                    <label>Bank Name</label>
                                                    <input type="text" id='bank_name' name="bank_name" value="@if(isset($data['studentfeesdetails']['bank_name'])) {{$data['studentfeesdetails']['bank_name']}} @endif" class="form-control" required>
                                                </div>
                                                <div class="col-md-3 form-group">
                                                    <label>Bank Branch</label>
                                                    <input type="text" id='bank_branch' name="bank_branch" value="@if(isset($data['studentfeesdetails']['bank_branch'])) {{$data['studentfeesdetails']['bank_branch']}} @endif" class="form-control">
                                                </div>                                      
                                                 <div class="col-md-3 form-group">
                                                    <label>IFSC Code</label>
                                                    <input type="text" id='ifsc_code' name="ifsc_code" value="@if(isset($data['studentfeesdetails']['ifsc_code'])) {{$data['studentfeesdetails']['ifsc_code']}} @endif" class="form-control" required>
                                                </div>                                      
                                                <div class="col-md-3 form-group">
                                                    <label>Registration Date</label>                                                  
                                                    <input type="text" id="registration_date" name="registration_date" value="@if(isset($data['studentfeesdetails']['registration_date'])) {{$data['studentfeesdetails']['registration_date']}} @endif" class="form-control mydatepicker" required autocomplete="off">
                                                </div> 
                                                <div class="col-md-3 form-group">
                                                    <label>UMRN</label>
                                                    <input type="text" id='UMRN' name="UMRN" value="@if(isset($data['studentfeesdetails']['UMRN'])) {{$data['studentfeesdetails']['UMRN']}} @endif" class="form-control">
                                                </div>
                                                <div class="col-md-3 form-group ml-0 mr-0">
                                                    <label>Date</label>                                                   
                                                    <input type="text" id="date_" name="date_" class="form-control mydatepicker" autocomplete="off">
                                                </div>
                                                <div class="col-md-3 form-group ml-0 mr-0">
                                                    <label>Status </label>
                                                    <input type="text" id='status' name="status" class="form-control">
                                                </div>
                                                <div class="col-md-3 form-group ml-0">
                                                    <label>Rejection Reason</label>
                                                    <input type="text" id='rejected_reason' name="rejected_reason" class="form-control">
                                                </div>                                     
                                            </div> 

                                        <div class="h4 text-primary">Payment Method Mapping</div>
                                            <div class="row border rounded mb-3 mb-md-4 mt-3 p-4"> 
                                                <div class="table-responsive">
                                                    <table class="table table-bordered">
                                                        <tr>
                                                            <th>Month</th>
                                                            <th>Method</th>
                                                            <th>Date</th>
                                                            <th>Remarks</th>
                                                        </tr>
                                                        <tbody>                                                           
                                                            @if(isset($data['breakoff_MonthArr']))
                                                                @foreach($data['breakoff_MonthArr'] as $mkey => $mval)
                                                                 <tr>
                                                                    <td>{{$mval}}</td>
                                                                    <td>                                                                      
                                                                        <select name="payment_method[{{$mkey}}]" id="payment_method[{{$mkey}}]" class="form-control">
                                                                            <option value="">Select</option>
                                                                            <option value="DD" @if(isset($data['studentPM_Arr'][$mkey]) && $data['studentPM_Arr'][$mkey]['payment_method'] == 'DD') selected @endif >DD</option>
                                                                            <option value="NHCS" @if(isset($data['studentPM_Arr'][$mkey]) && $data['studentPM_Arr'][$mkey]['payment_method'] == 'NHCS') selected @endif >NHCS</option>
                                                                        </select>
                                                                    </td>
                                                                    <td> 
                                                                        <input type="text" id="month_date[{{$mkey}}]" name="month_date[{{$mkey}}]" 
                                                                        value="@if(isset($data['studentPM_Arr'][$mkey]) && $data['studentPM_Arr'][$mkey]['payment_date'] != '0000-00-00' ) {{$data['studentPM_Arr'][$mkey]['payment_date']}} @endif "
                                                                        class="form-control mydatepicker" autocomplete="off">
                                                                    </td>
                                                                    <td>
                                                                        <textarea type="text" id='month_remark[{{$mkey}}]' name="month_remark[{{$mkey}}]" class="form-control">@if(isset($data['studentPM_Arr'][$mkey])) {{$data['studentPM_Arr'][$mkey]['remarks']}} @endif</textarea>
                                                                    </td>
                                                                </tr>
                                                                @endforeach
                                                            @endif                                                            
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>                                             
                                       
                                    @if(Session::get('user_profile_name') != 'Student')                                
                                        <div class="col-md-12 form-group">
                                            <input type="submit" name="submit" value="Save" class="btn btn-success triz-btn">
                                        </div>
                                    @endif 
                                    
                                    <div class="h4 text-primary">Student Fees History</div>
                                        <div class="row border rounded mb-3 mb-md-4 mt-3 p-4"> 
                                            <div class="table-responsive">
                                            <div class="box-title">
                                                <label>Fees Structure</label>
                                            </div>
                                            <div class="table-responsive">
                                                <table class="table table-stripped" style="color:#000 !important;">
                                                    <tr>
                                                        <th>Month</th>
                                                        <th>Fees</th>
                                                        <th>Paid</th>
                                                        <th>Remaining</th>
                                                    </tr>
                                                    @php
                                                        $remainFees = 0;
                                                        $feesDetails= [];
                                                        $bk=$paid=$remain =array();
                                                        foreach ($data['paid_unpaid_fees'] as $id => $arr) {
                                                        $feesDetails[$arr['month']] = $arr['remain'];
                                                        if(isset($arr['bk'])){
                                                    @endphp
                                                    <tr>
                                                        <td>
                                                            {{ $arr['month'] }}
                                                        </td>
                                                        <td>
                                                            @php $bk[] = $arr['bk']; echo $arr['bk'];  @endphp
                                                        </td>
                                                        <td>
                                                            @php $paid[] = $arr['paid']; echo $arr['paid']; @endphp
                                                        </td>
                                                        <td>
                                                            @php $remain[] = $arr['remain'];echo $arr['remain'];  @endphp
                                                        </td>
                                                    </tr>
                                                    @php
                                                        }
                                                            $remainFees += $arr['remain'];
                                                            } 
                                                    @endphp
                                                    <tr>
                                                        <td>Total</td>
                                                        <td>
                                                            {{ array_sum($bk) }}
                                                        </td>
                                                        <td>
                                                            {{array_sum($paid) }}
                                                        </td>
                                                        <td>
                                                            {{array_sum($remain) }}
                                                        </td>
                                                    </tr>

                                                </table>
                                            </div>
                                            <div class="row">
                                                <div class="col-md-12 text-center mt-4">
                                                <button type="button" class="btn btn-info" data-toggle="modal" id="add_data" onclick="javascript:add_data({{ $data['stu_data']['enrollment']}},{{$data['stu_data']['student_id']}});">
                                                        Paid History
                                                    </button>
                                                </div>
                                            </div>
                                                </table>
                                            </div>
                                        </div>  
                                    
                                    </form>
                                </div>
                                <!-- END STUDENT FEES DETAILS -->
                                
                                <!-- START STUDENT TC INFORMATION -->                                
                                <div class="tab-pane p-3" id="section-linemove-12" role="tabpanel">
                                    <form name="student_tc_form" id="student_tc_form" method="post" action="{{ route('student_tc_detail.store') }}">
                                    {{ method_field("POST") }}
                                    @csrf
                                        <input type="hidden" name="student_id" id="student_id" value="{{$student_data['id']}}">
                                        <div class="h4 text-primary">TC Information</div>
                                        <div class="row border rounded mb-3 mb-md-4 mt-3 p-4">
                                            <div class="col-md-4 form-group">
                                                <label>Affiliation No.</label>   
                                                <input type="text" id='affiliation_no' name="affiliation_no" value="@if(isset($data['studentTcdetails']['affiliation_no'])) {{$data['studentTcdetails']['affiliation_no']}} @endif" class="form-control">
                                            </div>
                                            <div class="col-md-4 form-group">
                                                <label>School Code</label>   
                                                <input type="text" id='school_code' name="school_code" value="@if(isset($data['studentTcdetails']['school_code'])) {{$data['studentTcdetails']['school_code']}} @endif" class="form-control">
                                            </div>
                                            <div class="col-md-4 form-group">
                                                <label>Whether the candidate belongs to Schedule Caste or Schedule Tribe or OBC</label>   
                                                <input type="text" id='candidate_belongs_to' name="candidate_belongs_to" value="@if(isset($data['studentTcdetails']['candidate_belongs_to'])) {{$data['studentTcdetails']['candidate_belongs_to']}} @endif" class="form-control">
                                            </div>
                                            <div class="col-md-4 form-group">
                                                <label>Date of first admission in the School with class</label>
                                                <input type="text" id='date_of_first_admission' name="date_of_first_admission" value="@if(isset($data['studentTcdetails']['date_of_first_admission'])) {{$data['studentTcdetails']['date_of_first_admission']}} @endif" class="form-control" autocomplete="off">
                                            </div>
                                            <div class="col-md-4 form-group">
                                                <label>Class in which the pupil last studied (in figures)</label>
                                                <input type="text" id='class_in_which_pupil_last_studied' name="class_in_which_pupil_last_studied" value="@if(isset($data['studentTcdetails']['class_in_which_pupil_last_studied'])) {{$data['studentTcdetails']['class_in_which_pupil_last_studied']}} @endif" class="form-control">
                                            </div>
                                            <div class="col-md-4 form-group">
                                                <label>School/Board Annual examination last taken with result</label>
                                                <input type="text" id='last_school_board' name="last_school_board" value="@if(isset($data['studentTcdetails']['last_school_board'])) {{$data['studentTcdetails']['last_school_board']}} @endif" class="form-control">
                                            </div>                                      
                                             <div class="col-md-4 form-group">
                                                <label>Whether failed, if so once/twice in the same class</label>
                                                <input type="text" id='whether_failed' name="whether_failed" value="@if(isset($data['studentTcdetails']['whether_failed'])) {{$data['studentTcdetails']['whether_failed']}} @endif" class="form-control">
                                            </div>                                                                                  
                                            <div class="col-md-4 form-group">
                                                <label>Subjects Studied</label>
                                                <input type="text" id='subjects_studied' name="subjects_studied" value="@if(isset($data['studentTcdetails']['subjects_studied'])) {{$data['studentTcdetails']['subjects_studied']}} @endif" class="form-control">
                                            </div>
                                            <div class="col-md-4 form-group">
                                                <label>Whether qualified for promotion to the higher class</label>
                                                <input type="text" id='whether_qualified' name="whether_qualified" value="@if(isset($data['studentTcdetails']['whether_qualified'])) {{$data['studentTcdetails']['whether_qualified']}} @endif" class="form-control">
                                            </div>
                                            <div class="col-md-4 form-group">
                                                <label>If so, to which class (in fig.)</label>
                                                <input type="text" id='if_to_which_class' name="if_to_which_class" value="@if(isset($data['studentTcdetails']['if_to_which_class'])) {{$data['studentTcdetails']['if_to_which_class']}} @endif" class="form-control">
                                            </div>
                                            <div class="col-md-4 form-group">
                                                <label>Month upto which the pupil has paid school dues</label>
                                                <input type="text" id='month_up_paid_school_dues' name="month_up_paid_school_dues" value="@if(isset($data['studentTcdetails']['month_up_paid_school_dues'])) {{$data['studentTcdetails']['month_up_paid_school_dues']}} @endif" class="form-control">
                                            </div>
                                            <div class="col-md-4 form-group">
                                                <label>Total No. of working days in academic session</label>
                                                <input type="text" id='total_working_days' name="total_working_days" value="@if(isset($data['studentTcdetails']['total_working_days'])) {{$data['studentTcdetails']['total_working_days']}} @endif" class="form-control">
                                            </div>
                                            <div class="col-md-4 form-group">
                                                <label>Total No. of working days pupil present in school</label>
                                                <input type="text" id='total_working_days_present' name="total_working_days_present" value="@if(isset($data['studentTcdetails']['total_working_days_present'])) {{$data['studentTcdetails']['total_working_days_present']}} @endif" class="form-control">
                                            </div>
                                            <div class="col-md-4 form-group">
                                                <label>Games played or extra curricular activities in which the pupil usually took part(mention achievement level therein)</label>
                                                <input type="text" id='games_played' name="games_played" value="@if(isset($data['studentTcdetails']['games_played'])) {{$data['studentTcdetails']['games_played']}} @endif" class="form-control">
                                            </div>
                                            <div class="col-md-4 form-group">
                                                <label>General Conduct</label>
                                                <input type="text" id='general_conduct' name="general_conduct" value="@if(isset($data['studentTcdetails']['general_conduct'])) {{$data['studentTcdetails']['general_conduct']}} @endif" class="form-control">
                                            </div>
                                            <div class="col-md-4 form-group">
                                                <label>Date of application for certificate</label>
                                                <input type="text" id='date_of_application_for_certificate' name="date_of_application_for_certificate" value="@if(isset($data['studentTcdetails']['date_of_application_for_certificate'])) {{$data['studentTcdetails']['date_of_application_for_certificate']}} @endif" class="form-control mydatepicker" autocomplete="off">
                                            </div>
                                            <div class="col-md-4 form-group">
                                                <label>Date of issue of certificate</label>
                                                <input type="text" id='date_of_issue_of_certificate' name="date_of_issue_of_certificate" value="@if(isset($data['studentTcdetails']['date_of_issue_of_certificate'])) {{$data['studentTcdetails']['date_of_issue_of_certificate']}} @endif" class="form-control mydatepicker" autocomplete="off">
                                            </div>
                                            <div class="col-md-4 form-group">
                                                <label>Reasons for leaving the school</label>
                                                <input type="text" id='reason_leaving_school' name="reason_leaving_school" value="@if(isset($data['studentTcdetails']['reason_leaving_school'])) {{$data['studentTcdetails']['reason_leaving_school']}} @endif" class="form-control">
                                            </div>  
                                            <div class="col-md-4 form-group">
                                                <label>Proof for date of birth submitted at the time of admission</label>
                                                <input type="text" id='proof_for_dob' name="proof_for_dob" value="@if(isset($data['studentTcdetails']['proof_for_dob'])) {{$data['studentTcdetails']['proof_for_dob']}} @endif" class="form-control">
                                            </div>
                                            <div class="col-md-4 form-group">
                                                <label>Whether school is under Government / Minority / Independent Category</label>
                                                <input type="text" id='whether_school_is_under_goverment' name="whether_school_is_under_goverment" value="@if(isset($data['studentTcdetails']['whether_school_is_under_goverment'])) {{$data['studentTcdetails']['whether_school_is_under_goverment']}} @endif" class="form-control">
                                            </div>
                                            <div class="col-md-4 form-group">
                                                <label>Date on which pupil's name was struck off the rolls of the school</label>
                                                 <input type="text" id='date_on_which_pupil_name_was_struck' name="date_on_which_pupil_name_was_struck" value="@if(isset($data['studentTcdetails']['date_on_which_pupil_name_was_struck'])) {{$data['studentTcdetails']['date_on_which_pupil_name_was_struck']}} @endif" class="form-control mydatepicker" autocomplete="off">
                                            </div>
                                            <div class="col-md-4 form-group">
                                                <label>Any fees concession availed of, if so, the nature of such concession</label>
                                                <input type="text" id='any_fees_concession' name="any_fees_concession" value="@if(isset($data['studentTcdetails']['any_fees_concession'])) {{$data['studentTcdetails']['any_fees_concession']}} @endif" class="form-control">
                                            </div>
                                            <div class="col-md-4 form-group">
                                                <label>Whether NCC Cadet/ Boy Scout / Girl Guide (details may be given)</label>
                                                <input type="text" id='whether_ncc_cadet' name="whether_ncc_cadet" value="@if(isset($data['studentTcdetails']['whether_ncc_cadet'])) {{$data['studentTcdetails']['whether_ncc_cadet']}} @endif" class="form-control">
                                            </div>
                                            <div class="col-md-4 form-group">
                                                <label>Any other remarks</label>
                                                <input type="text" id='any_other_remarks' name="any_other_remarks" value="@if(isset($data['studentTcdetails']['any_other_remarks'])) {{$data['studentTcdetails']['any_other_remarks']}} @endif" class="form-control">
                                            </div>                                                                          
                                        </div>
                                        @if(Session::get('user_profile_name') != 'Student')                                                                  
                                        <div class="col-md-12 form-group">
                                            <input type="submit" name="submit" value="Save" class="btn btn-success triz-btn">
                                        </div>
                                        @endif
                                    </form>                                                                
                                </div>
                                <!-- END STUDENT TC INFORMATION -->

                                <!-- START ATTENDANCE REPORT -->
                                <div class="tab-pane p-3" id="section-linemove-13" role="tabpanel">
                                    <div class="table-responsive">
                                        <table id="attendance-report" class="table table-striped">
                                            <thead>
                                                <th>Month</th>
                                                <th>Present</th>
                                                <th>Absent</th>
                                                <th>Total Working Days</th>
                                                <th>Total Present Days</th>
                                            </thead>
                                            <tbody>
                                                @php
                                                $total_present = 0;
                                                $total_absent = 0;
                                                @endphp
                                                @if( $data['attendance_data'] )
                                                    @foreach ( $data['attendance_data'] as $attendance )
                                                        @if ( $attendance->TOTAL_CLASSES > 0 )
                                                        <tr>
                                                            <td>{{ $attendance->MONTH.'-'.$attendance->YEAR }}</td>
                                                            <td>{{ $attendance->TOTAL_PRESENT }}</td>
                                                            <td>{{ $attendance->TOTAL_ABSENT }}</td>
                                                            <td>{{ $attendance->TOTAL_PRESENT + $attendance->TOTAL_ABSENT }}</td>
                                                            @php
                                                                $total_present += $attendance->TOTAL_PRESENT;
                                                                $total_absent += $attendance->TOTAL_ABSENT;
                                                                $percent_friendly = '0%';
                                                                if ( $attendance->TOTAL_PRESENT ) {
                                                                    $totalDays = $attendance->TOTAL_PRESENT + $attendance->TOTAL_ABSENT;
                                                                    $percent = $attendance->TOTAL_PRESENT/$totalDays;
                                                                    $percent_friendly = number_format( $percent * 100, 2 ) . '%';
                                                                }
                                                            @endphp
                                                            <td>{{ $percent_friendly }}</td>
                                                        </tr>
                                                        @else
                                                            <tr>
                                                                <td colspan="5">No data found</td>
                                                            </tr>
                                                        @endif
                                                    @endforeach
                                                    <tr>
                                                        <td>Total</td>
                                                        <td>{{ $total_present }}</td>
                                                        <td>{{ $total_absent }}</td>
                                                        <td>{{ $total_present + $total_absent }}</td>
                                                        @php
                                                            $total_percent_friendly = '0%';
                                                            if ( $total_present ) {
                                                                $total_days = $total_present + $total_absent;
                                                                $total_percent = $total_present/$total_days;
                                                                $total_percent_friendly = number_format( $total_percent * 100, 2 ) . '%';
                                                            }
                                                        @endphp
                                                        <td>{{ $total_percent_friendly }}</td>
                                                    </tr>
                                                @else
                                                    <tr>
                                                        <td colspan="6">No Data Found</td>
                                                    </tr>
                                                @endif
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                                <!-- END ATTENDANCE REPORT -->

                                <!-- START PARENT COMMUNICATION -->
                                <div class="tab-pane p-3" id="section-linemove-14" role="tabpanel">
                                    <div class="tab-pane p-3" id="section-linemove-13" role="tabpanel">
                                        <div class="table-responsive">
                                            <table id="parent-communication" class="table table-striped">
                                                <thead>
                                                    <th>Title</th>
                                                    <th>Description</th>
                                                    <th>Created At</th>
                                                    <th>Reply</th>
                                                    <th>Reply By</th>
                                                    <th>Reply On</th>
                                                </thead>
                                                <tbody>
                                                @if( $data['stu_par_communication'] )
                                                    @foreach ( $data['stu_par_communication'] as $stuParComunication )
                                                    <tr>
                                                        <td>{{ $stuParComunication->title }}</td>
                                                        <td>{{ $stuParComunication->message }}</td>
                                                        <td>{{ $stuParComunication->created_at }}</td>
                                                        <td>{{ $stuParComunication->reply }}</td>
                                                        <td>{{ $stuParComunication->reply_by }}</td>
                                                        <td>{{ $stuParComunication->reply_on }}</td>
                                                    </tr>
                                                    @endforeach
                                                @else
                                                    <tr>
                                                        <td colspan="6">No Data Found</td>
                                                    </tr>
                                                @endif
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                                <!-- END PARENT COMMUNICATION -->
                                
                                <!-- START LEAVE APPLICATION -->
                                <div class="tab-pane p-3" id="section-linemove-15" role="tabpanel">
                                    <div class="tab-pane p-3" id="section-linemove-14" role="tabpanel">
                                        <div class="table-responsive">
                                            <table id="leave-application" class="table table-striped">
                                                <thead>
                                                    <th>No</th>
                                                    <th>Student Name</th>
                                                    <th>STD/DIV</th>
                                                    <th>Mobile</th>
                                                    <th>Apply Date</th>
                                                    <th>From Date</th>
                                                    <th>To Date</th>
                                                    <th>Message</th>
                                                    <th>File</th>
                                                    <th>Reply</th>
                                                    <th>Reply By</th>
                                                    <th>Status</th>
                                                </thead>
                                                <tbody>
                                                @if($arr = $data['leave_data'])
                                                    @foreach($arr as $id => $leaveData)
                                                        <tr>
                                                            <td>{{ intval($id) + 1 }}</td>
                                                            <td>{{ $leaveData['name'] }}</td>
                                                            <td>{{ $leaveData['stddiv'] }}</td>
                                                            <td>{{ $leaveData['mobile'] }}</td>
                                                            <td>{{ $leaveData['apply_date'] }}</td>
                                                            <td>{{ $leaveData['from_date'] }}</td>
                                                            <td>{{ $leaveData['to_date'] }}</td>
                                                            <td>{{ $leaveData['message'] }}</td>
                                                            <td>{{ $leaveData['files'] }}</td>
                                                            <td>{{ $leaveData['reply'] }}</td>
                                                            <td>{{ $leaveData['reply_by'] }}</td>
                                                            <td>{{ $leaveData['status'] }}</td>
                                                        </tr>
                                                    @endforeach
                                                @else
                                                    <tr>
                                                        <td colspan="6">No Data Found</td>
                                                    </tr>
                                                @endif
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                                <!-- END LEAVE APPLICATION --> 
                                
                                <div id="overlay" style="display:none;"><img id="loading" src="https://i1.wp.com/cdnjs.cloudflare.com/ajax/libs/galleriffic/2.0.1/css/loader.gif"></div>
                            
                            </div>
                            <!-- /content -->
                        </div>
                        <!-- /tabs -->
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!--Modal: Add ChapterModal-->
<div id="printThis">
    <div class="modal fade right modal-scrolling" id="ChapterModal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel"
        style="display: none;" aria-hidden="true">
        <div class="modal-dialog modal-side modal-bottom-right modal-notify modal-info" role="document" style="min-width: 85%;">
            <!--Content-->
            <div class="modal-content">
                <!--Header-->
                <div class="modal-header">
                    <h5 class="modal-title" id="heading">Fees Payment History</h5>
                    <button type="button" class="close" id="refresh_data" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">x</span>
                    </button>
                </div>
                <!--Body-->
                <div class="modal-body">
                    <div class="row">
                        <!-- TABLE START-->
                        <div class="card">
                            <div class="table-responsive">
                                <table id="example" class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Sr No.</th>
                                            <th>GR No.</th>
                                            <th>{{App\Helpers\get_string('StudentName','request')}}<span id="menuId" style="display:none"></span><a href="{{route('norm-clature.create')}}"><i class="mdi mdi-lead-pencil"></i></a></th>
                                            <th>Std-Div</th>
                                            <th>Uniqueid</th>
                                            <th>Month</th>
                                            <th>Receipt No</th>
                                            <th>Payment Mode</th>
                                            <th>Bank Details</th>
                                            <!--<th>Cheque Date</th>-->
                                            <th>Receipt Date</th>
                                            <th>Collected By</th>
                                            <!--<th>Created On</th>-->
                                            <th>Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody id="table_data">
                                        <!-- //data -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <!-- table end -->
                    </div>
                </div>
                <!--Footer-->
                <!--  <div class="modal-footer" style="display: block !important;">
                        <div id="overlay" style="display:none;"><center><p style="margin-top: 273px;color:red;font-weight: 700;">Please do not refresh the page, while the process is going on.</p><img src="http://dev.triz.co.in/admin_dep/images/loader.gif"></center></div>
                        <center>
                            <button id="ajax_PDF" type="button" class="btn btn-primary">Print Receipt</button>
                        </center>
                    </div>
                </div> -->
                <!--/.Content-->
            </div>
        </div>
    </div>
<!--Modal: Add ChapterModal-->

@include('includes.footerJs')
<script src="../../../admin_dep/js/cbpFWTabs.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.3/js/select2.min.js"></script>
<script type="text/javascript">
    (function() {
        [].slice.call(document.querySelectorAll('.sttabs')).forEach(function(el) {
            new CBPFWTabs(el);
        });
    })();

</script>
<script>
   $( document ).ready(function() {
       //$("a[data-id='document']").trigger("click");
       
       //START if once fees is paid for current year admission year,standard,student quota,academic section can't be edited
       if("{{$data['edit_disable']}}" == "disabled")
       {           
            $('option', "#grade").not(':eq(0), :selected').remove(); 
            $("#grade").find('option[value=""]').remove();
            $("#grade").attr("readonly",true); 

            $('option', "#standard").not(':eq(0), :selected').remove(); 
            $("#standard").find('option[value=""]').remove();
            $("#standard").attr("readonly",true); 

            $('option', "#student_quota").not(':eq(0), :selected').remove(); 
            $("#student_quota").find('option[value=""]').remove();
            $("#student_quota").attr("readonly",true); 

            $('option', "#admission_year").not(':eq(0), :selected').remove(); 
            $("#admission_year").find('option[value=""]').remove();
            $("#admission_year").attr("readonly",true);                          
       }
       //END if once fees is paid for current year admission year,standard,student quota,academic section can't be edited

    });

    function getUsername(){

        var first_name = document.getElementById("first_name").value;
        var last_name = document.getElementById("last_name").value;
        var username = first_name.toLowerCase()+"_"+last_name.toLowerCase();
        document.getElementById("username").value = username;
    }

    function addNewRow(og,add){
        var divHtml = document.getElementById(og).innerHTML;
        var extradiv = '<div style="height:60px; width:100%; clear:both;"></div>';
        $('#'+add+':last').after(divHtml + extradiv);
                
        //Again initalize datepicker
        jQuery('.mydatepicker, #datepicker').datepicker({
            autoclose: true,
            format: 'dd-mm-yyyy',
            orientation: 'bottom'
        }); 
        
    }

    function removeNewRow() {
        // $(".addButtonDrop:last" ).remove();
    }

    function getStudents(value)
    {
        var URL = "{{route('search_student_name')}}";
        // var data = value;

        if(value.length > 2)
        {

            $.post(URL,
          {
            'value': value
          },
          function(result, status){
            alert(result);
            $('#studentSearchList').find('option').remove().end();
            for(var i=0;i < result.length;i++){

                    $("#studentSearchList").append($("<option></option>").val(result[i]['id']).html(result[i]['student']));
            }
          });
        }
    }

    function onInput() {
        var val = document.getElementById("student").value;
        var opts = document.getElementById('studentSearchList').childNodes;        
        var URL = "{{route('search_student_id')}}";
        for (var i = 0; i < opts.length; i++) {

              if (opts[i].value === val) {

               $.ajax({
                    url: URL,
                    type: 'POST',
                    data: {
                        'student_id': val
                    },
                    success: function(result){
                        document.getElementById("siblings_new").style.display = "BLOCK";
                        for(var i=0;i < result.length;i++){
                            document.getElementById("student_name_new").setAttribute( "value", result[i]['student_name'] );
                            document.getElementById("division_new").setAttribute( "value", result[i]['division'] );
                            document.getElementById("standard_new").setAttribute( "value", result[i]['standard'] );
                            document.getElementById("enrollment_no_new").setAttribute( "value", result[i]['enrollment_no'] );
                            document.getElementById('add_new').setAttribute( "onClick", "addSiblings('"+result[i]['id']+"','{{$student_data['id']}}');" );
                        }
                    }
                });

            break;
          }
        }
      }
                  
        $('#student_document_form').submit(function(e) {
            e.preventDefault();           
            var URL = "{{route('student_document.store')}}";            
            var formData = new FormData(this);
            $('#overlay').show();            
            $.ajax({
                url: URL,
                type: 'POST',
                data: formData,
                cache: false,
                contentType: false,
                processData: false,
                success: function (data) {                                        
                    $('#overlay').hide();
                    //alert("Document Uploaded Successfully");
                    location.reload();
                    //$('#section-linemove-10').addClass('active');
                    $("a[data-id='document']").trigger("click");
                },
                error: function(response){
                    alert("Document Failed.");
                }                
            });       
        });

      function addSiblings(sibling_id,student_id)
      {
        var URL = "{{route('add_student_siblings')}}";
        if(sibling_id == student_id)
        {
            alert("Please select different student");
            
            document.getElementById("student_name_new").setAttribute( "value","" );
            document.getElementById("division_new").setAttribute( "value","");
            document.getElementById("standard_new").setAttribute( "value","" );
            document.getElementById("enrollment_no_new").setAttribute( "value","" );                
            document.getElementById("student").value = "";                          
            document.getElementById("siblings_new").style.display = "none";
            return false;
        }
        else{
            $.ajax({
                url: URL,
                type: 'POST',
                data: {
                    'student_id': student_id,
                    'sibling_id': sibling_id,
                    'type': 'Add'
                },
                success: function(result){
                    alert("Siblings successfully added");
                    location.reload();
                }
            });
        }
      }

      function removeSiblings(sibling_id,student_id)
      {
        var URL = "{{route('add_student_siblings')}}";
        $.ajax({
                    url: URL,
                    type: 'POST',
                    data: {
                        'student_id': student_id,
                        'sibling_id': sibling_id,
                        'type': 'Remove'
                    },
                    success: function(result){
                        alert("Siblings successfully deleted");
                        location.reload();
                    }
                });

      }

        function showInactive(x)
        {
            if(x == 1)
            {
                document.getElementById("end_date_div").style.display = 'block';
                document.getElementById("end_date_div").required = true;
                document.getElementById("remarks_div").style.display = 'block';
                document.getElementById("remarks_div").required = true;

            }else{
                document.getElementById("end_date_div").style.display = 'none';
                document.getElementById("end_date_div").required = false;
                document.getElementById("remarks_div").style.display = 'none';
                document.getElementById("remarks_div").required = false;
            }
        }
</script>
<script src="../../../plugins/bower_components/dropify/dist/js/dropify.min.js"></script>
    <script>
    $(document).ready(function() {
        $(document).ready(function() {      
            $('#dob').datepicker('setEndDate', new Date());     
        });
    
        // Basic
        $('.dropify').dropify();
        // Translated
        $('.dropify-fr').dropify({
            messages: {
                default: 'Glissez-d�posez un fichier ici ou cliquez',
                replace: 'Glissez-d�posez un fichier ou cliquez pour remplacer',
                remove: 'Supprimer',
                error: 'D�sol�, le fichier trop volumineux'
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
            }
        });
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

            if(division_check == false)
            { 
                alert('Please select other division.');
                return false;
            }

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
      </script>
@include('includes.footer')

<!--fees payment history code  -->
<script src="https://cdn.jsdelivr.net/gh/gitbrent/bootstrap4-toggle@3.6.1/js/bootstrap4-toggle.min.js"></script>
		<script>
			function add_data(grno, student_id) {
				$(document).ready(function() {
					$.ajax({
						url: '/fees/feesDetails/getDetails/' + grno + "/" + student_id,
						type: 'GET',
						dataType: 'json',
						success: function(data) {
							const months = ["Jan", "Feb", "Mar", "Apr", "May", "June", "July", "Aug", "Sep", "Oct", "Nov", "Dec"];

							$.each(data, function(index, value) {
								index++;
								const term_id = value['term_id'];
								year = String(term_id).slice(-4);
								month = String(term_id).substring(0, String(term_id).length - 4);
								month--;
								// const d = new Date(value['term_id']);
								let monthyear = months[month] + "/" + year;
								// console.log(monthyear);

								if (value['uniqueid'] == 'null') {
									valueuni = value['uniqueid'];
								} else {
									valueuni = '';
								}
								// console.log(value['student_name']);
								$('#table_data').append("<tr><td>" + index + "</td><td>" + value['enrollment_no'] + "</td><td>" + value[
										'student_name'] + "</td><td>" + value['division_name'] + "</td><td>" + valueuni + "</td><td>" +
									monthyear + "</td><td>" + value['receipt_no'] + "</td><td>" + value['payment_mode'] + "</td><td>" +
									value['cheque_bank_name'] + "</td><td>" + value['receiptdate'] + "</td><td>" + value['user_name'] +
									"</td><td id='total_amt'>" + value['actual_amountpaid'] + "</td></tr>");
							});

							var total = 0;

							$('#table_data tr').each(function(index) {
								var found = $(this).find('#total_amt')
								if (found) {
									total += parseInt(found.text());
								}
								// console.log(total);
							});

							$('#table_data').append("<tr><td colspan=11>Total</td><td>" + total + "</td></tr>");
							$('#ChapterModal').modal('show');

						}
					});
				});
			}

			$('body').on('hidden.bs.modal', '.modal', function() {
				$("#table_data").empty();
			});
		</script>

<script>
    var menuId = localStorage.getItem('current_id');
    var spans = document.querySelectorAll('span.menuId');
    for (var i = 0; i < spans.length; i++) {
        spans[i].textContent = menuId;
    }
    var url = '{{ route("norm-clature.create") }}?menu_id=' + menuId;
    var links = document.querySelectorAll('a[href="{{ route("norm-clature.create") }}"]');
    for (var j = 0; j < links.length; j++) {
        links[j].href = url;
    }
</script>
