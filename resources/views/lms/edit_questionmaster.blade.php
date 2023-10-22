{{--@include('includes.lmsheadcss')--}}
@extends('lmslayout')
@section('container')
<link href="/plugins/bower_components/clockpicker/dist/jquery-clockpicker.min.css" rel="stylesheet">
<!-- <link href="{{ asset('/plugins/bower_components/summernote/dist/summernote.css') }}" rel="stylesheet" /> -->
<style>
.tooltip-inner {
    max-width: 1100px !important;
}
br {
     display: block !important;
}
</style>
{{--@include('includes.header')
@include('includes.sideNavigation')--}}
<!-- Content main Section -->
<div id="page-wrapper">
    <div class="container-fluid mb-5">
        <div class="row bg-title align-items-center justify-content-between">
            <div class="col-lg-6 col-md-4 col-sm-4 col-xs-12 mb-4">
                <h1 class="h4 mb-3">Edit Question Answer</h1>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb bg-transparent p-0">
                        <li class="breadcrumb-item"><a href="{{route('course_master.index')}}">LMS</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('chapter_master.index',['standard_id'=>$data['breadcrum_data']->standard_id,'subject_id'=>$data['breadcrum_data']->subject_id]) }}">{{$data['breadcrum_data']->subject_name}}</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('topic_master.index',['id'=>$data['breadcrum_data']->chapter_id]) }}">{{$data['breadcrum_data']->chapter_name}}</a></li>
                        @isset($data['questionmaster_data']['topic_id'])
                            <li class="breadcrumb-item"><a href="{{ route('topic_master.index',['id'=>$data['breadcrum_data']->chapter_id]) }}">{{$data['breadcrum_data']->topic_name}}</a></li>
                        @endisset
                        <li class="breadcrumb-item active" aria-current="page">Edit Question Answer</li>
                    </ol>
                </nav>
            </div>
        </div>
        <div class="card border-0">
            <div class="card-body">
                <form method="post" action="{{ route('question_master.update',[$data['questionmaster_data']['id']]) }}" enctype='multipart/form-data'>
                    {{ method_field("PUT") }}
                    @csrf
                    <input type="hidden" name="grade_id" id="grade_id" value="@if(isset($data['questionmaster_data']['grade_id'])){{$data['questionmaster_data']['grade_id']}}@endif">
                    <input type="hidden" name="standard_id" id="standard_id" value="@if(isset($data['questionmaster_data']['standard_id'])){{$data['questionmaster_data']['standard_id']}}@endif">
                    <input type="hidden" name="subject_id" id="subject_id" value="@if(isset($data['questionmaster_data']['subject_id'])){{$data['questionmaster_data']['subject_id']}}@endif">
                    <input type="hidden" name="chapter_id" id="chapter_id" value="@if(isset($data['questionmaster_data']['chapter_id'])){{$data['questionmaster_data']['chapter_id']}}@endif">
                    @isset($data['questionmaster_data']['topic_id'])
                        <input type="hidden" name="topic_id" id="topic_id" value="@if(isset($data['questionmaster_data']['topic_id'])){{$data['questionmaster_data']['topic_id']}}@endif">
                    @endisset

                    <div class="row align-items-center">

                        <div class="col-md-8">
                            <div class="form-group">
                                <label for="topicType">Question</label>
                                <!-- <input type="text" class="form-control" id="question_title" name="question_title" placeholder="Enter Question"
                                value="@if(isset($data['questionmaster_data']['question_title'])){{$data['questionmaster_data']['question_title']}}@endif"> -->
                                 <textarea name="question_title" id="question_title" contenteditable="true" onchange="check_input(this)">
                                     @if(isset($data['questionmaster_data']['question_title'])){{$data['questionmaster_data']['question_title']}}@endif
                                 </textarea>
                                 <!-- <textarea class="summernote" id="question_title" name="question_title">
                                     @if(isset($data['questionmaster_data']['question_title'])){{$data['questionmaster_data']['question_title']}}@endif
                                 </textarea> -->
                            </div>
                        </div>

                    </div>
                    <div class="row">

                    </div>
                    <div class="row">
                        <div class="col-md-8">
                            <div class="form-group">
                                <label for="description">Description</label>
                                <textarea type="text" rows="4" class="form-control" name="description" id="description" placeholder="Description">@if(isset($data['questionmaster_data']['description'])){{$data['questionmaster_data']['description']}}@endif</textarea>
                            </div>
                        </div>
                    </div>


                        @if(isset($data['question_mapping_data']))
                            @php $j = 1; @endphp
                            @foreach($data['question_mapping_data'] as $mkey => $mval)
                                <div class="addButtonCheckbox_old" id="old_data_{{$j}}">
                                    <div class="row align-items-center">
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label for="topicType">Mapping Type</label>
                                                <select class="load_map_value cust-select form-control mb-0" name="mapping_type[]" data-new = "{{$j}}">
                                                    <option value="">Select Mapping Type</option>
                                                    @if(isset($data['lms_mapping_type']))
                                                        @foreach($data['lms_mapping_type'] as $key => $value)
                                                        <option value="{{$value['id']}}" @if( $mval['TYPE_ID'] == $value['id']) selected @endif>{{$value['name']}}</option>
                                                        @endforeach
                                                    @endif
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label for="topicType2">Mapping Value</label>
                                                <select name="mapping_value[]" data-new = "{{$j}}" class="cust-select form-control mb-0">
                                                    @foreach($data['lms_mapping_value'][$mval['TYPE_ID']] as $key1 => $value1)
                                                        <option value="{{$key1}}" @if( $mval['VALUE_ID'] == $key1) selected @endif>{{$value1}}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>

                                         <div class="col-md-3">
                                <div class="form-group">
                                    <label for="topicType2">Reason</label>
                                    <textarea name="reasons[]" class="form-control" data-reason="{{$j}}" row="2">{{$mval['REASONS']}}</textarea>
                                </div>
                            </div>
                                        <div class="col-md-3">
                                            <a href="javascript:void(0);" onclick="removeOldRow({{$j}});" class="d-inline btn btn-danger btn-sm"><i class="mdi mdi-minus"></i></a>
                                        </div>
                                        <div class="clearfix"></div>
                                    </div>
                                </div>

                                @php $j++; @endphp
                            @endforeach
                        @endif

                        <div class="addButtonCheckbox_MAPPING">
                            <div class="row align-items-center">
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="topicType">Mapping Type</label>
                                        <select class="load_map_value cust-select form-control mb-0" name="mapping_type[]" data-new = "999">
                                            <option value="">Select Mapping Type</option>
                                            @if(isset($data['lms_mapping_type']))
                                                @foreach($data['lms_mapping_type'] as $key => $value)
                                                <option value="{{$value['id']}}">{{$value['name']}}</option>
                                                @endforeach
                                            @endif
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="topicType2">Mapping Value</label>
                                        <select name="mapping_value[]" data-new = "999" class="cust-select form-control mb-0">
                                            <option value="">Select Mapping Value</option>
                                        </select>
                                    </div>
                                </div>

                                        <div class="col-md-3">
                                <div class="form-group">
                                    <label for="topicType2">Reason</label>
                                    <textarea name="reasons[]" class="form-control" data-reason="999" row="2"></textarea>
                                </div>
                            </div>

                                <div class="col-md-3">
                                    <a href="javascript:void(0);" onclick="addNewRow_MAPPING();" class="d-inline btn btn-success btn-sm mr-2"><i class="mdi mdi-plus"></i></a>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                                <div class="form-group">
                                    <label for="topicType2">Learning Outcome</label>
                                  <textarea name="learning_outcome" class="form-control" row="2">{{$data['questionmaster_data']['learning_outcome']}}</textarea>
                                  </div>
                            </div>
                    <div class="col-md-8 border">
                        <label for="title" class="mt-2 text-primary font-weight-bold">Pre Topic</label>
                        @php
                        $prestandard_id = $presubject_id = $prechapter_id = $pretopic_id = "";

                        if(isset($data['pretopicData']) && count($data['pretopicData']) > 0 )
                        {
                            $prestandard_id = $data['pretopicData']['standard_id'];
                            $presubject_id = $data['pretopicData']['subject_id'];
                            $prechapter_id = $data['pretopicData']['chapter_id'];
                            $pretopic_id = "";
                            if(isset($data['pretopicData']['topic_id']))
                            {
                                $pretopic_id = $data['pretopicData']['topic_id'];
                            }
                        }
                        @endphp
                        {{ App\Helpers\LMSSearchChain('3','single','pre',$data['questionmaster_data']['standard_id'],'std,sub,chapter,topic',$prestandard_id,$presubject_id,$prechapter_id,$pretopic_id) }}
                    </div>

                    <div class="col-md-8 border">
                        <label for="title" class="mt-2 text-primary font-weight-bold">Post Topic</label>
                        @php
                        $poststandard_id = $postsubject_id = $postchapter_id = $posttopic_id = "";

                        if(isset($data['posttopicData']) && count($data['posttopicData']) > 0 )
                        {
                            $poststandard_id = $data['posttopicData']['standard_id'];
                            $postsubject_id = $data['posttopicData']['subject_id'];
                            $postchapter_id = $data['posttopicData']['chapter_id'];
                            $posttopic_id = "";
                            if(isset($data['posttopicData']['topic_id']))
                            {
                                $posttopic_id = $data['posttopicData']['topic_id'];
                            }
                        }

                        @endphp
                        {{ App\Helpers\LMSSearchChain('3','single','post',$data['questionmaster_data']['standard_id'],'std,sub,chapter,topic',$poststandard_id,$postsubject_id,$postchapter_id,$posttopic_id) }}
                    </div>

                    <div class="col-md-8 border">
                        <label for="title" class="mt-2 text-primary font-weight-bold">Cross Curriculum</label>
                        @php
                        $ccstandard_id = $ccsubject_id = $ccchapter_id = $cctopic_id = "";

                        if(isset($data['cctopicData']) && count($data['cctopicData']) > 0 )
                        {
                            $ccstandard_id = $data['cctopicData']['standard_id'];
                            $ccsubject_id = $data['cctopicData']['subject_id'];
                            $ccchapter_id = $data['cctopicData']['chapter_id'];
                            $cctopic_id = "";
                            if(isset($data['cctopicData']['topic_id']))
                            {
                                $cctopic_id = $data['cctopicData']['topic_id'];
                            }
                        }

                        @endphp
                        {{ App\Helpers\LMSSearchChain('3','single','cross-curriculum',$data['questionmaster_data']['standard_id'],'std,sub,chapter,topic',$ccstandard_id,$ccsubject_id,$ccchapter_id,$cctopic_id) }}
                    </div>

                    <div class="row">

                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="subject">Question Type:</label>
                                <select name="question_type_id" id="question_type_id" class="form-control" required onchange="show_ans(this.value);"
                                @if(isset($data['questionmaster_data']['question_type_id'])) disabled  @endif>
                                    <option value="">Select Question Type</option>
                                        @foreach($data['questiontype_data'] as $key1 => $value1)
                                        <option value="{{$value1['id']}}" @if(isset($data['questionmaster_data']['question_type_id'])) @if($data['questionmaster_data']['question_type_id']==$value1['id']) selected='selected' @endif @endif>{{ucwords($value1['question_type'])}}</option>
                                        @endforeach
                                </select>
                                @if(isset($data['questionmaster_data'])) <input type="hidden" name="hid_question_type_id" value="{{$data['questionmaster_data']['question_type_id']}}"> @endif
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label for="restrictAccess">Question Mark</label>
                                <input type="text" class="form-control" id="points" name="points" placeholder="Question Mark"
                                value="@if(isset($data['questionmaster_data']['points'])){{$data['questionmaster_data']['points']}}@endif">
                                <!-- <div class="d-flex align-items-center">
                                    <small id="emailHelp" class="form-text text-muted mb-3 ml-2">Marks</small>
                                </div> -->
                            </div>
                        </div>

                        <div class="col-md-2">
                            <div class="form-group">
                                <label>Multiple Answers</label>
                                <br><input type="checkbox" id="multiple_answer" name="multiple_answer" value="1" onclick="add_checkbox(this);"
                                @if( isset($data['questionmaster_data']['multiple_answer']) && $data['questionmaster_data']['multiple_answer'] == 1)
                                checked
                                @endif
                                @if(isset($data['questionmaster_data'])) disabled  @endif
                                >
                            </div>
                        </div>

                        <div class="col-md-2">
                            <div class="form-group">
                                <label>Show</label>
                                <br><input type="checkbox" id="status" name="status" value="1"
                                @if( isset($data['questionmaster_data']['status']) && $data['questionmaster_data']['status'] == 1)
                                checked
                                @elseif(!isset($data['questionmaster_data']))
                                checked
                                @endif
                                >
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="concept">Concept</label>
                                <input type="text" class="form-control" id="concept" name="concept" placeholder="Concept"
                                value="@if(isset($data['questionmaster_data']['concept'])){{$data['questionmaster_data']['concept']}}@endif">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="subconcept">Sub Concept</label>
                                <input type="text" class="form-control" id="subconcept" name="subconcept" placeholder="Sub Concept"
                                value="@if(isset($data['questionmaster_data']['subconcept'])){{$data['questionmaster_data']['subconcept']}}@endif">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="answer">Answer</label>
                                <textarea class="form-control" id="answer" name="answer">@if(isset($data['questionmaster_data']['answer'])){{$data['questionmaster_data']['answer']}}@endif</textarea>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="hint_text">Hint</label>
                                <textarea class="form-control" id="hint_text" name="hint_text">@if(isset($data['questionmaster_data']['hint_text'])){{$data['questionmaster_data']['hint_text']}}@endif</textarea>
                            </div>
                        </div>
                        <!-- <div class="col-md-12">
                            <div class="custom-control custom-checkbox mb-3">
                                <input type="checkbox" class="custom-control-input" id="customCheckDisabled">
                                <label class="custom-control-label pt-1" for="customCheckDisabled">Shuffle the Choice</label>
                            </div>
                        </div> -->
                    </div>

                    <div class="border rounded mb-3 mb-md-4 mt-3 p-4 main_option_div" style="display:none;">
                        <div class="h4 mb-3">Answer</div>
                        <div class="form-group row addButtonCheckbox" style="margin-bottom: 20px!important;">
                            <label for="colFormLabel" class="col-sm-1 col-form-label">Options</label>
                            <div class="col-sm-4">
                              <input type="text" class="form-control" id='options[NEW][]' name='options[NEW][]' placeholder="Enter Option">
                            </div>
                            <div class="col-sm-1">
                                <div class="custom-control custom-radio">
                                    <input type="radio" class="form-control radio_checkbox" name="correct_answer[]" value="0" style="height:30%;width: 15px;">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <a href="javascript:void(0);" onclick="addNewRow();" class="d-inline btn btn-success btn-sm mr-2"><i class="mdi mdi-plus"></i></a>
                            </div>
                        </div>
                        <div class="clearfix"></div>
                    </div>
                    @if( isset($data['answer_data']) )
                    <div class="border rounded mb-3 mb-md-4 mt-3 p-4 main_option_div">
                        <div class="h4 mb-3">Answer</div>
                            @foreach($data['answer_data'] as $key =>$val)
                            <div class="addButtonCheckbox row" id="optionblock_{{$val['id']}}">
                                <div class="col-sm-4">
                                    <input type="text" id="options[EDIT][{{$val['id']}}]" name="options[EDIT][{{$val['id']}}]"  value="{{$val['answer']}}" required class="form-control">
                                </div>
                                <div class="col-sm-4">
                                    <input type="text" id="feedback[EDIT][{{$val['id']}}]" name="feedback[EDIT][{{$val['id']}}]"  value="{{$val['feedback']}}" class="form-control">
                                </div>
                                <div class="col-sm-1">
                                    <div class="custom-control custom-radio">
                                    @php
                                    if($data['questionmaster_data']['multiple_answer'] == 1){
                                        $types = "checkbox";
                                    }else{
                                        $types = "radio";
                                    }
                                    @endphp
                                    <input type="{{$types}}" name="correct_answer[]" class="form-control radio_checkbox" value="{{$val['id']}}" style="height:30%;width: 15px;"
                                    @if( $val['correct_answer'] == 1)
                                    checked
                                    @endif>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <a href="javascript:void(0);" onclick="removeNewRowAjax({{$val['id']}});" class="d-inline btn btn-danger btn-sm mr-2"><i class="mdi mdi-minus"></i></a>
                                </div>
                            </div>
                            <div class="clearfix"></div>
                            @endforeach
                        </div>
                    </div>
                    @endif

                    <div class="col-md-2">
                        <div class="form-group">
                            <button class="btn btn-primary" type="submit">Save</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

