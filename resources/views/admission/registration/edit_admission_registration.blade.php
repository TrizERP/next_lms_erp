@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

@php
$editData = array();
    if(isset($data['editData']))
    {
        $student_data = $editData = $data['editData'];
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
                                <input type="text" id='first_name' name='first_name' @if(isset($editData['first_name'])) value="{{$editData['first_name']}}" @endif required class="form-control">
                            </div>
                            @if (Session::get('sub_institute_id') != '198') 
                            <div class="col-md-3 form-group">
                                <label>Father Name </label>
                                <input type="text" id='middle_name' name='middle_name' @if(isset($editData['middle_name'])) value="{{$editData['middle_name']}}" @endif required class="form-control">
                            </div>
                            @endif
                            <div class="col-md-3 form-group">
                                <label>Surname </label>
                                <input type="text" id='last_name' name='last_name' @if(isset($editData['last_name'])) value="{{$editData['last_name']}}" @endif required class="form-control">
                            </div>
                            <div class="col-md-3 form-group">
                                <label>Gender </label>
                                <div class="radio radio-success">
                                    <input type="radio" id='male' @if(isset($editData['gender'])) @if($editData['gender'] == 'M') checked="checked" @endif @endif value="M" >
                                    <label for="male"> Male </label>
                                </div>
                                <div class="radio radio-success">
                                    <input type="radio" id='female' @if(isset($editData['gender'])) @if($editData['gender'] == 'F') checked="checked" @endif @endif value="F" >
                                    <label for="female"> Female </label>
                                </div>
                            </div>
                            <div class="col-md-3 form-group">
                                <label>Mobile </label>
                                <input type="text" id='mobile' name='mobile' @if(isset($editData['mobile'])) value="{{$editData['mobile']}}" @endif required  class="form-control">
                            </div>
                            <div class="col-md-3 form-group">
                                <label>Email </label>
                                <input type="email" id='email' name='email' @if(isset($editData['email'])) value="{{$editData['email']}}" @endif required  class="form-control">
                            </div>
                            <div class="col-md-3 form-group">
                                <label>Date of Birth </label>
                                <input type="text"   @if(isset($editData['date_of_birth'])) value="{{$editData['date_of_birth']}}" @endif onchange="calculate_age(this.value);" id='date_of_birth' name='date_of_birth' required class="form-control mydatepicker">
                            </div>
                            <div class="col-md-3 form-group">
                                <label>Age </label>
                                <input type="text" id='age' name='age' @if(isset($editData['age'])) value="{{$editData['age']}}" @endif required class="form-control">
                            </div>
                            <div class="col-md-3 form-group">
                                <label>Address </label>
                                <textarea id='address' name='address' required class="form-control">@if(isset($editData['address'])){{$editData['address']}}@endif</textarea>
                            </div>
                            <div class="col-md-3 form-group">
                                <label>Previous School Name </label>
                                <input type="text" id='previous_school_name' name='previous_school_name' @if(isset($editData['previous_school_name'])) value="{{$editData['previous_school_name']}}" @endif required  class="form-control">
                            </div>
                            <div class="col-md-3 form-group">
                                <label>Previous Standard </label>
                                <select id='previous_standard' required name="previous_standard" class="form-control">
                                    <option value=""> Select Standard </option>
                                    <option value="NA" @if(isset($editData['previous_standard'])) @if($editData['previous_standard'] == 'NA') selected="selected" @endif @endif> NA </option>
                                    <option value="NURSERY" @if(isset($editData['previous_standard'])) @if($editData['previous_standard'] == 'NURSERY') selected="selected" @endif @endif> Nursery </option>
                                    <option value="JRKG" @if(isset($editData['previous_standard'])) @if($editData['previous_standard'] == 'JRKG') selected="selected" @endif @endif> Jrkg </option>
                                    <option value="SRKG" @if(isset($editData['previous_standard'])) @if($editData['previous_standard'] == 'SRKG') selected="selected" @endif @endif> Srkg </option>
                                    <option value="1" @if(isset($editData['previous_standard'])) @if($editData['previous_standard'] == '1') selected="selected" @endif @endif> 1 </option>
                                    <option value="2" @if(isset($editData['previous_standard'])) @if($editData['previous_standard'] == '2') selected="selected" @endif @endif> 2 </option>
                                    <option value="3" @if(isset($editData['previous_standard'])) @if($editData['previous_standard'] == '3') selected="selected" @endif @endif> 3 </option>
                                    <option value="4" @if(isset($editData['previous_standard'])) @if($editData['previous_standard'] == '4') selected="selected" @endif @endif> 4 </option>
                                    <option value="5" @if(isset($editData['previous_standard'])) @if($editData['previous_standard'] == '5') selected="selected" @endif @endif> 5 </option>
                                    <option value="6" @if(isset($editData['previous_standard'])) @if($editData['previous_standard'] == '6') selected="selected" @endif @endif> 6 </option>
                                    <option value="7" @if(isset($editData['previous_standard'])) @if($editData['previous_standard'] == '7') selected="selected" @endif @endif> 7 </option>
                                    <option value="8" @if(isset($editData['previous_standard'])) @if($editData['previous_standard'] == '8') selected="selected" @endif @endif> 8 </option>
                                    <option value="9" @if(isset($editData['previous_standard'])) @if($editData['previous_standard'] == '9') selected="selected" @endif @endif> 9 </option>
                                    <option value="10" @if(isset($editData['previous_standard'])) @if($editData['previous_standard'] == '10') selected="selected" @endif @endif> 10 </option>
                                    <option value="11COM" @if(isset($editData['previous_standard'])) @if($editData['previous_standard'] == '11COM') selected="selected" @endif @endif> 11 COM </option>
                                    <option value="11ART" @if(isset($editData['previous_standard'])) @if($editData['previous_standard'] == '11ART') selected="selected" @endif @endif> 11 ART </option>
                                    <option value="11SCI" @if(isset($editData['previous_standard'])) @if($editData['previous_standard'] == '11SCI') selected="selected" @endif @endif> 11 SCI </option>
                                    <option value="12COM" @if(isset($editData['previous_standard'])) @if($editData['previous_standard'] == '12COM') selected="selected" @endif @endif> 12 COM </option>
                                    <option value="12ART" @if(isset($editData['previous_standard'])) @if($editData['previous_standard'] == '12ART') selected="selected" @endif @endif> 12 ART </option>
                                    <option value="12SCI" @if(isset($editData['previous_standard'])) @if($editData['previous_standard'] == '12SCI') selected="selected" @endif @endif> 12 SCI </option>
                                </select>
                            </div>

                            <div class="col-md-3 form-group">
                                <label>Admission Standard </label>
                                <select id='admission_standard' name="admission_standard" required class="form-control" onchange="getDivision(this.value);">
                                <option value=""> Select Standard </option>
                                    @foreach($data['standard'] as $key => $value)
                                        <option value="{{$value['id']}}" @if(isset($editData['admission_standard'])) @if($editData['admission_standard'] == $value['id']) selected="selected" @endif @endif> {{$value['name']}} </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-3 form-group">
                                <label>Source of enquiry </label>
                                <input type="text" id='source_of_enquiry' name='source_of_enquiry' @if(isset($editData['source_of_enquiry'])) value="{{$editData['source_of_enquiry']}}" @endif required class="form-control">
                            </div>
                            <div class="col-md-3 form-group">
                                <label>Remarks </label>
                                <input type="text" id='remarks' name='remarks' @if(isset($editData['remarks'])) value="{{$editData['remarks']}}" @endif  name="remarks" class="form-control">
                            </div>
                            <div class="col-md-3 form-group">
                                <label>Followup Date </label>
                                <input type="text" id='followup_date' name='followup_date' @if(isset($editData['followup_date'])) value="{{$editData['followup_date']}}" @endif required name="followup_date" class="form-control mydatepicker" autocomplete="off">
                            </div>
                            <div class="col-md-3 form-group">
                                <label>Register Number/Application Number</label>
                                <input type="text" id='register_number' @if(isset($editData['register_number'])) value="{{$editData['register_number']}}" @endif required name="register_number" class="form-control">
                            </div>                    
                            <div class="col-md-3">
                                <label>Mother Name </label>
                                <input type="text" id='mother_name' @if(isset($editData['mother_name'])) value="{{$editData['mother_name']}}" @endif required name="mother_name" class="form-control">
                            </div>
                            @if(Session::get('sub_institute_id') == '46')
                                <div class="col-md-3 form-group">
                                    <label>Mother Mobile Number </label>
                                    <input type="text" id='mother_mobile_number' @if(isset($editData['mobile_number_mother'])) value="{{$editData['mobile_number_mother']}}" @endif name="mother_mobile_number" class="form-control">
                                </div>
                            @else
                                <div class="col-md-3 form-group">
                                    <label>Mother Mobile Number </label>
                                    <input type="text" id='mother_mobile_number' @if(isset($editData['mother_mobile_number'])) value="{{$editData['mother_mobile_number']}}" @endif name="mother_mobile_number" class="form-control">
                                </div>
                            @endif
                          
                            <div class="col-md-3 form-group">
                                <label>Aadhar Number </label>
                                <input type="text"  id='aadhar_number' @if(isset($editData['aadhar_number'])) value="{{$editData['aadhar_number']}}" @endif  name="aadhar_number" class="form-control">
                            </div>
                            <div class="col-md-3 form-group">
                                <label>Status </label>
                                <select id='status' required name="status" class="form-control">
                                    <option value=""> Select Status </option>
                                    <option value="OPEN" @if(isset($editData['status'])) @if($editData['status'] == 'OPEN') selected="selected" @endif @endif> Open </option>
                                    <option value="CLOSE" @if(isset($editData['status'])) @if($editData['status'] == 'CLOSE') selected="selected" @endif @endif> Close </option>
                                </select>
                            </div>
                            <div class="col-md-3 form-group">
                                <label>Place of Birth </label>
                                <input type="text" id='place_of_birth' @if(isset($editData['place_of_birth'])) value="{{$editData['place_of_birth']}}" @endif  name="place_of_birth" class="form-control">
                            </div>
                            <div class="col-md-3 form-group">
                                <label>Student Quota </label>
                                <select id='student_quota' required="required" name="student_quota" class="form-control">
                                <option value=""> Select Quota </option>
                                    @if(isset($data['category']))
                                        @foreach($data['category'] as $key => $value)
                                            <option value="{{$value['id']}}" @if(isset($editData['student_quota'])) @if($editData['student_quota'] == $value['id']) selected="selected" @endif @endif>{{$value['title']}}</option>
                                        @endforeach
                                    @endif
                                </select>
                            </div>
                            <div class="col-md-3 form-group">
                                <label>Division </label>
                                <select id='admission_division' required="required" name="admission_division" class="form-control">
                                <option value=""> Select Division </option>
                                    @if(isset($data['division']))
                                        @foreach($data['division'] as $key => $value)
                                            <option value="{{$value['id']}}" @if(isset($editData['admission_division'])) @if($editData['admission_division'] == $value['id']) selected="selected" @endif @endif>{{$value['name']}}</option>
                                        @endforeach
                                    @endif
                                </select>
                            </div>
                            <span id="division_error_span" style="text-align: right;margin-left: 18%;"></span>
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
                                @endif required name="enrollment_no" class="form-control" @php echo $display; @endphp>
                            </div>
                            <div class="col-md-3 form-group">
                                <label>Amount </label>
                                <input type="text" id='amount' @if(isset($editData['amount'])) value="{{$editData['amount']}}" @endif  name="amount" class="form-control">
                            </div>

                            <div class="col-md-3 form-group">
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

                            <div class="col-md-3 form-group">
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
                            <div class="col-md-3 form-group">
                                <label>Date of Payment </label>
                                <input type="text" id='date_of_payment' @if(isset($editData['date_of_payment'])) value="{{$editData['date_of_payment']}}" @endif  name="date_of_payment" class="form-control mydatepicker" autocomplete="off">
                            </div>
                            <div class="col-md-3 form-group">
                                <label>Date of Admission </label>
                                <input type="text" id='admission_date' @if(isset($editData['admission_date'])) value="{{$editData['admission_date']}}" @endif required name="admission_date" class="form-control mydatepicker" autocomplete="off">
                            </div>
                            <div class="col-md-3 form-group">
                                <label>Admission Confirmation </label>
                                <select id='admission_status' required name="admission_status" class="form-control">
                                    <option value=""> Select Status </option>
                                    <option value="YES" @if(isset($editData['admission_status'])) @if($editData['admission_status'] == 'YES') selected="selected" @endif @endif> YES </option>
                                    <option value="NO" @if(isset($editData['admission_status'])) @if($editData['admission_status'] == 'NO') selected="selected" @endif @endif> NO </option>
                                </select>
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

     $('document').ready(function(){
        //START Check Division Capacity Validation - 18/11/2021
        var division_check = false;
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

            if(division_check == false)
            { 
                alert('Please select other division.');
                return false;
            }

        });
    });

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
@include('includes.footer')
