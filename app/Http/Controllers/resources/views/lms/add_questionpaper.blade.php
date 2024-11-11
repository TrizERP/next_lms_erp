{{--@include('includes.lmsheadcss')--}}
@extends('lmslayout')
@section('container')
<link href="/plugins/bower_components/clockpicker/dist/jquery-clockpicker.min.css" rel="stylesheet">
<style>
.tooltip-inner {
    max-width: 1100px !important;
}
br{
    display:  block !important;
}
.image-text>img{
    width:350px !important;
    height: 200px !important;
}
</style>
{{--@include('includes.header')
@include('includes.sideNavigation')--}}
<!-- Content main Section -->
<div class="content-main flex-fill">
    <div class="row">
        <div class="col-md-6">
            <h1 class="h4 mb-3">
            @if(!isset($data['questionpaper_data']))
            Add Exam
            @else
            Edit Exam
            @endif </h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb bg-transparent p-0">
                    <li class="breadcrumb-item"><a href="{{route('course_master.index')}}">LMS</a></li>
                    <li class="breadcrumb-item">Exam</li>
                    <li class="breadcrumb-item active" aria-current="page">Add Exam</li>
                </ol>
            </nav>
        </div>

    </div>

    <div class="container-fluid mb-5">
        <div class="card border-0">
            <div class="card-body">
                @if ($sessionData = Session::get('data'))
                    <div
                        class="@if ($sessionData['status_code'] == 1) alert alert-success alert-block @else alert alert-danger alert-block @endif ">
                        <button type="button" class="close" data-dismiss="alert">×</button>
                        <strong>{{ $sessionData['message'] }}</strong>
                    </div>
                @endif

                @if ($message = Session::get('success'))
                <div class="alert alert-success alert-block">
                    <button type="button" class="close" data-dismiss="alert">×</button>
                    <strong>{{ $message }}</strong>
                </div>
                @endif
            @if ($message = Session::get('failed'))
                <div class="alert alert-danger alert-block">
                    <button type="button" class="close" data-dismiss="alert">×</button>
                    <strong>{{ $message }}</strong>
                </div>
                @endif
            @php
        $grade_id = $standard_id = $subject_id = $chapter_id[] = $topic_id[] = $map_type[] = $map_val[] = '';

            if(isset($data['grade_id'])){
                $grade_id = $data['grade_id'];
                $standard_id = $data['standard_id'];
            }
            if(isset($data['subject_id'])){
                $subject_id = $data['subject_id'];
            }

            if(isset($data['chapter_id'])){
                $chapter_id = $data['chapter_id'];
            }
             if(isset($data['topic_id'])){
                $topic_id = $data['topic_id'];
            }
            if(isset($data['map_type'])){
                $map_type = $data['map_type'];
            }

            if(isset($data['map_value'])){
                $map_val = $data['map_value'];
            }
        @endphp

                    <form action="{{url('/lms/question_paper/search')}}" method="post">
                        @csrf
                    <div class="row align-items-center">
                        <div class="col-md-12 form-group">
                            <div class="row align-items-center">
                                {{ App\Helpers\SearchChain('4','','grade,std',$grade_id,$standard_id) }}

                                <div class="col-md-4 form-group">
                                    <label for="subject"  id="subject_div" name="subject_div">Select Subject:</label>
                                    <select name="subject" id="subject" class="form-control mb-0" >
                                        <!-- <option value="">Select Subject</option> -->
                                        <!-- search data -->
                                        @if(isset($data['subjects']) && isset($grade_id))
                                         @foreach($data['subjects'] as $key => $value)
                                        <option value="{{ $value['subject_id'] }}" @if($subject_id == $value['subject_id']) selected='selected' @endif>{{$value['display_name']}}</option>
                                            @endforeach
                                        @endif
                                        <!-- search data -->

                                    @if(isset($data['questionpaper_data']))
                                        @foreach($data['subjects'] as $key => $value)

                                        <option value="{{$value['subject_id']}}" @if(isset($data['questionpaper_data']['subject_id'])) @if($data['questionpaper_data']['subject_id']==$value['subject_id']) selected='selected' @endif @endif>{{$value['display_name']}}</option>
                                        @endforeach
                                        @endif
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-12 form-group">
                            <div class="row mt-4">
            @if(!isset($data['questionpaper_data']))

                                <div class="col-md-3 form-group">
                                    <select name="search_chapter[]" id="search_chapter" class="form-control mb-0" multiple="multiple">
                                        <option value="">Search By Chapter</option>
                                        @if(isset($data['chapters']) && isset($grade_id))
                                         @foreach($data['chapters'] as $key => $value)
                                        <option value="{{ $value['id'] }}" @if(in_array($value['id'],$chapter_id )) selected='selected' @endif>{{$value['chapter_name']}}</option>
                                            @endforeach
                                        @endif
                                    </select>
                                </div>
                                <div class="col-md-3 form-group">
                                    <select name="search_topic[]" id="search_topic" class="form-control mb-0" multiple="multiple">
                                        <option value="">Search By Topic</option>
                                        @if(isset($data['topics']) && isset($grade_id))
                                         @foreach($data['topics'] as $key => $value)
                                        <option value="{{ $value['id'] }}" @if(isset($data['topics']) && in_array($value['id'],$topic_id)) selected='selected' @endif>{{$value['name']}}</option>
                                            @endforeach
                                        @endif
                                    </select>
                                </div>
                                <div class="col-md-3 form-group">
                                    <select name="search_mapping_type[]" id="search_mapping_type" class="form-control mb-0" multiple="multiple">
                                        <option value="">Search By Mapping Type</option>

                                        @if(isset($data['lms_mapping_type']))
                                            @foreach($data['lms_mapping_type'] as $key => $val)
                                                <option value="{{$val['id']}}" @if(isset($data['map_value']) && in_array($val['id'],$map_type)) selected='selected' @endif>{{$val['name']}}</option>
                                            @endforeach
                                        @endif

                                    </select>
                                </div>
                                <div class="col-md-2 form-group">
                                    <select name="search_mapping_value[]" id="search_mapping_value" class="form-control mb-0" multiple="multiple">
                                        <option value="">Search By Mapping Value</option>

                                        @if(isset($data['mapping_value']) && isset($grade_id))
                                         @foreach($data['mapping_value'] as $key => $value)
                                        <option value="{{ $value->id }}" @if(in_array($value->id,$map_val)) selected='selected' @endif>{{$value->name}}</option>
                                            @endforeach
                                        @endif
                                    </select>
                                </div>

                                <div class="col-md-1 form-group">
                                        <input type="hidden" name="action" value="search">

                                    <input type="submit" name="search" value="Search" class="btn btn-success">
                                    <!-- <input type="button" name="search" value="hello" class="btn btn-success" onclick="search_questionList();"> -->
                                </div>
            @endif
                            </div>
                        </div>

                        <div class="col-md-4 form-group">
                            <label>Exam Name / Paper Name  <i class="fa fa-asterisk" aria-hidden="true" style="color:red;font-size: 6px;"></i></label>
                            <input type="text" id='paper_name' name="paper_name" value="@if(isset($data['questionpaper_data']['paper_name'])){{$data['questionpaper_data']['paper_name']}}@endif" class="form-control mb-0" >
                        </div>

                        <div class="col-md-4 form-group">
                            <label>Exam Description / Paper Description  <i class="fa fa-asterisk" aria-hidden="true" style="color:red;font-size: 6px;"></i></label>
                            <input type="text" id='paper_desc' name="paper_desc" value="@if(isset($data['questionpaper_data']['paper_desc'])){{$data['questionpaper_data']['paper_desc']}}@endif" class="form-control mb-0" >
                        </div>

                        <div class="col-md-4 form-group">
                            <label for="subject">Attempt Allowed:  <i class="fa fa-asterisk" aria-hidden="true" style="color:red;font-size: 6px;"></i></label>
                            <select name="attempt_allowed" id="attempt_allowed" class="form-control mb-0"  onchange="show_ans(this.value);">
                                <option value="">Select Attempt Allowed  <i class="fa fa-asterisk" aria-hidden="true" style="color:red;font-size: 6px;"></i></option>
                                    <option value="unlimited" @if(isset($data['questionpaper_data']['attempt_allowed'])) @if($data['questionpaper_data']['attempt_allowed']=='unlimited') selected='selected' @endif @endif>Unlimited</option>
                                    @for($i=1;$i<=10;$i++)
                                    <option value="{{$i}}" @if(isset($data['questionpaper_data']['attempt_allowed'])) @if($data['questionpaper_data']['attempt_allowed']==$i) selected='selected' @endif @endif>{{$i}}</option>
                                    @endfor
                            </select>
                        </div>

                        <div class="col-md-4 form-group">
                            <label>Open Date</label>
                            <div class="input-daterange input-group" id="date-range">
                                <input type="text" class="form-control mydatepicker mb-0 text-left" placeholder="dd/mm/yyyy" value="@if(isset($data['questionpaper_data']['open_date']) && $data['questionpaper_data']['open_date'] !=""){{date('Y-m-d', strtotime($data['questionpaper_data']['open_date']))}}@endif" name="open_date" autocomplete="off">
                                <span class="input-group-addon"><i class="icon-calender"></i></span>
                            </div>
                        </div>

                        <div class="col-md-4 form-group">
                            <label>Close Date</label>
                            <div class="input-daterange input-group" id="date-range">
                                <input type="text" class="form-control mydatepicker mb-0 text-left" placeholder="dd/mm/yyyy" value="@if(isset($data['questionpaper_data']['close_date']) && $data['questionpaper_data']['close_date'] !="" ){{date('Y-m-d', strtotime($data['questionpaper_data']['close_date']))}}@endif" name="close_date" autocomplete="off">
                                <span class="input-group-addon"><i class="icon-calender"></i></span>
                            </div>
                        </div>

                        <div class="col-md-2 form-group">
                            <label for="timelimit_enable">Enable Timelimit</label>
                            <input type="checkbox" id="timelimit_enable" name="timelimit_enable" value="1" onchange="show_time_allowed();"
                            @if( isset($data['questionpaper_data']['timelimit_enable']) && $data['questionpaper_data']['timelimit_enable'] == 1)
                            checked
                            @elseif(!isset($data['questionpaper_data']))
                            checked
                            @endif
                            >
                        </div>

                        <div class="col-md-2 form-group">
                            <label for='time_allowed'>Allowed Time (mins)  <i class="fa fa-asterisk" aria-hidden="true" style="color:red;font-size: 6px;"></i></label>
                            <input type="number" id='time_allowed' name="time_allowed"
                            value="@if(isset($data['questionpaper_data']['time_allowed'])){{$data['questionpaper_data']['time_allowed']}}@endif"
                            @if( isset($data['questionpaper_data']['timelimit_enable']) && $data['questionpaper_data']['timelimit_enable'] == 0)
                            readonly
                            @endif
                            class="form-control" style="width: 100px;" ><b></b>
                        </div>

                        <div class="col-md-4 form-group">
                            <label class="control-label">Exam Type</label>
                            <div class="radio-list">
                                <label class="radio-inline p-0">
                                    <div class="radio radio-success">
                                        <input type="radio" name="exam_type" value="online"
                                        @if( isset($data['questionpaper_data']['exam_type']) && $data['questionpaper_data']['exam_type'] == "online")
                                        checked
                                        @else if( !isset($data['questionpaper_data']['exam_type']) )
                                        checked
                                        @endif>
                                        <label for="online">Online</label>
                                    </div>
                                </label>
                                <label class="radio-inline">
                                    <div class="radio radio-success">
                                        <input type="radio" name="exam_type" value="offline"
                                        @if( isset($data['questionpaper_data']['exam_type']) && $data['questionpaper_data']['exam_type'] == "offline")
                                        checked
                                        @endif>
                                        <label for="offline">Offline</label>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <div class="col-md-2 form-group">
                            <label for="shuffle_question">Shuffle Question</label>
                            <input type="checkbox" id="shuffle_question" name="shuffle_question" value="1"
                            @if( isset($data['questionpaper_data']['shuffle_question']) && $data['questionpaper_data']['shuffle_question'] == 1)
                            checked
                            @elseif(!isset($data['questionpaper_data']))
                            checked
                            @endif
                            >
                        </div>

                        <div class="col-md-2 form-group">
                            <label for="show_feedback">Show Feedback</label>
                            <input type="checkbox" id="show_feedback" name="show_feedback" value="1"
                            @if( isset($data['questionpaper_data']['show_feedback']) && $data['questionpaper_data']['show_feedback'] == 1)
                            checked
                            @elseif(!isset($data['questionpaper_data']))
                            checked
                            @endif
                            >
                        </div>

                        <div class="col-md-2 form-group">
                            <label for="show_hide">Show</label>
                            <input type="checkbox" id="show_hide" name="show_hide" value="1"
                            @if( isset($data['questionpaper_data']['show_hide']) && $data['questionpaper_data']['show_hide'] == 1)
                            checked
                            @elseif(!isset($data['questionpaper_data']))
                            checked
                            @endif
                            >
                        </div>

                        <div class="col-md-2 form-group">
                            <label for="show_hide">Show Right Answer after Result</label>
                            <input type="checkbox" id="result_show_ans" name="result_show_ans" value="1"
                            @if( isset($data['questionpaper_data']['result_show_ans']) && $data['questionpaper_data']['result_show_ans'] == 1)
                            checked
                            @elseif(!isset($data['questionpaper_data']))
                            checked
                            @endif
                            >
                        </div>

                        <div class="col-md-3 form-group">
                            <label for='total_ques'>Total Question</label>
                            <input type="text" id='total_ques' name="total_ques" value="@if(isset($data['questionpaper_data']['total_ques'])){{$data['questionpaper_data']['total_ques']}}@endif" class="form-control mb-0" readonly>
                        </div>

                        <div class="col-md-3 form-group">
                            <label for='total_marks'>Total Marks</label>
                            <input type="text" id='total_marks' name="total_marks" value="@if(isset($data['questionpaper_data']['total_marks'])){{$data['questionpaper_data']['total_marks']}}@endif" class="form-control mb-0" readonly>
                        </div>
                        @if(isset($data['questionData']) && count($data['questionData']) > 0)

                        <div class="col-md-12 form-group border border-dark" >
                            <table id="questiontable" class="table table-striped table-bordered mb-0">
                                <thead>
                                    <tr>
                                        <th></th>
                                        <th>Question Title</th>
                                        <th>Chapter</th>
                                        <th>Chapter No</th>
                                        <th>Topic</th>
                                        <th>Question Type</th>
                                        <th>Correct Answer</th>
                                        <th>Marks</th>
                                        <th>Mappings</th>
                                    </tr>
                                </thead>
                                <tbody>

                                    @php
                                        $j = 0;
                                    if(isset($data['questionpaper_data'])){
                                        $ids = explode(',',$data['questionpaper_data']['question_ids']);
                                    }
                                    @endphp


                                @foreach($data['questionData'] as $key=> $data2)
                                <tr>
                                    <td><input type="checkbox" onclick="add_question();" name="questions[]" title="{{$data2['points']}}'" value="{{$data2['id']}}" @if(isset($data['questionpaper_data']['question_ids'])) @if(in_array($data2['id'],$ids)) checked @endif @endif></td>
                                    <td class="image-text">
                                        @if (Str::contains($data2['question_title'], '<img '))
                                            {!! $data2['question_title'] !!} {{-- Display the image --}}
                                        @else
                                            {!! $data2['question_title'] !!} {{-- Display the text --}}
                                        @endif
                                    </td>
                                    <td>{{$data2['chapter_name']}}</td>
                                    <td>{{$data2['sort_order']}}</td>
                                    <td>@if(isset($data2['topic_name'])){{$data2['topic_name']}}@endif</td>
                                    <td>{{$data2['question_type']}}</td>
                                    <td><input type="hidden" value="{{$data2['correct_answer']}}" name="correct_answer">{{$data2['correct_answer']}}</td>
                                    <td>{{$data2['points']}}</td>
                                    <td>@if(isset($data2['LMS_MAPPING_DATA'])){!! $data2['LMS_MAPPING_DATA'] !!}@endif</td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                        @endif
                        @php
                        $question_ids = "";
                        if( isset($data['questionpaper_data']['question_ids']) )
                        {
                           $question_ids = $data['questionpaper_data']['question_ids'];
                        }
                        @endphp
                           <input type="hidden" name="edit_id" value="@if(isset($data['edit_id'])){{$data['edit_id']}} @endif">
                        <input type="hidden" id="hidden_question_ids" name="hidden_question_ids" value={{$question_ids}}>
                        <div class="col-md-12 form-group">
                            <center>
                        @if(!isset($data['questionpaper_data']['question_ids']) )
                         <input type="hidden" name="action" value="save">

                                <input type="submit" name="submit" value="Save" onclick="return check_validation();" class="btn btn-success" >

                        @else
                         {{ method_field("PUT") }}
                        <input type="submit" name="update" value="Update" class="btn btn-success" >

                        @endif

                                                       </center>

                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

