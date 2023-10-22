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
                <h1 class="h4 mb-3">Add Question Answer</h1>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb bg-transparent p-0 mb-0">
                        <li class="breadcrumb-item">Counselling</li>
                        <li class="breadcrumb-item active" aria-current="page">Add Question Answer</li>
                    </ol>
                </nav>
            </div>
        </div>
        <div class="card border-0">
            <div class="card-body">
                <form action="{{ route('lmsCounsellingQuestion.store') }}" method="post" enctype='multipart/form-data'>
                    {{ method_field("POST") }}
                    @csrf

                    <input type="hidden" name="course_id" id="course_id" value="{{$data['course_id']}}">

                    <div class="row">
                        <div class="col-md-8">

                            <div class="row align-items-center">
                                <div class="col-md-12">
                                    <div class="form-group">
                                        <label for="topicType">Question</label>
                                        <input type="text" class="form-control" id="question_title" name="question_title" placeholder="Enter Question">
                                    </div>
                                </div>

                                <div class="col-md-12">
                                    <div class="form-group">
                                        <label for="description">Description</label>
                                        <textarea type="text" rows="4" class="form-control mb-0" name="description" id="description" placeholder="Description"></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>


                    <div class="addButtonCheckbox1">
                        <div class="row align-items-center">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="topicType">Mapping Type</label>
                                    <select class="load_map_value cust-select form-control mb-0" name="mapping_type[]" data-new = "1">
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
                                    <select class="cust-select form-control mb-0" name="mapping_value[]" data-new="1">
                                        <option value="">Select Mapping Value</option>
                                    </select>
                                </div>
                            </div>

                            <div class="col-md-4">
                                <a href="javascript:void(0);" onclick="addNewRow1();" class="btn btn-success btn-sm mr-2"><i class="mdi mdi-plus"></i></a>
                            </div>
                        </div>
                    </div>



                    <div class="row">
                        <div class="col-md-8">
                        <div class="form-group">
                                <label for="question_type_id">Question Type:</label>
                                <select name="question_type_id" id="question_type_id" class="form-control" required onchange="show_ans(this.value);"
                                @if(isset($data['questionmaster_data']['question_type_id'])) disabled  @endif>
                                    <option value="">Select Question Type</option>
                                        @foreach($data['questiontype_data'] as $key1 => $value1)
                                        <option value="{{$value1['id']}}" @if(isset($data['questionmaster_data']['question_type_id'])) @if($data['questionmaster_data']['question_type_id']==$value1['id']) selected='selected' @endif @endif>{{ucwords($value1['question_type'])}}</option>
                                        @endforeach
                                </select>
                                @if(isset($data['questionmaster_data'])) <input type="hidden" name="hid_question_type_id" value="{{$data['questionmaster_data']['question_type_id']}}"> @endif
                            </div>
                            <div class="form-group">
                                <label for="points">Question Mark</label>
                                <input type="text" class="form-control" id="points" name="points" placeholder="Question Mark" required>
                            </div>
                            <div class="form-group">
                                <label for="multiple_answer">Multiple Answers</label>
                                <input type="checkbox" id="multiple_answer" name="multiple_answer" value="1" onclick="add_checkbox(this);"
                                @if( isset($data['questionmaster_data']['multiple_answer']) && $data['questionmaster_data']['multiple_answer'] == 1)
                                checked
                                @endif
                                @if(isset($data['questionmaster_data'])) disabled  @endif
                                >
                            </div>
                            <div class="form-group">
                                <label for="status">Show</label>
                                <input type="checkbox" id="status" name="status" value="1"
                                @if( isset($data['questionmaster_data']['status']) && $data['questionmaster_data']['status'] == 1)
                                checked
                                @elseif(!isset($data['questionmaster_data']))
                                checked
                                @endif
                                >
                            </div>
                            <div class="border rounded mb-3 mb-md-4 mt-3 p-4 main_option_div" style="display:none1;">
                                <div class="h4 mb-3">Answer</div>
                                <div class="form-group row addButtonCheckbox mb-3 align-items-center">
                                    <label for="colFormLabel" class="col-sm-3 col-form-label my-2">Options</label>
                                    <div class="col-sm-4 my-2">
                                    <input type="text" class="form-control option_class mb-0" id='options[NEW][]' name='options[NEW][]' placeholder="Enter Option" required>
                                    </div>
                                    <div class="col-sm-1">
                                        <div class="custom-control custom-radio my-2">
                                            <input type="radio" class="form-control radio_checkbox mb-0" name="correct_answer[]" value="0">
                                        </div>
                                    </div>
                                    <div class="col-md-4 my-2">
                                        <a href="javascript:void(0);" onclick="addNewRow();" class="btn btn-success btn-sm mr-2 mb-0"><i class="mdi mdi-plus"></i></a>
                                    </div>
                                </div>
                                <div class="clearfix"></div>
                            </div>
                        </div>
                    </div>
                    <button class="btn btn-primary" type="submit">Submit</button>
                </form>
            </div>
        </div>
    </div>
</div>
@include('includes.lmsfooterJs')

<script>
$(document).on('change','.load_map_value', function(e){
    e.stopPropagation();
    e.preventDefault();
    var mapping_type = $(this).val();
    var data_new = $(this).attr('data-new');
    //alert(mapping_type);

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
function addNewRow1(){
    $('select[name="mapping_type[]"]').each(function(){
        data_new =  parseInt($(this).attr('data-new'));
        html = $(this).html();
    });
    data_new = parseInt(data_new) + 1;

    var mapping_type_data = html;//$('#mapping_type:first').html();
    var htmlcontent = '';
    htmlcontent += '<div class="clearfix"></div><div class="addButtonCheckbox1"><div class="row align-items-center">';

    htmlcontent += '<div class="col-md-4"><div class="form-group"><label for="topicType">Mapping Type</label><select class="load_map_value cust-select form-control mb-0" name="mapping_type[]" data-new='+data_new+'>'+mapping_type_data+'</select></div></div>';
    htmlcontent += '<div class="col-md-4"><div class="form-group"><label for="topicType2">Mapping Value</label><select class="cust-select form-control mb-0" name="mapping_value[]" data-new='+data_new+'><option value="">Select Mapping Value</option></select></div></div>';
    htmlcontent += '<div class="col-md-4"><a href="javascript:void(0);" onclick="removeNewRow1();" class="btn btn-danger btn-sm"><i class="mdi mdi-minus"></i></a></div></div></div>';

    $('.addButtonCheckbox1:last').after(htmlcontent);
}
function removeNewRow1() {
    $(".addButtonCheckbox1:last" ).remove();
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

    html += '<div class="clearfix"></div><div class="form-group row addButtonCheckbox mb-3 align-items-center">';
    html += '<label for="colFormLabel" class="col-sm-3 col-form-label my-2">Options</label><div class="col-sm-4 my-2"><input type="text" class="form-control option_class mb-0" id="options[NEW][]" name="options[NEW][]" placeholder="Enter Option"></div>';
    html += '<div class="col-sm-1 my-2"><div class="custom-control custom-radio mb-0"><input type="'+types+'" class="form-control radio_checkbox" name="correct_answer[]" value="'+count+'"></div></div>';
    html += '<div class="col-md-4 my-2"><a href="javascript:void(0);" onclick="removeNewRow();" class="btn btn-danger btn-sm mr-2 mb-0"><i class="mdi mdi-minus"></i></a></div></div>';
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

</script>
@include('includes.footer')
@endsection
