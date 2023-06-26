@include('includes.headcss')
<link rel="stylesheet" href="../../../plugins/bower_components/dropify/dist/css/dropify.min.css">
@include('includes.header')
@include('includes.sideNavigation')
<style>
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
</style>
<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Add User</h4>
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
        <!-- <div class="row">
                <div class="col-lg-2 col-sm-4 col-xs-12">
                    <a href="{{ route('add_user.create') }}"><button class="btn btn-block btn-info btn-rounded">User Information</button></a>
                </div>
                <div class="col-lg-2 col-sm-4 col-xs-12">
                    <a href="{{ route('add_user_past_education.index') }}"><button class="btn btn-block btn-default btn-rounded">Past Education</button></a>
                </div>
                <div class="col-lg-2 col-sm-4 col-xs-12">
                    <button class="btn btn-block btn-default btn-rounded">Primary</button>
                </div>
                <div class="col-lg-2 col-sm-4 col-xs-12">
                    <button class="btn btn-block btn-default btn-rounded">Success</button>
                </div>
                <div class="col-lg-2 col-sm-4 col-xs-12">
                    <button class="btn btn-block btn-default btn-rounded">Danger</button>
                </div>
            </div>
            <br> -->
            <form action="{{ route('add_user.store') }}" enctype="multipart/form-data" method="post">
                {{ method_field("POST") }}
                @csrf
                <div class="row">
                    <div class="col-md-4 form-group">
                        <label>Name Suffix</label>
                        <select name="name_suffix" id="name_suffix" class="form-control" required>
                            <option> Select Name Suffix</option>
                            <option value="Mr."> Mr.</option>
                            <option value="Mrs."> Mrs.</option>
                            <option value="Miss."> Miss.</option>
                        </select>
                    </div>
                    <div class="col-md-4 form-group">
                        <label>First Name </label>
                        <input type="text" id='first_name' required name="first_name" class="form-control">
                    </div>
                    <div class="col-md-4 form-group">
                        <label>Middle Name</label>
                        <input type="text" id='middle_name' required name="middle_name" class="form-control">
                    </div>
                    <div class="col-md-4 form-group">
                        <label>Last Name</label>
                        <input type="text" id='last_name' onchange="getUsername();" required name="last_name"
                               class="form-control">
                    </div>
                    <div class="col-md-4 form-group">
                        <label>User Name </label>
                        <input type="text" id='user_name' required name="user_name" class="form-control">
                    </div>
                    <div class="col-md-4 form-group">
                        <label>Email</label>
                        <input type="text" id='email' required name="email" class="form-control">
                        <span id="email_error_span"></span>
                    </div>
                    <div class="col-md-4 form-group">
                        <label>Mobile</label>
                        <input type="text" id='mobile' required name="mobile" class="form-control">
                    </div>
                    <div class="col-md-4 form-group">
                        <label>Subject</label>
                        <select name="subject_ids[]" id="subject_ids[]" class="form-control" multiple>
                            <option value="0"> Select Subject</option>
                            @if(!empty($subject_data))
                                @foreach($subject_data as $key => $val)
                                    <option value="{{ $val['id'] }}"> {{ $val['subject_name'] }} </option>
                                @endforeach
                            @endif
                        </select>
                    </div>
                    <div class="col-md-4 form-group">
                        <label>Address</label>
                        <textarea class="form-control" required name="address"></textarea>
                    </div>
                    <div class="col-md-4 form-group">
                        <label class="control-label">Gender</label>
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
                        </div>
                    </div>
                    <div class="col-md-4 form-group">
                        <label>City</label>
                        <input type="text" id='city' required name="city" class="form-control">
                    </div>
                    <div class="col-md-4 form-group">
                        <label>State</label>
                        <input type="text" id='state' required name="state" class="form-control">
                    </div>
                    <div class="col-md-4 form-group">
                        <label>Pincode</label>
                        <input type="number" id='pincode' required name="pincode" class="form-control">
                    </div>
                    <div class="col-md-4 form-group">
                        <label>User Profile</label>
                        <select name="user_profile_id" required id="user_profile_id" class="form-control">
                            <option value="0"> Select Parent Profile</option>

                            @if(!empty($user_profiles))
                                @foreach($user_profiles as $key => $value)

                                    <option value="{{ $value['id'] }}"> {{ $value['name'] }} </option>
                                @endforeach
                            @endif
                        </select>
                    </div>
                    <div class="col-md-4 form-group" id="total_lecture_div">
                        <label>Total Lectures</label>
                        <input type="number" id='total_lecture' name="total_lecture" class="form-control">
                    </div>
                    <div class="col-md-4 form-group">
                        <label>Join Year</label>
                        <input type="number" id='join_year' required name="join_year" class="form-control">
                    </div>
                    <div class="col-md-4 form-group">
                        <label>Password</label>
                        <input type="password" id='password' required name="password" class="form-control">
                    </div>
                    <div class="col-md-4 form-group ml-0 mr-0">
                        <label>Birthdate</label>
                        <div class="input-daterange input-group" id="date-range">
                            <input type="text" required class="form-control mydatepicker" placeholder="dd/mm/yyyy"
                                   name="birthdate" autocomplete="off"><span class="input-group-addon"><i
                                    class="icon-calender"></i></span>
                        </div>
                    </div>
                    <div class="col-sm-4 ol-md-4 col-xs-12">
                        <label for="input-file-now">User Image</label>
                        <input type="file" accept="image/*" name="user_image" id="input-file-now" class="dropify"/>
                    </div>
                    @if(isset($custom_fields))
                        @foreach($custom_fields as $key => $value)
                            <div class="col-md-4 form-group">
                                <label>{{ $value['field_label'] }}</label>
                                @if($value['field_type'] == 'file')
                                    <input type="{{ $value['field_type'] }}" accept="image/*" id="input-file-now"
                                           required name="{{ $value['field_name'] }}" class="form-control">
                                @elseif($value['field_type'] == 'date')
                                    <div class="input-daterange input-group">
                                        <input type="text" class="form-control mydatepicker" placeholder="dd/mm/yyyy"
                                               autocomplete="off" id="{{ $value['field_name'] }}" required
                                               name="{{ $value['field_name'] }}" class="form-control"><span
                                            class="input-group-addon"><i class="icon-calender"></i></span>
                                    </div>
                                @elseif($value['field_type'] == 'checkbox')
                                    <div class="checkbox-list">
                                        @if(isset($data_fields[$value['id']]))
                                            @foreach($data_fields[$value['id']] as $keyData => $valueData )
                                                <label class="checkbox-inline">
                                                    <div class="checkbox checkbox-success">
                                                        <input type="checkbox" name="{{ $value['field_name'] }}[]"
                                                               value="{{ $valueData['display_value'] }}"
                                                               id="{{ $valueData['display_value'] }}" required>
                                                        <label
                                                            for="{{ $valueData['display_value'] }}">{{ $valueData['display_text'] }}</label>
                                                    </div>
                                                </label>
                                            @endforeach
                                        @endif
                                    </div>
                                @elseif($value['field_type'] == 'dropdown')
                                    <select name="{{ $value['field_name'] }}" class="form-control" required
                                            id="{{ $value['field_name'] }}">
                                        <option value=""> SELECT {{ strtoupper($value['field_label']) }} </option>

                                        @if(isset($data_fields[$value['id']]))
                                            @foreach($data_fields[$value['id']] as $keyData => $valueData)
                                                <option
                                                    value="{{ $valueData['display_value'] }}"> {{ $valueData['display_text'] }} </option>
                                            @endforeach
                                        @endif
                                    </select>
                                @elseif($value['field_type'] == 'textarea')
                                    <textarea id="{{ $value['field_name'] }}" class="form-control" required
                                              name="{{ $value['field_name'] }}"
                                              placeholder="{{ $value['field_message'] }}">
                        </textarea>
                                @else
                                    <input type="{{ $value['field_type'] }}" id="{{ $value['field_name'] }}"
                                           placeholder="{{ $value['field_message'] }}" required
                                           name="{{ $value['field_name'] }}" class="form-control">
                                @endif
                            </div>
                        @endforeach
                    @endif

                    <div class="col-md-4 form-group">
                        <label>Job Title Id</label>
                        <select id='jobtitle_id' name="jobtitle_id" class="form-control">
                            <option value="0">Select Title</option>
                            @foreach($job_titles as $title)
                                    <option value="{{$title->id}}">{{$title->title}}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-4 form-group">
                        <label>Department Id</label>
                        <input type="text" id='department_id' name="department_id" class="form-control">
                    </div>

                    <div class="col-md-4 form-group">
                        <label>Joining Date</label>
                        <input type="date" id='joined_date' name="joined_date" class="form-control">
                    </div>
                    <div class="col-md-4 form-group">
                        <label>Probation Period From</label>
                        <input type="date" id='probation_period_from' name="probation_period_from" class="form-control">
                    </div>

                    <div class="col-md-4 form-group">
                        <label>Probation Period To</label>
                        <input type="date" id='probation_period_to' name="probation_period_to" class="form-control">
                    </div>

                    <div class="col-md-4 form-group">
                        <label>Terminated Date</label>
                        <input type="date" id='terminated_date' name="terminated_date" class="form-control">
                    </div>
                    <div class="col-md-4 form-group">
                        <label>Termination Reason</label>
                        <input type="text" id='termination_reason' name="termination_reason" class="form-control">
                    </div>

                    <div class="col-md-4 form-group">
                        <label>Notice From Date</label>
                        <input type="date" id='notice_fromdate' name="notice_fromdate" class="form-control">
                    </div>
                    <div class="col-md-4 form-group">
                        <label>Notice To Date</label>
                        <input type="date" id='notice_todate' name="notice_todate" class="form-control">
                    </div>

                    <div class="col-md-4 form-group">
                        <label>Notice Reason</label>
                        <input type="text" id='noticereason' name="noticereason" class="form-control">
                    </div>
                    <div class="col-md-4 form-group">
                        <label>Opening Leave</label>
                        <input type="text" id='openingleave' name="openingleave" class="form-control">
                    </div>

                    <div class="col-md-4 form-group">
                        <label>Relieving Date</label>
                        <input type="date" id='relieving_date' name="relieving_date" class="form-control">
                    </div>

                    <div class="col-md-4 form-group">
                        <label>Relieving Reason</label>
                        <input type="text" id='relieving_reason' name="relieving_reason" class="form-control">
                    </div>
                    <div class="col-md-4 form-group">
                        <label>CL Opening Leave</label>
                        <input type="text" id='CL_opening_leave' name="CL_opening_leave" class="form-control">
                    </div>

                    <div class="col-md-12 form-group">
                        <h4>Report To</h4>
                    </div>

                    <div class="col-md-4 form-group">
                        <label>Supervisor / Subordinate</label>
                        <select id='supervisor_opt' name="supervisor_opt" class="form-control">
                            <option value="Supervisor">Supervisor</option>
                            <option value="Subordinate">Subordinate</option>
                        </select>
                    </div>

                    <div class="col-md-4 form-group">
                        <label>Employee Name</label>
                        <select id='employee_id' name="employee_id" class="form-control">
                            <option value="">Select Employee</option>
                            @foreach($employees as $title)
                                    <option value="{{$title->id}}">{{$title->first_name .' ' . $title->last_name}}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-4 form-group">
                        <label>Reporting Method</label>
                        <select id='reporting_method' name="reporting_method" class="form-control">
                            <option value="Direct">Direct</option>
                            <option value="Indirect">In Direct</option>
                        </select>
                    </div>

                    <div class="col-md-12 form-group">
                        <h4>Direct Deposit</h4>
                    </div>

                    <div class="col-md-4 form-group">
                        <label>Bank Name</label>
                        <input type="text" id='bank_name' name="bank_name" class="form-control">
                    </div>

                    <div class="col-md-4 form-group">
                        <label>Branch Name</label>
                        <input type="text" id='branch_name' name="branch_name" class="form-control">
                    </div>

                    <div class="col-md-4 form-group">
                        <label>Account</label>
                        <input type="text" id='account_no' name="account_no" class="form-control">
                    </div>
                    <div class="col-md-4 form-group">
                        <label>IFSC</label>
                        <input type="text" id='ifsc_code' name="ifsc_code" class="form-control">
                    </div>

                    <div class="col-md-4 form-group">
                        <label>Amount</label>
                        <input type="text" id='amount' name="amount" class="form-control">
                    </div>
                    <div class="col-md-4 form-group">
                        <label>Transfer Type</label>
                        <select id='transfer_type' name="transfer_type" class="form-control">
                            <option value="Direct">Direct</option>
                            <option value="Indirect">In Direct</option>
                        </select>
                    </div>

                    <div class="col-md-12 form-group">
                        <h4>Off Days</h4>
                    </div>
                    <div class="col-md-1 form-group">
                        <label>Mon</label>
                        <input type="checkbox" id='monday' name="monday"  value="1" class="">
                    </div>
                    <div class="col-md-1 form-group">
                        <label>Tue</label>
                        <input type="checkbox" id='tuesday' name="tuesday" value="1" class="">
                    </div>
                    <div class="col-md-1 form-group">
                        <label>Wed</label>
                        <input type="checkbox" id='wednesday' name="wednesday" value="1" class="">
                    </div>
                    <div class="col-md-1 form-group">
                        <label>Thu</label>
                        <input type="checkbox" id='thursday' name="thursday" value="1" class="">
                    </div>
                    <div class="col-md-1 form-group">
                        <label>Fri</label>
                        <input type="checkbox" id='friday' name="friday" value="1" class="">
                    </div>
                    <div class="col-md-1 form-group">
                        <label>Sat</label>
                        <input type="checkbox" id='saturday' name="saturday" value="1" class="">
                    </div>
                    <div class="col-md-1 form-group">
                        <label>Sun</label>
                        <input type="checkbox" id='sunday' name="sunday" value="1" class="">
                    </div>

                    <div class="col-md-6 form-group">
                        <label>Monday In Date</label>
                        <input type="time" id='monday_in_date' name="monday_in_date" class="form-control">
                    </div>
                    <div class="col-md-6 form-group">
                        <label>Monday Out Date</label>
                        <input type="time" id='monday_out_date' name="monday_out_date" class="form-control">
                    </div>

                    <div class="col-md-6 form-group">
                        <label>Tuesday In Date</label>
                        <input type="time" id='tuesday_in_date' name="tuesday_in_date" class="form-control">
                    </div>
                    <div class="col-md-6 form-group">
                        <label>Tuesday Out Date</label>
                        <input type="time" id='tuesday_out_date' name="tuesday_out_date" class="form-control">
                    </div>

                    <div class="col-md-6 form-group">
                        <label>Wednesday In Date</label>
                        <input type="time" id='wednesday_in_date' name="wednesday_in_date" class="form-control">
                    </div>
                    <div class="col-md-6 form-group">
                        <label>Wednesday Out Date</label>
                        <input type="time" id='wednesday_out_date' name="wednesday_out_date" class="form-control">
                    </div>

                    <div class="col-md-6 form-group">
                        <label>Thursday In Date</label>
                        <input type="time" id='thursday_in_date' name="thursday_in_date" class="form-control">
                    </div>
                    <div class="col-md-6 form-group">
                        <label>Thursday Out Date</label>
                        <input type="time" id='thursday_out_date' name="thursday_out_date" class="form-control">
                    </div>

                    <div class="col-md-6 form-group">
                        <label>Friday In Date</label>
                        <input type="time" id='friday_in_date' name="friday_in_date" class="form-control">
                    </div>
                    <div class="col-md-6 form-group">
                        <label>Friday Out Date</label>
                        <input type="time" id='friday_out_date' name="friday_out_date" class="form-control">
                    </div>

                    <div class="col-md-6 form-group">
                        <label>Saturday In Date</label>
                        <input type="time" id='saturday_in_date' name="saturday_in_date" class="form-control">
                    </div>
                    <div class="col-md-6 form-group">
                        <label>Saturday Out Date</label>
                        <input type="time" id='saturday_out_date' name="saturday_out_date" class="form-control">
                    </div>

                    <div class="col-md-6 form-group">
                        <label>Sunday In Date</label>
                        <input type="time" id='sunday_in_date' name="sunday_in_date" class="form-control">
                    </div>
                    <div class="col-md-6 form-group">
                        <label>Sunday Out Date</label>
                        <input type="time" id='sunday_out_date' name="sunday_out_date" class="form-control">
                    </div>

                    <div class="col-md-12 form-group">
                        <center>
                            <input type="submit" name="submit" id="Submit" value="Save" class="btn btn-success">
                        </center>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

