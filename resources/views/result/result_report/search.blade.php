@include('../includes.headcss')
@include('../includes.header')
@include('../includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Result Report</h4>
            </div>
        </div>
        <div class="card">
            @if(!empty($data['message']))
                <div class="alert alert-{{ $data['class'] }} alert-block">
                    <button type="button" class="close" data-dismiss="alert">×</button>
                    <strong>{{ $data['message'] }}</strong>
                </div>
            @endif
            <div class="col-lg-12 col-sm-12 col-xs-12">
                <form action="{{ route('show_result_report') }}" method="post">
                    {{ method_field("POST") }}
                    {{csrf_field()}}

                    <div class="row">

                        <div class="col-md-4 form-group">
                            <label for="report_of">Select Report</label>
                            <select name="report_of" id="report_of" class="form-control" required
                                    onchange="check_report(this.value);">
                                <option value="">Select Report</option>
                                <option value="merit_report">Merit Report</option>
                                <option value="subject_progress_report">Subject Progress Report</option>
                                <option value="classwise_report">Classwise Report</option>
                                <option value="overall_report">Overall Report</option>
                                <option value="marks_report">Marks Report</option>
                            </select>
                        </div>

                        {{ App\Helpers\TermDD() }}
                        
                        {{ App\Helpers\SearchChain('4','single','grade,std,div') }}

                        <div class="col-md-4 form-group" style="display: none;" id="for_additional_subjects">
                            <label for="additional_subjects">Select Subject</label>
                            <select name="additional_subjects[]" id="additional_subjects" class="form-control mb-0" multiple>
                                <option value="">Select Subject</option>
                            </select>
                        </div>

                        <div class="col-md-4 form-group" style="display: none;" id="for_subject">
                            <label for="subject">Select Subject</label>
                            <select name="subject" id="subject" class="form-control mb-0">
                                <option value="">Select Subject</option>
                            </select>
                        </div>

                        <div class="col-md-4 form-group" style="display: none;" id="for_top_students">
                            <label>Top Students</label>
                            <input type="number" id="top_students" name="top_students" class="form-control">
                        </div>

                        <div class="col-md-4 form-group" style="display: none;" id="for_roll_no">
                            <label>Roll No</label>
                            <input type="number" id="roll_no" name="roll_no" class="form-control">
                        </div>

                        <div class="col-md-4 form-group" style="display: none;" id="for_from_date">
                            <label>From Date</label>
                            <input type="text" id="from_date" name="from_date" class="form-control mydatepicker"
                                   autocomplete="off">
                        </div>

                        <div class="col-md-4 form-group" style="display: none;" id="for_to_date">
                            <label>To Date</label>
                            <input type="text" id="to_date" name="to_date" class="form-control mydatepicker"
                                   autocomplete="off">
                        </div>

                        <div class="col-md-4 form-group" style="display: none;" id="for_exam_type">
                            <label>Exam Type</label>
                            <select name="exam_type" id="exam_type" class="form-control">
                                <option value="">Select</option>
                              
                            </select>
                        </div>

                        <div class="col-md-12 form-group">
                            <center>
                                <input type="submit" name="submit" value="Search" class="btn btn-success">
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
<script type="text/javascript">
    $('#grade').prop('required', true);
    $('#standard').prop('required', true);
    $('#division').prop('required', true);

    $("#standard").change(function(){
        var std_id = $("#standard").val();
        var path = "{{ route('ajax_StandardwiseSubject') }}";
        $('#subject').find('option').remove().end().append('<option value="">Select Subject</option>').val('');
        $.ajax({
            url: path, data: 'std_id=' + std_id, success: function (result) {
                for (var i = 0; i < result.length; i++) {
                    $('#subject').
                        append($('<option></option>').val(result[i]['subject_id']).html(result[i]['display_name']));
                }
            },
        });

        $('#additional_subjects').find('option').remove().end().append('<option value="">Select Subject</option>').val('');
        $.ajax({
            url: path, data: 'std_id=' + std_id, success: function (result) {
                for (var i = 0; i < result.length; i++) {
                    $('#additional_subjects').
                        append($('<option></option>').val(result[i]['subject_id']).html(result[i]['display_name']));
                }
            },
        });

        var termID ={{session()->get('term_id')}};

        if (std_id && termID) {
            $.ajax({
                type: "GET",
                url: "/api/get-exam-master-list?standard_id=" + std_id +
                        "&term_id=" + termID,
                success: function (res) {
                    if (res) {
                        $("#exam_type").empty();
                        $("#exam_type").append('<option value="">Select</option>');
                        $.each(res, function (key, value) {
                            $("#exam_type").append('<option value="' + key + '">' + value + '</option>');
                        });

                    } else {
                        $("#exam_type").empty();
                    }
                }
            });
        } else {
            $("#exam_type").empty();
            $("#exam_type").append('<option value="">Select</option>');
            if (termID == "") {
                alert("Please Select Term.");
            }
        }
    })

    function check_report (report_val) {
        if (report_val == 'merit_report') {
            document.getElementById('for_top_students').style.display = 'block';
            document.getElementById('for_subject').style.display = 'none';
            document.getElementById('for_roll_no').style.display = 'none';
            document.getElementById('for_exam_type').style.display = 'none';
            document.getElementById('for_from_date').style.display = 'block';
            document.getElementById('for_to_date').style.display = 'block';
            $('#subject').prop('required', false);
        }

        if (report_val == 'subject_progress_report') {
            document.getElementById('for_top_students').style.display = 'none';
            document.getElementById('for_subject').style.display = 'block';
            document.getElementById('for_roll_no').style.display = 'block';
            document.getElementById('for_exam_type').style.display = 'block';
            document.getElementById('for_from_date').style.display = 'block';
            document.getElementById('for_to_date').style.display = 'block';
            $('#subject').prop('required', true);
        }

        if (report_val == 'overall_report') {
            document.getElementById('for_top_students').style.display = 'none';
            document.getElementById('for_subject').style.display = 'none';
            document.getElementById('for_roll_no').style.display = 'none';
            document.getElementById('for_exam_type').style.display = 'block';
            document.getElementById('for_from_date').style.display = 'none';
            document.getElementById('for_to_date').style.display = 'none';
            $('#subject').prop('required', false);
        }

        if (report_val == 'classwise_report') {
            document.getElementById('for_top_students').style.display = 'none';
            document.getElementById('for_subject').style.display = 'none';
            document.getElementById('for_roll_no').style.display = 'none';
            document.getElementById('for_exam_type').style.display = 'block';
            document.getElementById('for_from_date').style.display = 'none';
            document.getElementById('for_to_date').style.display = 'none';
            $('#subjects').prop('required', false);
        }

        if (report_val == 'marks_report') {
            document.getElementById('for_top_students').style.display = 'none';
            document.getElementById('for_subject').style.display = 'none';
            document.getElementById('for_roll_no').style.display = 'none';
            document.getElementById('for_exam_type').style.display = 'block';
            document.getElementById('for_from_date').style.display = 'block';
            document.getElementById('for_to_date').style.display = 'block';
            document.getElementById('for_additional_subjects').style.display = 'block';
            $('#additional_subjects').prop('required', true);
        }

        if (report_val == '') {
            document.getElementById('for_top_students').style.display = 'none';
            document.getElementById('for_subject').style.display = 'none';
            document.getElementById('for_roll_no').style.display = 'none';
            document.getElementById('for_exam_type').style.display = 'none';
            document.getElementById('for_from_date').style.display = 'none';
            document.getElementById('for_to_date').style.display = 'none';
            document.getElementById('for_additional_subjects').style.display = 'none';
            $('#subject').prop('required', false);
        }
    }

  
</script>
@include('includes.footer')
