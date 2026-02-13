@extends('layout')
@section('container')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Capture Student Attendance</h4>
            </div>
        </div>
        <div class="card">
            @if ($sessionData = Session::get('data'))
            <div class="alert alert-success alert-block">
                <button type="button" class="close" data-dismiss="alert">×</button>
                <strong>{{ $sessionData['message'] }}</strong>
            </div>
            @endif

            <form class="row" action="{{route('student_face_attendance.store')}}" method="POST" enctype="multipart/form-data">
                @csrf
                @if(strtoupper(session()->get('user_profile_name'))=="ADMIN")
                <div class="col-md-3 form-group">
                    <label for="">{{ App\Helpers\get_string('grno','request')}}</label>
                    <input type="text" id='enrollment_no' list="studentSearchList" name="enrollment_no"
                        onkeyup="getStudents(this.value);" required="required"
                        class="form-control" placeholder="Type Student Name OR GR No.">
                    <datalist id="studentSearchList">
                    </datalist>
                </div>
                @endif
                <div class="col-md-3 form-group">
                    <label for="">File</label>
                    <input type="file" class="form-control" name="stu_image[]" multiple="multiple">
                </div>
                <div class="col-md-12">
                    <center>
                        <input type="submit" value="Submit" class="btn btn-primary">
                    </center>
                </div>
            </form>

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
            }, function(result, status) {
                $('#studentSearchList').find('option').remove().end();
                for (var i = 0; i < result.length; i++) {
                    $("#studentSearchList").append($("<option></option>").val(result[i]['student'] + '|' + result[i]['id']).html(result[i]['student']));
                }
            });
        }
    }
</script>
@include('includes.footer')
@endsection