@include('includes.footerJs')
<script src="../../../admin_dep/js/cbpFWTabs.js"></script>
<script type="text/javascript">
    (function () {
        [].slice.call(document.querySelectorAll('.sttabs')).forEach(function (el) {
            new CBPFWTabs(el);
        });
    })();
</script>
<script src="../../../plugins/bower_components/dropify/dist/js/drsopify.min.js"></script>
<script>
    $(document).ready(function () {
        $("#total_lecture_div").css("display", "none");

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
        drEvent.on('dropify.beforeClear', function (event, element) {
            return confirm("Do you really want to delete \"" + element.file.name + "\" ?");
        });
        drEvent.on('dropify.afterClear', function (event, element) {
            alert('File deleted');
        });
        drEvent.on('dropify.errors', function (event, element) {
            console.log('Has Errors');
        });
        var drDestroy = $('#input-file-to-destroy').dropify();
        drDestroy = drDestroy.data('dropify')
        $('#toggleDropify').on('click', function (e) {
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
    function getUsername() {
        var first_name = document.getElementById("first_name").value;
        var last_name = document.getElementById("last_name").value;
        var username = first_name.toLowerCase() + "_" + last_name.toLowerCase();
        document.getElementById("user_name").value = username;
    }


    //START Unique Email Validation
    var email_state = false;
    $("#email").on("blur", function (event) {
        email_val = this.value;
        var path = "{{ route('ajax_checkEmailExist') }}";
        $.ajax({
            url: path,
            data: 'email=' + email_val,
            success: function (result) {
                if (result == 1) {
                    $("#email_error_span").removeClass().addClass("email_error").text('Email already taken');
                    email_state = true;
                } else {
                    $("#email_error_span").removeClass().addClass("email_success").text('Email available');
                    email_state = false;
                }
            }
        });
    });
    //END Unique Email Validation

    $("#user_profile_id").on("change", function (event) {
        var val1 = $.trim($("#user_profile_id").find("option:selected").text());

        if (val1 == 'Teacher' || val1 == 'TEACHER') {
            $("#total_lecture_div").css("display", "block");
        } else {
            $("#total_lecture_div").css("display", "none");
        }
    });

    $('#Submit').on('click', function () {

        if (email_state == true) {
            alert('Fix the errors in the form first');
            return false;
        }

    });


</script>
@include('includes.footer')