</div>



<!--START Modal -->
<div class="modal fade" id="myModal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
                <h4 class="modal-title" id="myModalLabel">LMS Mapping</h4>
            </div>
            <div class="modal-body" id="modal-body">

            </div>
          <!--   <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary">Save changes</button>
            </div>
 -->        </div>
    </div>
</div>
<!--END Modal -->

@include('includes.lmsfooterJs')
<script src="//cdnjs.cloudflare.com/ajax/libs/moment.js/2.9.0/moment-with-locales.js"></script>
<script src="//cdn.rawgit.com/Eonasdan/bootstrap-datetimepicker/e8bddc60e73c1ec2475f827be36e1957af72e2ea/src/js/bootstrap-datetimepicker.js"></script>

<script src="//cdn.mathjax.org/mathjax/latest/MathJax.js">
 MathJax.Hub.Config({
   extensions: ["mml2jax.js"],
   jax: ["input/MathML", "output/HTML-CSS"]
 });
</script>

<script type="text/javascript">
$(function () {
    $('#datetimepicker').datetimepicker({
        //format: 'DD/MM/YYYY hh:SS A'
    });
    $('#datetimepicker1').datetimepicker({
        //format: 'DD/MM/YYYY hh:SS A'
    });

});

function getStandardwiseDivision(std_id){
    var path = "{{ route('ajax_StandardwiseDivision') }}";
    $('#division_id').find('option').remove().end().append('<option value="">Select Division</option>').val('');
    $.ajax({url: path,data:'standard_id='+std_id, success: function(result){
        for(var i=0;i < result.length;i++){
            $("#division_id").append($("<option></option>").val(result[i]['division_id']).html(result[i]['name']));
        }
    }
    });
}

