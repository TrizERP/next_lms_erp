{{--@include('includes.lmsheadcss')--}}
@extends('lmslayout')
@section('container')
<link href="/plugins/bower_components/clockpicker/dist/jquery-clockpicker.min.css" rel="stylesheet">
<style>
.tooltip-inner {
    max-width: 1100px !important;
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
                         <li class="breadcrumb-item">Counselling</li>
                        <li class="breadcrumb-item active" aria-current="page">Edit Question Answer</li>
                    </ol>
                </nav>
            </div>
        </div>
        <div class="card border-0">
            <div class="card-body">
                <form method="post" action="{{ route('lmsCounsellingQuestion.update',['id'=>$data['questionmaster_data']['id']]) }}" enctype='multipart/form-data'>
                    {{ method_field("PUT") }}
                    @csrf
                    <input type="hidden" name="course_id" id="course_id" value="@if(isset($data['questionmaster_data']['counselling_course_id'])){{$data['questionmaster_data']['counselling_course_id']}}@endif">

                    <div class="row align-items-center">

                        <div class="col-md-8">
                            <div class="form-group">
                                <label for="topicType">Question</label>
                                <input type="text" class="form-control" id="question_title" name="question_title" placeholder="Enter Question"
                                value="@if(isset($data['questionmaster_data']['question_title'])){{$data['questionmaster_data']['question_title']}}@endif">
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
                                        <div class="col-md-4">
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
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label for="topicType2">Mapping Value</label>
                                                <select name="mapping_value[]" data-new = "{{$j}}" class="cust-select form-control mb-0">
                                                    @foreach($data['lms_mapping_value'][$mval['TYPE_ID']] as $key1 => $value1)
                                                        <option value="{{$key1}}" @if( $mval['VALUE_ID'] == $key1) selected @endif>{{$value1}}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
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
                                <div class="col-md-4">
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
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="topicType2">Mapping Value</label>
                                        <select name="mapping_value[]" data-new = "999" class="cust-select form-control mb-0">
                                            <option value="">Select Mapping Value</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <a href="javascript:void(0);" onclick="addNewRow_MAPPING();" class="d-inline btn btn-success btn-sm mr-2"><i class="mdi mdi-plus"></i></a>
                                </div>
                            </div>
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
                                <div class="col-md-4">
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

    htmlcontent += '<div class="col-md-4"><div class="form-group"><label for="topicType">Mapping Type</label><select class="load_map_value form-control cust-select" name="mapping_type[]" data-new='+data_new+'>'+mapping_type_data+'</select></div></div>';
    htmlcontent += '<div class="col-md-4"><div class="form-group"><label for="topicType2">Mapping Value</label><select class="form-control cust-select" name="mapping_value[]" data-new='+data_new+'><option value="">Select Mapping Value</option></select></div></div>';
    htmlcontent += '<div class="col-md-4" style="margin-top: 32px;"><a href="javascript:void(0);" onclick="removeNewRow_MAPPING();" class="d-inline btn btn-danger btn-sm"><i class="mdi mdi-minus"></i></a></div></div>';

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
        var path = "{{ route('ajaxdestroycounsellinganswer_master') }}";
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




</script>
@include('includes.footer')
@endsection
