{{--@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')--}}
@extends('layout')
@section('container')
<div id="page-wrapper">
    <div class="container-fluid">
        <div class=" bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Online Admission Confirmation</h4>
            </div>
        </div>
        <div class="card">
            @if ($sessionData = Session::get('data'))
                <div class="alert alert-success alert-block">
                    <button type="button" class="close" data-dismiss="alert">×</button>
                    <strong>{{ $sessionData['message'] }}</strong>
                </div>
                @endif
                @if (isset($data['message']) && $data['message'] != 'Success')
                <div class="alert alert-success alert-block">
                    <button type="button" class="close" data-dismiss="alert">×</button>
                    <strong>{{ $data['message'] }}</strong>
                </div>
                @endif
                <form action="{{ route('online_admission_confirm.create') }}">
                    <div class="row">
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Token Number</label>
                                <input type="text" name="token_no" class="form-control"
                                       placeholder="Please Enter Token No." value="@if(isset($data['token_no'])) {{$data['token_no']}} @endif">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <input type="submit" name="submit" value="Search" class="btn btn-success" >
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        @if(isset($data['student_data']) && count($data['student_data'])>0)
            @php
                if(isset($data['student_data'])){
                    $student_data = $data['student_data'];
                    $finalData = $data;
                }
                $for_admin_disable = $for_principal_disable = $for_account_disable = '';
                if(Session::get('user_profile_name') == 'Admin')
                {
                    $for_admin_disable = 'disabled';
                }

                if(Session::get('user_profile_name') == 'Principal')
                {
                    $for_principal_disable = 'disabled';
                }

                if(Session::get('user_profile_name') == 'Assistant Admin')
                {
                    $for_account_disable = 'disabled';
                }
        @endphp
            <div class="card">
                <form method="POST" enctype="multipart/form-data" action="{{ route('online_admission_confirm.store') }}">
                    @csrf
                    <div class="table-responsive">
                        <table id="example1" class="display nowrap table table-hover table-striped table-bordered dataTable">
                            <thead>
                                <tr>
                                    <th><input id="checkall" onchange="checkAll(this);" type="checkbox"></th>
                                    <th data-toggle="tooltip" data-placement="top" title="Admin Approval">Admin Approval</th>
                                    <th data-toggle="tooltip" data-placement="top" title="Principal Approval">Principal Approval</th>
                                    <th data-toggle="tooltip" data-placement="top" title="Account Approval">Account Approval</th>
                                    <th data-toggle="tooltip" data-placement="top" title="Syear">Syear</th>
                                    <th data-toggle="tooltip" data-placement="top" title="Admission Std">Admission Std</th>
                                    <th data-toggle="tooltip" data-placement="top" title="Date of Birth">Date of Birth</th>
                                    <th data-toggle="tooltip" data-placement="top" title="Age">Age</th>
                                    <th data-toggle="tooltip" data-placement="top" title="Mobile">Mobile</th>
                                    <th data-toggle="tooltip" data-placement="top" title="Address">Address</th>
                                    <th data-toggle="tooltip" data-placement="top" title="Student Name">Student Name</th>
                                    <th data-toggle="tooltip" data-placement="top" title="Father Name">Father Name</th>
                                    <th data-toggle="tooltip" data-placement="top" title="Email">Email</th>
                                    <th data-toggle="tooltip" data-placement="top" title="Father Aadhaar">Father Aadhaar</th>
                                    <th data-toggle="tooltip" data-placement="top" title="Mother Aadhaar">Mother Aadhaar</th>
                                    <th data-toggle="tooltip" data-placement="top" title="Sibling Details">Sibling Details</th>
                                    <th data-toggle="tooltip" data-placement="top" title="Token">Token</th>
                                    <th data-toggle="tooltip" data-placement="top" title="Admission for child/twins">Admission for child/twins</th>
                                    <th data-toggle="tooltip" data-placement="top" title="Birth Place">Birth Place</th>
                                    <th data-toggle="tooltip" data-placement="top" title="Town">Town</th>
                                    <th data-toggle="tooltip" data-placement="top" title="District">District</th>
                                    <th data-toggle="tooltip" data-placement="top" title="State">State</th>
                                    <th data-toggle="tooltip" data-placement="top" title="Citizenship">Citizenship</th>
                                    <th data-toggle="tooltip" data-placement="top" title="Gender">Gender</th>
                                    <th data-toggle="tooltip" data-placement="top" title="Cast">Cast</th>
                                    <th data-toggle="tooltip" data-placement="top" title="Sub Cast">Sub Cast</th>
                                    <th data-toggle="tooltip" data-placement="top" title="Religion">Religion</th>
                                    <th data-toggle="tooltip" data-placement="top" title="Mother Tongue">Mother Tongue</th>
                                    <th data-toggle="tooltip" data-placement="top" title="Language spoken at home">Language spoken at home</th>
                                    <th data-toggle="tooltip" data-placement="top" title="Other language spoken">Other language spoken</th>
                                    <th data-toggle="tooltip" data-placement="top" title="Whether a Member Of Scheduled caste or Community Classified as Backward  class or tribe by the state govt">Whether a Member Of Scheduled caste or Community Classified as Backward  class or tribe by the state govt</th>
                                    <th data-toggle="tooltip" data-placement="top" title="House No & Building Name">House No & Building Name</th>
                                    <th data-toggle="tooltip" data-placement="top" title="Area">Area</th>
                                    <th data-toggle="tooltip" data-placement="top" title="City">City</th>
                                    <th data-toggle="tooltip" data-placement="top" title="State">State</th>
                                    <th data-toggle="tooltip" data-placement="top" title="Pin-Code">Pin-Code</th>
                                    <th data-toggle="tooltip" data-placement="top" title="Blood Group">Blood Group</th>
                                    <th data-toggle="tooltip" data-placement="top" title="Height In Cms">Height In Cms</th>
                                    <th data-toggle="tooltip" data-placement="top" title="Weight In Kgs">Weight In Kgs</th>
                                    <th data-toggle="tooltip" data-placement="top" title="Has the child given all the vaccination?">Has the child given all the vaccination?</th>
                                    <th data-toggle="tooltip" data-placement="top" title="Family History Of illness(Diabetics)">Family History Of illness(Diabetics)</th>
                                    <th data-toggle="tooltip" data-placement="top" title="Family History Of illness(Blood Pressure)">Family History Of illness(Blood Pressure)</th>
                                    <th data-toggle="tooltip" data-placement="top" title="Was the child admitted to hospital at any time?">Was the child admitted to hospital at any time?</th>
                                    <th data-toggle="tooltip" data-placement="top" title="If YES then reason">If YES then reason</th>
                                    <th data-toggle="tooltip" data-placement="top" title="how long">how long</th>
                                    <th data-toggle="tooltip" data-placement="top" title="Does the child have identified allergies if so, give details">Does the child have identified allergies if so, give details</th>
                                    <th data-toggle="tooltip" data-placement="top" title="Habit Of Bed Wetting?">Habit Of Bed Wetting?</th>
                                    <th data-toggle="tooltip" data-placement="top" title="Habit Of Thumb Sucking?">Habit Of Thumb Sucking?</th>
                                    <th data-toggle="tooltip" data-placement="top" title="Habit Of Anti Acid Activity?">Habit Of Anti Acid Activity?</th>
                                    <th data-toggle="tooltip" data-placement="top" title="Habit Of Drug Allergy?">Habit Of Drug Allergy?</th>
                                    <th data-toggle="tooltip" data-placement="top" title="Is the child too much dependent on parents?">Is the child too much dependent on parents?</th>
                                    <th data-toggle="tooltip" data-placement="top" title="Mention behavioral problems if any">Mention behavioral problems if any</th>
                                    <th data-toggle="tooltip" data-placement="top" title="Is the child taking plain milk regularly?">Is the child taking plain milk regularly?</th>
                                    <th data-toggle="tooltip" data-placement="top" title="Does the child take curd?">Does the child take curd?</th>
                                    <th data-toggle="tooltip" data-placement="top" title="Is the child taking all vegetables?">Is the child taking all vegetables?</th>
                                    <th data-toggle="tooltip" data-placement="top" title="Father Name">Father Name</th>
                                    <th data-toggle="tooltip" data-placement="top" title="Father Date Of Birth">Father Date Of Birth</th>
                                    <th data-toggle="tooltip" data-placement="top" title="Father Qualification">Father Qualification</th>
                                    <th data-toggle="tooltip" data-placement="top" title="Father Blood Group">Father Blood Group</th>
                                    <th data-toggle="tooltip" data-placement="top" title="Father Occupation">Father Occupation</th>
                                    <th data-toggle="tooltip" data-placement="top" title="Name of the Organization(Father)">Name of the Organization(Father)</th>
                                    <th data-toggle="tooltip" data-placement="top" title="Father Designation">Father Designation</th>
                                    <th data-toggle="tooltip" data-placement="top" title="Father Business Office Address">Father Business Office Address</th>
                                    <th data-toggle="tooltip" data-placement="top" title="Father Mobile No">Father Mobile No</th>
                                    <th data-toggle="tooltip" data-placement="top" title="Father Email">Father Email</th>
                                    <th data-toggle="tooltip" data-placement="top" title="Father Gross Annual Income">Father Gross Annual Income</th>
                                    <th data-toggle="tooltip" data-placement="top" title="Mother Name">Mother Name</th>
                                    <th data-toggle="tooltip" data-placement="top" title="Mother Date Of Birth">Mother Date Of Birth</th>
                                    <th data-toggle="tooltip" data-placement="top" title="Mother Qualification">Mother Qualification</th>
                                    <th data-toggle="tooltip" data-placement="top" title="Mother Blood Group">Mother Blood Group</th>
                                    <th data-toggle="tooltip" data-placement="top" title="Mother Occupation">Mother Occupation</th>
                                    <th data-toggle="tooltip" data-placement="top" title="Mother Name of the Organization?">Mother Name of the Organization?</th>
                                    <th data-toggle="tooltip" data-placement="top" title="Mother Designation">Mother Designation</th>
                                    <th data-toggle="tooltip" data-placement="top" title="Mother Business Office Address">Mother Business Office Address</th>
                                    <th data-toggle="tooltip" data-placement="top" title="Mother Mobile No">Mother Mobile No</th>
                                    <th data-toggle="tooltip" data-placement="top" title="Mother Email">Mother Email</th>
                                    <th data-toggle="tooltip" data-placement="top" title="Mother Gross Annual Income">Mother Gross Annual Income</th>
                                    <th data-toggle="tooltip" data-placement="top" title="Guardian Full Name">Guardian Full Name</th>
                                    <th data-toggle="tooltip" data-placement="top" title="Guardian Address">Guardian Address</th>
                                    <th data-toggle="tooltip" data-placement="top" title="Guardian Mobile No">Guardian Mobile No</th>
                                    <th data-toggle="tooltip" data-placement="top" title="Guardian Email">Guardian Email</th>
                                    <th data-toggle="tooltip" data-placement="top" title="Relationship with the child">Relationship with the child</th>
                                    <th data-toggle="tooltip" data-placement="top" title="Sibling1 Name">Sibling1 Name</th>
                                    <th data-toggle="tooltip" data-placement="top" title="Sibling1 Date Of Birth">Sibling1 Date Of Birth</th>
                                    <th data-toggle="tooltip" data-placement="top" title="Sibling1 Education">Sibling1 Education</th>
                                    <th data-toggle="tooltip" data-placement="top" title="Sibling1 School/College">Sibling1 School/College</th>
                                    <th data-toggle="tooltip" data-placement="top" title="Sibling2 Name">Sibling2 Name</th>
                                    <th data-toggle="tooltip" data-placement="top" title="Sibling2 Date Of Birth">Sibling2 Date Of Birth</th>
                                    <th data-toggle="tooltip" data-placement="top" title="Sibling2 Education">Sibling2 Education</th>
                                    <th data-toggle="tooltip" data-placement="top" title="Sibling2 School/College">Sibling2 School/College</th>
                                    <th data-toggle="tooltip" data-placement="top" title="Birth Certificate">Birth Certificate</th>
                                    <th data-toggle="tooltip" data-placement="top" title="Student’s Aadhar Card">Student’s Aadhar Card</th>
                                    <th data-toggle="tooltip" data-placement="top" title="Student’s Cast Certificate (if applicable)">Student’s Cast Certificate (if applicable)</th>
                                    <th data-toggle="tooltip" data-placement="top" title="Father’s Cast Certificate">Father’s Cast Certificate</th>
                                    <th data-toggle="tooltip" data-placement="top" title="Passport Size Photo Of Student">Passport Size Photo Of Student</th>
                                    <th data-toggle="tooltip" data-placement="top" title="Post Card Size Family Photograph (Latest)">Post Card Size Family Photograph (Latest)</th>
                                    <th data-toggle="tooltip" data-placement="top" title="Vaccination Record">Vaccination Record</th>
                                    <th data-toggle="tooltip" data-placement="top" title="Medical Examination Report Certified by your Family Doctor">Medical Examination Report Certified by your Family Doctor</th>
                                    <th data-toggle="tooltip" data-placement="top" title="Self Attested Copy Of Father Aadhar Card">Self Attested Copy Of Father Aadhar Card</th>
                                    <th data-toggle="tooltip" data-placement="top" title="Self Attested Copy Of Mother Aadhar Card">Self Attested Copy Of Mother Aadhar Card</th>
                                    <th data-toggle="tooltip" data-placement="top" title="Address Proof (Ration Card/Agreement)">Address Proof (Ration Card/Agreement)</th>
                                    <th data-toggle="tooltip" data-placement="top" title="Father Signature">Father Signature</th>
                                    <th data-toggle="tooltip" data-placement="top" title="Mother Signature">Mother Signature</th>
                                </tr>
                            </thead>
                            <tbody>
                                    @php
                                    $j=1;
                                    $not_varified_by_admin = $not_varified_by_principal = '';
                                    @endphp
                                @foreach($student_data as $key => $data)

                                    @php
                                    $not_varified_by_admin = $not_varified_by_principal = '';
                                    if(Session::get('user_profile_name') == 'Principal' && $data->admin_status != 'Verified')
                                    {
                                        $not_varified_by_admin = 'disabled';
                                    }

                                    if(Session::get('user_profile_name') == 'Assistant Admin' && $data->principal_status != 'Approved')
                                    {
                                        $not_varified_by_principal = 'disabled';
                                    }
                                    @endphp
                                    <tr>
                                        <td>
                                            <input id="{{$data->CHECKBOX}}" value="{{$data->CHECKBOX}}"
                                                   name="students[]" type="checkbox">
                                        </td>
                                        <td>
                                            <select class="form-control" id="admin_status[{{$data->CHECKBOX}}]"
                                                    name="admin_status[{{$data->CHECKBOX}}]"
                                                    style="width: 100px !important;"
                                                    @php echo $for_principal_disable; @endphp
                                                    @php echo $for_account_disable; @endphp
                                                    @if($data->admin_status == 'Verified') disabled @endif
                                            >
                                                <option>Select Status</option>
                                                <option value="Verified"
                                                        @if($data->admin_status == 'Verified') selected=selected @endif>
                                                    Verified
                                                </option>
                                            </select>
                                        </td>
                                        <td>

                                            <select class="form-control" id="principal_status[{{$data->CHECKBOX}}]"
                                                    name="principal_status[{{$data->CHECKBOX}}]"
                                                    style="width: 100px !important;"
                                                    @php echo $for_admin_disable; @endphp
                                                    @php echo $for_account_disable;
                                            echo $not_varified_by_admin;
                                                    @endphp
                                                    @if(isset($data->principal_status)) disabled @endif>
                                                <option>Select Status</option>
                                                <option value="Approved"
                                                        @if($data->principal_status == 'Approved') selected=selected @endif>
                                                    Approved
                                                </option>
                                                <option value="Not Approved"
                                                        @if($data->principal_status == 'Not Approved') selected=selected @endif>
                                                    Not Approved
                                                </option>
                                            </select>
                                        </td>
                                        <td>
                                            <select class="form-control" id="account_status[{{$data->CHECKBOX}}]"
                                                    name="account_status[{{$data->CHECKBOX}}]"
                                                    style="width: 100px !important;"
                                                    @php echo $for_admin_disable; echo $not_varified_by_principal; @endphp
                                                    @php echo $for_principal_disable; @endphp
                                                    @if(isset($data->account_status)) disabled @endif>
                                                <option>Select Status</option>
                                                <option value="Confirm"
                                                        @if($data->account_status == 'Confirm') selected=selected @endif>
                                                    Confirm
                                                </option>
                                                <option value="Cancel"
                                                        @if($data->account_status == 'Cancel') selected=selected @endif>
                                                    Cancel
                                                </option>
                                            </select>
                                        </td>
                                        <td><a target="blank"
                                               href="https://erp.triz.co.in/New_Admission/other_details.php?token_no={{$data->token}}"
                                               style="color: #007bff;">{{$data->token}}</a></td>
                                        <td>{{$data->child_name}}</td>
                                        <td>{{$data->syear}}</td>
                                        <td>{{$data->admission_std}}</td>
                                    <td>{{$data->date_of_birth}}</td>
                                    <td>{{$data->age}}</td>
                                    <td>{{$data->mobile}}</td>
                                    <td>{{$data->address}}</td>

                                    <td>{{$data->father_name}}</td>
                                    <td>{{$data->mail}}</td>
                                    <td>{{$data->father_adhar}}</td>
                                    <td>{{$data->mother_adhar}}</td>
                                    <td>{{$data->sibling_details}}</td>
                                    <td>{{$data->admission_for_child_twins}}</td>
                                    <td>{{$data->birth_place}}</td>
                                    <td>{{$data->town}}</td>
                                    <td>{{$data->district}}</td>
                                    <td>{{$data->state}}</td>
                                    <td>{{$data->citizenship}}</td>
                                    <td>{{$data->gender}}</td>
                                    <td>{{$data->cast}}</td>
                                    <td>{{$data->sub_cast}}</td>
                                    <td>{{$data->religion}}</td>
                                    <td>{{$data->mother_tongue}}</td>
                                    <td>{{$data->language_spoken_at_home}}</td>
                                    <td>{{$data->other_language_spoken}}</td>
                                    <td>{{$data->backward_class}}</td>
                                    <td>{{$data->house_no}}</td>
                                    <td>{{$data->area}}</td>
                                    <td>{{$data->city}}</td>
                                    <td>{{$data->pin_code}}</td>
                                    <td>{{$data->blood_group}}</td>
                                    <td>{{$data->height}}</td>
                                    <td>{{$data->weight}}</td>
                                    <td>{{$data->vaccination}}</td>
                                    <td>{{$data->diabetes}}</td>
                                    <td>{{$data->blood_pressure}}</td>
                                    <td>{{$data->child_admitted}}</td>
                                    <td>{{$data->if_yes_then_reason}}</td>
                                    <td>{{$data->how_long}}</td>
                                    <td>{{$data->child_allergies}}</td>
                                    <td>{{$data->habit_of_bed_wetting}}</td>
                                    <td>{{$data->habit_of_thumb_sucking}}</td>
                                    <td>{{$data->habit_of_anti_acid_activity}}</td>
                                    <td>{{$data->habit_of_drug_allergy}}</td>
                                    <td>{{$data->child_dependent}}</td>
                                    <td>{{$data->behavioral_problem}}</td>
                                    <td>{{$data->child_taking_milk}}</td>
                                    <td>{{$data->child_taking_curd}}</td>
                                    <td>{{$data->child_taking_vegetables}}</td>
                                    <td>{{$data->father_dob}}</td>
                                    <td>{{$data->father_qualification}}</td>
                                    <td>{{$data->father_blood_group}}</td>
                                    <td>{{$data->father_occupation}}</td>
                                    <td>{{$data->father_organization_name}}</td>
                                    <td>{{$data->father_designation}}</td>
                                    <td>{{$data->father_office_address}}</td>
                                    <td>{{$data->father_email}}</td>
                                    <td>{{$data->father_income}}</td>
                                    <td>{{$data->mother_name}}</td>
                                    <td>{{$data->mother_dob}}</td>
                                    <td>{{$data->mother_qualification}}</td>
                                    <td>{{$data->mother_blood_group}}</td>
                                    <td>{{$data->mother_occupation}}</td>
                                    <td>{{$data->mother_organization_name}}</td>
                                    <td>{{$data->mother_designation}}</td>
                                    <td>{{$data->mother_mobile_no}}</td>
                                    <td>{{$data->mother_email}}</td>
                                    <td>{{$data->mother_income}}</td>
                                    <td>{{$data->guardian_name}}</td>
                                    <td>{{$data->guardian_address}}</td>
                                    <td>{{$data->guardian_mobile_no}}</td>
                                    <td>{{$data->guardian_email}}</td>
                                    <td>{{$data->guardian_relation_with_child}}</td>
                                    <td>{{$data->sibling1_name}}</td>
                                    <td>{{$data->sibling1_dob}}</td>
                                    <td>{{$data->sibling1_education}}</td>
                                    <td>{{$data->sibling1_college}}</td>
                                    <td>{{$data->sibling2_name}}</td>
                                    <td>{{$data->sibling2_dob}}</td>
                                    <td>{{$data->sibling2_education}}</td>
                                    <td>{{$data->sibling2_college}}</td>
                                    <td><a target="blank" href="../../../../storage/student_document/{{$data->birth_certificate}}">{{$data->birth_certificate}}</a></td>
                                    <td><a target="blank" href="../../../../storage/student_document/{{$data->student_adharcard}}"></td>
                                    <td><a target="blank" href="../../../../storage/student_document/{{$data->student_cast_certificate}}">{{$data->student_cast_certificate}}</td>
                                    <td><a target="blank" href="../../../../storage/student_document/{{$data->father_cast_certificate}}">{{$data->father_cast_certificate}}</td>
                                        <td><a target="blank"
                                               href="../../../../storage/student_document/{{$data->student_passport_size_photo}}">{{$data->student_passport_size_photo}}
                                        </td>
                                        <td><a target="blank"
                                               href="../../../../storage/student_document/{{$data->family_photo}}">{{$data->family_photo}}
                                        </td>
                                        <td><a target="blank"
                                               href="../../../../storage/student_document/{{$data->family_photo}}">{{$data->family_photo}}
                                        </td>
                                        <td><a target="blank"
                                               href="../../../../storage/student_document/{{$data->medical_examination_report}}">{{$data->medical_examination_report}}
                                        </td>
                                        <td><a target="blank"
                                               href="../../../../storage/student_document/{{$data->father_adharcard}}">{{$data->father_adharcard}}
                                        </td>
                                        <td><a target="blank"
                                               href="../../../../storage/student_document/{{$data->mother_adharcard}}">{{$data->mother_adharcard}}
                                        </td>
                                        <td><a target="blank"
                                               href="../../../../storage/student_document/{{$data->address_proof}}">{{$data->address_proof}}
                                        </td>
                                        <td><a target="blank"
                                               href="../../../../storage/student_document/{{$data->father_signature}}">{{$data->father_signature}}
                                        </td>
                                        <td><a target="blank"
                                               href="../../../../storage/student_document/{{$data->mother_signature}}">{{$data->mother_signature}}
                                        </td>
                                        <td>{{$data->parents_declaration}}</td>
                                        <td>{{$data->created_on}}</td>
                                    </tr>
                                    @php
                                        $j++;
                                    @endphp
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="row">
                        <div class="col-md-12 form-group">
                            <center>
                                <input type="hidden" name="token_no" @if(isset($finalData['token_no'])) value="{{$finalData['token_no']}}" @endif">
                                <br>
                                <input type="submit" name="submit" value="Submit" class="btn btn-success" style="float: left;">
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
    function checkAll(ele) {
         var checkboxes = document.getElementsByTagName('input');
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
<script>
$(document).ready(function () {
    $('#example').DataTable();
});
</script>
@include('includes.footer')
@endsection
