@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Add Student Infirmary</h4>
            </div>
        </div>
        <div class="card">
		      <!-- @TODO: Create a saperate tmplate for messages and include in all tempate -->
            @if ($sessionData = Session::get('data'))
                @if($sessionData['status_code'] == 1)
                    <div class="alert alert-success alert-block">
                        @else
                            <div class="alert alert-danger alert-block">
                                @endif
                                <button type="button" class="close" data-dismiss="alert">×</button>
                                <strong>{{ $sessionData['message'] }}</strong>
                            </div>
                        @endif
                        <div class="row">
                            <div class="col-lg-12 col-sm-12 col-xs-12">
                                <form action="{{ route('student_infirmary.store') }}" enctype="multipart/form-data"
                                      method="post">
                                    {{ method_field("POST") }}
                                    @csrf
                                    <div class="row">
                                        <div class="col-md-4 form-group">
                                            <label>{{ App\Helpers\get_string('studentname','request')}}</label>
                                            <input type="text" id='student' list="studentSearchList" name="student_id"
                                                   onkeyup="getStudents(this.value);" required="required"
                                                   class="form-control" placeholder="Type Student Name OR GR No.">
                                            <datalist id="studentSearchList">
                                            </datalist>
                                        </div>
                                        <div class="col-md-4 form-group">
                                            <label>{{ App\Helpers\get_string('caseno','request')}}</label>
                                            <input type="text" id='medical_case_no' name="medical_case_no"
                                                   class="form-control" value="{{$medical_case_no}}" readonly="readonly">
                                        </div>
                                        <div class="col-md-4 form-group">
                                            <label>{{ App\Helpers\get_string('doctorname','request')}}</label>
                                            <input type="text" id='doctor_name' name="doctor_name" class="form-control">
                                        </div>
                                        <div class="col-md-4 form-group">
                                            <label>{{ App\Helpers\get_string('doctorcontact','request')}}</label>
                                <input type="number" id='doctor_contact' name="doctor_contact" class="form-control">
                            </div>
                            <div class="col-md-4 form-group">
                                <label>{{ App\Helpers\get_string('opendate','request')}}</label>
                                <input type="text" id='date' name="date" class="form-control mydatepicker" autocomplete="off" value="{{ date('Y-m-d') }}">
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Complaint </label>
                                <input type="text" id='complaint'  name="complaint" class="form-control">
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Symptoms </label>
                                <input type="text" id='symptoms'  name="symptoms" class="form-control"">
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Disease </label>
                                <input type="text" id='disease' name="disease" class="form-control">
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Treatments </label>
                                <input type="text" id='treatments' name="treatments" class="form-control">
                            </div>
                                        <div class="col-md-4 form-group">
                                            <label>Close Date </label>
                                            <input type="text" id='medical_close_date' name="medical_close_date"
                                                   class="form-control mydatepicker" autocomplete="off">
                                        </div>
                                        <div class="col-md-4 form-group">
                                            <label>Health Center </label>
                                            <input type="text" id='health_center' name="health_center"
                                                   class="form-control">
                                        </div>
                                        <div class="col-md-12 form-group">
                                            <center>
                                                <input type="submit" name="submit" value="Save" class="btn btn-success">
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
        function getStudents(value) {
            var URL = "{{route('search_student_name')}}";
            var token = "{{ csrf_token() }}"
            if (value.length > 2) {
                // $.ajax({
                //     url: URL,
                //     method: 'POST',
                //     data: {
                //         'value': value,
                //     },
                //     success: function(result){
                //         $('#studentSearchList').find('option').remove().end();
                //         for (var i = 0; i < result.length; i++) {
                //             $("#studentSearchList").append($("<option></option>").val(result[i]['student'] + ' - ' + result[i]['id']).html(result[i]['student']));
                //         }
                //     }
                // });
                $.post(URL, {
                    'value': value
                }, function (result, status) {
                    $('#studentSearchList').find('option').remove().end();
                    for (var i = 0; i < result.length; i++) {
                        $("#studentSearchList").append($("<option></option>").val(result[i]['student'] + ' - ' + result[i]['id']).html(result[i]['student']));
                    }
                });
            }
        }


    </script>
@include('includes.footer')
