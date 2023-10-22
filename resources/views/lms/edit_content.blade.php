{{--@include('includes.lmsheadcss')
@include('includes.header')
@include('includes.sideNavigation')--}}
@extends('lmslayout')
@section('container')
<link href="../../../plugins/bower_components/bootstrap-tagsinput/dist/bootstrap-tagsinput.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/gh/gitbrent/bootstrap4-toggle@3.6.1/css/bootstrap4-toggle.min.css" rel="stylesheet">
<style>
.toggle.btn.btn-danger {
    width: 200px !important;
}
.toggle.btn.btn-warning {
    width: 200px !important;
}
</style>
<!-- Content main Section -->
<div class="content-main flex-fill">
    <div class="row">
        <div class="col-md-6">
            <h1 class="h4 mb-3">Edit Content</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb bg-transparent p-0">
                    <li class="breadcrumb-item"><a href="{{route('course_master.index')}}">LMS</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('chapter_master.index',['standard_id'=>$data['breadcrum_data']->standard_id ?? 0,'subject_id'=>$data['breadcrum_data']->subject_id ?? 0]) }}">{{$data['breadcrum_data']->subject_name ?? 0}}</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('topic_master.index',['id'=>$data['breadcrum_data']->chapter_id ?? 0]) }}">{{$data['breadcrum_data']->chapter_name ?? 0}}</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('topic_master.index',['id'=>$data['breadcrum_data']->chapter_id ?? 0]) }}">{{$data['breadcrum_data']->topic_name ?? 0}}</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Edit Content</li>
                </ol>
            </nav>
        </div>

    </div>

    <div class="container-fluid mb-5">
        <div class="card border-0">
            <div class="card-body">
            @if ($message = Session::get('data'))
            <div class="alert alert-success alert-block">
                <button type="button" class="close" data-dismiss="alert">×</button>
                <strong>{{ $message }}</strong>
            </div>
            @endif
            <div class="card-body">
                <form action="{{ route('content_master.update',$data['content_data']['id'])}}" method="post" enctype='multipart/form-data'>
                    {{ method_field("PUT") }}
                    @csrf

                    <input type="hidden" name="grade" id="grade" value="{{$data['content_data']['grade_id']}}">
                    <input type="hidden" name="standard" id="standard" value="{{$data['content_data']['standard_id']}}">
                    <input type="hidden" name="subject" id="subject" value="{{$data['content_data']['subject_id']}}">
                    <input type="hidden" name="chapter" id="chapter" value="{{$data['content_data']['chapter_id']}}">
                    <input type="hidden" name="topic" id="topic" value="{{$data['content_data']['topic_id']}}">

                    <div class="mt-2 mb-4 col-md-8">
                        <input disabled type="checkbox" id="toggle_basic_advanced" name="toggle_basic_advanced" data-toggle="toggle" data-on="Basic" data-off="Advanced" data-onstyle="warning" data-offstyle="danger" onchange="show_basic_advanced_div();"
                        @if(isset($data['content_data']['basic_advance']) && ($data['content_data']['basic_advance'] == "" || $data['content_data']['basic_advance'] == 1))
                        checked
                        @endif
                        >
                    </div>

                        <div class="basic_advanced_div">
                            @if(isset($data['content_mapping_type']))
                            @php $j = 1; @endphp
                            @foreach($data['content_mapping_type'] as $mkey => $mval)
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
                                                <select name="mapping_value[]" data-new = "{{$j}}" class="cust-select form-control mb-0 ">
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
                        <div class="addButtonCheckbox">
                            <div class="row align-items-center">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="topicType">Mapping Type</label>
                                        <select class="load_map_value cust-select form-control mb-0" name="mapping_type[]" data-new = "{{$j}}">
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
                                        <select name="mapping_value[]" data-new = "{{$j}}" class="cust-select form-control mb-0">
                                            <option value="">Select Mapping Value</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <a href="javascript:void(0);" onclick="addNewRow();" class="d-inline btn btn-success btn-sm mr-2"><i class="mdi mdi-plus"></i></a>
                                </div>
                            </div>
                        </div>

                        <div class="row ml-2">
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
                            {{ App\Helpers\LMSSearchChain('3','single','pre',$data['content_data']['standard_id'],'std,sub,chapter,topic',$prestandard_id,$presubject_id,$prechapter_id,$pretopic_id) }}
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
                            {{ App\Helpers\LMSSearchChain('3','single','post',$data['content_data']['standard_id'],'std,sub,chapter,topic',$poststandard_id,$postsubject_id,$postchapter_id,$posttopic_id) }}
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
                            {{ App\Helpers\LMSSearchChain('3','single','cross-curriculum',$data['content_data']['standard_id'],'std,sub,chapter,topic',$ccstandard_id,$ccsubject_id,$ccchapter_id,$cctopic_id) }}
                        </div>
                        </div>
                    </div>


                    <div class="row mt-4">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="topicType">File Upload Type</label>
                                <select class="cust-select form-control mb-0" id="contentType" name="contentType" required onchange="restrict_filetype(this.value);">
                                    <option value="">Select Type</option>
                                    <option value="pdf" @if(isset($data['content_data']['file_type'])) @if( $data['content_data']['file_type'] == "pdf" ) selected='selected' @endif @endif>PDF</option>
                                    <option value="mp3" @if(isset($data['content_data']['file_type'])) @if( $data['content_data']['file_type'] == "mp3" ) selected='selected' @endif @endif>MP3</option>
                                    <option value="mp4" @if(isset($data['content_data']['file_type'])) @if( $data['content_data']['file_type'] == "mp4" ) selected='selected' @endif @endif>MP4</option>
                                    <option value="html" @if(isset($data['content_data']['file_type'])) @if( $data['content_data']['file_type'] == "html" ) selected='selected' @endif @endif>HTML</option>
                                    <option value="jpg" @if(isset($data['content_data']['file_type'])) @if( $data['content_data']['file_type'] == "jpg" ) selected='selected' @endif @endif>JPG</option>
                                    <option value="link" @if(isset($data['content_data']['file_type'])) @if( $data['content_data']['file_type'] == "link" ) selected='selected' @endif @endif>Link</option>
                                </select>
                                <input type="hidden" name="hid_file_type" id="hid_file_type" value="{{$data['content_data']['file_type']}}">
                                <!-- <small id="emailHelp" class="form-text text-muted">(PDF,mp4,html,jpg)</small> -->
                            </div>
                        </div>

                        <div id="link_div" class="col-md-4">
                            <div class="form-group">
                                <label for="topicType">Link</label>
                                 <input type="text" class="form-control" id="link" name="link" placeholder="Enter Link" value="{{$data['content_data']['filename']}}">
                            </div>
                        </div>

                        <div id="upload_div" class="col-md-4">
                            <div class="form-group">
                                <label for="title">Upload</label>
                                <input type="file" id='filename' name="filename" class="form-control" onChange='getFileNameWithExt(event)'>

                                @if( isset($data['content_data']['filename']) && $data['content_data']['filename'] != "" && $data['content_data']['file_type'] != "link" )
                                <a target="_blank" href="../../../storage{{$data['content_data']['file_folder']}}/{{$data['content_data']['filename']}}">{{$data['content_data']['filename']}}</a>
                                <input type="hidden" name="hid_filename" id="hid_filename" value="{{$data['content_data']['file_folder']}}/{{$data['content_data']['filename']}}">
                                @endif
                            </div>
                        </div>

                        <div class="col-md-8">
                            <div class="form-group">
                                <label for="description">Title</label>
                                <input type="text" class="form-control" id="title" name="title" placeholder="Title" value="@if(isset($data['content_data']['title'])){{$data['content_data']['title']}}@endif" required>
                            </div>
                        </div>
                        <div class="col-md-8">
                            <div class="form-group">
                                <label for="description">Description</label>
                                <textarea type="text" rows="4" class="form-control" id="description" name="description" placeholder="Description">@if(isset($data['content_data']['description'])){{$data['content_data']['description']}}@endif</textarea>
                            </div>
                        </div>
                    </div>
                    <div class="row align-items-center">
                        <!-- <div class="col-md-4">
                            <div class="form-group">
                                <label for="restrictAccess">Restrict Access</label>
                                <select class="cust-select form-control mb-0 border-0" id="topicType">
                                    <option>Date</option>
                                    <!-- <option>Time</option>
                                    <option>Month</option>
                                    <option>Year</option>
                                </select>
                            </div>
                        </div> -->
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="restrict_date">Restirct Date</label>
                                <div class="input-daterange input-group" id="date-range">
                                    <input type="text" class="form-control mydatepicker" placeholder="dd/mm/yyyy" value="@if(isset($data['content_data']['restrict_date'])){{$data['content_data']['restrict_date']}}@endif" name="restrict_date" autocomplete="off">
                                    <span class="input-group-addon"><i class="icon-calender"></i></span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="subject">Select Content Catergory:</label>
                                <select name="content_category" id="content_category" class="form-control">
                                  <option value="{{$data['content_data']['content_category']}}">{{$data['content_data']['content_category']}}</option>

                                    @if(isset($data['content_category']))
                                        @foreach($data['content_category'] as $key => $value)
                                        <option value="{{$value['category_name']}}" @if(isset($data['content_data']['content_category'])) @if( $data['content_data']['content_category'] == $value['category_name'] ) selected='selected' @endif @endif >{{$value['category_name']}}</option>
                                        @endforeach
                                    @endif

                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="meta_tags">Tags</label>
                                <div class="tags-default">
                                    <input type="text" name="meta_tags" value="@if( isset($data['content_data']['meta_tags']) ) {{$data['content_data']['meta_tags']}} @endif" data-role="tagsinput" placeholder="add tags" />
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="display">Display</label>
                                <label class="switch d-block">
                                    <input type="checkbox" id="show_hide" name="show_hide" value="1"
                                    @if( isset($data['content_data']['show_hide']) && $data['content_data']['show_hide'] == 1)
                                    checked
                                    @endif
                                    >
                                    <span class="slider round"></span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- <div class="row align-items-center">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="restrictAccess">Start Date</label>
                                <select class="cust-select form-control mb-0 border-0" id="topicType2">
                                    <option>DD/MM/YYYY</option>
                                    <option>DD/MM/YYYY</option>
                                    <option>DD/MM/YYYY</option>
                                    <option>DD/MM/YYYY</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="topicType2">End Date</label>
                                <select class="cust-select form-control mb-0 border-0" id="topicType2">
                                    <option>DD/MM/YYYY</option>
                                    <option>DD/MM/YYYY</option>
                                    <option>DD/MM/YYYY</option>
                                    <option>DD/MM/YYYY</option>
                                </select>
                            </div>
                        </div>
                    </div> -->
                    @php
                    if(isset($_REQUEST['preload_lms'])){
                        $readonly = "pointer-events:none";
                    }
                    @endphp
                    <button class="btn btn-primary" type="submit" style="{{$readonly ?? ''}}">Save</button>
                    <!-- <button class="btn btn-outline-primary" type="submit">Reset</button> -->
                </form>
            </div>
        </div>
    </div>