</div>
@include('includes.lmsfooterJs')
<!-- <script src="{{asset('/plugins/bower_components/summernote/dist/summernote.min.js')}}"></script> -->
<script src="{{ asset("/ckeditor_wiris/ckeditor4/ckeditor.js") }}"></script>
<script>
    CKEDITOR.config.toolbar_Full =
        [
        { name: 'document', items : [ 'Source'] },
        { name: 'clipboard', items : [ 'Cut','Copy','Paste','-','Undo','Redo' ] },
        { name: 'editing', items : [ 'Find'] },
        { name: 'basicstyles', items : [ 'Bold','Italic','Underline'] },
        { name: 'paragraph', items : [ 'JustifyLeft','JustifyCenter','JustifyRight'] }
        ];
    CKEDITOR.config.height = '40px';
    CKEDITOR.plugins.addExternal('divarea', '../examples/extraplugins/divarea/', 'plugin.js');
    CKEDITOR.plugins.addExternal('sharedspace', '../examples/extraplugins/sharedspace/', 'plugin.js');
    CKEDITOR.plugins.addExternal('filebrowser', '../examples/extraplugins/filebrowser/', 'plugin.js');
    CKEDITOR.plugins.addExternal('enterkey', '../examples/extraplugins/enterkey/', 'plugin.js');
    CKEDITOR.plugins.addExternal('FMathEditor', '../examples/extraplugins/FMathEditor/', 'plugin.js');
    CKEDITOR.config.removePlugins = 'maximize';
    CKEDITOR.config.removePlugins = 'resize';
    CKEDITOR.config.sharedSpaces = { top: 'toolbar1'};
    CKEDITOR.replace('question_title', {
         extraPlugins: 'filebrowser,divarea,sharedspace,FMathEditor,enterkey',
         enterMode: '2',
         language: 'en',
         filebrowserUploadUrl: "{{route('uploadimage',['_token' => csrf_token() ])}}",
         filebrowserUploadMethod: 'form'
    });
    var editor = CKEDITOR.instances['question_title'];

