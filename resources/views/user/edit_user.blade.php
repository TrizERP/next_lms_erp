{{-- @include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation') --}}
<link rel="stylesheet" href="../../../plugins/bower_components/dropify/dist/css/dropify.min.css">
@extends('layout')
@section('container')
<style type="text/css">
br {
     display: block;
}
.dropdown-content {
    display: none;
    position: absolute;
    background-color: #f9f9f9;
    box-shadow: 0px 8px 16px 0px rgba(0,0,0,0.2);
    padding: 10px;
    z-index: 1;
    width: 100%;
    border: 1px solid #ccc;
    border-radius: 4px;
    margin-top: 2px;
}

.dropdown-content label {
    display: block;
    padding: 5px 0;
}

.dropdown-content input[type="checkbox"] {
    margin-right: 10px;
}
.mainHead{
        background:#a2cdf3;
    }
    .subHead{
        background:#d9edff;
    }
</style>

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-9 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Edit User</h4>
            </div>
        </div>
        <div class="card">
            <!-- @TODO: Create a saperate tmplate for messages and include in all tempate -->
            @if ($message = Session::get('success'))
            <div class="alert alert-success alert-block">
                <button type="button" class="close" data-dismiss="alert">×</button>
                <strong>{{ $message }}</strong>
            </div>
            @endif
            <div class="row">
                <div class="col-lg-12 col-sm-12 col-xs-12">
                    <div class="sttabs tabs-style-linemove triz-verTab bg-white style2">
                        <center>
                        <ul class="nav nav-tabs tab-title mb-4">
                            <li class="nav-item"><a href="#section-linemove-1" class="nav-link active" aria-selected="true" data-toggle="tab"><span>Personal Details</span></a></li>
                            <li class="nav-item"><a href="#section-linemove-2" class="nav-link" aria-selected="false" data-toggle="tab"><span>Upload Document</span></a></li>
                            @if(!empty($data['salary_structure']))
                            <li class="nav-item"><a href="#section-linemove-3" class="nav-link" aria-selected="false" data-toggle="tab"><span>Salary</span></a></li>
                            @endif
                           
                            <li class="nav-item"><a href="#section-linemove-4" class="nav-link" aria-selected="false" data-toggle="tab"><span>My Skills & Certifications</span></a></li>
                            @if(session()->get('sub_institute_id')==47)
                            <li class="nav-item"><a href="#section-linemove-5" class="nav-link" aria-selected="false" data-toggle="tab"><span>Contact Details</span></a></li>
                            @endif
                        </ul>
                        </center>
                    @php
                    $departments = $data['departments'];
                    $new_emp_code = $data['new_emp_code'];
                    $qualificationList = $data['qualificationList'];
                    $masterSetups = $data['masterSetups'];
                    $occupationList = $data['occupationList'];
                    $documentTypeLists = $data['documentTypeLists'];
                    $documentLists = $data['documentLists'];
                    $employees = $data['employees'];
                    $job_titles = $data['job_titles'];
                    $user_profiles = $data['user_profiles'];
                    $subject_data = $data['subject_data'];
                    $standardLists = $data['standardLists'];
                    $subject_data_selected_arr = $data['subject_data_selected_arr'];
                    $custom_fields = $data['custom_fields'];
                    $data_fields = $data['data_fields'];
                    $payrollTypes = $data['payroll_types'];
                    $salary_deposit = $data['salary_deposit'];
                    $SalaryStructure = $data['salary_structure'];
                    $contactDetails = $data['contactDetails'];
                    $data = $data['data'];
                    @endphp
                    <!-- tabs starts  -->
                    <div class="tab-content">
                    <!-- tab 1 start  -->
                    <div class="tab-pane p-3 active" id="section-linemove-1" role="tabpanel">      
                    <form action="{{ route('add_user.update', $data['id']) }}" enctype="multipart/form-data" method="post">
                    {{ method_field("PUT") }}
                    @csrf
                        <div class="row">
                            <div class="col-md-4 form-group">
                                <label>Name Suffix</label>
                                <select name="name_suffix" id="name_suffix" class="form-control" required>
                                    <option> Select Name Suffix </option>
                                    <option value="Mr." @if(isset($data))@if("Mr." == $data['name_suffix']) selected @endif  @endif> Mr. </option>
                                    <option value="Mrs." @if(isset($data))@if("Mrs." == $data['name_suffix']) selected @endif  @endif> Mrs. </option>
                                    <option value="Miss." @if(isset($data))@if("Miss." == $data['name_suffix']) selected @endif  @endif> Miss. </option>
                                </select>
                            </div>
                            <div class="col-md-4 form-group">
                                <label>First Name </label>
                                <input type="text" id='first_name' value="@if(isset($data['first_name'])){{ $data['first_name'] }}@endif" required name="first_name" class="form-control">
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Middle Name</label>
                                <input type="text" value="@if(isset($data['middle_name'])){{ $data['middle_name'] }}@endif" id='middle_name' required name="middle_name" class="form-control">
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Last Name</label>
                                <input type="text" onchange="getUsername();" value="@if(isset($data['last_name'])){{ $data['last_name'] }}@endif" id='last_name' required name="last_name" class="form-control">
                            </div>
                            <div class="col-md-4 form-group">
                                <label>User Name </label>
                                <input type="text" value="@if(isset($data['user_name'])){{ $data['user_name'] }}@endif" id='user_name' required name="user_name" class="form-control">
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Email</label>
                                <!--<span><br><b>{{ $data['email'] }}</b></span>-->
                                <input type="text" id='email' value="@if(isset($data['email'])){{ $data['email'] }}@endif" required name="email" class="form-control">
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Mobile</label>
                                <input type="text" value="@if(isset($data['mobile'])){{ $data['mobile'] }}@endif" id='mobile' required name="mobile" class="form-control">
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Subject</label>
                                <select name="subject_ids[]" id="subject_ids[]" class="form-control" multiple style="height:200px;">
                                    <option value="0"> Select Subject </option>
                                    @if(!empty($subject_data))
                                    @foreach($subject_data as $key => $val)
                                        <option value="{{ $val['id'] }}"

                                        @php if( in_array($val['id'],$subject_data_selected_arr) ){ echo "selected"; }@endphp

                                        > {{ $val['subject_name'] }} </option>
                                    @endforeach
                                    @endif
                                </select>
                            </div>
                            <!-- // 10-01-2025 start supervisor rights -->
                            <div class="col-md-4" class="form-group">
                                <label for="allocate_standard">Allocate Standard</label>
                                <select name="allocated_standards[]" id="allocated_standards" class="form-control resizableVertical" multiple>
                                    @php 
                                        $allocatedStd = (isset($data['allocated_standards']) && $data['allocated_standards']!='') ? explode(',',$data['allocated_standards']) : [];
                                    @endphp

                                    @if(!empty($standardLists))
                                    @foreach($standardLists as $sk => $sv)
                                       <option value="{{$sv['id']}}" @if(in_array($sv['id'],$allocatedStd)) selected @endif>{{$sv['name']}}</option>
                                    @endforeach
                                    @endif
                                </select>
                            </div>
                            <!-- // 10-01-2025 end supervisor rights -->
                            <div class="col-md-4 form-group">
                                <label class="control-label">Gender</label>
                                <div class="radio-list">
                                    <label class="radio-inline p-0">
                                        <div class="radio radio-success">
                                            <input type="radio" @if(isset($data))@if("M" == $data['gender']) checked @endif  @endif name="gender" id="male" value="M" required>
                                            <label for="male">Male</label>
                                        </div>
                                    </label>
                                    <label class="radio-inline">
                                        <div class="radio radio-success">
                                            <input type="radio" @if(isset($data))@if("F" == $data['gender']) checked @endif  @endif name="gender" id="female" value="F" required>
                                            <label for="female">Female</label>
                                        </div>
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Address</label>
                                <textarea class="form-control" required name="address">@if(isset($data['address'])){{ $data['address'] }}@endif</textarea>
                            </div>
                            <div class="col-md-4 form-group">
                                <label>City</label>
                                <input type="text" value="@if(isset($data['city'])){{ $data['city'] }}@endif" id='city' required name="city" class="form-control">
                            </div>
                            <div class="col-md-4 form-group">
                                <label>State</label>
                                <input type="text" value="@if(isset($data['state'])){{ $data['state'] }}@endif" id='state' required name="state" class="form-control">
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Pincode</label>
                                <input type="number" value="@if(isset($data['pincode'])){{$data['pincode']}}@endif" id='pincode' required name="pincode" class="form-control">
                            </div>
                            <div class="col-md-4 form-group">
                                <label>User Profile</label>
                                <select name="user_profile_id" required id="user_profile_id" class="form-control">
                                    <option value="0"> Select Parent Profile </option>

                                    @if(!empty($user_profiles))
                                    @foreach($user_profiles as $key => $value)

                                        <option value="{{ $value['id'] }}" @if(isset($data)) @if($value['id'] == $data['user_profile_id']) selected @endif  @endif  > {{ $value['name'] }} </option>
                                    @endforeach
                                    @endif
                                </select>
                            </div>
                            <div class="col-md-4 form-group" id="total_lecture_div">
                                <label>Total Lectures</label>
                                <input type="number" id='total_lecture' name="total_lecture" class="form-control" value="@if(isset($data['total_lecture'])){{$data['total_lecture']}}@endif">
                            </div>
                            @if(session()->get('sub_institute_id')!=47)
                            <div class="col-md-4 form-group">
                                <label>Join Year</label>
                                <input type="number" value="@if(isset($data['join_year'])){{$data['join_year']}}@endif" id='join_year' required name="join_year" class="form-control">
                            </div>
                            @endif
                            <div class="col-md-4 form-group">
                                <label>Password</label>
                                <input type="password" value="@if(isset($data['password'])){{ $data['password'] }}@endif" id='password' required name="password" class="form-control">
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Birthdate</label>
                                <div class="input-daterange input-group" id="date-range">
                                <input type="text" required class="form-control mydatepicker" placeholder="dd/mm/yyyy" value="@if(isset($data['birthdate'])){{$data['birthdate']}}@endif" name="birthdate" autocomplete="off"><span class="input-group-addon"><i class="icon-calender"></i></span>
                             </div>
                            </div>
                            <div class="col-md-4 form-group ml-0 mr-0">
                                <label>Inactive Status</label>
                                <select id='status' name="status" class="form-control">
                                <option value="1" @if(isset($data['status'])) @if($data['status'] == 1) selected @endif @endif> No </option>
                                <option value="0" @if(isset($data['status'])) @if($data['status'] == 0) selected @endif @endif> Yes </option>
                                </select>
                            </div>
                            <div class="col-sm-4 ol-md-4 col-xs-12">
                                <label for="input-file-now">User Image</label>
                                <input type="file" accept="image/*" name="user_image" @if(isset($data))data-default-file="/storage/user/{{ $data['image'] }}" @else required @endif id="input-file-now" class="dropify" />
                            </div>
                            @if(isset($custom_fields))
                            @foreach($custom_fields as $key => $value)
                            <div class="col-md-4 form-group">
                                <label>{{ $value['field_label'] }}</label>
                                @if($value['field_type'] == 'file')
                                <input type="{{ $value['field_type'] }}" accept="image/*" id="input-file-now" data-default-file="@if(isset($data[$value['field_name']])){{'/storage/user/'.$data[$value['field_name']]}}@endif"  required name="{{ $value['field_name'] }}" class="form-control">
                                @elseif($value['field_type'] == 'date')
                                <div class="input-daterange input-group" >
                                <input type="text" class="form-control mydatepicker" placeholder="dd/mm/yyyy" value="@if(isset($data[$value['field_name']])){{$data[$value['field_name']]}}@endif" autocomplete="off" id="{{ $value['field_name'] }}" required name="{{ $value['field_name'] }}" class="form-control"><span class="input-group-addon"><i class="icon-calender"></i></span>
                                </div>
                                @elseif($value['field_type'] == 'checkbox')
                                <div class="checkbox-list">
                                    @if(isset($data_fields[$value['id']]))
                                    @foreach($data_fields[$value['id']] as $keyData => $valueData )
                                        <label class="checkbox-inline">
                                            <div class="checkbox checkbox-success">
                                                <input type="checkbox" @if($valueData['display_value'] == $data[$value['field_name']]) checked @endif name="{{ $value['field_name'] }}[]" value="{{ $valueData['display_value'] }}" id="{{ $valueData['display_value'] }}" required>
                                                <label for="{{ $valueData['display_value'] }}">{{ $valueData['display_text'] }}</label>
                                            </div>
                                        </label>
                                        @endforeach
                                    @endif
                                </div>
                                @elseif($value['field_type'] == 'dropdown')
                                        <select name="{{ $value['field_name'] }}" class="form-control" required id="{{ $value['field_name'] }}">
                                            <option value=""> SELECT {{ strtoupper($value['field_label']) }} </option>

                                        @if(isset($data_fields[$value['id']]))
                                            @foreach($data_fields[$value['id']] as $keyData => $valueData)
                                            @php
                                                $selected = '';
                                            @endphp
                                            @if(isset($data))
                                                @if($data[$value['field_name']]== $valueData['display_value'])
                                                    @php
                                                        $selected = 'selected';
                                                    @endphp
                                                @endif
                                            @endif
                                            <option value="{{ $valueData['display_value'] }}" {{$selected}}> {{ $valueData['display_text'] }} </option>
                                            @endforeach
                                        @endif
                                        </select>
                                @elseif($value['field_type'] == 'textarea')
                                <textarea id="{{ $value['field_name'] }}" class="form-control" required name="{{ $value['field_name'] }}" placeholder="{{ $value['field_message'] }}">
                                @if(isset($data[$value['field_name']])){{$data[$value['field_name']]}}@endif
                                </textarea>
                                @else
                                <input type="{{ $value['field_type'] }}" id="{{ $value['field_name'] }}" value="@if(isset($data[$value['field_name']])){{$data[$value['field_name']]}}@endif" placeholder="{{ $value['field_message'] }}" required name="{{ $value['field_name'] }}" class="form-control">
                                @endif
                            </div>
                            @endforeach
                            @endif

                            <div class="col-md-4 form-group">
                                <label>Job Title</label>
                                <select id='jobtitle_id' name="jobtitle_id" class="form-control">
                                    <option value="0">Select Title</option>
                                    @foreach($job_titles as $title)
                                        @if(isset($data['jobtitle_id']) && $data['jobtitle_id'] == $title->id)
                                            <option selected value="{{$title->id}}">{{$title->title}}</option>
                                        @else
                                            <option value="{{$title->id}}">{{$title->title}}</option>
                                        @endif
                                    @endforeach
                                </select>
                            </div>
                        <!-- employee load  -->
                        <div class="col-md-4 form-group">
                            <label>Week Load</label>
                            <input type="number" id='load' name="load" class="form-control" value="@if(isset($data['load'])){{$data['load']}}@endif">
                        </div>

                        <!-- employee department  -->
                            <div class="col-md-4 form-group">
                                <label>Department Id</label>
                                <select id='department_id' name="department_id" class="form-control">
                                    <option value="0">Select Title</option>
                                    @foreach($departments as $title)
                                            <option value="{{$title->id}}" @if($data['department_id'] == $title->id) selected @endif>{{$title->department}}</option>
                                    @endforeach
                                </select>
                                <!-- <input type="text" id='department_id' name="department_id" value="{{$data['department_id']}}" class="form-control"> -->
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Employee Id</label>
                                <input type="text" id='employee_no' name="employee_no" class="form-control"  value="{{$new_emp_code}}">
                            </div>
                            <!-- qualification and occupation  -->
                            <div class="col-md-4 form-group">
                                @if(isset($masterSetups['Qualification']) && !empty($masterSetups['Qualification']))
                                    @php 
                                        $options  = explode('||',$masterSetups['Qualification']['fieldvalue']);
                                    @endphp
                                    <label>{{$masterSetups['Qualification']['fieldname']}}</label>
                                    <div class="dropdown">
                                    <input type="text" id="qualification-input" class="form-control" value="{{ isset($data['qualification']) ? $data['qualification'] : '' }}" name="qualification" autocomplete="off"/>
                                    <div class="dropdown-content" id="dropdown-content">
                                    @foreach($options as $key => $value)
                                        <label><input type="checkbox" value="{{$value}}">{{$value}}</label>
                                    @endforeach
                                    </div>
                                </div>
                                @else
                                    <label>Qualification</label>
                                    <input type="text" id='qualification' list="qualifications"  name="qualification" class="form-control" value="{{$data['qualification']}}">
                                    <datalist id="qualifications" height="100" style="height:100px">
                                    @if(!empty($qualificationList))
                                        @foreach($qualificationList as $key => $value)
                                            <option value="{{$value}}" {{ isset($data['qualification']) && $data['qualification'] == $value ? 'selected' : '' }}>{{$value}}</option>
                                        @endforeach
                                    @endif
                                @endif
                                </datalist>
                            </div>
                            <!-- end qulifications -->

                            <div class="col-md-4 form-group">
                            @if(isset($masterSetups['Occupations']) && !empty($masterSetups['Occupations']))
                                    @php 
                                        $options  = explode('||',$masterSetups['Occupations']['fieldvalue']);
                                    @endphp
                                    <label>{{$masterSetups['Occupations']['fieldname']}}</label>
                                    <select id="occupation" name="occupation" class="form-control">
                                    <option value="">Select any one</option>
                                    @foreach($options as $key => $value)
                                        <option value="{{$value}}"  {{ isset($data['occupation']) && $data['occupation'] == $value ? 'selected' : '' }}>{{$value}}</option>
                                    @endforeach
                                    </select>
                            @else
                                <label>Occupation</label>
                                <input type="text" id='occupation'  list="occupations" name="occupation" class="form-control" value="{{$data['occupation']}}" {{ $data['occupation'] ? $data['occupation'] : '' }}>
                                <datalist id="occupations" height="100" style="height:100px">
                                @if(!empty($occupationList))
                                    @foreach($occupationList as $key => $value)
                                        <option value="{{$value}}" {{ isset($data['occupation']) && $data['occupation'] == $value ? 'selected' : '' }}>{{$value}}</option>
                                    @endforeach
                                @endif 
                                </datalist>
                            @endif
                            </div>
                            <!-- end qualification and occupation -->
                            <!--  added on 01-08-2024 mmis -->
                            @php 
                                $radioArr = ['tds_deduction'=>'TDS Deduction','pf_deduction'=>'PF Deduction','pt_deduction'=>'PT Deduction'];
                                $textArr = ['pf_no'=>"PF No",'pan_no'=>"PAN No",'aadhar_no'=>"Aadhar No",'esic_no'=>"ESIC No",'uan_no'=>"UAN No"];
                            @endphp
                            @foreach($radioArr as $k => $v)
                            <div class="col-md-4 from-group">
                                @php $checked = $data[$k]; @endphp
                                <label for="{{$k}}">{{$v}}</label>
                                <div class="radio-list">
                                    <label class="radio-inline p-0">
                                        <div class="radio radio-success">
                                            <input type="radio" name="{{$k}}" id="{{$k}}" value="Y" {{ ($checked=='Y') ? 'Checked' : '' }}>
                                            <label for="Eligible">Eligible</label>
                                        </div>
                                    </label>
                                    <label class="radio-inline">
                                        <div class="radio radio-success">
                                            <input type="radio" name="{{$k}}" id="{{$k}}" value="N" {{ ($checked=='N') ? 'Checked' : '' }}>
                                            <label for="Non-Eligible">Non-Eligible</label>
                                        </div>
                                    </label>
                                </div>
                            </div>
                            @endforeach
                            @foreach($textArr as $k => $v)
                            <div class="col-md-4 form-group">
                                <label>{{$v}}</label>
                                <input type="text" id='{{$k}}' name="{{$k}}" class="form-control" value="{{$data[$k]}}">
                            </div>
                            @endforeach
                            <!--  added on 01-08-2024 mmis  -->
                            <div class="col-md-4 form-group">
                                <label>Joining Date</label>
                                <input type="date" id='joined_date' name="joined_date" value="{{ $data['joined_date'] ? date('Y-m-d',strtotime($data['joined_date'])) : '' }}" class="form-control">
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Probation Period From</label>
                                <input type="date" id='probation_period_from' name="probation_period_from"   value="{{ $data['probation_period_from'] ? date('Y-m-d',strtotime($data['probation_period_from'])) : '' }}" class="form-control">
                            </div>

                            <div class="col-md-4 form-group">
                                <label>Probation Period To</label>
                                <input type="date" id='probation_period_to'  value="{{ $data['probation_period_to'] ? date('Y-m-d',strtotime($data['probation_period_to'])) : '' }}" name="probation_period_to" class="form-control">
                            </div>

                            <div class="col-md-4 form-group">
                                <label>Terminated Date</label>
                                <input type="date" id='terminated_date' value="{{ $data['terminated_date'] ? date('Y-m-d',strtotime($data['terminated_date'])) : '' }}" name="terminated_date" class="form-control">
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Termination Reason</label>
                                <input type="text" id='termination_reason' value="{{$data['termination_reason']}}" name="termination_reason" class="form-control">
                            </div>

                            <div class="col-md-4 form-group">
                                <label>Notice From Date</label>
                                <input type="date" id='notice_fromdate' value="{{ $data['notice_fromdate'] ? date('Y-m-d',strtotime($data['notice_fromdate'])) : '' }}" name="notice_fromdate" class="form-control">
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Notice To Date</label>
                                <input type="date" id='notice_todate'  value="{{ $data['notice_todate'] ? date('Y-m-d',strtotime($data['notice_todate'])) : '' }}" name="notice_todate" class="form-control">
                            </div>

                            <div class="col-md-4 form-group">
                                <label>Notice Reason</label>
                                <input type="text" id='noticereason' value="{{$data['noticereason']}}" name="noticereason" class="form-control">
                            </div>
                            <div class="col-md-4 form-group">
                                <label>EL Opening Leave</label>
                                <input type="text" id='openingleave' value="{{$data['openingleave']}}" name="openingleave" class="form-control">
                            </div>

                            <div class="col-md-4 form-group">
                                <label>Relieving Date</label>
                                <input type="date" id='relieving_date' value="{{ $data['relieving_date'] ? date('Y-m-d',strtotime($data['relieving_date'])) : '' }}" name="relieving_date" class="form-control">
                            </div>

                            <div class="col-md-4 form-group">
                                <label>Relieving Reason</label>
                                <input type="text" id='relieving_reason' value="{{$data['relieving_reason']}}" name="relieving_reason" class="form-control">
                            </div>
                            <div class="col-md-4 form-group">
                                <label>CL Opening Leave</label>
                                <input type="text" id='CL_opening_leave' value="{{$data['CL_opening_leave']}}" name="CL_opening_leave" class="form-control">
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Total Experience</label>
                                <input type="text" id='total_experience' value="{{$data['total_experience']}}" name="total_experience" class="form-control">
                            </div>
                            <div class="col-md-12 form-group">
                                <h4>Report To</h4>
                            </div>

                            <div class="col-md-4 form-group">
                                <label>Supervisor / Subordinate</label>
                                <select id='supervisor_opt' name="supervisor_opt" class="form-control">

                                    @if(isset($data['supervisor_opt']) && $data['supervisor_opt'] == "Supervisor")
                                        <option value="Supervisor">Supervisor</option>
                                        <option value="Subordinate" selected>Subordinate</option>
                                    @else
                                        <option value="Supervisor">Supervisor</option>
                                        <option value="Subordinate" selected>Subordinate</option>
                                    @endif
                                </select>
                            </div>

                            <div class="col-md-4 form-group">
                                <label>Employee Name</label>
                                <select id='employee_id' name="employee_id" class="form-control">
                                    <option value="0">Select Employee</option>
                                    @foreach($employees as $title)
                                        @if(isset($data['employee_id']) && $data['employee_id'] == $title->id)
                                            <option selected value="{{$title->id}}">{{$title->first_name .' ' . $title->last_name}}</option>
                                        @else
                                            <option value="{{$title->id}}">{{$title->first_name .' ' . $title->last_name}}</option>
                                        @endif
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-4 form-group">
                                <label>Reporting Method</label>
                                <select id='reporting_method' name="reporting_method" class="form-control">
                                    @if(isset($data['reporting_method']) && $data['reporting_method'] == "Direct")
                                    <option value="Direct" selected>Direct</option>
                                    <option value="Indirect">In Direct</option>
                                    @else
                                        <option value="Direct" >Direct</option>
                                        <option value="Indirect" selected>In Direct</option>
                                    @endif
                                </select>
                            </div>

                            <div class="col-md-12 form-group">
                                <h4>Direct Deposit</h4>
                            </div>

                            <div class="col-md-4 form-group">
                                <label>Bank Name</label>
                                <input type="text" id='bank_name' value="{{$data['bank_name']}}" name="bank_name" class="form-control">
                            </div>

                            <div class="col-md-4 form-group">
                                <label>Branch Name</label>
                                <input type="text" id='branch_name' value="{{$data['branch_name']}}" name="branch_name" class="form-control">
                            </div>

                            <div class="col-md-4 form-group">
                                <label>Account</label>
                                <input type="text" id='account_no' value="{{$data['account_no']}}" name="account_no" class="form-control">
                            </div>
                            <div class="col-md-4 form-group">
                                <label>IFSC</label>
                                <input type="text" id='ifsc_code' value="{{$data['ifsc_code']}}" name="ifsc_code" class="form-control">
                            </div>

                            <div class="col-md-4 form-group">
                                <label>Amount</label>
                                <input type="text" id='amount' value="{{$data['amount']}}" name="amount" class="form-control">
                            </div>
                            <div class="col-md-4 form-group">
                            @if(isset($masterSetups['Bank Transfer Type']) && !empty($masterSetups['Bank Transfer Type']))
                                @php 
                                    $options  = explode('||',$masterSetups['Bank Transfer Type']['fieldvalue']);
                                @endphp
                                    <label>{{$masterSetups['Bank Transfer Type']['fieldname']}}</label>                       
                                    <select id='transfer_type' name="transfer_type" class="form-control">
                                        @foreach($options as $key => $value)
                                        <option value="{{$value}}" @if(isset($data['transfer_type']) && $data['transfer_type']==$value) Selected @endif>{{$value}}</option>
                                        @endforeach
                                    </select>
                            @else
                                <label>Transfer Type</label>
                                <select id='transfer_type' name="transfer_type" class="form-control">
                                    @if(isset($data['transfer_type']) && $data['transfer_type'] == "Direct")
                                        <option value="Direct" selected>Direct</option>
                                        <option value="Indirect">In Direct</option>
                                    @else
                                        <option value="Direct" >Direct</option>
                                        <option value="Indirect" selected>In Direct</option>
                                    @endif
                                </select>
                            @endif
                            </div>

                            <div class="col-md-12 form-group">
                                <h4>Off Days</h4>
                            </div>
                            <div class="col-md-1 form-group">
                                <label>Mon</label>
                                <input type="checkbox" id='monday' name="monday"  value="1" {{$data['monday'] ? 'checked' :''}} class="">
                            </div>
                            <div class="col-md-1 form-group">
                                <label>Tue</label>
                                <input type="checkbox" id='tuesday' name="tuesday" value="1" {{$data['tuesday'] ? 'checked' :''}} class="">
                            </div>
                            <div class="col-md-1 form-group">
                                <label>Wed</label>
                                <input type="checkbox" id='wednesday' name="wednesday" value="1" {{$data['wednesday'] ? 'checked' :''}} class="">
                            </div>
                            <div class="col-md-1 form-group">
                                <label>Thu</label>
                                <input type="checkbox" id='thursday' name="thursday" value="1" {{$data['thursday'] ? 'checked' :''}} class="">
                            </div>
                            <div class="col-md-1 form-group">
                                <label>Fri</label>
                                <input type="checkbox" id='friday' name="friday" value="1" {{$data['friday'] ? 'checked' :''}} class="">
                            </div>
                            <div class="col-md-3 form-group">
                                <label>Sat</label>
                                <input type="checkbox" id='saturday' name="saturday" value="1" {{$data['saturday'] ? 'checked' :''}} class="">
                            </div>
                            <!-- <div class="col-md-1 form-group">
                                <label>Sun</label>
                                <input type="checkbox" id='sunday' name="sunday" value="1"  {{$data['sunday'] ? 'checked' :''}} class="">
                            </div> -->

                            <div class="col-md-6 form-group">
                                <label>Monday In Date</label>
                                <input type="time" id='monday_in_date' value="{{ $data['monday_in_date'] ? date('H:i',strtotime($data['monday_in_date'])) : '' }}" name="monday_in_date" class="form-control">
                            </div>
                            <div class="col-md-6 form-group">
                                <label>Monday Out Date</label>
                                <input type="time" id='monday_out_date'  value="{{ $data['monday_out_date'] ? date('H:i',strtotime($data['monday_out_date'])) : '' }}" name="monday_out_date" class="form-control">
                            </div>

                            <div class="col-md-6 form-group">
                                <label>Tuesday In Date</label>
                                <input type="time" id='tuesday_in_date'  value="{{ $data['tuesday_in_date'] ? date('H:i',strtotime($data['tuesday_in_date'])) : '' }}" name="tuesday_in_date" class="form-control">
                            </div>
                            <div class="col-md-6 form-group">
                                <label>Tuesday Out Date</label>
                                <input type="time" id='tuesday_out_date'  value="{{ $data['tuesday_out_date'] ? date('H:i',strtotime($data['tuesday_out_date'])) : '' }}" name="tuesday_out_date" class="form-control">
                            </div>

                            <div class="col-md-6 form-group">
                                <label>Wednesday In Date</label>
                                <input type="time" id='wednesday_in_date'  value="{{ $data['wednesday_in_date'] ? date('H:i',strtotime($data['wednesday_in_date'])) : '' }}" name="wednesday_in_date" class="form-control">
                            </div>
                            <div class="col-md-6 form-group">
                                <label>Wednesday Out Date</label>
                                <input type="time" id='wednesday_out_date'  value="{{ $data['wednesday_out_date'] ? date('H:i',strtotime($data['wednesday_out_date'])) : '' }}" name="wednesday_out_date" class="form-control">
                            </div>

                            <div class="col-md-6 form-group">
                                <label>Thursday In Date</label>
                                <input type="time" id='thursday_in_date'  value="{{ $data['thursday_in_date'] ? date('H:i',strtotime($data['thursday_in_date'])) : '' }}" name="thursday_in_date" class="form-control">
                            </div>
                            <div class="col-md-6 form-group">
                                <label>Thursday Out Date</label>
                                <input type="time" id='thursday_out_date'  value="{{ $data['thursday_out_date'] ? date('H:i',strtotime($data['thursday_out_date'])) : '' }}" name="thursday_out_date" class="form-control">
                            </div>

                            <div class="col-md-6 form-group">
                                <label>Friday In Date</label>
                                <input type="time" id='friday_in_date' value="{{ $data['friday_in_date'] ? date('H:i',strtotime($data['friday_in_date'])) : '' }}" name="friday_in_date" class="form-control">
                            </div>
                            <div class="col-md-6 form-group">
                                <label>Friday Out Date</label>
                                <input type="time" id='friday_out_date' value="{{ $data['friday_out_date'] ? date('H:i',strtotime($data['friday_out_date'])) : '' }}" name="friday_out_date" class="form-control">
                            </div>

                            <div class="col-md-6 form-group">
                                <label>Saturday In Date</label>
                                <input type="time" id='saturday_in_date'  value="{{ $data['saturday_in_date'] ? date('H:i',strtotime($data['saturday_in_date'])) : '' }}" name="saturday_in_date" class="form-control">
                            </div>
                            <div class="col-md-6 form-group">
                                <label>Saturday Out Date</label>
                                <input type="time" id='saturday_out_date'   value="{{ $data['saturday_out_date'] ? date('H:i',strtotime($data['saturday_out_date'])) : '' }}" name="saturday_out_date" class="form-control">
                            </div>

                            <!-- <div class="col-md-6 form-group">
                                <label>Sunday In Date</label>
                                <input type="time" id='sunday_in_date'  value="{{ $data['sunday_in_date'] ? date('H:i',strtotime($data['sunday_in_date'])) : '' }}" name="sunday_in_date" class="form-control">
                            </div> -->
                            <!-- <div class="col-md-6 form-group">
                                <label>Sunday Out Date</label>
                                <input type="time" id='sunday_out_date'  value="{{ $data['sunday_out_date'] ? date('H:i',strtotime($data['sunday_out_date'])) : '' }}" name="sunday_out_date" class="form-control">
                            </div> -->
                            @if(in_array(session()->get('user_profile_name'),["Admin","Super Admin"]))              
                            <div class="col-md-12 form-group mt-2">
                                <center>
                                    <input type="submit" name="submit" value="Update" class="btn btn-success" >
                                </center>
                            </div>
                            @endif
                        </div>
                    </form>
                    </div>
                    <!-- tab 1 ends  -->
                    <!-- tab 2 start  -->
                    <div class="tab-pane p-3" id="section-linemove-2" role="tabpanel">
                        @include('user.documentModel')
                    </div>
                    <!-- tab 2 ends  -->
                      <!-- tab 4 start  -->
                      <div class="tab-pane p-3" id="section-linemove-3" role="tabpanel">
                        @include('payroll.employee_salary_structure.salaryDetails')
                    </div>
                    <!-- tab 4 ends  -->
                     <!-- tab 4 start  -->
                    <div class="tab-pane p-3" id="section-linemove-4" role="tabpanel">
                        @include('lms.triz_skills')
                    </div>
                    <!-- tab 4 ends  -->
                    <!-- tab 5 start  -->
                    <div class="tab-pane p-3" id="section-linemove-5" role="tabpanel">
                        @include('user.contactDetails')
                    </div>
                    <!-- tab 5 ends  -->
                </div>
                <!-- tabs ends  -->
                </div>
            </div>
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
</script>
<script src="../../../plugins/bower_components/dropify/dist/js/dropify.min.js"></script>
    <script>
    $(document).ready(function() {
        var val1 = $.trim($("#user_profile_id").find("option:selected").text());

        if(val1 == 'Teacher' || val1 == 'TEACHER')
        {
            $("#total_lecture_div").css("display","block");
        }
        else
        {
            $("#total_lecture_div").css("display","none");
        }

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
    </script>
    <script>
        $("#user_profile_id").on( "change", function( event ) {
            var val1 = $.trim($("#user_profile_id").find("option:selected").text());

            if(val1 == 'Teacher' || val1 == 'TEACHER')
            {
                $("#total_lecture_div").css("display","block");
            }
            else
            {
                $("#total_lecture_div").css("display","none");
            }
        });

        function getUsername(){
            var first_name = document.getElementById("first_name").value;
            var last_name = document.getElementById("last_name").value;
            var username = first_name.toLowerCase()+"_"+last_name.toLowerCase();
            document.getElementById("user_name").value = username;
        }

    $(document).ready(function() {
        $('#qualification-input').on('click', function() {
            $('#dropdown-content').toggle();
        });

        $('.dropdown-content input[type="checkbox"]').on('change', function() {
            var selectedFruits = [];
            $('.dropdown-content input[type="checkbox"]:checked').each(function() {
                selectedFruits.push($(this).val());
            });
            $('#qualification-input').val(selectedFruits.join(', '));
        });

        // Hide dropdown when clicking outside
        $(document).on('click', function(event) {
            if (!$(event.target).closest('.dropdown').length) {
                $('#dropdown-content').hide();
            }
        });
    });

    </script>

@include('includes.footer')
@endsection