</div>
@include('includes.lmsfooterJs')
<script src="{{asset('/plugins/bower_components/bootstrap-tagsinput/dist/bootstrap-tagsinput.min.js')}}"></script>
<script src="https://cdn.jsdelivr.net/gh/gitbrent/bootstrap4-toggle@3.6.1/js/bootstrap4-toggle.min.js"></script>
<script>

$( document ).ready(function() {
    //START Show & hide Basic Advance block
    //Basic means 1 and advance means 0
    var hid_basic_advance = {{$data['content_data']['basic_advance']}};

    if(hid_basic_advance == 1 || hid_basic_advance == null)
    {
        $(".basic_advanced_div").hide();
    }
    else
    {
        $(".basic_advanced_div").show();
    }
    //END Show & hide Basic Advance block

    var file_type = $('#hid_file_type').val();
    if(file_type == "link")
    {
        $("#upload_div").hide();
        $("#link").attr("required", "true");
    }
    else
    {
        $("#link_div").hide();
        //$("#filename").attr("required", "true");
    }
    $('#datetimepicker').datetimepicker({
        //format: 'DD/MM/YYYY hh:SS A'
    });

});


//START Bind Mapping Value
// $('select[name="mapping_type[]"]').each(function(){
// $(this).change(function () {
//$(".load_map_value").change(function(){
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