editor.on('blur', function() {
       // Call the check_input function when the CKEditor loses focus
       check_input(editor.getData());
   });
</script>
<script>

$(document).on('change','.load_map_value', function(){
    var mapping_type = $(this).val();
    var data_new = $(this).attr('data-new');
       // alert(mapping_type);
       // alert(data_new);

    var path = "{{ route('ajax_LMS_MappingValue') }}";
    //$('#mapping_value').find('option').remove().end();
    $.ajax({
        url:path,
        data:'mapping_type='+mapping_type,
        success:function(result){
            //var e = $('#mapping_value[data-new='+data_new+']');
            var e = $('select[name="mapping_value[]"][data-new='+data_new+']');
            $(e).find('option').remove().end();
            for(var i=0;i < result.length ;i++)
            {
                $(e).append($("<option></option>").val(result[i]['id']).html(result[i]['name']));
                //$("#mapping_value[]").append($("<option></option>").val(result[i]['id']).html(result[i]['name']));
            }
        }
    });
});



//START Multiple LMS mapping
function addNewRow_MAPPING(){
    $('select[name="mapping_type[]"]').each(function(){
        data_new =  parseInt($(this).attr('data-new'));
        html = $(this).html();
    });
    data_new = parseInt(data_new) + 1;

    var mapping_type_data = html;//$('#mapping_type:first').html();
    var htmlcontent = '';
    htmlcontent += '<div class="clearfix"></div><div class="addButtonCheckbox_MAPPING" style="display: flex; margin-right: -15px; margin-left: -15px; flex-wrap: wrap;">';

    htmlcontent += '<div class="col-md-3"><div class="form-group"><label for="topicType">Mapping Type</label><select class="load_map_value form-control cust-select" name="mapping_type[]" data-new='+data_new+'>'+mapping_type_data+'</select></div></div>';
    htmlcontent += '<div class="col-md-3"><div class="form-group"><label for="topicType2">Mapping Value</label><select class="form-control cust-select" name="mapping_value[]" data-new='+data_new+'><option value="">Select Mapping Value</option></select></div></div>';
    htmlcontent += '<div class="col-md-3"><div class="form-group"><label for="topicType2">Reasons</label><textarea class="form-control " name="reasons[]" data-reason='+data_new+' row=2></textarea></div></div>';
    htmlcontent += '<div class="col-md-3" style="margin-top: 32px;"><a href="javascript:void(0);" onclick="removeNewRow_MAPPING();" class="d-inline btn btn-danger btn-sm"><i class="mdi mdi-minus"></i></a></div></div>';

    $('.addButtonCheckbox_MAPPING:last').after(htmlcontent);
}
function removeNewRow_MAPPING() {
    $(".addButtonCheckbox_MAPPING:last" ).remove();
}
//END Multiple LMS mapping

