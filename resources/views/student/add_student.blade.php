@extends('layout')
@section('container')
<link rel="stylesheet" href="../../../plugins/bower_components/dropify/dist/css/dropify.min.css">
<link href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.3/css/select2.min.css" rel="stylesheet" />

<style>
  .select2-dropdown.select2-dropdown--below {
    width: 460px !important;
  }

  .select2-selection.select2-selection--single {
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
        <h4 class="page-title">Add New Student</h4>
      </div>
    </div>
    <div class="card">
      @if ($message = Session::get('success'))
      <div class="alert alert-success alert-block">
        <button type="button" class="close" data-dismiss="alert">×</button>
        <strong>{{ $message }}</strong>
      </div>
      @endif
      @if(Session::get('user_profile_name') != 'Student')
      <div class="content-wrap text-center">

        <!-- <section id="section-linemove-1"> -->
        <form action="{{ route('add_student.store') }}" enctype="multipart/form-data" class="row" method="post">
          {{ method_field("POST") }}
          @csrf
            <!-- data from master_fields start  -->
            @if(count($data['masterData'])>0)
            @foreach($data['masterData'] as $section=>$sectionVal) 
             @foreach($sectionVal as $key=>$vals) 

                @php 
                  $requiredFields = ($vals['is_mandatory']==1) ? 'required' : '';
                  $visibleFields = ($vals['is_visible']==0 && $vals['is_mandatory']==0) ? 'display:none' : '';
                  $fieldValArr = (isset($vals['field_value']) && $vals['field_value']!='') ? json_decode($vals['field_value']) : [];
                  $defaultValue = (isset($vals['default_value']) && $vals['default_value']!='') ? $vals['default_value'] : '';
                  if($vals['field_name']=="enrollment_no"){
                    $defaultValue = isset($data['new_enrollment_no']) ? $data['new_enrollment_no'] : $defaultValue;
                  }
                  $asterisk = ($vals['is_mandatory']==1) ? '<span class="mdi mdi-asterisk" style="color:red;font-size:10px;"></span>' : '';
                @endphp
  <!-- if condition starts "email","admission_year","state","city","cast","subcast" -->
                <div class="col-md-4 form-group text-left" style="{{$visibleFields}}">
                  <label for="">{{$vals['field_label']}}</label> {!! $asterisk !!}
                @if(!in_array($vals['field_name'],["email","admission_year","state","city","cast","subcast"]))
              
                  @if($vals['field_type']=="textarea")
                    <textarea name="{{$vals['field_name']}}" id="{{$vals['field_name']}}" class="form-control" {{$requiredFields}}>{{$defaultValue}}</textarea>
                  @elseif($vals['field_type']=="dropdown")
                    <select name="{{$vals['field_name']}}" id="{{$vals['field_name']}}" class="form-control">
                      <option value="">Select any one</option>
                      @foreach($fieldValArr as $k => $v)
                      <option value="{{$k}}" @if($defaultValue!='' && $defaultValue==$k) selected @endif>{{$v}}</option>
                      @endforeach
                    </select>
                  @elseif(in_array($vals['field_type'],["image","file"]))
                    <input type="file" {{$vals['validation_rules']}} name="{{$vals['field_name']}}" id="input-file-now" class="dropify" {{$requiredFields}} /> 
                  @elseif($vals['field_type']=="checkbox")
                  <br>
                    @foreach($fieldValArr as $k => $v)
                    <input type="checkbox" value="{{$k}}" name="{{$vals['field_name']}}" id="{{$vals['field_name']}}" @if($defaultValue!='' && $defaultValue==$k) checked @endif {{$requiredFields}}> <span>{{$v}}</span>
                    @endforeach
                  @elseif($vals['field_type']=="radio")
                  <div class="radio-list">
                    @foreach($fieldValArr as $k => $v)
                    <label class="radio-inline p-0">
                          <div class="radio">
                              <input type="radio" name="{{$vals['field_name']}}" id="{{$v}}" value="{{$k}}" {{$requiredFields}} @if($defaultValue!='' && $defaultValue==$k) checked @endif>
                              <label for="{{$v}}">{{$v}}</label>
                          </div>
                      </label>
                    @endforeach
                  </div>
                  @else

                    @php 
                     $class = ($vals['field_type']=='date') ? 'mydatepicker birthdate_picker' :  '';
                     $type = ($vals['field_type']=='date') ? 'text' : $vals['field_type'];
                    @endphp
                  <input type="{{$type}}" name="{{$vals['field_name']}}" class="form-control {{$class}}" @if($defaultValue!='') value="{{$defaultValue}}" @else placeholder="Enter {{$vals['field_label']}}" @endif {{$requiredFields}} autocomplete="off" {{$vals['validation_rules']}}>
                  @endif
                  <!-- else ends "email","admission_year","state","city","cast","subcast" -->
                @else
                  @if($vals['field_name']=="email")
                    <input type="email" id='email' required name="email" class="form-control">
                    <span id="email_error_span"></span>
                  @endif
                  @if($vals['field_name']=="admission_year")
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
                  @endif
                  @if($vals['field_name']=="state")
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
                  @endif
                  @if($vals['field_name']=="city")
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
                    </select>
                    @endif
                  @endif

                  @if($vals['field_name']=="cast")
                    <select id='cast' name="cast" class="form-control">
                      <option value="">--Select--</option>  
                      @if(isset($data['caste_data']))
                          @foreach($data['caste_data'] as $key => $value)
                              <option value="{{ $value['id'] }}">{{ $value['caste_name'] }}</option>
                          @endforeach
                      @endif                                                  
                  </select>
                  @endif
                  @if($vals['field_name']=="subcast")
                    <input type="text" id='subcast' name="subcast" class="form-control">
                  @endif
                  <!-- if condition ends "email","admission_year","state","city","cast","subcast" -->
                @endif
                </div>
                
              @endforeach
            @endforeach
            <div class="col-md-4 form-group text-left">
              <label>{{App\Helpers\get_string('studentquota','request')}}</label><span class="mdi mdi-asterisk" style="color:red;font-size:10px;"></span>
              <select id='student_quota' required name="student_quota" class="form-control">
                  <option value="">--Select--</option>
                  @if(isset($data['student_quota']))
                      @foreach($data['student_quota'] as $key => $value)
                          <option value="{{ $value['id'] }}">{{ $value['title'] }}</option>
                      @endforeach
                  @endif
              </select>
          </div> 
            <div class="col-md-4 form-group text-left">
              <label>Student Batch</label>
              <select id='studentbatch' name="studentbatch" class="form-control">
                  <option value="">--Select--</option>                                                    
              </select>
          </div>
            <!-- main if condition else part  -->
            @else
                <div class="alert alert-danger alert-block">
                  <button type="button" class="close" data-dismiss="alert">×</button>
                  <strong>Please Master Data</strong>
              </div>
            @endif
              {{ App\Helpers\SearchChain('4','single','grade,std,div') }}
                <span id="division_error_span"></span>
            </div>  
            <div class="col-md-12 form-group">
                <input type="submit" name="submit" id="Submit" value="Add Student" class="btn btn-success" >
            </div>
        </form>
      </div>
      @endif
    </div>
  </div>

<!-- check student Model  -->
<div class="modal fade" id="studentModal" tabindex="-1" role="dialog" aria-labelledby="studentModalLabel"
  aria-hidden="true">
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
  (function () {
    [].slice.call(document.querySelectorAll('.sttabs')).forEach(function (el) {
      new CBPFWTabs(el);
    });
  })();

</script>
<script>
  function getUsername() {

    var first_name = document.getElementById("first_name").value;
    var last_name = document.getElementById("last_name").value;
    var username = first_name.toLowerCase() + "_" + last_name.toLowerCase();
    document.getElementById("username").value = username;
  }
  function addNewRow() {
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
  $(document).ready(function () {
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

  //START Bind Batch
  $("#division").change(function () {
    var div_id = $("#division").val();
    var std_id = $("#standard").val();
    var path = "{{ route('ajax_getBatch') }}";
    $('#studentbatch').find('option').remove().end();
    $.ajax({
      url: path,
      data: 'div_id=' + div_id + '&std_id=' + std_id,
      success: function (result) {
        for (var i = 0; i < result.length; i++) {
          $("#studentbatch").append($("<option></option>").val(result[i]['id']).html(result[i]['title']));
        }
      }, error: function (xhr, status, error) {
        alert(JSON.parse(xhr.responseText).message);
      }
    });
  })
  // check student lists
  $("#mobile").change(function () {
    checkStudentExists();
  })
  $('#first_name').change(function () {
    checkStudentExists();
  })
  $('#last_name').change(function () {
    checkStudentExists();
  })
  //END Bind Batch

  //START Bind Optional Subject
  $("#standard").change(function () {
    var std_id = $("#standard").val();
    var path = "{{ route('ajax_getOptionalSubject') }}";
    $('#optional_subject').find('option').remove().end();
    $.ajax({
      url: path,
      data: 'std_id=' + std_id,
      success: function (result) {
        for (var i = 0; i < result.length; i++) {
          $("#optional_subject").append($("<option></option>").val(result[i]['subject_id']).html(result[i]['subject_name']));
        }
      }
    });
  })
  //END Bind Optional Subject

  $("#division").attr('required', true);

  $(document).ready(function () {
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
            // email_state = true; // 2025-04-15 by uma
            email_state = false;
          }
          else {
            $("#email_error_span").removeClass().addClass("email_success").text('Email available');
            email_state = false;
          }
        }
      });
    });
    //END Unique Email Validation

    //START Check Division Capacity Validation - 18/11/2021
    var division_check = false;
    document.getElementById('division').addEventListener('change', function () {
      var selected_division_id = $("#division").val();
      var selected_std_id = $("#standard").val();

      var path = "{{ route('ajax_checkDivisionCapacity') }}";
      $.ajax({
        url: path,
        data: 'std_id=' + selected_std_id + '&division_id=' + selected_division_id,
        success: function (result) {
          var capacity = result.split("/");
          if (capacity[1] != 0) {

            $("#division_error_span").removeClass().addClass("division_success").text('Total Capacity : ' + capacity[0] + ' / Remaining Capacity : ' + capacity[1]);
            division_check = true;
          }
          else if (capacity[1] == '') {
            division_check = true;
          }
          else {
            $("#division_error_span").removeClass().addClass("division_error").text('Total Capacity : ' + capacity[0] + ' / Remaining Capacity : ' + capacity[1]);
            division_check = false;
          }
        }
      });

    });
    //END Check Division Capacity Validation - 18/11/2021

    $('#Submit').on('click', function () {

      if (email_state == true) {
        alert('Fix the errors in the form first');
        return false;
      }
      if (division_check == false) {
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

  function getStatewiseCity(state_name) {
    var path = "{{ route('ajax_StatewiseCity') }}";
    //alert('dk');
    $('#city').find('option').remove().end().append('<option value="">Select City</option>').val('');
    $.ajax({
      url: path, data: 'state_name=' + state_name, success: function (result) {
        for (var i = 0; i < result.length; i++) {
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
    if (aadhar != '') {
      if (aadhar.match(adharcardTwelveDigit)) {
        return true;
      } else {
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
  function checkStudentExists() {
    var first_name = $('#first_name').val();
    var last_name = $('#last_name').val();
    var mobile = $('#mobile').val();
    // alert(first_name);
    $.ajax({
      url: "{{route('checkExists')}}",
      data: { first_name: first_name, last_name: last_name, mobile: mobile },
      type: 'GET',
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
      error: function (xhr, status, error) {
        console.error(xhr.responseText);
      }
    })
  }
</script>
@include('includes.footer')
@endsection