//});


//END Bind Mapping Value

function addNewRow(){
    $('select[name="mapping_type[]"]').each(function(){
        data_new =  parseInt($(this).attr('data-new'));
        html = $(this).html();
    });
    data_new = parseInt(data_new) + 1;

    var mapping_type_data = html;//$('#mapping_type:first').html();
    var htmlcontent = '';
    htmlcontent += '<div class="clearfix"></div><div class="addButtonCheckbox" style="display: flex; margin-right: -15px; margin-left: -15px; flex-wrap: wrap;">';

    htmlcontent += '<div class="col-md-4"><div class="form-group"><label for="topicType">Mapping Type</label><select class="load_map_value form-control cust-select" name="mapping_type[]" data-new='+data_new+'>'+mapping_type_data+'</select></div></div>';
    htmlcontent += '<div class="col-md-4"><div class="form-group"><label for="topicType2">Mapping Value</label><select class="form-control cust-select" name="mapping_value[]" data-new='+data_new+'><option>Select Mapping Value</option></select></div></div>';
    htmlcontent += '<div class="col-md-4" style="margin-top: 32px;"><a href="javascript:void(0);" onclick="removeNewRow();" class="d-inline btn btn-danger btn-sm"><i class="mdi mdi-minus"></i></a></div></div>';

    $('.addButtonCheckbox:last').after(htmlcontent);
}
function removeNewRow() {
    $(".addButtonCheckbox:last" ).remove();
}

function removeOldRow(j) {
    $("#old_data_"+j ).remove();
}

function restrict_filetype(filetype)
{
    if(filetype == "link")
    {
        $("#link_div").show();
        $("#upload_div").hide();
        $("#link").attr("required", "true");
        $("#filename").removeAttr('required');
    }
    else
    {
        $("#link_div").hide();
        $("#upload_div").show();
        $("#filename").attr("required", "true");
        $("#link").removeAttr('required');
        $("#filename").attr('accept',filetype);
    }
}

function getFileNameWithExt(event) {

  if (!event || !event.target || !event.target.files || event.target.files.length === 0) {
    return;
  }

  var name = event.target.files[0].name;
  var lastDot = name.lastIndexOf('.');

  var fileName = name.substring(0, lastDot);
  var ext = name.substring(lastDot + 1);

//alert(fileName);
//alert(ext);

  var contentType = $("#contentType").val();
  //alert(contentType);

  if(contentType != ext)
  {
    $("#filename").val("");
    alert("Please Upload file of "+contentType+" extension");
    return false;
  }
  // outputfile.value = fileName;
  // extension.value = ext;
}

</script>
@include('includes.footer')
@endsection