function addNewRow(){
    var elements = document.getElementsByClassName("radio_checkbox");
    var count = 0;
    for(var i = 0; i < elements.length;i++){
            count++;
    }

    if($("#multiple_answer").prop("checked") == true){
        types = "checkbox";
    }else{
        types = "radio";
    }
    var html = '';

    html += '<div class="clearfix"></div><div class="form-group row addButtonCheckbox" style="margin-right: -15px !important;margin-left: -15px !important;">';
    html += '<label for="colFormLabel" class="col-sm-1 col-form-label">Options</label><div class="col-sm-4"><input type="text" class="form-control" id="options[NEW][]" name="options[NEW][]" placeholder="Enter Option"></div>';
    html += '<div class="col-sm-1"><div class="custom-control custom-radio"><input type="'+types+'" class="form-control radio_checkbox" name="correct_answer[]" value="'+count+'" style="height:30%;width: 15px;"></div></div>';
    html += '<div class="col-md-4"><a href="javascript:void(0);" onclick="removeNewRow();" class="d-inline btn btn-danger btn-sm mr-2"><i class="mdi mdi-minus"></i></a></div></div>';
    $('.addButtonCheckbox:last').after(html);
}

function removeNewRow() {
    $(".addButtonCheckbox:last" ).remove();
}

