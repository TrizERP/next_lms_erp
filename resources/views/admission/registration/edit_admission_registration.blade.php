{{--@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')--}}
@extends('layout')
@section('container')
@php
$editData = array();
    if(isset($data['editData']))
    {
        $student_data = $editData = $data['editData'];
    }
    $divisionRequired = $genderRequired = '';
    if(session()->get('sub_institute_id')==254){
        $divisionRequired = 'required';
        $genderRequired = 'required';
    }
@endphp
<style type="text/css">
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
                <h4 class="page-title">Admission Confirmation</h4> </div>
        </div>
        <div class="card">
            <div class="row">
                <div class="col-lg-9 col-sm-8 col-md-8 col-xs-12">
                    <a href="{{route('admission_follow_up.index')}}?enquiry_id={{$editData['id']}}&module=registration">Admission Follow Up</a>
                </div>
            </div>
            @if ($message = Session::get('success'))
            <div class="alert alert-success alert-block">
                <button type="button" class="close" data-dismiss="alert">×</button>
                <strong>{{ $message }}</strong>
            </div>
            @endif

            @php 
                $class="hide";
                $oldAdmissionInstitutes = [47,48,49,62,69,72,195,201,202,203,204,233,254];
                if(in_array(Session::get('sub_institute_id'),$oldAdmissionInstitutes))
                {
                    $class="show";
                }

                $unmandatoryFileds = ['category','previous_school_name','previous_standard','send_sms','remarks','source_of_enquiry','followup_date','register_number','mother_name','mother_mobile_number','aadhar_number','place_of_birth','amount','blood_group','payment_mode','date_of_payment','religion','cast','aadhar_number'];
            @endphp
            <div class="row">
                <div class="col-lg-12 col-sm-12 col-xs-12">
                    <form action="{{ route('admission_confirmation.update', $editData['id']) }}" enctype="multipart/form-data" method="post">
                    {{ method_field("PUT") }}
                    @csrf
                        <div class="row">
                            <div class="col-md-3 form-group">
                                <label>Enquiry Number </label>
                                <input type="hidden" readonly="readonly" id='enquiry_id' @if(isset($editData['enquiry_id'])) value="{{$editData['enquiry_id']}}" @endif required name="enquiry_id" class="form-control">
                                <input type="text" readonly="readonly" id='enquiry_no' @if(isset($editData['enquiry_no'])) value="{{$editData['enquiry_no']}}" @endif required name="enquiry_no" class="form-control">
                            </div>
                            <div class="col-md-3 form-group">
                                <label>Student Name </label>
                                <input type="text" id='first_name' required name='first_name' @if(isset($editData['first_name'])) value="{{$editData['first_name']}}" @endif  class="form-control">
                            </div>
                            @if (Session::get('sub_institute_id') != '198')
                            <div class="col-md-3 form-group">
                                <label>Father Name </label>
                                <input type="text" id='middle_name' required name='middle_name' @if(isset($editData['middle_name'])) value="{{$editData['middle_name']}}" @endif  class="form-control">
                            </div>
                            @endif
                            <div class="col-md-3 form-group">
                                <label>Surname </label>
                                <input type="text" id='last_name' required name='last_name' @if(isset($editData['last_name'])) value="{{$editData['last_name']}}" @endif  class="form-control">
                            </div>
                            <div class="col-md-3 form-group">
                                <label>Gender <span style="color: red;">*</span></label>
                                <div class="radio radio-success">
                                    <input type="radio" id="male" name="gender" @if(isset($editData['gender']) && $editData['gender'] == 'M') checked="checked" @endif value="M" {{$genderRequired}}>
                                    <label for="male"> Male </label>
                                </div>
                                <div class="radio radio-success">
                                    <input type="radio" id="female" name="gender" @if(isset($editData['gender']) && $editData['gender'] == 'F') checked="checked" @endif value="F" {{$genderRequired}}>
                                    <label for="female"> Female </label>
                                </div>
                            </div>

                            <div class="col-md-3 form-group">
                                <label>Mobile </label>
                                <input type="text" id='mobile' name='mobile' @if(isset($editData['mobile'])) value="{{$editData['mobile']}}" @endif   class="form-control" required>
                            </div>
                            <div class="col-md-3 form-group">
                                <label>Email </label>
                                <input type="email" id='email' name='email' @if(isset($editData['email'])) value="{{$editData['email']}}" @endif   class="form-control" required>
                            </div>
                            <div class="col-md-3 form-group">
                                <label>Date of Birth </label>
                                <input type="text"   @if(isset($editData['date_of_birth'])) value="{{$editData['date_of_birth']}}" @endif onchange="calculate_age(this.value);" id='date_of_birth' name='date_of_birth'  class="form-control mydatepicker" required>
                            </div>
                            <div class="col-md-3 form-group">
                                <label>Age </label>
                                <input type="text" id='age' name='age' @if(isset($editData['age'])) value="{{$editData['age']}}" @endif  class="form-control" required>
                            </div>
                            <div class="col-md-3 form-group">
                                <label>Address </label>
                                <textarea id='address' name='address'  class="form-control">@if(isset($editData['address'])){{$editData['address']}}@endif</textarea>
                            </div>
                            <div class="col-md-3 form-group {{$class}} previous_school_nameDiv">
                                <label>Previous School Name </label>
                                <input type="text" id='previous_school_name' name='previous_school_name' @if(isset($editData['previous_school_name'])) value="{{$editData['previous_school_name']}}" @endif  class="form-control">
                            </div>
                            <div class="col-md-3 form-group {{$class}} previous_standardDiv">
                                <label>Previous Standard </label>
                                <select id='previous_standard' name="previous_standard" class="form-control">
                                    <option value=""> Select Standard </option>
                                    @foreach($data['standard'] as $key=>$previous)
                                <option value="{{$previous['id']}}" @if(isset($editData['previous_standard']) && $previous['id']==$editData['previous_standard']) Selected @endif> {{$previous['name']}}</option>
                                @endforeach
                                </select>
                            </div>

                            <div class="col-md-3 form-group">
                                <label>Admission Standard </label>
                                <select id='admission_standard' name="admission_standard"  class="form-control" onchange="getDivision(this.value);">
                                <option value=""> Select Standard </option>
                                    @foreach($data['standard'] as $key => $value)
                                        <option value="{{$value['id']}}" @if(isset($editData['admission_standard'])) @if($editData['admission_standard'] == $value['id']) selected="selected" @endif @endif> {{$value['name']}} </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-3 form-group {{$class}} source_of_enquiryDiv">
                                <label>Source of enquiry </label>
                                <input type="text" id='source_of_enquiry' name='source_of_enquiry' @if(isset($editData['source_of_enquiry'])) value="{{$editData['source_of_enquiry']}}" @endif  class="form-control">
                            </div>
                            <div class="col-md-3 form-group  {{$class}} remarksDiv">
                                <label>Remarks </label>
                                <input type="text" id='remarks' name='remarks' @if(isset($editData['remarks'])) value="{{$editData['remarks']}}" @endif  name="remarks" class="form-control">
                            </div>
                            <div class="col-md-3 form-group {{$class}} followup_dateDiv">
                                <label>Followup Date </label>
                                <input type="text" id='followup_date' name='followup_date' @if(isset($editData['followup_date'])) value="{{$editData['followup_date']}}" @endif  name="followup_date" class="form-control mydatepicker" autocomplete="off">
                            </div>
                            <div class="col-md-3 form-group {{$class}} register_numberDiv">
                                <label>Register Number/Application Number</label>
                                <input type="text" id='register_number' @if(isset($editData['register_number'])) value="{{$editData['register_number']}}" @endif  name="register_number" class="form-control">
                            </div>
                            <div class="col-md-3 {{$class}} mother_nameDiv">
                                <label>Mother Name </label>
                                <input type="text" id='mother_name' @if(isset($editData['mother_name'])) value="{{$editData['mother_name']}}" @endif  name="mother_name" class="form-control">
                            </div>
                            @if(Session::get('sub_institute_id') == '46')
                                <div class="col-md-3 form-group {{$class}} mother_mobile_numberDiv">
                                    <label>Mother Mobile Number </label>
                                    <input type="text" id='mother_mobile_number' @if(isset($editData['mobile_number_mother'])) value="{{$editData['mobile_number_mother']}}" @endif name="mother_mobile_number" class="form-control">
                                </div>
                            @else
                                <div class="col-md-3 form-group {{$class}} mother_mobile_numberDiv">
                                    <label>Mother Mobile Number </label>
                                    <input type="text" id='mother_mobile_number' @if(isset($editData['mother_mobile_number'])) value="{{$editData['mother_mobile_number']}}" @endif name="mother_mobile_number" class="form-control">
                                </div>
                            @endif

                            <div class="col-md-3 form-group {{$class}} aadhar_numberDiv">
                                <label>Aadhar Number </label>
                                <input type="text"  id='aadhar_number' @if(isset($editData['aadhar_number'])) value="{{$editData['aadhar_number']}}" @endif  name="aadhar_number" class="form-control">
                            </div>
                            <div class="col-md-3 form-group">
                                <label>Status </label>
                                <select id='status'  name="status" class="form-control">
                                    <option value=""> Select Status </option>
                                    <option value="OPEN" @if(isset($editData['status'])) @if($editData['status'] == 'OPEN') selected="selected" @endif @endif> Open </option>
                                    <option value="CLOSE" @if(isset($editData['status'])) @if($editData['status'] == 'CLOSE') selected="selected" @endif @endif> Close </option>
                                </select>
                            </div>
                            <div class="col-md-3 form-group  {{$class}} place_of_birthDiv">
                                <label>Place of Birth </label>
                                <input type="text" id='place_of_birth' @if(isset($editData['place_of_birth'])) value="{{$editData['place_of_birth']}}" @endif  name="place_of_birth" class="form-control">
                            </div>
                            <div class="col-md-3 form-group">
                                <label>Student Quota </label>
                                <select id='student_quota' ="" name="student_quota" class="form-control">
                                <!-- <option value=""> Select Quota </option> -->
                                @if(isset($data['category']))
                                    <p style="display: none;">{{$ids = DB::table('student_quota')->where(['sub_institute_id'=>Session::get('sub_institute_id'),'title'=>'General'])->get()}}</p>
                                         <option value="@foreach($ids as $id){{$id->id}}@endforeach" >General</option>

                                        @foreach($data['category'] as $key => $value)
                                            <option value="{{$value['id']}}" @if(isset($editData['student_quota'])) @if($editData['student_quota'] == $value['id']) selected="selected" @endif @endif>{{$value['title']}}</option>
                                        @endforeach
                                    @endif

                                </select>
                            </div>
                            <div class="col-md-3 form-group">
                                <label>Division </label>
                                <select id='admission_division' name="admission_division" class="form-control" {{$divisionRequired}}>
                                <!-- <option value=""> Select Division </option> -->
                                    @if(isset($data['division']))
                                        @foreach($data['division'] as $key => $value)
                                            <option value="{{$value['id']}}" @if(isset($editData['admission_division'])) @if($editData['admission_division'] == $value['id']) selected="selected" @endif @endif>{{$value['name']}}</option>
                                        @endforeach
                                    @endif
                                </select>
                                <span id="division_error_span"></span>
                            </div>
                            
                            @php
                            if (Session::get('sub_institute_id') == '47')
                            {
                                $display = 'readonly';
                                $value = $data['new_enrollment_no'];
                            }
                            else{
                                $display = '';
                                $value = $data['new_enrollment_no'];
                            }
                            @endphp
                            <div class="col-md-3 form-group">
                                <label>Enrollment No/GR No</label>
                                <input type="text" id='enrollment_no'
                                @if(isset($value))
                                value="{{$value}}"
                                @endif  name="enrollment_no" class="form-control" {{$display}}>
                            </div>
                            <div class="col-md-3 form-group {{$class}} amountDiv">
                                <label>Amount </label>
                                <input type="text" id='amount' @if(isset($editData['amount'])) value="{{$editData['amount']}}" @endif  name="amount" class="form-control">
                            </div>

                            <div class="col-md-3 form-group {{$class}} blood_groupDiv">
                                <label>Blood Group</label>
                                <select id='blood_group' name="blood_group" class="form-control">
                                    <option value="">Select</option>
                                    @if(isset($data['bloodgroup_data']))
                                        @foreach($data['bloodgroup_data'] as $key => $value)
                                            <option @if($editData['blood_group'] == $value['id'] ) selected="selected" @endif value="{{ $value['id'] }}">{{ $value['bloodgroup'] }}</option>
                                        @endforeach
                                    @endif
                                </select>
                            </div>
                            
                            <div class="col-md-3 form-group {{$class}} payment_modeDiv">
                                <label>Payment Mode </label>
                                <select id='payment_mode' name="payment_mode" onchange="displayBank(this.value);" class="form-control" >
                                    <option value=""> Select Payment Mode </option>
                                    <option value="cash" @if(isset($editData['payment_mode'])) @if($editData['payment_mode'] == 'cash') selected="selected" @endif @endif> Cash </option>
                                    <option value="cheque" @if(isset($editData['payment_mode'])) @if($editData['payment_mode'] == 'cheque') selected="selected" @endif @endif> Cheque </option>
                                    <option value="dd" @if(isset($editData['payment_mode'])) @if($editData['payment_mode'] == 'dd') selected="selected" @endif @endif> DD </option>
                                </select>
                            </div>
                            <div id="bankdetails" class="col-md-12" style="display: none;">
                                <div class="row">
                                    <div class="col-md-3 form-group">
                                        <label>Bank Name </label>
                                        <input type="text" id='bank_name' @if(isset($editData['bank_name'])) value="{{$editData['bank_name']}}" @endif name="bank_name" class="form-control">
                                    </div>

                                    <div class="col-md-3 form-group">
                                        <label>Bank Branch</label>
                                        <input type="text" id='bank_branch' @if(isset($editData['bank_branch'])) value="{{$editData['bank_branch']}}" @endif name="bank_branch" class="form-control">
                                    </div>

                                    <div class="col-md-3 form-group">
                                        <label>Cheque Number </label>
                                        <input type="text" id='cheque_no' @if(isset($editData['cheque_no'])) value="{{$editData['cheque_no']}}" @endif name="cheque_no" class="form-control">
                                    </div>

                                    <div class="col-md-3 form-group">
                                        <label>Cheque Date </label>
                                        <input type="text" id='cheque_date' @if(isset($editData['cheque_date'])) value="{{$editData['cheque_date']}}" @endif name="cheque_date" class="form-control mydatepicker" autocomplete="off">
                                    </div>
                                </div>
                            </div>

                            <!--<div class="col-md-3 form-group">
                                <label>Blood Group </label>
                                <select id='blood_group'  name="blood_group" class="form-control">
                                    <option value=""> Select Blood Group </option>
                                    <option value="a+" @if(isset($editData['blood_group'])) @if($editData['blood_group'] == 'a+') selected="selected" @endif @endif> A+ </option>
                                    <option value="a-" @if(isset($editData['blood_group'])) @if($editData['blood_group'] == 'a-') selected="selected" @endif @endif> A- </option>
                                    <option value="b+" @if(isset($editData['blood_group'])) @if($editData['blood_group'] == 'b+') selected="selected" @endif @endif> B+ </option>
                                    <option value="b-" @if(isset($editData['blood_group'])) @if($editData['blood_group'] == 'b-') selected="selected" @endif @endif> B- </option>
                                    <option value="ab+" @if(isset($editData['blood_group'])) @if($editData['blood_group'] == 'ab+') selected="selected" @endif @endif> AB+ </option>
                                    <option value="ab-" @if(isset($editData['blood_group'])) @if($editData['blood_group'] == 'ab-') selected="selected" @endif @endif> AB- </option>
                                    <option value="o+" @if(isset($editData['blood_group'])) @if($editData['blood_group'] == 'o+') selected="selected" @endif @endif> O+ </option>
                                    <option value="o-" @if(isset($editData['blood_group'])) @if($editData['blood_group'] == 'o-') selected="selected" @endif @endif> O- </option>
                                </select>
                            </div>-->
                            <div class="col-md-3 form-group {{$class}} date_of_paymentDiv">
                                <label>Date of Payment </label>
                                <input type="text" id='date_of_payment' @if(isset($editData['date_of_payment'])) value="{{$editData['date_of_payment']}}" @endif  name="date_of_payment" class="form-control mydatepicker" autocomplete="off">
                            </div>
                            <div class="col-md-3 form-group {{$class}} admission_dateDiv">
                                <label>Date of Admission </label>
                                <input type="text" id='admission_date' @if(isset($editData['admission_date'])) value="{{$editData['admission_date']}}" @endif  name="admission_date" class="form-control mydatepicker" autocomplete="off">
                            </div>
                            <div class="col-md-3 form-group">
                                <label>Admission Confirmation </label>
                                <select id='admission_status'  name="admission_status" class="form-control">
                                    <option value=""> Select Status </option>
                                    <option value="YES" @if(isset($editData['admission_status'])) @if($editData['admission_status'] == 'YES') selected="selected" @endif @endif> YES </option>
                                    <option value="NO" @if(isset($editData['admission_status'])) @if($editData['admission_status'] == 'NO') selected="selected" @endif @endif> NO </option>
                                </select>
                            </div>
                            @if(isset($data['custom_fields']))
                            @foreach($data['custom_fields'] as $key => $value)
                                @if(!in_array($value['field_name'],$unmandatoryFileds))
                                <div class="col-md-4 form-group">
                                    <label>{{ $value['field_label'] }}</label>
                                    @if($value['field_type'] == 'file')
                                    <input type="{{ $value['field_type'] }}" id="input-file-now"  @if($value['required'] == 1)  required @endif data-default-file="/storage/student/{{ $student_data[$value['field_name']] }}" name="{{ $value['field_name'] }}" class="dropify">
                                    <a href="/storage/student/{{ $student_data[$value['field_name']] }}" download="{{$student_data->username.'_'.$student_data[$value['field_name']]}}"><label>Download</label></a>
                                    @elseif($value['field_type'] == 'date')
                                    <div class="input-daterange input-group" >
                                    <input type="text" class="form-control mydatepicker" placeholder="dd/mm/yyyy" autocomplete="off" id="{{ $value['field_name'] }}" @if($value['required'] == 1)  required @endif value="{{ $student_data[$value['field_name']] }}" name="{{ $value['field_name'] }}" class="form-control"><span class="input-group-addon"><i class="icon-calender"></i></span>
                                    </div>
                                    @elseif($value['field_type'] == 'checkbox')
                                    <div class="checkbox-list">
                                        @if(isset($data['data_fields'][$value['id']]))
                                        @foreach($data['data_fields'][$value['id']] as $keyData => $valueData )
                                            <label class="checkbox-inline">
                                                <div class="checkbox checkbox-success">
                                                    <input type="checkbox" @if($valueData['display_value'] == $student_data[$value['field_name']]) checked @endif name="{{ $value['field_name'] }}[]" value="{{ $valueData['display_value'] }}"  id="{{ $valueData['display_value'] }}" @if($value['required'] == 1)  required @endif>
                                                    <label for="{{ $valueData['display_value'] }}">{{ $valueData['display_text'] }}</label>
                                                </div>
                                            </label>
                                            @endforeach
                                        @endif
                                    </div>
                                    @elseif($value['field_type'] == 'dropdown')

                                            <!-- <div class="custom-select"> -->
                                            <select name="{{ $value['field_name'] }}" class="form-control" @if($value['required'] == 1)  required @endif id="{{ $value['field_name'] }}">
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
                                    <textarea id="{{ $value['field_name'] }}" class="form-control" @if($value['required'] == 1)  required @endif name="{{ $value['field_name'] }}">
                                    {{ $student_data[$value['field_name']] }}
                                    </textarea>
                                    @else
                                    <input type="{{ $value['field_type'] }}" id="{{ $value['field_name'] }}" placeholder="{{ $value['field_message'] }}" value="{{ $student_data[$value['field_name']] }}" @if($value['required'] == 1)  required @endif name="{{ $value['field_name'] }}" class="form-control">
                                    @endif
                                </div>
                                @endif
                            @endforeach
                            @endif
                            <!-- 2024-08-27 add religion and cast Hills -->
                            @if(session()->get('sub_institute_id')!=257)
                            <div class="col-md-4 form-group text-left {{$class}} religionDiv">
                                <label>Student Religion</label>
                                <select id='religion' name="religion" class="form-control">
                                    <option value="">--Select--</option>  
                                    @if(isset($data['religion_data']))
                                        @foreach($data['religion_data'] as $key => $value)
                                            <option value="{{ $value['id'] }}" @if(isset($editData['religion']) && $editData['religion']== $value['id']) Selected @endif>{{ $value['religion_name'] }}</option>
                                        @endforeach
                                    @endif                                                  
                                </select>
                            </div>
                            
                            <div class="col-md-4 form-group text-left {{$class}} castDiv">
                                <label>Student Caste</label>
                                <select id='cast' name="cast" class="form-control">
                                    <option value="">--Select--</option>  
                                    @if(isset($data['caste_data']))
                                        @foreach($data['caste_data'] as $key => $value)
                                            <option value="{{ $value['id'] }}" @if(isset($editData['cast']) && $editData['cast']== $value['id']) Selected @endif>{{ $value['caste_name'] }}</option>
                                        @endforeach
                                    @endif                                                  
                                </select>
                            </div>
                            @endif
                            <!-- 2024-08-27 end religion and cast Hills -->
                            <div class="col-md-12 form-group">
                                <center>
                                    <input type="submit" name="submit" value="Update" class="btn btn-success division_alert" >
                                </center>
                            </div>
                        </div>
                    </form>
                    @if(isset($editData['mobile']))
                    <form action="{{ route('admission_student') }}" enctype="multipart/form-data" method="post">
                        {{ method_field("POST") }}
                        @csrf
                        <div class="row">
                            <div class="col-md-12 form-group">
                                <center>
                                    <input type="hidden" name="id" value="{{$editData['id']}}">
                                    @if(isset($data['display_save_student']))

                                        @if($data['display_save_student'] == 1 && $editData['registration_enquiry_id'] != '')
                                            <input type="submit" name="submit" value="Add Student" class="btn btn-success">

                                            @if (Session::get('sub_institute_id') == '47')
                                            <span class="d-inline-block mb-2" tabindex="0" data-toggle="tooltip" title="Enrollment Numbers will be assigned automatically while saving new students. It may differ from the current displayed Enrollment Number.">
                                                <button class="btn btn-danger" style="pointer-events: none;" type="button" disabled="">Note</button>
                                            </span>
                                            @endif
                                        @endif
                                    @endif
                                </center>
                            </div>
                        </div>
                    </form>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

@include('includes.footerJs')
<script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.3/js/select2.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
<script type="text/javascript">
    function calculate_age(dateString) {
        var today = new Date();
        var birthDate = new Date(dateString);

        var age = today.getFullYear() - birthDate.getFullYear();
        var m = today.getMonth() - birthDate.getMonth();
          if (m < 0 || (m === 0 && today.getDate() < birthDate.getDate())) {
            age--;
          }
        document.getElementById('age').value = age;
    }

    function displayBank(x) {
        if(x != "cash")
        {
            document.getElementById('bankdetails').style.display = 'block';
        }else{
            document.getElementById('bankdetails').style.display = 'none';
        }
    }

    

    function getDivision(standard_id) {
        var path = "{{ route('ajax_getDivision') }}";
        $('#admission_division').find('option').remove().end().append('<option value=""> Select Division </option>').val('');
        $.ajax({
            url:path,
            data:'standard_id='+standard_id,
            success:function(result){
                for(var i=0;i < result.length ;i++)
                {
                    $("#admission_division").append($("<option></option>").val(result[i]['id']).html(result[i]['name']));
                }
            }
        });
    }

</script>
<script>
$('document').ready(function(){
    //START Check Division Capacity Validation - 18/11/2021
    var division_check = true;
    document.getElementById('admission_division').addEventListener('change', function(){
        var selected_division_id = $("#admission_division").val();
        var selected_std_id = $("#admission_standard").val();
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

    $('.division_alert').on('click', function(){

        if(division_check == true)
        {
            return true;
        }else{
            alert('Please select other division.');
            return false;
        }

    });
});

@if(isset($data['custom_fields']))
        @foreach($data['custom_fields'] as $key => $value)
            @if(in_array($value['field_name'],$unmandatoryFileds))
                var fieldName = "{{$value['field_name']}}";
                var fieldLabel = "{{$value['field_label']}}";
                // alert($fieldName);

                $('.'+fieldName+'Div').removeClass('hide');
                $('.' + fieldName + 'Div').addClass('show').find('label').text(fieldLabel);
                @if($value['required']==1)
                    $('#'+fieldName).prop('required',true);
                    alert("{{$value['required']}}");
                @endif
            @endif 
        @endforeach
        @endif
</script>
@include('includes.footer')
@endsection