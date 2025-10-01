{{--@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')--}}
@extends('layout')
@section('container')
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
</style>
<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Admission Enquiry</h4>
            </div>
        </div>
        <div class="card">
            @if ($message = Session::get('success'))
            <div class="alert alert-success alert-block">
                <button type="button" class="close" data-dismiss="alert">×</button>
                <strong>{{ $message }}</strong>
            </div>
            @endif
            <div class="row">
                <div class="col-lg-12 col-sm-12 col-xs-12">
                    <form action="{{ route('admission_enquiry.store') }}" enctype="multipart/form-data" method="post">
                    {{ method_field("POST") }}
                    @csrf
                    <div class="row">
                        @php
                        if (Session::get('sub_institute_id') != '198') // maheshvari ladavi
                        {
                            $readonly = ' readonly="readonly" ';
                        }else{
                            $readonly = '';
                        }

                        $class="hide";
                        $oldAdmissionInstitutes = [47,48,49,62,69,72,195,201,202,203,204,233,254];
                        if(in_array(Session::get('sub_institute_id'),$oldAdmissionInstitutes))
                        {
                            $class="show";
                        }
                        $unmandatoryFileds = ['category','previous_school_name','previous_standard','send_sms','remarks','source_of_enquiry','followup_date'];
                        @endphp

                        <div class="col-md-3 form-group">
                            <label>Enquiry Number </label>
                            <input type="text" id='enquiry_id'  id='enquiry_id' @if(isset($data['enquiry_no'])) value="{{$data['enquiry_no']}}" @endif name="enquiry_no" class="form-control" {{ $readonly}}>
                        </div>
                        <div class="col-md-3 form-group">
                            <label>Student Name </label>
                            <input type="text" id='first_name' required name="first_name" class="form-control">
                        </div>
                        @if (Session::get('sub_institute_id') != '198')
                        <div class="col-md-3 form-group">
                            <label>Middle Name(Father Name)</label>
                            <input type="text"  id='middle_name' required name="middle_name" class="form-control">
                        </div>
                        @endif
                        <div class="col-md-3 form-group">
                            <label>Surname </label>
                            <input type="text" id='last_name' required name="last_name" class="form-control">
                        </div>
                        <div class="col-md-3 form-group">
                            <label>Mobile (SMS Number)</label>
                            <input type="text" id='mobile' pattern="[1-9]{1}[0-9]{9}" required name="mobile" class="form-control">
                        </div>
                        <div class="col-md-3 form-group">
                            <label>Email </label>
                            <!--  pattern="/^[a-zA-Z0-9.!#$%&'*+/=?^_`{|}~-]+@[a-zA-Z0-9-]+(?:\.[a-zA-Z0-9-]+)*$/" -->
                            <input type="email" id='email'  name="email" class="form-control">
                        </div>
                        <div class="col-md-3 form-group">
                            <label>Admission Standard </label>
                            <select id='admission_standard' name="admission_standard" required class="form-control" onchange="display_link(this.value);add_data();">
                            <option value=""> Select Standard </option>
                                @foreach($data['standard'] as $key => $value)
                                    <option value="{{$value['id']}}"> {{$value['name']}} </option>
                                @endforeach
                            </select>
                            <input type="hidden" name="hidden_std_id" id="hidden_std_id" value="">
                            <input type="hidden" name="status" value="approve"> <!-- 1 for approve added on 18-08-2025-->
                        </div>
                        <div class="col-md-3 form-group">
                            <label>Date of Birth </label>
                            <input type="text" onchange="calculate_age(this.value);" id='date_of_birth' required name="date_of_birth" class="form-control mydatepicker" autocomplete="off">
                        </div>
                        <div class="col-md-3 form-group">
                            <label>Age </label>
                            <input type="text" id='age' name="age" class="form-control">
                              <span class="error_message" style="color:red;"></span>
                        </div>
                        @if (Session::get('sub_institute_id') != '198')
                        <div class="col-md-3 form-group">
                            <label>Address </label>
                            <textarea id='address' name="address" class="form-control"></textarea>
                        </div>
                        @endif
                        <div class="col-md-3 form-group {{$class}} previous_school_nameDiv">
                            <label>Previous School Name </label>
                            <input type="text" id='previous_school_name' name="previous_school_name" class="form-control">
                        </div>
                        <div class="col-md-3 form-group {{$class}} previous_standardDiv">
                            <label>Previous Standard </label>
                            <select id='previous_standard' name="previous_standard" class="form-control">
                                <option value=""> Select Standard </option>
                                @foreach($data['standard'] as $key=>$previous)
                                <option value="{{$previous['id']}}"> {{$previous['name']}}</option>
                                @endforeach
                            </select>
                        </div>
                        
                        <div class="col-md-3 form-group {{$class}} followup_dateDiv">
                            <label>Followup Date </label>
                            <input type="text" value="{{ date('Y-m-d') }}" id="followup_date" name="followup_date" class="form-control mydatepicker" autocomplete="off">

                            <span id="followup_date_span"></span>
                        </div>
                        <div class="col-md-3 form-group  {{$class}} remarksDiv">
                            <label>Remarks </label>
                            <input type="text" id='remarks'  name="remarks" class="form-control">
                        </div>
                        <div class="col-md-3 form-group  {{$class}} source_of_enquiryDiv">
                            <label>Source of enquiry </label>
                            <input type="text" id='source_of_enquiry'  name="source_of_enquiry" class="form-control">
                        </div>
                        <div class="col-md-3 form-group  {{$class}} categoryDiv">
                            <label>Category </label>
                            <select id='category' name="category" class="form-control">
                            <option value=""> Select Category </option>
                                @if(isset($data['category']))
                                    @foreach($data['category'] as $key => $value)
                                        <option value="{{$value['id']}}">{{$value['caste_name']}}</option>
                                    @endforeach
                                @endif
                            </select>
                        </div>
                        <div class="col-md-3 form-group">
                            <label>Gender </label>
                            <div class="radio radio-success">
                                <input type="radio" id='male' name="gender" value="M" checked>
                                <label for="male"> Male </label>
                            </div>
                            <div class="radio radio-success">
                                <input type="radio" id='female' name="gender" value="F">
                                <label for="female"> Female </label>
                            </div>
                        </div>
                        <div class="col-md-3 form-group  {{$class}} send_smsDiv">
                            <label>Send Sms </label>
                            <select id='send_sms' name="send_sms" onchange="showMessageBox(this.value);" class="form-control">
                            <option value="0"> No </option>
                            <option value="1"> Yes </option>
                            </select>
                        </div>
                        <div class="col-md-3 form-group" id="sms_message_box" style="display: none;">
                            <label>Sms </label>
                            <textarea type="text" id='sms_message' name="sms_message" class="form-control"></textarea>
                        </div>
                        
                        @if(isset($data['custom_fields']))
                        @foreach($data['custom_fields'] as $key => $value)
                            @if(!in_array($value['field_name'],$unmandatoryFileds))
                            <div class="col-md-3 form-group">
                                <label>{{ $value['field_label'] }}</label>
                                @if($value['field_type'] == 'file')
                                <input type="{{ $value['field_type'] }}" accept="image/*" id="input-file-now" @if($value['required'] == 1) required @endif name="{{ $value['field_name'] }}" class="dropify">
                                @elseif($value['field_type'] == 'date')
                                <input type="date" class="form-control mydatepicker" placeholder="dd/mm/yyyy" autocomplete="off" id="{{ $value['field_name'] }}" @if($value['required'] == 1) required @endif name="{{ $value['field_name'] }}" class="form-control">
                                @elseif($value['field_type'] == 'time')
                                    <input type="time" autocomplete="off" id="{{ $value['field_name'] }}" @if($value['required'] == 1) required @endif name="{{ $value['field_name'] }}"  class="form-control">
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
                                    @if($value['field_name']=='siblings')
                                        <input type="{{ $value['field_type'] }}"  list="studentList" id="{{ $value['field_name'] }}" placeholder="{{ $value['field_message'] }}" @if($value['required'] == 1) required @endif placeholder="Enter Siblings name" class="form-control" name="siblings">
                                        {{-- <div id="SelectedStudents" class=""></div> --}}
                                        {{-- <input type="hidden" name="{{ $value['field_name'] }}" id="siblings_id"> --}}
                                    {{-- <datalist id="studentList"></datalist> --}}
                                    @else 
                                        <input type="{{ $value['field_type'] }}" id="{{ $value['field_name'] }}" placeholder="{{ $value['field_message'] }}" @if($value['required'] == 1) required @endif name="{{ $value['field_name'] }}" class="form-control">
                                    @endif
                                @endif
                            </div>
                            @endif
                        @endforeach
                        @endif
                        @if (in_array(Session::get('sub_institute_id'), ['198','201','202','203','204','324','326','327']))
                        <div class="col-md-3 form-group">
                            <label>Admission Form Charges </label>
                            <input type="number" id='admission_fees' name="admission_fees" class="form-control">
                        </div>
                        <div class="col-md-3 form-group">
                            <label>Fees Circular Form No </label>
                            <input type="text" id='fees_circular_form_no' name="fees_circular_form_no" class="form-control">
                        </div>
                        @endif

                        <div class="col-md-3 form-group">
                            
                        </div>

                        @if (in_array(Session::get('sub_institute_id'), ['201','202','203','204','324','326','327']))
                        <div class="col-md-3 form-group">
                            <label style="display: none;" id="label_for_fees_amount">Fees Amount </label>
                            <input type="number" id='fees_amount' name="fees_amount" class="form-control" style="display: none;">
                            <input type="hidden" id='original_fees_bf' name="original_fees_bf" class="form-control">
                        </div>
                        <div class="col-md-3 form-group">
                            <label style="display: none;" id="label_for_fees_remarks">Fees Remark </label>
                            <textarea id='fees_remark' name="fees_remark" class="form-control" style="display: none;"></textarea>
                        </div>
                        @endif

                        <div class="col-md-12 form-group">
                            <center>
                                <input type="submit" name="submit" id="submit" value="Save" class="btn btn-success save-btn" >
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
    //10-01-2022 START display holiday,vacation & event in Followup date
    $('document').ready(function(){
        $("#followup_date").on( "change", function( event ) {
            followup_date_val = this.value;
            var path = "{{ route('ajax_listCalendarData') }}";
            $.ajax({
                url:path,
                data:'followup_date='+followup_date_val,
                success:function(result){
                    if(result != 0)
                    {
                        $("#followup_date_span").removeClass().addClass("followup_data").text('You may have Holiday, Event or Vacation on this date.');
                    }else{
                        $("#followup_date_span").removeClass().addClass("followup_data").text('');
                    }
                }
            });
        });
        $("#siblings").autocomplete({
                source: function(request, response) {
                    let searchText = request.term;
                    let admission_enquiry = 'admission_enquiry';
                    $.ajax({
                        url: "{{ route('studentLists') }}",
                        type: 'get',
                        data: {
                            stu_name: searchText,
                            module: admission_enquiry
                        },
                        success: function(data) {
                            response($.map(data, function(student) {
                                return {
                                    label: student.first_name + ' ' + student.middle_name + ' ' + student.last_name,
                                    value: student.first_name + ' ' + student.middle_name + ' ' + student.last_name
                                };
                            }));
                        }
                    });
                }
            });
    // 08-10-2024 start siblings selection 
    // let selectedStudents = [];
    // let selectedStudentIds = [];

    // $('#siblings').on('input', function () {
    //     let searchText = $(this).val();
    //     let admission_enquiry = 'admission_enquiry';
    //     // AJAX request to fetch student list
    //     $.ajax({
    //         url: "{{ route('studentLists') }}",
    //         type: 'GET',
    //         data: { stu_name: searchText,module:admission_enquiry },
    //         success: function(response) {
    //             let studentList = $('#studentList');
    //             studentList.empty(); 
    //             console.log(response);
    //             // Populate the datalist with new options
    //             response.forEach(student => {
    //                 studentList.append('<option value="' + student.first_name+' '+ student.middle_name+' '+ student.last_name + '('+student.enrollment_no+')" data-id="' + student.id + '">' + student.first_name+' '+ student.middle_name+' '+ student.last_name + '('+student.enrollment_no+')</option>');
    //             });
    //         }
    //     });
    // });

    // // Event listener for selecting a student
    // $('#siblings').on('change', function () {
    //     let selectedStudentName = $(this).val();
    //     let selectedOption = $('#studentList option[value="' + selectedStudentName + '"]');

    //     if (selectedOption.length) {
    //         let studentId = selectedOption.data('id');

    //         // Check if student is already selected
    //         if (!selectedStudentIds.includes(studentId)) {
    //             selectedStudents.push(selectedStudentName);
    //             selectedStudentIds.push(studentId);

    //             // Update the SelectedStudents div
    //             // $('#SelectedStudents').append('<span class="selected-student">' + selectedStudentName + '</span>');
    //             $('#SelectedStudents').append(`
    //             <span class="selected-student" data-id="${studentId}">
    //                 ${selectedStudentName} <span class="remove-student" style="cursor:pointer;color:red;">&times;</span>
    //             </span>
    //         `);

    //             // Update the siblings_id input
    //             $('#siblings_id').val(selectedStudentIds.join(','));

    //             // Clear the input field
    //             $('#siblings').val('');
    //         }
    //     }
    // });
    // // Event listener for removing a selected student
    // $('#SelectedStudents').on('click', '.remove-student', function () {
    //     let studentElement = $(this).closest('.selected-student');
    //     let studentId = studentElement.data('id');

    //     // Remove the student from selectedStudents and selectedStudentIds
    //     selectedStudentIds = selectedStudentIds.filter(id => id !== studentId);
    //     selectedStudents = selectedStudents.filter(name => name !== studentElement.text().trim());

    //     // Remove the student element from the DOM
    //     studentElement.remove();

    //     // Update the siblings_id input
    //     $('#siblings_id').val(selectedStudentIds.join(','));
    // });
    // 08-10-2024 end siblings selection 
        
    });
    //10-01-2022 END display holiday,vacation & event in Followup date

    function calculate_age(dateString)
    {
        // value = dateString;
        // today = new Date();
        // dob = new Date(value.replace(/(\d{2})-(\d{2})-(\d{4})/, "$2/$1/$3"));
        // age = today.getFullYear() - dob.getFullYear(); //This is the update
        // document.getElementById('age').value = age;
        var ageValidation = @json($data['ageValidation']);
        console.log(ageValidation);
        $('.error_message').empty();
        $('.save-btn').attr('disabled', false);
        value = dateString; // Input date in "dd-mm-yyyy" format
        today = new Date();
        dob = new Date(value.replace(/(\d{2})-(\d{2})-(\d{4})/, "$2/$1/$3"));

        age = today.getFullYear() - dob.getFullYear();

        if (
            today.getMonth() < dob.getMonth() || 
            (today.getMonth() === dob.getMonth() && today.getDate() < dob.getDate())
        ) {
            age--;
        }

        if(age<0){
            age=0;
        }

        let standard = parseInt($('#admission_standard').val(), 10);
        // check if key exists in object
            if (ageValidation.hasOwnProperty(standard)) {
                var standardData = ageValidation[standard];
                console.log("Found:", standardData);

                var standardDate = new Date(standardData.date); // this is '2020-02-06'
                console.log("Standard Date:", standardDate);

                // Now compare DOB
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
            } else {
                console.log("Standard not found in validation array");
            }

        document.getElementById('age').value = age;

    }

    function showMessageBox(x)
    {
        if(x == 1)
        {
            document.getElementById("sms_message_box").style.display = 'block';
        }else{
            document.getElementById("sms_message_box").style.display = 'none';
        }
    }
</script>

<script>

    function add_data()
    {
        var standard_id = $('#hidden_std_id').val();
        var path = "{{ route('ajax_getFeesBreakoff') }}";
        $.ajax({
                url: path,
                data:'standard_id='+standard_id,
                success: function(result){
                    $('#fees_amount').val(result);
                    $('#original_fees_bf').val(result);
                }
        });

    }

    function display_link(val)
    {
        var standard_id = val;
        if(standard_id != '' || standard_id != 0)
        {
            $('#label_for_fees_remarks').css("display", "block");
            $('#label_for_fees_amount').css("display", "block");
            $('#fees_amount').css("display", "block");
            $('#fees_remark').css("display", "block");
            $('#fees_amount').attr("required", true);
            // $('#fees_remark').attr("required", true);
            $('#hidden_std_id').val(standard_id);
        }else{
            $('#label_for_fees_remarks').css("display", "none");
            $('#label_for_fees_amount').css("display", "none");
            $('#fees_amount').css("display", "none");
            $('#fees_remark').css("display", "none");
        }
    }
        $('#submit').on('click', function(){
            var entered_fees_amt = $('#fees_amount').val();
            var original_fees_bf = $('#original_fees_bf').val();

            if(original_fees_bf != entered_fees_amt)
            {
                $('#fees_remark').attr("required", true);
            }else{
                $('#fees_remark').attr("required", false);
            }

        });

        @if(isset($data['custom_fields']))
        @foreach($data['custom_fields'] as $key => $value)
            @if(in_array($value['field_name'],$unmandatoryFileds))
                var fieldName = "{{$value['field_name']}}";
                var fieldLabel = "{{$value['field_label']}}";

                $('.'+fieldName+'Div').removeClass('hide');
                $('.' + fieldName + 'Div').addClass('show').find('label').text(fieldLabel);
            @endif 
        @endforeach
        @endif
</script>
@include('includes.footer')
@endsection