$( document ).ready(function() {
    //START Load question on edit question paper
    var hid = $("#hidden_question_ids").val();
    if(hid != "")
    {
        $("#subject").trigger("change");
    }
    //END Load question on edit question paper

    $('#timelimit_enable').click(function(){
        if($(this).prop("checked") == true){
            $('#time_allowed').attr('readonly', false);
            $('#time_allowed').val('');
        }
        else if($(this).prop("checked") == false){
            $('#time_allowed').attr('readonly', true);
            $('#time_allowed').val('');
        }
    });

    $("#standard").change(function(){
        var subject_id = $("#subject_id").val();
        var std_id = $("#standard").val();

        var path = "{{ route('ajax_LMS_StandardwiseSubject') }}";
        $('#subject').find('option').remove().end().append('<option value="">Select Subject</option>').val('');
        $.ajax({url: path,data:'std_id='+std_id, success: function(result){
            console.log(result);
            for(var i=0;i < result.length;i++){

                $("#subject").append($("<option></option>").val(result[i]['subject_id']).html(result[i]['display_name']));
            }
        }
        });
    })

    $("#search_mapping_type").change(function(){
        var mapping_type = $("#search_mapping_type").val();
        var path = "{{ route('ajax_LMS_MappingValue') }}";

        $('#search_mapping_value').find('option').remove().end().append('<option value="">Search By Mapping Value</option>').val('');

        $.ajax({
            url: path,
            data:'mapping_type='+mapping_type,
            success: function(result){
            for(var i=0;i < result.length;i++){
                $("#search_mapping_value").append($("<option></option>").val(result[i]['id']).html(result[i]['name']));
            }
        }
        });
    })

    $("#search_chapter").change(function(){
        var chapter_id = $("#search_chapter").val();
        var path = "{{ route('ajax_LMS_ChapterwiseTopic') }}";

        $('#search_topic').find('option').remove().end().append('<option value="">Search By Topic</option>').val('');

        $.ajax({
            url: path,
            data:'chapter_id='+chapter_id,
            success: function(result){
            for(var i=0;i < result.length;i++){
                $("#search_topic").append($("<option></option>").val(result[i]['id']).html(result[i]['name']));
            }
        }
        });
    })

});