function show_ans(question_type)
{
    if(question_type == 1)//Show Multiple Answer block
    {
        $(".main_option_div").show();
        $(".option_class").attr('required', 'true');
    }else{ //Show Narrrative Answer block
        $(".main_option_div").hide();
        $(".option_class").removeAttr('required');
    }
}

function add_checkbox()
{
    if($("#multiple_answer").prop("checked") == true){
        $(".radio_checkbox").attr('type', 'checkbox');
    }else{
        $(".radio_checkbox").attr('type', 'radio');
    }
}

function removeNewRowAjax(id) {
    var elements = document.getElementsByClassName("radio_checkbox");
    var count = 0;
    for(var i = 0; i < elements.length;i++){
            count++;
    }
    count = (count-1);
    if(count == 1)
    {
        alert("Atleast one answer is required");
    }
    else{
        var path = "{{ route('ajaxdestroyanswer_master') }}";
        $.ajax({
            url: path,
            type:'post',
            data: {"id": id},
            success: function(result){
                $("#optionblock_"+id).remove();
            }
        });
    }
}

function removeOldRow(j) {
    $("#old_data_"+j ).remove();
}

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
    $("#standard").change(function(){
        var std_id = $("#standard").val();
        var path = "{{ route('ajax_StandardwiseSubject') }}";
        $('#subject').find('option').remove().end().append('<option value="">Select Subject</option>').val('');
        $.ajax({url: path,data:'std_id='+std_id, success: function(result){
            for(var i=0;i < result.length;i++){
                $("#subject").append($("<option></option>").val(result[i]['subject_id']).html(result[i]['display_name']));
            }
        }
        });
    })
});

