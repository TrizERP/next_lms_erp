@include('../includes.headcss')
@include('../includes.header')
@include('../includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">       
            <div class="card">
                @if(!empty($data['message']))
                <div class="alert alert-{{ $data['class'] }} alert-block">
                    <button type="button" class="close" data-dismiss="alert">×</button>
                    <strong>{{ $data['message'] }}</strong>
                </div>
                @endif
                <div class="col-lg-12 col-sm-12 col-xs-12">
                    <form action="{{ route('result-marks-excel.create') }}" enctype="multipart/form-data">
                        {{csrf_field()}}
                        <div class="row">
                            {{ App\Helpers\TermDD() }}
                        
                            {{ App\Helpers\SearchChain('4','single','grade,std,div') }}
                        

                            <div class="col-md-4 form-group">
                                <label for="title">Select Subject:</label>
                                <select name="subject" id="subject" class="form-control">
                                    <option value="">Select</option>
                                </select>
                            </div>

                            <div class="col-md-4 form-group">
                                <label for="title">Select Exam:</label>
                                <select name="exam_id" id="exam" class="form-control">
                                    <option value="">Select</option>
                                </select>
                            </div>


                            <div class="col-md-12 form-group">
                                <center>
                                    <input type="submit" name="submit" value="Download Excel" class="btn btn-success" >
                                </center>
                            </div>
                        </div>
                    </form>
                </div>
                @if (count($errors) > 0)
                <div class="alert alert-danger">
                    <strong>Whoops!</strong> There were some problems with your input.<br><br>
                    <ul>
                        @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
                @endif
            </div>       
    </div>
</div>

@include('includes.footerJs')
<script>
    $("#grade").prop('required', true);
    $("#standard").prop('required', true);
    $("#division").prop('required', true);
    $("#subject").prop('required', true);
    $("#term").prop('required', true);
    $("#exam").prop('required', true);
    $('#term').change(function () {
        $("#grade").val("");
        $("#standard").empty();
        $("#standard").append('<option value="">Select</option>');
        $("#division").empty();
        $("#division").append('<option value="">Select</option>');
        $("#subject").empty();
        $("#subject").append('<option value="">Select</option>');
        $("#exam").empty();
        $("#exam").append('<option value="">Select</option>');
    });
    $('#grade').change(function () {
        $("#subject").empty();
        $("#subject").append('<option value="">Select</option>');
        $("#exam").empty();
        $("#exam").append('<option value="">Select</option>');
    });
    $('#standard').change(function () {
        $("#subject").empty();
        $("#subject").append('<option value="">Select</option>');
        $("#exam").empty();
        $("#exam").append('<option value="">Select</option>');
    });
    $('#division').on('change', function () {
        $("#exam").empty();
        $("#exam").append('<option value="">Select</option>');
        var standardID = $("#standard").val();
        var divisionID = $("#division").val();
        if (standardID) {
            $.ajax({
                type: "GET",
                url: "/api/get-subject-list?standard_id=" + standardID + "&division_id="+ divisionID,
                success: function (res) {
                    if (res) {
                        $("#subject").empty();
                        $("#subject").append('<option value="">Select</option>');
                        $.each(res, function (key, value) {
                            $("#subject").append('<option value="' + key + '">' + value + '</option>');
                        });

                    } else {
                        $("#subject").empty();
                    }
                }
            });
        } else {
            $("#subject").empty();
        }

    });
    $('#subject').on('change', function () {
        var standardID = $("#standard").val();
        var subjectID = $("#subject").val();
        var termID = $("#term").val();

        if (standardID && subjectID && termID) {
            $.ajax({
                type: "GET",
                url: "/api/get-exam-list?standard_id=" + standardID +
                        "&subject_id=" + subjectID + "&term_id=" + termID,
                success: function (res) {
                    if (res) {
                        $("#exam").empty();
                        $("#exam").append('<option value="">Select</option>');
                        $.each(res, function (key, value) {
                            $("#exam").append('<option value="' + key + '">' + value + '</option>');
                        });

                    } else {
                        $("#exam").empty();
                    }
                }
            });
        } else {
            $("#exam").empty();
            $("#exam").append('<option value="">Select</option>');
            if (termID == "") {
                alert("Please Select Term.");
            }
        }

    });
</script>
@include('includes.footer')