function show_mappings(id)
{
    var data_html = $("#mapping_data_"+id).val();
    $('#map').html(data_html);

    $('#modal-body').html(data_html);

    $('#myModal').modal('show');

}

//START Load Questions
$("#subject").change(function(){
    var subject = $("#subject").val();
    var standard = $("#standard").val();

    // START Bind subject-wise chapter
    var getchapter_path = "{{ route('ajax_LMS_SubjectwiseChapter') }}";
    $('#search_chapter').find('option').remove().end().append('<option value="">Search By Chapter</option>').val('');
    $.ajax({
        url:getchapter_path,
        data:'sub_id='+subject+'&std_id='+standard,
        success:function(result)
        {
            for(var i=0;i < result.length;i++){
                $("#search_chapter").append($("<option></option>").val(result[i]['id']).html(result[i]['chapter_name']));
            }
        }
    });
    // END Bind subject-wise chapter


    // get_questionList(); //Bind Question List
})
//END Load Questions


function add_question(points) {
    var checked_questions = total_marks = 0;

    $("input[name='questions[]']:checked").each(function () {
        var val = $(this).attr('title');
        total_marks = parseInt(total_marks) + parseInt(val);
        checked_questions = checked_questions + 1;

        var correctAnswer = $(this).closest('tr').find("input[name='correct_answer']").val();
        console.log(correctAnswer);
        // Display the correct answer in an alert for the current selected checkbox
        if (correctAnswer && correctAnswer == '-' || correctAnswer == 'NULL' || correctAnswer == '') {
            alert("Answer of selected question not mapped . Please map answer first");
            $(this).prop('checked', false);
        }
    });

    $("#total_ques").val(checked_questions);
    $("#total_marks").val(total_marks);
    $("#total_marks").attr('checked', 'checked');
}

function check_validation()
{
    var checked_questions = err = 0;

   if($('#paper_name').val() == ''){
      alert('Paper Name can not be empty');
        err = 1;
   }
if($('#paper_desc').val() == ''){
      alert('Paper Description can not be empty');
        err = 1;

   }
   if($('#attempt_allowed').val() == ''){
      alert('Attempt Allowed can not be empty');
        err = 1;

   }
   if($('#time_allowed').val() == ''){
      alert('Allowed Time (mins) can not be empty');
        err = 1;

   }

    $("input[name='questions[]']:checked").each(function ()
    {
        checked_questions = checked_questions + 1;
    });
    if(checked_questions == 0)
    {
        alert("Please Select Atleast one question in paper from search");
        err = 1;
    }

    var open_date = $("#open_date").val();
    var close_date = $("#close_date").val();
    if(open_date != "" && close_date != "")
    {
        if(Date.parse(open_date) > Date.parse(close_date))
        {
            alert("Please select proper Open Date and Close Date");
            err = 1;

        }
    }

    if(err == 1)
    {
        return false;
    }else{
        return true;
    }
}
</script>

@include('includes.footer')
@endsection
