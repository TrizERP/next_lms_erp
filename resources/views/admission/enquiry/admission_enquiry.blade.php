@include('includes.headcss')
<style type="text/css">
    .followup_data {
        width: 80%;
        height: 35px;
        font-size: 1.1em;
        color: green;
        font-weight: bold;
    }

    .selected-student {
        display: inline-block;
        background-color: #f1f1f1;
        padding: 5px;
        margin-right: 5px;
        border-radius: 3px;
    }

    .content-main {
        padding: 50px !important;
    }

    .paymentData {
        display: none;
        margin-top: 15px;
        padding: 15px;
    }

    .onlinePay,
    .cashPay {
        display: none;
    }
</style>
@php
    $usingMasterFields = isset($data['masterData']) && count($data['masterData']) > 0;
    $oldAdmissionInstitutes = [47,48,49,62,69,72,195,201,202,203,204,233,254];
    $showOldFields = in_array($_REQUEST['sub_institute_id'],$oldAdmissionInstitutes);
@endphp
<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row">
            <div class="col-lg-12 col-md-4 col-sm-4 col-xs-12 text-center">
                <img src="{{$data['logo'] ?? ''}}" style="height: 50px;" alt="logo">
            </div>
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Admission Enquiry</h4>
            </div>
        </div>
        <div class="card">
            @if (session()->has('data'))
                @if (isset(session('data')['status_code']) && session('data')['status_code'] == 1)
                    <div class="alert alert-success alert-block">
                    @else
                        <div class="alert alert-danger alert-block">
                @endif
                <button type="button" class="close" data-dismiss="alert">×</button>
                <strong>{{ isset(session('data')['message']) ? session('data')['message'] : 'something wrong' }}</strong>
        </div>
        @endif
        <div class="row">
             <div class="col-md-12">
               <div class="alert alert-warning" role="alert">
                 Fill the enquiry form (As per the Child's Birth Certificate only)
                 </div>
             </div>
             <div class="col-lg-12 col-sm-12 col-xs-12">
                <form action="{{ route('admission_enquiry.storeNew') }}" enctype="multipart/form-data" method="post">
                    {{ method_field('POST') }}
                    @csrf
                    <div class="row">
                        <input type="hidden" value="{{ $_REQUEST['type'] ?? '' }}" name="type">
                        <input type="hidden" value="{{ $_REQUEST['sub_institute_id'] ?? '' }}" name="sub_institute_id">
                        <input type="hidden" value="{{ $_REQUEST['syear'] ?? '' }}" name="syear">

                        @if($usingMasterFields)
                            {{-- Dynamic fields from master_fields system --}}
                            @foreach($data['masterData'] as $section=>$sectionVal) 
                                @foreach($sectionVal as $key=>$vals)
                                    @php 
                                        $requiredFields = ($vals['is_mandatory']==1) ? 'required' : '';
                                        $visibleFields = ($vals['is_visible']==0 && $vals['is_mandatory']==0) ? 'display:none' : '';
                                        $fieldValArr = (isset($vals['field_value']) && $vals['field_value']!='') ? json_decode($vals['field_value']) : [];
                                        $defaultValue = (isset($vals['default_value']) && $vals['default_value']!='') ? $vals['default_value'] : '';
                                        $asterisk = ($vals['is_mandatory']==1) ? '<span class="mdi mdi-asterisk" style="color:red;font-size:10px;"></span>' : '';
                                    @endphp
                                            @if($vals['field_name']=="admission_standard" || $vals['field_name']=="previous_standard")
                                         {{-- Special handling for admission_standard and previous_standard - uses $data['standard'] --}}
                                         <div class="col-md-3 form-group text-left" @if($visibleFields) style="{{$visibleFields}}" @endif>
                                             <label for="">{{$vals['field_label']}}</label> {!! $asterisk !!}
                                             <select name="{{$vals['field_name']}}" id="{{$vals['field_name']}}" class="form-control" {{$requiredFields}} @if($vals['field_name']=="admission_standard") onchange="display_link(this.value);add_data();" @endif>
                                                 <option value=""> Select Standard </option>
                                                 @foreach ($data['standard'] as $key => $value)
                                                     <option value="{{ $value['id'] }}"> {{ $value['name'] }} </option>
                                                 @endforeach
                                             </select>
                                             @if($vals['field_name']=="admission_standard")
                                                 <input type="hidden" name="hidden_std_id" id="hidden_std_id" value="">
                                             @endif
                                         </div>
                                     @elseif($vals['field_name']=="gender")
                                        {{-- Special handling for gender - uses field_value from master_fields --}}
                                        <div class="col-md-3 form-group text-left" @if($visibleFields) style="{{$visibleFields}}" @endif>
                                            <label for="">{{$vals['field_label']}}</label> {!! $asterisk !!}
                                            <div class="radio-list">
                                                @foreach($fieldValArr as $k => $v)
                                                <label class="radio-inline p-0">
                                                    <div class="radio radio-success">
                                                        <input type="radio" name="{{$vals['field_name']}}" id="{{$k}}" value="{{$k}}" {{$requiredFields}} @if($defaultValue!='' && $defaultValue==$k) checked @endif>
                                                        <label for="{{$k}}">{{$v}}</label>
                                                    </div>
                                                </label>
                                                @endforeach
                                            </div>
                                        </div>
                                    @elseif($vals['field_name']=="category")
                                        {{-- Special handling for category - uses $data['category'] from caste model --}}
                                        <div class="col-md-3 form-group text-left" @if($visibleFields) style="{{$visibleFields}}" @endif>
                                            <label for="">{{$vals['field_label']}}</label> {!! $asterisk !!}
                                            <select name="{{$vals['field_name']}}" id="{{$vals['field_name']}}" class="form-control" {{$requiredFields}}>
                                                <option value=""> Select Category </option>
                                                @if (isset($data['category']))
                                                    @foreach ($data['category'] as $key => $cat)
                                                        <option value="{{ $cat['id'] }}">{{ $cat['caste_name'] }}</option>
                                                    @endforeach
                                                @endif
                                            </select>
                                        </div>
                                    @elseif($vals['field_name']=="enquiry_no")
                                        {{-- Enquiry number is readonly --}}
                                        <div class="col-md-3 form-group text-left" @if($visibleFields) style="{{$visibleFields}}" @endif>
                                            <label for="">{{$vals['field_label']}}</label> {!! $asterisk !!}
                                            <input type="text" name="{{$vals['field_name']}}" id="{{$vals['field_name']}}" class="form-control" readonly
                                                @if (isset($data['enquiry_no'])) value="{{ $data['enquiry_no'] }}" @endif>
                                        </div>
                                    @elseif($vals['field_name']=="date_of_birth")
                                        <div class="col-md-3 form-group text-left" @if($visibleFields) style="{{$visibleFields}}" @endif>
                                            <label for="">{{$vals['field_label']}}</label> {!! $asterisk !!}
                                            <input type="date" onchange="calculate_age(this.value);" name="{{$vals['field_name']}}" id="{{$vals['field_name']}}" class="form-control" autocomplete="off">
                                        </div>
                                    @elseif($vals['field_name']=="send_sms")
                                        {{-- Send SMS uses custom options and triggers SMS message box --}}
                                        <div class="col-md-3 form-group text-left" @if($visibleFields) style="{{$visibleFields}}" @endif>
                                            <label for="">{{$vals['field_label']}}</label> {!! $asterisk !!}
                                            <select name="{{$vals['field_name']}}" id="{{$vals['field_name']}}" class="form-control" {{$requiredFields}} onchange="showMessageBox(this.value);">
                                                <option value="0"> No </option>
                                                <option value="1"> Yes </option>
                                            </select>
                                        </div>
                                    @elseif($vals['field_name']=="sms_message")
                                        {{-- SMS message box shown conditionally --}}
                                        <div class="col-md-3 form-group text-left" id="sms_message_box" style="display: none;" @if($visibleFields) style="{{$visibleFields}}" @endif>
                                            <label for="">{{$vals['field_label']}}</label> {!! $asterisk !!}
                                            <textarea type="text" id="{{$vals['field_name']}}" name="{{$vals['field_name']}}" class="form-control"></textarea>
                                        </div>
                                    @else
                                        <div class="col-md-3 form-group text-left" @if($visibleFields) style="{{$visibleFields}}" @endif>
                                            <label for="">{{$vals['field_label']}}</label> {!! $asterisk !!}
                                            @if($vals['field_type']=="textarea")
                                                <textarea name="{{$vals['field_name']}}" id="{{$vals['field_name']}}" class="form-control" {{$requiredFields}}>{{$defaultValue}}</textarea>
                                            @elseif($vals['field_type']=="dropdown")
                                                <select name="{{$vals['field_name']}}" id="{{$vals['field_name']}}" class="form-control" {{$requiredFields}}>
                                                    <option value="">Select any one</option>
                                                    @foreach($fieldValArr as $k => $v)
                                                    <option value="{{$k}}" @if($defaultValue!='' && $defaultValue==$k) selected @endif>{{$v}}</option>
                                                    @endforeach
                                                </select>
                                            @elseif($vals['field_type']=="radio")
                                            <div class="radio-list">
                                                @foreach($fieldValArr as $k => $v)
                                                <label class="radio-inline p-0">
                                                    <div class="radio radio-success">
                                                        <input type="radio" name="{{$vals['field_name']}}" id="{{$v}}" value="{{$k}}" {{$requiredFields}} @if($defaultValue!='' && $defaultValue==$k) checked @endif>
                                                        <label for="{{$v}}">{{$v}}</label>
                                                    </div>
                                                </label>
                                                @endforeach
                                            </div>
                                            @elseif($vals['field_type']=="date")
                                                <input type="date" name="{{$vals['field_name']}}" id="{{$vals['field_name']}}" class="form-control" {{$requiredFields}} autocomplete="off">
                                            @else
                                                <input type="{{$vals['field_type']}}" name="{{$vals['field_name']}}" id="{{$vals['field_name']}}" class="form-control" @if($defaultValue!='') value="{{$defaultValue}}" @else placeholder="Enter {{$vals['field_label']}}" @endif {{$requiredFields}} autocomplete="off" {{$vals['validation_rules'] ?? ''}}>
                                            @endif
                                        </div>
                                    @endif
                                @endforeach
                            @endforeach
                        @else
                            {{-- Fallback to hardcoded fields for backward compatibility --}}
                            <div class="col-md-3 form-group">
                                <label>Enquiry Number </label>
                                <input type="text" id='enquiry_id' name="enquiry_no" class="form-control" readonly
                                    @if (isset($data['enquiry_no'])) value="{{ $data['enquiry_no'] }}" @endif>
                            </div>
                            <div class="col-md-3 form-group">
                                <label>Student Name </label>
                                <input type="text" id='first_name' required name="first_name" class="form-control">
                            </div>

                            <div class="col-md-3 form-group">
                                <label>Middle Name(Father Name)</label>
                                <input type="text" id='middle_name' required name="middle_name" class="form-control">
                            </div>

                            <div class="col-md-3 form-group">
                                <label>Surname </label>
                                <input type="text" id='last_name' required name="last_name" class="form-control">
                            </div>
                            <div class="col-md-3 form-group">
                                <label>Mobile (SMS Number)</label>
                                <input type="text" id='mobile' pattern="[1-9]{1}[0-9]{9}" maxlength="10" required name="mobile"
                                    class="form-control">
                            </div>
                            <div class="col-md-3 form-group">
                                <label>Email </label>
                                <input type="email" id='email' name="email" class="form-control">
                            </div>
                            <div class="col-md-3 form-group">
                                <label>Admission Standard </label>
                                <select id='admission_standard' name="admission_standard" required class="form-control"
                                    onchange="display_link(this.value);add_data();">
                                    <option value=""> Select Standard </option>
                                    @foreach ($data['standard'] as $key => $value)
                                        <option value="{{ $value['id'] }}"> {{ $value['name'] }} </option>
                                    @endforeach
                                </select>
                                <input type="hidden" name="hidden_std_id" id="hidden_std_id" value="">
                            </div>

                            <div class="col-md-3 form-group">
                                <label>Date of Birth </label>
                                <input type="date" onchange="calculate_age(this.value);" id='date_of_birth' required
                                    name="date_of_birth" class="form-control" autocomplete="off">
                            </div>
                            <div class="col-md-3 form-group">
                                <label>Age </label>
                                <input type="text" id='age' name="age" class="form-control">
                                <span class="error_message" style="color:red;"></span>
                            </div>
                            <div class="col-md-3 form-group">
                                <label>Address </label>
                                <textarea id='address' name="address" class="form-control"></textarea>
                            </div>
                            <div class="col-md-3 form-group">
                                <label>Gender </label>
                                <div class="radio radio-success">
                                    <input type="radio" id='male' name="gender" value="M">
                                    <label for="male"> Male </label>
                                </div>
                                <div class="radio radio-success">
                                    <input type="radio" id='female' name="gender" value="F">
                                    <label for="female"> Female </label>
                                </div>
                            </div>
                            @if($showOldFields)
                                <div class="col-md-3 form-group">
                                    <label>Previous School's Name </label>
                                    <input type="text" id='previous_school_name' name="previous_school_name"
                                        class="form-control">
                                </div>
                                <div class="col-md-3 form-group">
                                    <label>Previous Standard </label>
                                    <select id='previous_standard' name="previous_standard" class="form-control">
                                        <option value=""> Select Standard </option>
                                        @foreach ($data['standard'] as $key => $previous)
                                            <option value="{{ $previous['id'] }}"> {{ $previous['name'] }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-3 form-group d-none">
                                    <label>Followup Date </label>
                                    <input type="date" id='followup_date' name="followup_date" class="form-control"
                                        autocomplete="off" value="{{ date('Y-m-d') }}">
                                    <span id="followup_date_span"></span>
                                </div>
                                <div class="col-md-3 form-group d-none">
                                    <label>Remarks </label>
                                    <input type="text" id='remarks' name="remarks" class="form-control">
                                </div>
                                <div class="col-md-3 form-group d-none">
                                    <label>Source of enquiry </label>
                                    <input type="text" id='source_of_enquiry' name="source_of_enquiry" class="form-control">
                                </div>
                                <div class="col-md-3 form-group d-none">
                                    <label>Category </label>
                                    <select id='category' name="category" class="form-control">
                                        <option value=""> Select Category </option>
                                        @if (isset($data['category']))
                                            @foreach ($data['category'] as $key => $value)
                                                <option value="{{ $value['id'] }}">{{ $value['caste_name'] }}</option>
                                            @endforeach
                                        @endif
                                    </select>
                                </div>
                                <div class="col-md-3 form-group d-none">
                                    <label>Send Sms </label>
                                    <select id='send_sms' name="send_sms" onchange="showMessageBox(this.value);"
                                        class="form-control">
                                        <option value="0"> No </option>
                                        <option value="1"> Yes </option>
                                    </select>
                                </div>
                                <div class="col-md-3 form-group" id="sms_message_box" style="display: none;">
                                    <label>Sms </label>
                                    <textarea type="text" id='sms_message' name="sms_message" class="form-control"></textarea>
                                </div>
                            @endif
                                @if(isset($data['custom_fields']))
                                @foreach ($data['custom_fields'] as $key => $value)
                                    <!-- remove field institute branch 2025-02-18  -->
                                    @if (!in_array($value['field_name'], ['institute_branch']))
                                        <div class="col-md-3 form-group">
                                            <label>{{ $value['field_label'] }}</label>
                                            @if ($value['field_type'] == 'file')
                                                <input type="{{ $value['field_type'] }}" accept="image/*"
                                                    id="input-file-now" @if ($value['required'] == 1) required @endif
                                                    name="{{ $value['field_name'] }}" class="dropify">
                                            @elseif($value['field_type'] == 'date')
                                                <input type="date" class="form-control" placeholder="dd/mm/yyyy"
                                                    autocomplete="off" id="{{ $value['field_name'] }}"
                                                    @if ($value['required'] == 1) required @endif
                                                    name="{{ $value['field_name'] }}" class="form-control">
                                            @elseif($value['field_type'] == 'time')
                                                <input type="time" autocomplete="off" id="{{ $value['field_name'] }}"
                                                    @if ($value['required'] == 1) required @endif
                                                    name="{{ $value['field_name'] }}" class="form-control">
                                            @elseif($value['field_type'] == 'checkbox')
                                                <div class="checkbox-list">
                                                    @if (isset($data['data_fields'][$value['id']]))
                                                        @foreach ($data['data_fields'][$value['id']] as $keyData => $valueData)
                                                            <label class="checkbox-inline">
                                                                <div class="checkbox checkbox-success">
                                                                    <input type="checkbox"
                                                                        name="{{ $value['field_name'] }}[]"
                                                                        value="{{ $valueData['display_value'] }}"
                                                                        id="{{ $valueData['display_value'] }}"
                                                                        @if ($value['required'] == 1) required @endif>
                                                                    <label
                                                                        for="{{ $valueData['display_value'] }}">{{ $valueData['display_text'] }}</label>
                                                                </div>
                                                            </label>
                                                        @endforeach
                                                    @endif
                                                </div>
                                            @elseif($value['field_type'] == 'dropdown')
                                                <select name="{{ $value['field_name'] }}" class="form-control"
                                                    @if ($value['required'] == 1) required @endif
                                                    id="{{ $value['field_name'] }}">
                                                    <option value=""> SELECT {{ strtoupper($value['field_label']) }}
                                                    </option>
                                                    @if (isset($data['data_fields'][$value['id']]))
                                                        @foreach ($data['data_fields'][$value['id']] as $keyData => $valueData)
                                                            <option value="{{ $valueData['display_value'] }}">
                                                                {{ $valueData['display_text'] }} </option>
                                                        @endforeach
                                                    @endif
                                                </select>
                                            @elseif($value['field_type'] == 'textarea')
                                                <textarea id="{{ $value['field_name'] }}" class="form-control" @if ($value['required'] == 1) required @endif
                                                    name="{{ $value['field_name'] }}" placeholder="{{ $value['field_message'] }}">
                                        </textarea>
                                            @else
                                                @if ($value['field_name'] == 'siblings' && !in_array($_REQUEST['sub_institute_id'], ['1', '254']))
                                                    <input type="{{ $value['field_type'] }}" list="studentList"
                                                        id="{{ $value['field_name'] }}"
                                                        placeholder="{{ $value['field_message'] }}"
                                                        @if ($value['required'] == 1) required @endif
                                                        placeholder="Enter Siblings name" class="form-control">
                                                    <div id="SelectedStudents" class=""></div>
                                                    <input type="hidden" name="{{ $value['field_name'] }}"
                                                        id="siblings_id">
                                                    <datalist id="studentList"></datalist>
                                                @elseif ($value['field_name'] == 'siblings' && in_array($_REQUEST['sub_institute_id'], ['1', '254']))
                                                        <input type="text" id="{{ $value['field_name'] }}" name="{{ $value['field_name'] }}" class="form-control">
                                                @else
                                                    <input type="{{ $value['field_type'] }}"
                                                        id="{{ $value['field_name'] }}"
                                                        placeholder="{{ $value['field_message'] }}"
                                                        @if ($value['required'] == 1) required @endif
                                                        name="{{ $value['field_name'] }}" class="form-control">
                                                @endif
                                            @endif
                                        </div>
                                    @endif
                                @endforeach
                            @endif
                            @if (in_array(Session::get('sub_institute_id'), ['198', '201', '202', '203', '204', '324', '326', '327']))
                                <div class="col-md-3 form-group">
                                    <label>Admission Form Charges </label>
                                    <input type="number" id='admission_fees' name="admission_fees"
                                        class="form-control">
                                </div>
                                <div class="col-md-3 form-group">
                                    <label>Fees Circular Form No </label>
                                    <input type="text" id='fees_circular_form_no' name="fees_circular_form_no"
                                        class="form-control">
                                </div>
                            @endif

                            @if (in_array($_REQUEST['sub_institute_id'], ['254']))
                                <div class="col-md-3 form-group">
                                    <label>Father's Mobile No. </label>
                                    <input type="text" id='mobile_number_father' name="mobile_number_father" pattern="[1-9]{1}[0-9]{9}" maxlength="10" class="form-control" required>
                                </div>
                                <div class="col-md-3 form-group">
                                    <label>Mother's Mobile No. </label>
                                    <input type="text" id='mobile_number_mother' name="mobile_number_mother" pattern="[1-9]{1}[0-9]{9}" maxlength="10" class="form-control" required>
                                </div>
                                <!-- Payment Type Section -->
                                <div class="col-md-3 form-group">
                                    <label for="payment">Select Payment Type</label>
                                    <select id="payment_type" name="payment_type" class="form-control" required>
                                        <option value="">Select</option>
                                        <option value="cash">Cash</option>
                                        <option value="online">Online</option>
                                    </select>
                                    <span class="cashPay" style="color:green">Please Save To get Token!</span>
                                </div>
                            @endif
                            @if (in_array(Session::get('sub_institute_id'), ['201', '202', '203', '204', '324', '326', '327']))
                                <div class="col-md-3 form-group">
                                    <label style="display: none;" id="label_for_fees_amount">Fees Amount </label>
                                    <input type="number" id='fees_amount' name="fees_amount" class="form-control"
                                        style="display: none;">
                                    <input type="hidden" id='original_fees_bf' name="original_fees_bf"
                                        class="form-control">
                                </div>
                                <div class="col-md-3 form-group">
                                    <label style="display: none;" id="label_for_fees_remarks">Fees Remark </label>
                                    <textarea id='fees_remark' name="fees_remark" class="form-control" style="display: none;"></textarea>
                                </div>
                            @endif
                        @endif

                        <div class="col-md-12 form-group">
                            <center>
                                <input type="submit" name="submit" id="submit" value="Save"
                                    class="btn btn-success save-btn">
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
    $('document').ready(function() {
        $('.cashPay').hide();
        $('#payment_type').on('change', function() {
            var payment_type = $(this).val();
            $('.cashPay').show();
        });

        $("#followup_date").on("change", function(event) {
            followup_date_val = this.value;
            var path = "{{ route('ajax_listCalendarData') }}";
            $.ajax({
                url: path,
                data: 'followup_date=' + followup_date_val,
                success: function(result) {
                    if (result != 0) {
                        $("#followup_date_span").removeClass().addClass("followup_data")
                            .text('You may have Holiday, Event or Vacation on this date.');
                    } else {
                        $("#followup_date_span").removeClass().addClass("followup_data")
                            .text('');
                    }
                }
            });
        });
        // 08-10-2024 start siblings selection 
        let selectedStudents = [];
        let selectedStudentIds = [];

        $('#siblings').on('input', function() {
            let searchText = $(this).val();
            let admission_enquiry = 'admission_enquiry';
            let sub_institute_id = "{{ $_REQUEST['sub_institute_id'] ?? '' }}";
            $.ajax({
                url: "{{ route('studentLists') }}",
                type: 'GET',
                data: {
                    stu_name: searchText,
                    module: admission_enquiry,
                    sub_institute_id: sub_institute_id
                },
                success: function(response) {
                    let studentList = $('#studentList');
                    studentList.empty();
                    response.forEach(student => {
                        studentList.append('<option value="' + student.first_name +
                            ' ' + student.middle_name + ' ' + student
                            .last_name + '(' + student.enrollment_no +
                            ')" data-id="' + student.id + '">' + student
                            .first_name + ' ' + student.middle_name + ' ' +
                            student.last_name + '(' + student.enrollment_no +
                            ')</option>');
                    });
                }
            });
        });

        $('#siblings').on('change', function() {
            let selectedStudentName = $(this).val();
            let selectedOption = $('#studentList option[value="' + selectedStudentName + '"]');

            if (selectedOption.length) {
                let studentId = selectedOption.data('id');

                if (!selectedStudentIds.includes(studentId)) {
                    selectedStudents.push(selectedStudentName);
                    selectedStudentIds.push(studentId);

                    $('#SelectedStudents').append(`
                        <span class="selected-student" data-id="${studentId}">
                            ${selectedStudentName} <span class="remove-student" style="cursor:pointer;color:red;">&times;</span>
                        </span>
                    `);

                    $('#siblings_id').val(selectedStudentIds.join(','));
                    $('#siblings').val('');
                }
            }
        });

        $('#SelectedStudents').on('click', '.remove-student', function() {
            let studentElement = $(this).closest('.selected-student');
            let studentId = studentElement.data('id');

            selectedStudentIds = selectedStudentIds.filter(id => id !== studentId);
            selectedStudents = selectedStudents.filter(name => name !== studentElement.text().trim());

            studentElement.remove();

            $('#siblings_id').val(selectedStudentIds.join(','));
        });
    });

    function calculate_age(dateString) {
        var ageValidation = @json($data['ageValidation'] ?? []);
        $('.error_message').empty();
        $('.save-btn').attr('disabled', false);
        value = dateString;
        today = new Date();
        dob = new Date(value.replace(/(\d{2})-(\d{2})-(\d{4})/, "$2/$1/$3"));

        age = today.getFullYear() - dob.getFullYear();

        if (today.getMonth() < dob.getMonth() ||
            (today.getMonth() === dob.getMonth() && today.getDate() < dob.getDate())) {
            age--;
        }

        if (age < 0) {
            age = 0;
        }

        let standard = parseInt($('#admission_standard').val(), 10);
        if (ageValidation.hasOwnProperty(standard)) {
            var standardData = ageValidation[standard];
            var standardDate = new Date(standardData.date);

            var dob = new Date(value.replace(/(\d{2})-(\d{2})-(\d{4})/, "$2/$1/$3"));
            if (dob <= standardDate) {
                console.log("DOB is valid for this standard");
            } else {
                let formattedDate = new Date(standardDate).toISOString().split('T')[0];
                $('.error_message').text(
                    'DOB must be less than or equal to ' + formattedDate
                );
                $('.save-btn').attr('disabled', true);
            }
        }

        document.getElementById('age').value = age;
    }

    function showMessageBox(x) {
        if (x == 1) {
            document.getElementById("sms_message_box").style.display = 'block';
        } else {
            document.getElementById("sms_message_box").style.display = 'none';
        }
    }
</script>

<script>
    function add_data() {
        var standard_id = $('#hidden_std_id').val();
        var path = "{{ route('ajax_getFeesBreakoff') }}";
        $.ajax({
            url: path,
            data: 'standard_id=' + standard_id,
            success: function(result) {
                $('#fees_amount').val(result);
                $('#original_fees_bf').val(result);
            }
        });

    }

    function display_link(val) {
        var standard_id = val;
        if (standard_id != '' || standard_id != 0) {
            $('#label_for_fees_remarks').css("display", "block");
            $('#label_for_fees_amount').css("display", "block");
            $('#fees_amount').css("display", "block");
            $('#fees_remark').css("display", "block");
            $('#fees_amount').attr("required", true);
            $('#hidden_std_id').val(standard_id);
        } else {
            $('#label_for_fees_remarks').css("display", "none");
            $('#label_for_fees_amount').css("display", "none");
            $('#fees_amount').css("display", "none");
            $('#fees_remark').css("display", "none");
        }
    }
    $('#submit').on('click', function() {
        var entered_fees_amt = $('#fees_amount').val();
        var original_fees_bf = $('#original_fees_bf').val();

        if (original_fees_bf != entered_fees_amt) {
            $('#fees_remark').attr("required", true);
        } else {
            $('#fees_remark').attr("required", false);
        }

    });
</script>
@include('includes.footer')