//START Bind chapters
$("#subject").change(function(){
    var subject = $("#subject").val();
    var standard = $("#standard").val();
    var path = "{{ route('ajax_SubjectwiseChapter') }}";
    $('#chapter').find('option').remove().end().append('<option value="">Select Chapter</option>').val('');
    $.ajax({
        url:path,
        data:'sub_id='+subject+'&std_id='+standard,
        success:function(result){
            for(var i=0;i < result.length ;i++)
            {
                $("#chapter").append($("<option></option>").val(result[i]['id']).html(result[i]['chapter_name']));
            }
        }
    });

    //START Bind LO Category
    var path = "{{ route('ajax_SubjectwiseLoCategory') }}";
    $('#locategory').find('option').remove().end().append('<option value="">Select Lo Category</option>').val('');
    $.ajax({
        url:path,
        data:'sub_id='+subject+'&std_id='+standard,
        success:function(result){
            for(var i=0;i < result.length ;i++)
            {
                $("#locategory").append($("<option></option>").val(result[i]['id']).html(result[i]['title']));
            }
        }
    });
    //END Bind LO Category
})
//END Bind chapters

//START Bind LO Master
$("#chapter").change(function(){
    var chapter = $("#chapter").val();

    var path = "{{ route('ajax_ChapterwiseLOmaster') }}";
    $('#lomaster').find('option').remove().end();
    $.ajax({
        url:path,
        data:'chapter_id='+chapter,
        success:function(result){
            for(var i=0;i < result.length ;i++)
            {
                $("#lomaster").append($("<option></option>").val(result[i]['id']).html(result[i]['title']));
            }
        }
    });
})
//END Bind LO Master

