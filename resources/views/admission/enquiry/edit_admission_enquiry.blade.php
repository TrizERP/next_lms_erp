{{--@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')--}}
@extends('layout')
@section('container')
@php
$editData = array();
$student_data = array();
if(isset($data['editData']))
{
    $student_data = $editData = $data['editData'];
}
@endphp

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Admission Enquiry</h4>
            </div>
        </div>
        <div class="card">
            <div class="row">
                <div class="col-lg-9 col-sm-8 col-md-8 col-xs-12">
                    <a href="{{route('admission_follow_up.index')}}?enquiry_id={{$editData['id']}}&module=enquiry" class="btn btn-success mb-5">Admission Follow Up</a>
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
                    <form action="{{ route('admission_enquiry.update', $editData['id']) }}" enctype="multipart/form-data" method="post">
                        {{ method_field("PUT") }}
                        @csrf
                        <div class="row">
                            @php
                            if (Session::get('sub_institute_id') != '198') // maheshvari ladavi
                            {
                                $readonly = ' readonly="readonly" ';
                            }else{
                                $readonly = '';
                            }
                            @endphp
                            <div class="col-md-3 form-group">
                                <label>Enquiry Number </label>
                                <input type="text" id='enquiry_no' @if(isset($editData['enquiry_no'])) value="{{$editData['enquiry_no']}}" @endif required name="enquiry_no" class="form-control" {{ $readonly }}>
                            </div>

                            <div class="col-md-3 form-group">
                                <label>Student Name </label>
                                <input type="text" id='first_name' @if(isset($editData['first_name'])) value="{{$editData['first_name']}}" @endif required name="first_name" class="form-control">
                            </div>
                            @if (Session::get('sub_institute_id') != '198')
                            <div class="col-md-3 form-group">
                                <label>Middle Name(Father Name)</label>
                                <input type="text" id='middle_name' @if(isset($editData['middle_name'])) value="{{$editData['middle_name']}}" @endif required name="middle_name" class="form-control">
                            </div>
                            @endif
                            <div class="col-md-3 form-group">
                                <label>Surname </label>
                                <input type="text" id='last_name' @if(isset($editData['last_name'])) value="{{$editData['last_name']}}" @endif required name="last_name" class="form-control">
                            </div>

                            <div class="col-md-3 form-group">
                                <label>Mobile </label>
                                <input type="text" pattern="[1-9]{1}[0-9]{9}" id='mobile' @if(isset($editData['mobile'])) value="{{$editData['mobile']}}" @endif required name="mobile" class="form-control">
                            </div>
                            @php
                            $required = 'required';
                            if(Session::get('sub_institute_id') == '74')
                            {
                                $required = '';
                            }
                            @endphp
                            <div class="col-md-3 form-group">
                                <label>Email </label>
                                <!-- pattern="/^[a-zA-Z0-9.!#$%&'*+/=?^_`{|}~-]+@[a-zA-Z0-9-]+(?:\.[a-zA-Z0-9-]+)*$/ -->
                                <input type="email" id='email' @if(isset($editData['email'])) value="{{$editData['email']}}" @endif {{$required}} name="email" class="form-control">
                            </div>

                            <div class="col-md-3 form-group">
                                <label>Date of Birth </label>
                                <input type="text" @if(isset($editData['date_of_birth'])) value="{{$editData['date_of_birth']}}" @endif onchange="calculate_age(this.value);" id='date_of_birth' required name="date_of_birth" class="form-control mydatepicker">
                            </div>

                            <div class="col-md-3 form-group">
                                <label>Age </label>
                                <input type="text" id='age' @if(isset($editData['age'])) value="{{$editData['age']}}" @endif required name="age" class="form-control">
                            </div>
                            @if (Session::get('sub_institute_id') != '198')
                            <div class="col-md-3 form-group">
                                <label>Address </label>
                                <textarea id='address' required name="address" class="form-control">@if(isset($editData['address'])){{$editData['address']}}@endif</textarea>
                            </div>
                            @endif
                            <div class="col-md-3 form-group">
                                <label>Previous School Name </label>
                                <input type="text" id='previous_school_name' @if(isset($editData['previous_school_name'])) value="{{$editData['previous_school_name']}}" @endif required name="previous_school_name" class="form-control">
                            </div>

                            <div class="col-md-3 form-group">
                                <label>Previous Standard </label>
                                <select id='previous_standard' required name="previous_standard" class="form-control">
                                    <option value=""> Select Standard </option>
                                    @foreach($data['standard'] as $key=>$previous)
                                    <option value="{{$previous['id']}}" @if(isset($editData['previous_standard']) && $previous['id']==$editData['previous_standard']) selected @endif> {{$previous['name']}}</option>
                                    @endforeach
                                    
                                    {{-- <option value="NA" @if(isset($editData['previous_standard'])) @if($editData['previous_standard'] == 'NA') selected="selected" @endif @endif> NA </option>
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
                                    <option value="12SCI" @if(isset($editData['previous_standard'])) @if($editData['previous_standard'] == '12SCI') selected="selected" @endif @endif> 12 SCI </option>  --}}
                                </select>
                            </div>

                            <div class="col-md-3 form-group">
                                <label>Admission Standard </label>
                                <select id='admission_standard' name="admission_standard" required class="form-control">
                                <option value=""> Select Standard </option>
                                    @foreach($data['standard'] as $key => $value)
                                        <option value="{{$value['id']}}" @if(isset($editData['admission_standard'])) @if($editData['admission_standard'] == $value['id']) selected="selected" @endif @endif> {{$value['name']}} </option>
                                    @endforeach
                                </select>
                            </div>

                            <!-- <div class="col-md-3 form-group">
                                <label>Admission Standard </label>
                                <select id='admission_standard' name="admission_standard" class="form-control">
                                <option value=""> Select Standard </option>
                                    <option value="NURSERY" @if(isset($editData['admission_standard'])) @if($editData['admission_standard'] == 'NURSERY') selected="selected" @endif @endif> Nursery </option>
                                    <option value="JRKG" @if(isset($editData['admission_standard'])) @if($editData['admission_standard'] == 'JRKG') selected="selected" @endif @endif> Jrkg </option>
                                    <option value="SRKG" @if(isset($editData['admission_standard'])) @if($editData['admission_standard'] == 'SRKG') selected="selected" @endif @endif> Srkg </option>
                                    <option value="1" @if(isset($editData['admission_standard'])) @if($editData['admission_standard'] == '1') selected="selected" @endif @endif> 1 </option>
                                    <option value="2" @if(isset($editData['admission_standard'])) @if($editData['admission_standard'] == '2') selected="selected" @endif @endif> 2 </option>
                                    <option value="3" @if(isset($editData['admission_standard'])) @if($editData['admission_standard'] == '3') selected="selected" @endif @endif> 3 </option>
                                    <option value="4" @if(isset($editData['admission_standard'])) @if($editData['admission_standard'] == '4') selected="selected" @endif @endif> 4 </option>
                                    <option value="5" @if(isset($editData['admission_standard'])) @if($editData['admission_standard'] == '5') selected="selected" @endif @endif> 5 </option>
                                    <option value="6" @if(isset($editData['admission_standard'])) @if($editData['admission_standard'] == '6') selected="selected" @endif @endif> 6 </option>
                                    <option value="7" @if(isset($editData['admission_standard'])) @if($editData['admission_standard'] == '7') selected="selected" @endif @endif> 7 </option>
                                    <option value="8" @if(isset($editData['admission_standard'])) @if($editData['admission_standard'] == '8') selected="selected" @endif @endif> 8 </option>
                                    <option value="9" @if(isset($editData['admission_standard'])) @if($editData['admission_standard'] == '9') selected="selected" @endif @endif> 9 </option>
                                    <option value="10" @if(isset($editData['admission_standard'])) @if($editData['admission_standard'] == '10') selected="selected" @endif @endif> 10 </option>
                                    <option value="11COM" @if(isset($editData['admission_standard'])) @if($editData['admission_standard'] == '11COM') selected="selected" @endif @endif> 11 COM </option>
                                    <option value="11ART" @if(isset($editData['admission_standard'])) @if($editData['admission_standard'] == '11ART') selected="selected" @endif @endif> 11 ART </option>
                                    <option value="11SCI" @if(isset($editData['admission_standard'])) @if($editData['admission_standard'] == '11SCI') selected="selected" @endif @endif> 11 SCI </option>
                                    <option value="12COM" @if(isset($editData['admission_standard'])) @if($editData['admission_standard'] == '12COM') selected="selected" @endif @endif> 12 COM </option>
                                    <option value="12ART" @if(isset($editData['admission_standard'])) @if($editData['admission_standard'] == '12ART') selected="selected" @endif @endif> 12 ART </option>
                                    <option value="12SCI" @if(isset($editData['admission_standard'])) @if($editData['admission_standard'] == '12SCI') selected="selected" @endif @endif> 12 SCI </option>
                                </select>
                            </div> -->

                            <div class="col-md-3 form-group">
                                <label>Remarks </label>
                                <input type="text" id='remarks' @if(isset($editData['remarks'])) value="{{$editData['remarks']}}" @endif required name="remarks" class="form-control">
                            </div>

                            <div class="col-md-3 form-group">
                                <label>Source of enquiry </label>
                                <input type="text" id='source_of_enquiry' @if(isset($editData['source_of_enquiry'])) value="{{$editData['source_of_enquiry']}}" @endif required name="source_of_enquiry" class="form-control">
                            </div>

                            <div class="col-md-3 form-group">
                                <label>Followup Date </label>
                                <input type="text" id='followup_date' @if(isset($editData['followup_date'])) value="{{$editData['followup_date']}}" @endif required name="followup_date" class="form-control mydatepicker" autocomplete="off">
                            </div>

                            <div class="col-md-3 form-group">
                                <label>Gender </label>

                                <div class="radio radio-success">
                                    <input type="radio" id='male' @if(isset($editData['gender'])) @if($editData['gender'] == 'M') checked="checked" @endif @endif name="gender" value="M">
                                    <label for="male"> Male </label>
                                </div>
                                <div class="radio radio-success">
                                    <input type="radio" id='female' name="gender" @if(isset($editData['gender'])) @if($editData['gender'] == 'F') checked="checked" @endif @endif value="F">
                                    <label for="female"> Female </label>
                                </div>
                            </div>

                            <div class="col-md-3 form-group">
                                <label>Category </label>
                                <select id='category' {{$required}} name="category" class="form-control">
                                <option value=""> Select Category </option>
                                    @if(isset($data['category']))
                                        @foreach($data['category'] as $key => $value)
                                            <option value="{{$value['id']}}" @if(isset($editData['category'])) @if($editData['category'] == $value['id']) selected="selected" @endif @endif>{{$value['caste_name']}}</option>
                                        @endforeach
                                    @endif
                                </select>
                            </div>

                            <!-- <div class="col-md-3 form-group">
                                <label>Send Sms </label>
                                <select id='send_sms' required="required" name="send_sms" onchange="showMessageBox(this.value);" class="form-control">
                                <option value="0" @if(isset($editData['send_sms'])) @if($editData['send_sms'] == '0')  selected="selected" @endif @endif> No </option>
                                <option value="1" @if(isset($editData['send_sms'])) @if($editData['send_sms'] == '1') selected="selected" @endif @endif> Yes </option>
                                </select>
                            </div>

                            <div class="col-md-3 form-group" id="sms_message_box" @if(isset($editData['send_sms'])) @if($editData['send_sms'] == '0') style="display: none;" @endif @else style="display: none;" @endif>
                                <label>Sms </label>
                                <textarea type="text" id='sms_message' name="sms_message" class="form-control"> @if(isset($editData['sms_message'])) {{$editData['sms_message']}} @endif</textarea>
                            </div> -->



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
                            @if (Session::get('sub_institute_id') == '198' ||
                                Session::get('sub_institute_id') == '201' ||
                                Session::get('sub_institute_id') == '202' ||
                                Session::get('sub_institute_id') == '203' ||
                                Session::get('sub_institute_id') == '204')
                            <div class="col-md-3 form-group">
                                <label>Admission Form Charges </label>
                                <input type="number" id='admission_fees' @if(isset($editData['admission_fees'])) value="{{$editData['admission_fees']}}" @endif required name="admission_fees" class="form-control">
                            </div>
                            <div class="col-md-3 form-group">
                                <label>Fees Circular Form No </label>
                                <input type="text" id='fees_circular_form_no' @if(isset($editData['fees_circular_form_no'])) value="{{$editData['fees_circular_form_no']}}" @endif name="fees_circular_form_no" class="form-control" readonly="readonly">
                            </div>
                            @endif
                            <div class="col-md-12 form-group">
                                <center>
                                    <input type="submit" name="submit" value="Update" class="btn btn-success" >
                                </center>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

@include('includes.footerJs')
<script type="text/javascript">
    function calculate_age(dateString)
    {
        value = dateString;
        today = new Date();
        dob = new Date(value.replace(/(\d{2})-(\d{2})-(\d{4})/, "$2/$1/$3"));
        age = today.getFullYear() - dob.getFullYear(); //This is the update
        document.getElementById('age').value = age;

        // var today = new Date();
        // var birthDate = new Date(dateString);
        // var age = today.getFullYear() - birthDate.getFullYear();
        // var m = today.getMonth() - birthDate.getMonth();
        //   if (m < 0 || (m === 0 && today.getDate() < birthDate.getDate())) {
        //     age--;
        //   }
        // document.getElementById('age').value = age;
    }

    function showMessageBox(x)
    {
        if(x == 1)
        {
            document.getElementById("sms_message_box").style.display = 'block';
            document.getElementById("sms_message_box").required = true;
        }else{
            document.getElementById("sms_message_box").style.display = 'none';
            document.getElementById("sms_message_box").required = false;
        }
    }
</script>
@include('includes.footer')
@endsection