//START Bind LO Indicator
$("#lomaster").change(function(){
    var lomaster = $("#lomaster").val();
    var path = "{{ route('ajax_LoMasterwiseLoIndicator') }}";
    $('#loindicator').find('option').remove().end();
    $.ajax({
        url:path,
        data:'lomaster_ids='+lomaster,
        success:function(result){
            for(var i=0;i < result.length ;i++)
            {
                $("#loindicator").append($("<option></option>").val(result[i]['id']).html(result[i]['indicator']));
            }
        }
    });
})
//END Bind LO Indicator

</script>
//END Bind LO Indicator
</script>

@php
$std_name = DB::table('standard')->where(['id'=>$data['questionmaster_data']['standard_id'],'sub_institute_id'=>session()->get('sub_institute_id')])->first();
 @endphp
 <script>
//map value

// Define the load_map_value function
function load_map_value(data_new, selectedValue,map_val) {
    var mapping_type = $('select[name="mapping_type[]"][data-new=' + data_new + ']').val();
    var path = "{{ route('ajax_LMS_MappingValue') }}";
    $.ajax({
        url: path,
        data: 'mapping_type=' + mapping_type,
        success: function(result) {
            var e = $('select[name="mapping_value[]"][data-new=' + data_new + ']');
            $(e).find('option').remove().end();
            for (var i = 0; i < result.length; i++) {
                $(e).append($("<option></option>").val(result[i]['id']).html(result[i]['name']));
                if (result[i]['name'] === map_val) {
                    $(e).find('option[value="' + result[i]['id'] + '"]').attr('selected', true);
                }
            }
        }
    });
}

// map type
function check_input(inputElement) {
    var inputValue = inputElement.value;
    var editor = CKEDITOR.instances['question_title'];

// Get the content from the CKEditor instance
var inputValue = editor.getData() ?? inputElement.value;
    var std ="{!!$std_name->name!!}";

      var data = {
        "question": inputValue,
        "standard": std,
        "type_depth":9,
        "type_bloom":82,
        "type_learning":"learn",
    };

    var path = "{{ route('chat') }}";
    $.ajax({
        url:path,
        data: data,
        success:function(result){
            console.log(result);
            var selectElement_type = document.getElementById("mapping_type");

            $('select[name="mapping_type[]"]').each(function() {
                data_new =  parseInt($(this).attr('data-new'));
                html = $(this).html();
            });

            data_new = parseInt(data_new) + 1;

           var parsedResult = JSON.parse(result);

        // Extract the values for answer_depth and answer_bloom
        var answer_depth = parsedResult[0].question_depth;
        var reason_depth = parsedResult[0].reason_depth;
        // console.log(reason_depth);
        var answer_bloom = parsedResult[0].question_bloom;
        var reason_bloom = parsedResult[0].reason_bloom;

        var answer_learning = parsedResult[0].question_learning;

        var SelectElement_type1 = $('select[name="mapping_type[]"][data-new=1]');
        SelectElement_type1.val(9);
        load_map_value(1,9,answer_depth);
        $('textarea[name="reasons[]"][data-reason=1]').val(reason_depth);
        $('textarea[name="learning_outcome"]').val(answer_learning);

        var mappingTypeValues = [9, 82];
        var selbox = [2];

        var SelectElement_type2 = $('select[name="mapping_type[]"][data-new=2]');
        SelectElement_type2.val(82);
        load_map_value(2,82,answer_bloom);

        // $('textarea[name="reason_2"]').val(reason_bloom);
        $('textarea[name="reasons[]"][data-reason=2]').val(reason_bloom);

        $('textarea[name="learning"]').val(answer_learning);



        }
    });

}
</script>
@include('includes.footer')
@endsection
