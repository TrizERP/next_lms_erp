{{--@include('includes.lmsheadcss')
@include('includes.header')
@include('includes.sideNavigation')--}}
@extends('lmslayout')
@section('container')
<link href="../../plugins/bower_components/bootstrap-tagsinput/dist/bootstrap-tagsinput.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/gh/gitbrent/bootstrap4-toggle@3.6.1/css/bootstrap4-toggle.min.css" rel="stylesheet">
<!--style>
    .progress { position:relative; width:100%;height: 12px; }
    .bar { background-color: #008000; width:0%; height:20px; }
    .percent { position:absolute; display:inline-block; left:50%; color: #7F98B2;padding: 6px;}
</style-->
<style>
    #overlay {
        position: fixed; /* Sit on top of the page: none; /* content */
        display Hidden by default */
        width: 100%; /* Full width (cover the whole page) */
        height: 100%; /* Full height (cover the whole page) */
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: rgba(0, 0, 0, 0.5); /* Black background with opacity */
        z-index: 2; /* Specify a stack order in case you're using a different order for other elements */
        cursor: pointer; /* Add a pointer on hover */
    }

    .toggle.btn.btn-danger {
        width: 200px !important;
    }
.toggle.btn.btn-warning {
    width: 200px !important;
}
</style>
<div id="overlay" style="display:none;">
    <center>
        <p style="margin-top: 273px;color:red;font-weight: 700;">
            Please do not refresh the page, while the process is going on.
        </p>
        <img src="../../admin_dep/images/loader.gif">
    </center>
</div>

<!-- Content main Section -->
<div class="content-main flex-fill">
    <div class="row">
        <div class="col-md-6">
            <h1 class="h4 mb-3">Add Content</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb bg-transparent p-0">
                    <li class="breadcrumb-item"><a href="{{route('course_master.index')}}">LMS</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('chapter_master.index',['standard_id'=>$data['breadcrum_data']->standard_id ?? '','subject_id'=>$data['breadcrum_data']->subject_id ?? '']) }}">{{$data['breadcrum_data']->subject_name  ?? ''}}</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('topic_master.index',['id'=>$data['breadcrum_data']->chapter_id ?? '']) }}">{{$data['breadcrum_data']->chapter_name ?? ''}}</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('topic_master.index',['id'=>$data['breadcrum_data']->chapter_id ?? '']) }}">{{$data['breadcrum_data']->topic_name ?? ''}}</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Add Content</li>
                </ol>
            </nav>
        </div>
    </div>

    <div class="container-fluid mb-5">
        <div class="card border-0">
            <div class="card-body">
                <form action="{{route('content_master.store')}}" method="post" enctype='multipart/form-data'>
                    @csrf
                    <input type="hidden" name="hid_chapter_id" id="hid_chapter_id" value="{{$_REQUEST['chapter_id']}}">
                    {{-- <input type="hidden" name="hid_topic_id" id="hid_topic_id" value="{{$_REQUEST['topic_id']}}"> --}}
                    <input type="hidden" name="hid_standard_name" id="hid_standard_name" value="{{$data['breadcrum_data']->standard_name ?? ''}}">
                    <input type="hidden" name="hid_subject_name" id="hid_subject_name" value="{{$data['breadcrum_data']->subject_name ?? ''}}">
                    <input type="hidden" name="hid_chapter_name" id="hid_chapter_name" value="{{$data['breadcrum_data']->chapter_name ?? ''}}">
                    <input type="hidden" name="hid_topic_name" id="hid_topic_name" value="{{$data['breadcrum_data']->topic_name ?? ''}}">

                    <div class="mt-2 mb-4 col-md-8">
                        <button type="button" class="btn btn-info" data-toggle="modal" onclick="javascript:add_data();">
                            <i class="fa fa-plus mr-2"></i>Add You Tube Video Suggestions
                        </button>
                        <input type="checkbox" id="toggle_basic_advanced" name="toggle_basic_advanced" checked
                               data-toggle="toggle" data-on="Basic" data-off="Advanced" data-onstyle="warning"
                               data-offstyle="danger" onchange="show_basic_advanced_div();">
                    </div>

                    <!--Modal: Add ContentSuggestionModal-->
                    <div class="modal fade right modal-scrolling" id="ContentSuggestionModal" tabindex="-1"
                         role="dialog" aria-labelledby="myModalLabel" style="display: none;" aria-hidden="true">
                        <div class="modal-dialog modal-xl modal-side modal-bottom-right modal-notify modal-info"
                             role="document">
                            <!--Content-->
                            <div class="modal-content">
                                <!--Header-->
                                <div class="modal-header">
                                    <h5 class="modal-title" id="heading">Content Suggestion</h5>
                                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                        <span aria-hidden="true">x</span>
                                    </button>
                                </div>

                                <!--Body-->
                                <div class="modal-body">
                                    <div class="white-box">
                                        <div class="panel-body">
                                            <div class="row">
                                                <div class="col-md-3 form-group">
                                                    <label>Content Type</label>
                                                    <select id="third_party_content" name="third_party_content"
                                                            class="form-control">
                                                        <option value="youtude">You tube Content</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-6 form-group">
                                                    <label>Keywords</label>
                                                    <input type="text" id="keyword1" name="keyword1"
                                                           class="form-control"/>
                                                </div>
                                                <div class="col-md-3 form-group mt-4">
                                                    <input type="button" id="search" name="search" value="Search"
                                                           class="btn btn-success" onclick="load_content_data();"/>
                                                </div>

                                                <div class="col-md-12 form-group pt-4 border-top">
                                                    <ul id="YouTubeList" class="row gutter-10">
                                                    </ul>
                                                </div>

                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!--Footer-->
                                <div class="modal-footer flex-center justify-content-center">
                                    <input type="button" id="submit" name="submit" value="Save" class="btn btn-success"
                                           onclick="get_data_link();">
                                </div>
                            </div>
                            <!--/.Content-->
                        </div>
                    </div>
                    <!--Modal: Add ContentSuggestionModal-->


                    <div class="basic_advanced_div">
                        <div class="addButtonCheckbox">
                            <div class="row align-items-center">
                                <div class="col-md-4 my-2">
                                    <div class="form-group mb-0">
                                        <label for="topicType">Mapping Type</label>
                                        <select class="load_map_value cust-select form-control mb-0"
                                                name="mapping_type[]" data-new="1">
                                            <option value="">Select Mapping Type</option>
                                            @if(isset($data['lms_mapping_type']))
                                                @foreach($data['lms_mapping_type'] as $key => $value)
                                                    <option value="{{$value['id']}}">{{$value['name']}}</option>
                                                @endforeach
                                            @endif
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-4 my-2">
                                    <div class="form-group mb-0">
                                        <label for="topicType2">Mapping Value</label>
                                        <select name="mapping_value[]" data-new="1"
                                                class="cust-select form-control mb-0">
                                            <option value="">Select Mapping Value</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-4 mt-0 mb-3" style="padding-top:30px;">
                                    <a href="javascript:void(0);" onclick="addNewRow();"
                                       class="d-inline-block btn btn-success mr-2"><i class="mdi mdi-plus"></i></a>
                                    <!-- <a href="#" class="d-inline btn btn-danger btn-sm"><i class="mdi mdi-minus"></i></a> -->
                                </div>
                            </div>
                        </div>

                        <!-- Slide Count Section -->
                        <div class="addButtonCheckbox">
                            <div class="row align-items-center">
                                <div class="col-md-4 my-2">
                                    <div class="form-group mb-0">
                                        <label for="slideCount">Slide Count</label>
                                        <input type="number" id="slideCount" name="slide_count"
                                               class="form-control mb-0" placeholder="Enter slide count"
                                               min="1" max="50">
                                    </div>
                                </div>
                                <div class="col-md-4 my-2">
                                    <div class="form-group mb-0">
                                        <label for="aiSlideBtn">&nbsp;</label>
                                        <div class="d-flex align-items-center mt-2">
                                            <button type="button" id="aiSlideBtn" class="btn btn-success">
                                                <i class="fa fa-search-plus mr-1"></i>AI
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- IBL Key Topics Input -->
                      

                        <div class="row ml-1 mt-2">
                            <div class="col-md-8 border">
                                <label for="title" class="mt-2 text-primary font-weight-bold">Pre Topic</label>
                                {{ App\Helpers\LMSSearchChain('3','single','pre',$data['standard_id'],'std,sub,chapter,topic',"","","") }}
                            </div>

                            <div class="mt-2 mb-4 col-md-8 border">
                                <label for="title" class="mt-2 text-primary font-weight-bold">Post Topic</label>
                                {{ App\Helpers\LMSSearchChain('3','single','post',$data['standard_id'],'std,sub,chapter,topic',"","","") }}
                            </div>

                            <div class="mt-2 mb-4 col-md-8 border">
                                <label for="title" class="mt-2 text-primary font-weight-bold">Cross Curriculum</label>
                                {{ App\Helpers\LMSSearchChain('3','single','cross-curriculum',$data['standard_id'],'std,sub,chapter,topic',"","","") }}
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="topicType">File Upload Type</label>
                                <select class="cust-select form-control mb-0" id="contentType" name="contentType"
                                        required onchange="restrict_filetype(this.value);">
                                    <option value="">Select Type</option>
                                    <option value="pdf">PDF</option>
                                    <option value="mp3">MP3</option>
                                    <option value="mp4">MP4</option>
                                    <option value="html">HTML</option>
                                    <option value="jpg">JPG</option>
                                    <!-- <option value="pptx">PowerPoint</option>    -->
                                    <option value="link">Link</option>
                                </select>
                                <!-- <small id="emailHelp" class="form-text text-muted">(PDF,mp4,html,jpg)</small> -->
                            </div>
                        </div>
                        <div id="link_div" class="col-md-4">
                            <div class="form-group">
                                <label for="topicType">Link</label>
                                <input type="text" class="form-control" id="link" name="link" placeholder="Enter Link">
                            </div>
                        </div>

                        <div id="upload_div" class="col-md-4">
                            <div class="form-group">
                                <label for="title">Upload</label>
                                <input type="file" id='filename' name="filename" class="form-control"
                                       onChange='getFileNameWithExt(event)'>
                            </div>
                        </div>

                        <div class="col-md-8">
                            <div class="form-group">
                                <label for="description">Title</label>
                                <input type="text" class="form-control" id="title" name="title" placeholder="Title"
                                       required>
                            </div>
                        </div>
                        <div class="col-md-8">
                            <div class="form-group">
                                <label for="description">Description</label>
                                <textarea type="text" rows="4" class="form-control" id="description" name="description" placeholder="Description"></textarea>
                            </div>
                        </div>
                        <div class="col-md-8">
                            <div class="form-group">
                                <label for="prompt" id="test123">Prompt</label>
                                <textarea type="text" rows="4" class="form-control" id="prompt" name="prompt" placeholder="Prompt"></textarea>
                                <button id="refreshPrompt" style="cursor: pointer;">Refresh</button>
                            </div>
                        </div>
                    </div>
                    <div class="row align-items-center">
                        <!-- <div class="col-md-4">
                            <div class="form-group">
                                <label for="restrictAccess">Restrict Access</label>
                                <select class="cust-select form-control mb-0 border-0" id="topicType">
                                    <option>Date</option>
                                    <option>Time</option>
                                    <option>Month</option>
                                    <option>Year</option>
                                </select>
                            </div>
                        </div> -->
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="topicType2">Restirct Date</label>
                                <div class="input-daterange input-group" id="date-range">
                                    <input type="text" class="form-control mydatepicker text-left"
                                           placeholder="dd/mm/yyyy"
                                           value="@if(isset($data->restrict_date)){{date('d-m-Y', strtotime($data->restrict_date))}}@endif"
                                           name="restrict_date" autocomplete="off">
                                    <span class="input-group-addon"><i class="icon-calender"></i></span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="subject">Select Content Catergory:</label>
                                <select name="content_category" id="content_category" class="form-control">
                                        <option value="">--Select--</option>
                                    @if(isset($data['content_category']))
                                        @foreach($data['content_category'] as $key => $value)
                                            <option
                                                value="{{$value['category_name']}}">{{$value['category_name']}}</option>
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
                                    <input type="text" name="meta_tags" value="LMS,ERP" data-role="tagsinput"
                                           placeholder="add tags"/>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="display">Display</label>
                                <label class="switch d-block">
                                    <input type="checkbox" id="show_hide" name="show_hide" value="1" checked>
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
                    <button class="btn btn-primary" type="submit">Save</button>
                    <!-- <div class="progress">
                        <div class="bar"></div >
                        <div class="percent">0%</div >
                    </div> -->
                    <!-- <button class="btn btn-outline-primary" type="submit">Reset</button> -->
                </form>
            </div>
        </div>
    </div>
</div>

@include('includes.lmsfooterJs')
<script src="{{asset('/plugins/bower_components/bootstrap-tagsinput/dist/bootstrap-tagsinput.min.js')}}"></script>
<script src="https://cdn.jsdelivr.net/gh/gitbrent/bootstrap4-toggle@3.6.1/js/bootstrap4-toggle.min.js"></script>

<script type="text/javascript">
    $(function () {
        $("#link_div").hide();
        $(".basic_advanced_div").hide();

        $(document).ready(function () {
            //var bar = $('.bar');
            //var percent = $('.percent');

            // Bind AI button click event - use .one() to ensure single execution
            $('#aiSlideBtn').off('click').on('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                generateGammaPresentation();
            });

            $('form').ajaxForm({
                beforeSend: function () {
                    $("#overlay").css("display", "block");
                    //var percentVal = '0%';
                //bar.width(percentVal)
                //percent.html(percentVal);
            },
            uploadProgress: function(event, position, total, percentComplete) {
                //var percentVal = percentComplete + '%';
                //bar.width(percentVal)
                //percent.html(percentVal);
            },
            complete: function(xhr) {
                //alert('File Uploaded Successfully');
                $("#overlay").css("display","none");
                //window.location.href = "{{ url()->previous() }}";
                window.close();
            }
            });
        });
    });
</script>


<script>

    //START Bind Mapping Value
    // $('select[name="mapping_type[]"]').each(function(){
    // $(this).change(function () {
    //$(".load_map_value").change(function(){

    $(document).on('change', '.load_map_value', function () {
        var mapping_type = $(this).val();
        var data_new = $(this).attr('data-new');
        // alert(mapping_type);
        // alert(data_new);

        var path = "{{ route('ajax_LMS_MappingValue') }}";
        //$('#mapping_value').find('option').remove().end();
        $.ajax({
            url: path,
            data: 'mapping_type=' + mapping_type,
            success: function (result) {
                //var e = $('#mapping_value[data-new='+data_new+']');
                var e = $('select[name="mapping_value[]"][data-new=' + data_new + ']');
                $(e).find('option').remove().end();
                for (var i = 0; i < result.length; i++) {
                    $(e).append($("<option></option>").val(result[i]['id']).html(result[i]['name']));
                    //$("#mapping_value[]").append($("<option></option>").val(result[i]['id']).html(result[i]['name']));
                }
            }
        });
    });

    //});

    function addNewLmsMapping() {
        var path = "{{ route('ajax_AddLMS_MappingFromContent') }}";
        var new_mapping_type = $('#new_mapping_type').val();
        var new_mapping_value = $('#new_mapping_value').val();
        // var topic_id = $('#hid_topic_id').val();

        if (new_mapping_type != "" && new_mapping_value != "") {
            $.ajax({
                url: path,
                // data:'hid_topic_id='+topic_id+'&new_mapping_type='+new_mapping_type+'&new_mapping_value='+new_mapping_value,
                data: 'new_mapping_type=' + new_mapping_type + '&new_mapping_value=' + new_mapping_value,
                success: function (result) {
                    if (result == 1) {
                        alert("LMS Mapping Added Successfully");
                        $('#new_mapping_type').val('');
                        $('#new_mapping_value').val('');
                    }
                }
            });
        } else {
            alert("Please Select Mapping Type and value");
        }
    }

    //END Bind Mapping Value

    function addNewRow() {
        $('select[name="mapping_type[]"]').each(function () {
            data_new = parseInt($(this).attr('data-new'));
            html = $(this).html();
        });
        data_new = parseInt(data_new) + 1;

        var mapping_type_data = html;//$('#mapping_type:first').html();
        var htmlcontent = '';
        htmlcontent += '<div class="clearfix"></div><div class="addButtonCheckbox" style="display: flex; margin-right: -15px; margin-left: -15px; flex-wrap: wrap;">';

        htmlcontent += '<div class="col-md-4 my-2"><div class="form-group mb-0"><label for="topicType">Mapping Type</label><select class="load_map_value form-control cust-select" name="mapping_type[]" data-new=' + data_new + '>' + mapping_type_data + '</select></div></div>';
        htmlcontent += '<div class="col-md-4 my-2"><div class="form-group mb-0"><label for="topicType2">Mapping Value</label><select class="form-control cust-select" name="mapping_value[]" data-new=' + data_new + '><option>Select Mapping Value</option></select></div></div>';
        htmlcontent += '<div class="col-md-4 mt-0 mb-3" style="padding-top:30px;"><a href="javascript:void(0);" onclick="removeNewRow();" class="d-inline btn btn-danger"><i class="mdi mdi-minus"></i></a></div></div>';

        $('.addButtonCheckbox:last').after(htmlcontent);
    }

    function removeNewRow() {
        $(".addButtonCheckbox:last").remove();
    }

    function restrict_filetype(filetype) {
        if (filetype == "link") {
            $("#link_div").show();
            $("#upload_div").hide();
            $("#link").attr("required", "true");
            $("#filename").removeAttr('required');
        } else {
            $("#link_div").hide();
            $("#upload_div").show();
            $("#filename").attr("required", "true");
            $("#link").removeAttr('required');
            $("#filename").attr('accept', filetype);
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

        var contentType = $("#contentType").val();

        if (contentType != ext) {
            $("#filename").val("");
            alert("Please Upload file of " + contentType + " extension");
            return false;
        }
    }

    function add_data() {
        var default_keyword = $("#hid_standard_name").val() + ' / ' + $("#hid_subject_name").val() + ' / ' + $("#hid_chapter_name").val() + ' / ' + $("#hid_topic_name").val();
        $("#keyword1").val(default_keyword);
        $('#ContentSuggestionModal').modal('show');
    }

    function load_content_data() {
        var keywords = $("#keyword1").val();
        var third_party_content = $("#third_party_content").val();
        if (keywords != "") {
            var path = "{{ route('ajax_getYouTubeSuggestion') }}";
            $.ajax({
                url: path,
                data: 'keyword=' + keywords + '&type=' + third_party_content,
                success: function (result) {

                    $('#YouTubeList').html("");
                    var e = $('#YouTubeList');
                    for (var i = 0; i < result.length; i++) {
                        var html = "";
                        var video_link = result[i]['video_link'];
                        var title = result[i]['title'];
                        var image_url = result[i]['image_url'];
                        var description = result[i]['description'];
                        var html = '<li class="col-md-3 mb-3"><input type="radio" value=' + video_link + ' name="youtube_link" hidden><a href="' + video_link + '" target="_blank" class="list-group-item list-group-item-action d-block h-100 p-0"> <div class="custom-control custom-radio mb-2"> <input type="radio" value=' + video_link + ' name="youtube_link" class="custom-control-input" id=' + video_link + '> <label class="custom-control-label p-1" for=' + video_link + '></label> </div> <div class="image-parent text-center"><img src="' + image_url + '" class="img-fluid w-100" alt="quixote"></div> <div class="flex-column p-3"> <h5>' + title + '<p></h5> <small>' + description + '</small> </p>  <span class="badge badge-info badge-pill text-wrap">' + video_link + '</span>  </div>  </a></li>';

                        $(e).append(html);
                    }

                }
            });
        }
    }

    function get_data_link() {
        link_value = $('input[name="youtube_link"]:checked').val();
        $('#contentType').val('link');
        restrict_filetype('link');
        $('#link').val(link_value);
        $('#ContentSuggestionModal').modal('hide');
    }

    function show_basic_advanced_div() {
        if ($("#toggle_basic_advanced").prop("checked") == true) {
            $(".basic_advanced_div").hide();
        } else {
            $(".basic_advanced_div").show();
        }
    }

    // Add new slide count row
    function addNewSlideRow() {
        var slideCountContainer = $('.slide-count-container');
        if (slideCountContainer.length === 0) {
            $('.addButtonCheckbox:last').after('<div class="slide-count-container"></div>');
            slideCountContainer = $('.slide-count-container');
        }
        
        var rowCount = slideCountContainer.find('.slide-count-row').length;
        var htmlcontent = '';
        htmlcontent += '<div class="clearfix"></div><div class="slide-count-row" style="display: flex; margin-right: -15px; margin-left: -15px; flex-wrap: wrap;">';
        htmlcontent += '<div class="col-md-4 my-2"><div class="form-group mb-0"><label for="slideCount">Slide Count</label><input type="number" id="slideCount' + rowCount + '" name="slide_count[]" class="form-control mb-0" placeholder="Enter slide count" min="1" max="50"></div></div>';
        htmlcontent += '<div class="col-md-4 my-2"><div class="form-group mb-0"><label for="aiSlideBtn">&nbsp;</label><div class="d-flex align-items-center mt-2"><button type="button" class="btn btn-primary" onclick="generateAISlidesForRow(' + rowCount + ');">AI</button><a href="javascript:void(0);" onclick="removeSlideRow(this);" class="d-inline btn btn-danger ml-2"><i class="mdi mdi-minus"></i></a></div></div></div>';
        htmlcontent += '</div>';
        
        slideCountContainer.append(htmlcontent);
    }

    // Remove slide count row
    function removeSlideRow(element) {
        $(element).closest('.slide-count-row').remove();
    }

    // Generate AI slides for a specific row
    function generateAISlidesForRow(rowId) {
        var slideCount = $('#slideCount' + rowId).val();
        if (!slideCount || slideCount < 1) {
            alert('Please enter a valid slide count');
            return;
        }
        generateAISlides(slideCount);
    }

    // Main function to generate AI slides
    function generateAISlides(slideCount) {
        if (!slideCount) {
            slideCount = $('#slideCount').val();
        }
        
        if (!slideCount || slideCount < 1) {
            alert('Please enter a valid slide count');
            return;
        }

        var standardId = "{{ $data['breadcrum_data']->standard_id ?? '' }}";
        var contentCategory = $('#content_category').val();
        
        // Also get the mapping value ID for fallback
        var mappingValue = '';
        var mappingValueSelect = $('select[name="mapping_value[]"]');
        if (mappingValueSelect.length > 0) {
            mappingValue = mappingValueSelect.find('option:selected').val();
        }
        
        // If content category dropdown is empty, try to get from selected mapping value
        if (!contentCategory) {
            if (mappingValueSelect.length > 0) {
                contentCategory = mappingValueSelect.find('option:selected').text();
            }
        }
        
        if (!contentCategory) {
            contentCategory = 'General';
        }
        
        // Show loading overlay
        $("#overlay").css("display", "block");
        
        $.ajax({
            url: "{{ route('ai.generateSlides') }}",
            type: 'POST',
            data: {
                standard_id: standardId,
                subject_name: subjectName,
                chapter_name: chapterName,
                topic_name: topicName,
                content_category: contentCategory,
                mapping_value: mappingValue,
                slide_count: slideCount,
                _token: '{{ csrf_token() }}'
            },
            success: function(response) {
                $("#overlay").css("display", "none");
                
                if (response.success) {
                    // Auto-fill the uploaded PDF name into the Upload Name field
                    if (response.file_name) {
                        // Create a hidden input or trigger file upload
                        $('#filename').attr('data-auto-generated', 'true');
                        $('#filename').attr('data-generated-name', response.file_name);
                        
                        // Store the generated filename in a hidden field for form submission
                        var hiddenInput = $('<input>', {
                            type: 'hidden',
                            name: 'ai_generated_filename',
                            value: response.file_name
                        });
                        $('form').append(hiddenInput);
                        
                        alert('Slide content generated successfully! File: ' + response.file_name);
                    }
                } else {
                    alert('Error: ' + (response.message || 'Failed to generate slides'));
                }
            },
            error: function(xhr) {
                $("#overlay").css("display", "none");
                console.error(xhr);
                alert('An error occurred while generating slides.');
            }
        });
    }

    // NEW: Generate AI slides with KAAB competencies
    function generateKAABSlides() {
        var slideCount = $('#slideCount').val();
        
        if (!slideCount || slideCount < 1) {
            alert('Please enter a valid slide count');
            return;
        }

        // Get basic context
        var standardId = "{{ $data['breadcrum_data']->standard_id ?? '' }}";
        var subjectName = "{{ $data['breadcrum_data']->subject_name ?? '' }}";
        var chapterName = "{{ $data['breadcrum_data']->chapter_name ?? '' }}";
        var topicName = "{{ $data['breadcrum_data']->topic_name ?? '' }}";
        var contentCategory = $('#content_category').val() || 'General';
        
        // Get mapping type and value
        var mappingTypeId = $('select[name="mapping_type[]"]').first().val();
        var mappingValueId = $('select[name="mapping_value[]"]').first().val();
        var mappingTypeText = $('select[name="mapping_type[]"]').first().find('option:selected').text();
        var mappingValueText = $('select[name="mapping_value[]"]').first().find('option:selected').text();
        
        // Get KAAB competencies (from hidden fields or data attributes if available)
        var knowledgeItems = [];
        var abilityItems = [];
        var attitudeItems = [];
        var behaviourItems = [];
        
        // Try to get KAAB items from form fields (if they exist)
        $('input[name^="knowledge"]').each(function() {
            if ($(this).val()) {
                knowledgeItems.push({title: $(this).val()});
            }
        });
        $('input[name^="ability"]').each(function() {
            if ($(this).val()) {
                abilityItems.push({title: $(this).val()});
            }
        });
        $('input[name^="attitude"]').each(function() {
            if ($(this).val()) {
                attitudeItems.push({title: $(this).val()});
            }
        });
        $('input[name^="behaviour"]').each(function() {
            if ($(this).val()) {
                behaviourItems.push({title: $(this).val()});
            }
        });
        
        // Additional context from form
        var instructionTaskText = $('#instruction_task_text').val() || '';
        var criticalWorkFunction = $('#critical_work_function').val() || '';
        var jobRole = $('#job_role').val() || '';
        var keyTask = $('#key_task').val() || '';
        var industry = $('#industry').val() || '';
        var department = $('#department').val() || '';
        var modalityString = $('#modality_string').val() || 'Online';
        var mappingReason = $('#mapping_reason').val() || '';
        
        // Show loading overlay
        $("#overlay").css("display", "block");
        
        $.ajax({
            url: "{{ route('ai.generateKAABSlides') }}",
            type: 'POST',
            data: {
                standard_id: standardId,
                subject_name: subjectName,
                chapter_name: chapterName,
                topic_name: topicName,
                content_category: contentCategory,
                slide_count: slideCount,
                mapping_type: mappingTypeText,
                mapping_value: mappingValueText,
                mapping_reason: mappingReason,
                knowledge: knowledgeItems,
                ability: abilityItems,
                attitude: attitudeItems,
                behaviour: behaviourItems,
                instruction_task_text: instructionTaskText,
                critical_work_function: criticalWorkFunction,
                job_role: jobRole,
                key_task: keyTask,
                industry: industry,
                department: department,
                modality_string: modalityString,
                _token: '{{ csrf_token() }}'
            },
            success: function(response) {
                $("#overlay").css("display", "none");
                
                if (response.success) {
                    // Log the response to console (NOT to UI)
                    console.log('=== KAAB Slide Generation Response ===');
                    console.log(JSON.stringify(response, null, 2));
                    
                    if (response.slides_data) {
                        console.log('=== Generated Slides ===');
                        console.log(JSON.stringify(response.slides_data, null, 2));
                        
                        if (response.slides_data.kaab_summary) {
                            console.log('=== KAAB Summary ===');
                            console.log('Knowledge items used: ' + response.slides_data.kaab_summary.knowledge_used);
                            console.log('Ability items used: ' + response.slides_data.kaab_summary.ability_used);
                            console.log('Attitude items used: ' + response.slides_data.kaab_summary.attitude_used);
                            console.log('Behaviour items used: ' + response.slides_data.kaab_summary.behaviour_used);
                            console.log('Total KAAB items: ' + response.slides_data.kaab_summary.total_items);
                        }
                        
                        if (response.slides_data.slides) {
                            console.log('Total slides generated: ' + response.slides_data.slides.length);
                        }
                    }
                    
                    // Auto-fill the uploaded filename into the Upload Name field
                    if (response.file_name) {
                        $('#filename').attr('data-auto-generated', 'true');
                        $('#filename').attr('data-generated-name', response.file_name);
                        
                        var hiddenInput = $('<input>', {
                            type: 'hidden',
                            name: 'ai_generated_filename',
                            value: response.file_name
                        });
                        $('form').append(hiddenInput);
                        
                        alert('Slide content generated successfully! File: ' + response.file_name + '\nCheck console for detailed output.');
                    } else {
                        alert('Slide content generated successfully!\nCheck console for detailed JSON output.');
                    }
                } else {
                    alert('Error: ' + (response.message || 'Failed to generate slides'));
                }
            },
            error: function(xhr) {
                $("#overlay").css("display", "none");
                console.error('Error response:', xhr.responseText);
                alert('An error occurred while generating slides. Check console for details.');
            }
        });
    }

    // Generate PDF Presentation using Gamma API
    // Generate PDF Presentation using Gamma API
var isGammaGenerating = false; // Flag to prevent multiple generations

function generateGammaPresentation() {
    // Prevent multiple clicks
    if (isGammaGenerating) {
        alert('A presentation is already being generated. Please wait...');
        return;
    }
    
    var slideCount = $('#slideCount').val();
    
    if (!slideCount || slideCount < 1) {
        alert('Please enter a valid slide count');
        return;
    }

    // Set generating flag and disable button
    isGammaGenerating = true;
    $('#aiSlideBtn').prop('disabled', true).html('<i class="fa fa-spinner fa-spin mr-1"></i>Generating...');

    // Get basic context
    var standardId = "{{ $data['breadcrum_data']->standard_id ?? '' }}";
    var subjectName = "{{ $data['breadcrum_data']->subject_name ?? '' }}";
    var chapterName = "{{ $data['breadcrum_data']->chapter_name ?? '' }}";
    var topicName = "{{ $data['breadcrum_data']->topic_name ?? '' }}";
    var contentCategory = $('#content_category').val() || 'General';
    var keyTopics = $('#keyTopics').val() || '';
    
    // Get mapping type and value
    var mappingValueSelect = $('select[name="mapping_value[]"]');
    var mappingValue = '';
    if (mappingValueSelect.length > 0) {
        mappingValue = mappingValueSelect.find('option:selected').val();
    }
    
    // Show loading overlay
    $("#overlay").css("display", "block");
    
    $.ajax({
        url: "{{ route('ai.generateGammaPresentation') }}",
        type: 'POST',
        data: {
            standard_id: standardId,
            subject_name: subjectName,
            chapter_name: chapterName,
            topic_name: topicName,
            content_category: contentCategory,
            slide_count: parseInt(slideCount, 10),
            mapping_value: mappingValue,
            key_topics: keyTopics,
            _token: '{{ csrf_token() }}'
        },
        success: function(response) {
            console.log('=== Gamma Presentation Initial Response ===');
            console.log(JSON.stringify(response, null, 2));
            
            if (response.success) {
                console.log('Response is successful');
                
                if (response.data && response.data.generationId) {
                    console.log('Generation ID found:', response.data.generationId);
                    // Store generation ID for polling
                    localStorage.setItem('gamma_generation_id', response.data.generationId);
                    
                    // Update overlay message and keep it visible
                    $('#overlay p').text('Presentation generation started! Please wait while we fetch your presentation...');
                    
                    // Start polling for status
                    pollGammaGenerationStatus(response.data.generationId); 
                } else if (response.generation_id) {
                    console.log('Generation ID found (alternate location):', response.generation_id);
                    localStorage.setItem('gamma_generation_id', response.generation_id);
                    $('#overlay p').text('Presentation generation started! Please wait while we fetch your presentation...');
                    pollGammaGenerationStatus(response.generation_id);
                }
                
                if (response.url) {
                    console.log('Direct URL found in response:', response.url);
                    // If URL is returned directly, auto-fill the form
                    autoFillGammaForm(response.url, topicName, chapterName, subjectName);
                    // Reset flag and button
                    resetGammaButton();
                }
            } else {
                console.log('Response failed:', response.message);
                $("#overlay").css("display", "none");
                resetGammaButton();
                alert('Error: ' + (response.message || 'Failed to generate presentation'));
            }
        },
        error: function(xhr) {
            $("#overlay").css("display", "none");
            resetGammaButton();
            console.error('Error response:', xhr.responseText);
            alert('An error occurred while generating the presentation. Check console for details.');
        }
    });
}

// Reset Gamma button state
function resetGammaButton() {
    isGammaGenerating = false;
    $('#aiSlideBtn').prop('disabled', false).html('<i class="fa fa-search-plus mr-1"></i>AI');
}

// Poll for Gamma generation status
function pollGammaGenerationStatus(generationId) {
    var pollCount = 0;
    var maxPolls = 70; // Maximum 70 polls (2 minutes at 1 second interval)
    
    console.log('========================================');
    console.log('=== Starting Gamma Status Polling ===');
    console.log('Generation ID:', generationId);
    console.log('========================================');
    
    var pollInterval = setInterval(function() {
        pollCount++;
        
        // Update overlay message with progress
        $('#overlay p').text('Generating presentation... Please wait. Attempt ' + pollCount + '/' + maxPolls);
        
        $.ajax({
            url: "{{ route('ai.getGammaPresentationStatus') }}",
            type: 'POST',
            data: {
                generation_id: generationId,
                _token: '{{ csrf_token() }}'
            },
            success: function(response) {
                console.log('--- Poll #' + pollCount + ' Response ---');
                console.log('Full response:', JSON.stringify(response, null, 2));
                
                if (response.success) {
                    var status = response.status || (response.data && response.data.status) || 'unknown';
                    var rawData = response.raw_response || response.data || {};
                    
                    console.log('Current status:', status);
                    
                    // Check for completed/succeeded status
                    if (status === 'completed' || status === 'succeeded' || status === 'done') {
                        clearInterval(pollInterval);
                        $("#overlay").css("display", "none");
                        resetGammaButton();
                        
                        console.log('========================================');
                        console.log('=== PRESENTATION GENERATION COMPLETE ===');
                        console.log('========================================');
                        
                        // Try multiple possible URL locations
                        var pdfUrl = null;
                        
                        // Check direct response fields
                        if (response.pdf_url) {
                            pdfUrl = response.pdf_url;
                            console.log('Found URL in response.pdf_url:', pdfUrl);
                        } else if (response.url) {
                            pdfUrl = response.url;
                            console.log('Found URL in response.url:', pdfUrl);
                        }
                        
                        // Check nested data fields
                        if (!pdfUrl && response.data) {
                            if (response.data.pdf_url) {
                                pdfUrl = response.data.pdf_url;
                                console.log('Found URL in response.data.pdf_url:', pdfUrl);
                            } else if (response.data.url) {
                                pdfUrl = response.data.url;
                                console.log('Found URL in response.data.url:', pdfUrl);
                            } else if (response.data.exportUrl) {
                                pdfUrl = response.data.exportUrl;
                                console.log('Found URL in response.data.exportUrl:', pdfUrl);
                            } else if (response.data.shareUrl) {
                                pdfUrl = response.data.shareUrl;
                                console.log('Found URL in response.data.shareUrl:', pdfUrl);
                            } else if (response.data.presentation_url) {
                                pdfUrl = response.data.presentation_url;
                                console.log('Found URL in response.data.presentation_url:', pdfUrl);
                            }
                            
                            // Check exports array
                            if (!pdfUrl && response.data.exports && Array.isArray(response.data.exports)) {
                                console.log('Checking exports array:', response.data.exports);
                                for (var i = 0; i < response.data.exports.length; i++) {
                                    var exportItem = response.data.exports[i];
                                    if (exportItem.url) {
                                        if (exportItem.type === 'pdf' || !pdfUrl) {
                                            pdfUrl = exportItem.url;
                                            console.log('Found URL in exports[' + i + ']:', pdfUrl);
                                        }
                                    }
                                }
                            }
                        }
                        
                        // Check raw_response fields
                        if (!pdfUrl && rawData) {
                            console.log('Checking raw data:', rawData);
                            if (rawData.pdf_url) {
                                pdfUrl = rawData.pdf_url;
                                console.log('Found URL in rawData.pdf_url:', pdfUrl);
                            } else if (rawData.url) {
                                pdfUrl = rawData.url;
                                console.log('Found URL in rawData.url:', pdfUrl);
                            } else if (rawData.exportUrl) {
                                pdfUrl = rawData.exportUrl;
                                console.log('Found URL in rawData.exportUrl:', pdfUrl);
                            } else if (rawData.shareUrl) {
                                pdfUrl = rawData.shareUrl;
                                console.log('Found URL in rawData.shareUrl:', pdfUrl);
                            } else if (rawData.presentation_url) {
                                pdfUrl = rawData.presentation_url;
                                console.log('Found URL in rawData.presentation_url:', pdfUrl);
                            }
                            
                            // Check presentation object in raw data
                            if (!pdfUrl && rawData.presentation) {
                                console.log('Checking presentation object:', rawData.presentation);
                                if (rawData.presentation.url) {
                                    pdfUrl = rawData.presentation.url;
                                    console.log('Found URL in rawData.presentation.url:', pdfUrl);
                                } else if (rawData.presentation.shareUrl) {
                                    pdfUrl = rawData.presentation.shareUrl;
                                    console.log('Found URL in rawData.presentation.shareUrl:', pdfUrl);
                                }
                            }
                            
                            // Check exports in raw data
                            if (!pdfUrl && rawData.exports && Array.isArray(rawData.exports)) {
                                console.log('Checking rawData.exports array:', rawData.exports);
                                for (var j = 0; j < rawData.exports.length; j++) {
                                    var rawExport = rawData.exports[j];
                                    if (rawExport.url) {
                                        if (rawExport.type === 'pdf' || !pdfUrl) {
                                            pdfUrl = rawExport.url;
                                            console.log('Found URL in rawData.exports[' + j + ']:', pdfUrl);
                                        }
                                    }
                                }
                            }
                        }
                        
                        // Construct URL from presentation ID if available
                        if (!pdfUrl && rawData) {
                            var presentationId = rawData.presentationId || rawData.id || (rawData.presentation && rawData.presentation.id) || (response.data && (response.data.presentationId || response.data.id));
                            if (presentationId && (status === 'completed' || status === 'succeeded')) {
                                pdfUrl = "https://gamma.app/docs/" + presentationId;
                                console.log('Constructed URL from presentation ID:', pdfUrl);
                            }
                        }
                        
                        console.log('========================================');
                        console.log('=== FINAL EXTRACTED PDF URL ===');
                        console.log('PDF URL:', pdfUrl);
                        console.log('========================================');
                        
                        if (pdfUrl) {
                            var topicName = $('#hid_topic_name').val() || '';
                            var chapterName = $('#hid_chapter_name').val() || '';
                            var subjectName = $('#hid_subject_name').val() || '';
                            
                            console.log('=== Form Field Values ===');
                            console.log('Topic Name (hid_topic_name):', topicName);
                            console.log('Chapter Name (hid_chapter_name):', chapterName);
                            console.log('Subject Name (hid_subject_name):', subjectName);
                            
                            // Use setTimeout to ensure DOM is ready
                            setTimeout(function() {
                                autoFillGammaForm(pdfUrl, topicName, chapterName, subjectName);
                            }, 100);
                        } else {
                            console.log('========================================');
                            console.log('ERROR: No URL found in response!');
                            console.log('Full response for debugging:', JSON.stringify(response, null, 2));
                            console.log('========================================');
                            alert('Presentation generated but URL not found. Check console for full response.');
                        }
                    } else if (status === 'failed' || status === 'error') {
                        clearInterval(pollInterval);
                        $("#overlay").css("display", "none");
                        resetGammaButton();
                        console.log('ERROR: Presentation generation failed with status:', status);
                        alert('Presentation generation failed. Status: ' + status);
                    } else {
                        console.log('Status: ' + status + ' - continuing to poll...');
                    }
                }
            },
            error: function(xhr) {
                console.error('Polling error:', xhr);
                // Don't clear interval on error, continue polling
            }
        });
        
        // Stop polling after max attempts
        if (pollCount >= maxPolls) {
            clearInterval(pollInterval);
            $("#overlay").css("display", "none");
            resetGammaButton();
            console.log('Polling timeout reached after ' + maxPolls + ' attempts');
            alert('Presentation generation is taking longer than expected. Generation ID: ' + generationId + '\nPlease check back later or try again.');
        }
    }, 1000); // Poll every 1 second
}

// Auto-fill form with Gamma presentation data
function autoFillGammaForm(url, topicName, chapterName, subjectName) {
    console.log('========================================');
    console.log('=== autoFillGammaForm CALLED ===');
    console.log('========================================');
    console.log('URL received:', url);
    console.log('Topic Name:', topicName);
    console.log('Chapter Name:', chapterName);
    console.log('Subject Name:', subjectName);
    
    // Create a meaningful title
    var titleName = topicName;
    if (chapterName) {
        titleName = topicName + ' - ' + chapterName;
    }
    if (subjectName) {
        titleName += ' (' + subjectName + ')';
    }
    
    // If still empty, use a default name
    if (!titleName || titleName.trim() === '') {
        titleName = 'Generated Presentation';
    }
    
    console.log('Title Name generated:', titleName);
    
    // Set content type to LINK (important!)
    $('#contentType').val('link');
    console.log('ContentType field value set to: link');
    
    // Auto-fill Content Category to "Classroom Presentation"
    $('#content_category option').each(function() {
        if ($(this).text().toLowerCase().includes('classroom presentation') || 
            $(this).text().toLowerCase().includes('classroom')) {
            $(this).prop('selected', true);
            console.log('Content Category set to:', $(this).text());
        }
    });
    
    // Trigger change event to update UI (show link field)
    restrict_filetype('link');
    console.log('restrict_filetype called with: link');
    
    // Small delay to ensure UI is updated before setting link
    setTimeout(function() {
        // Auto-fill the generated URL in the Link field
        $('#link').val(url);
        console.log('Link field value set to:', $('#link').val());
        
        // Auto-fill the title field
        $('#title').val(titleName);
        console.log('Title field value set to:', $('#title').val());
        
        // ============================================
        // ADD HIDDEN INPUTS FOR FORM SUBMISSION
        // ============================================
        // Remove any existing hidden inputs
        $('input[name="gamma_presentation_url"]').remove();
        $('input[name="gamma_presentation_name"]').remove();
        $('input[name="ibl_generated_url"]').remove();
        $('input[name="ibl_content_type"]').remove();
        
        // IMPORTANT: Store the ACTUAL URL in gamma_presentation_url
        $('form').append($('<input>', { 
            type: 'hidden', 
            name: 'gamma_presentation_url', 
            value: url  // This is the Gamma PDF URL
        }));
        
        // Store the title separately (this is for reference only)
        $('form').append($('<input>', { 
            type: 'hidden', 
            name: 'gamma_presentation_name', 
            value: titleName 
        }));
        
        // Also set ibl fields for backward compatibility
        $('form').append($('<input>', { 
            type: 'hidden', 
            name: 'ibl_generated_url', 
            value: url 
        }));
        $('form').append($('<input>', { 
            type: 'hidden', 
            name: 'ibl_content_type', 
            value: 'link' 
        }));
        
        console.log('Hidden inputs added to form');
        console.log('Gamma URL stored in hidden field:', url);
        console.log('=== autoFillGammaForm COMPLETED ===');
        console.log('========================================');
        
        // Log the final values for verification
        console.log('=== FINAL FORM VALUES ===');
        console.log('Title:', $('#title').val());
        console.log('File Upload Type:', $('#contentType').val());
        console.log('Content Category:', $('#content_category').val());
        console.log('Link URL:', $('#link').val());
        console.log('Gamma URL hidden field:', $('input[name="gamma_presentation_url"]').val());
        console.log('========================================');
        
        // Show success notification
        showSuccessNotification('link', url);
    }, 200);
}

// Show success notification without blocking UI
function showSuccessNotification(contentType, url) {
    // Remove any existing notification
    $('#success-notification').remove();
    
    // Create notification element
    var notification = $('<div>', {
        id: 'success-notification',
        css: {
            position: 'fixed',
            top: '20px',
            right: '20px',
            backgroundColor: '#28a745',
            color: 'white',
            padding: '15px 20px',
            borderRadius: '5px',
            boxShadow: '0 4px 6px rgba(0,0,0,0.1)',
            zIndex: 9999,
            maxWidth: '400px'
        },
        html: '<strong>✓ Presentation Created!</strong><br>' +
              '<small>File Type: ' + contentType.toUpperCase() + '</small><br>' +
              '<small>URL: <a href="' + url + '" target="_blank" style="color: #fff; text-decoration: underline;">Open ' + contentType.toUpperCase() + '</a></small><br>' +
              '<small style="font-size: 11px;">Form fields have been auto-filled.</small>'
    });
    
    // Add close button
    var closeBtn = $('<span>', {
        css: {
            marginLeft: '15px',
            cursor: 'pointer',
            fontWeight: 'bold'
        },
        text: '×',
        click: function() {
            $('#success-notification').fadeOut(300, function() { $(this).remove(); });
        }
    });
    notification.prepend(closeBtn);
    
    // Add to page
    $('body').append(notification);
    
    // Auto-hide after 5 seconds
    setTimeout(function() {
        $('#success-notification').fadeOut(500, function() { $(this).remove(); });
    }, 5000);
}

</script>
<script type="text/javascript">
    $(document).ready(function () {
        // Trigger AI processing when content type or category changes
        $('#content_category').change(function() {
            processAIData();
        });
        $('#contentType').change(function() {
            processAIData();
        });
        $('#refreshPrompt').on('click', function() {
        processAIDataNew();
    });
    });

    function processAIData() {
        var standardId = "{{ $data['breadcrum_data']->standard_id ?? '' }}";
        var subjectName = "{{ $data['breadcrum_data']->subject_name ?? '' }}";
        var chapterName = "{{ $data['breadcrum_data']->chapter_name ?? '' }}";
        var topicName = "{{ $data['breadcrum_data']->topic_name ?? '' }}";
        var contentType = $('#contentType').val();
        var contentCategory = $('#content_category').val();
        let fileNames=[];
        $('.book-file-names').each(function(){
            fileNames.push($(this).val());
        });

        console.log('Content Type:', contentType);
        console.log('Content Category:', contentCategory);
        if (contentType && contentCategory) {
            console.log('Both content type and category are selected.');

            if (contentType === 'pdf' || contentType === 'jpg') {
                console.log('Processing AI data...');
                $.ajax({
                    url: "{{ route('ai.processData') }}",
                    type: 'POST',
                    data: {
                        standard_id: standardId,
                        subject_name: subjectName,
                        chapter_name: chapterName,
                        topic_name: topicName,
                        content_type: contentType,
                        content_category: contentCategory,
                        _token: '{{ csrf_token() }}'
                    },
                    success: function(response) {
                        // $('#title').val(response.title);
                        // $('#description').val(response.description);
                    },
                    error: function(xhr) {
                        console.error(xhr);
                        alert('An error occurred while processing your request.');
                    }
                });

                console.log('Generating Data...');
                $.ajax({
                    url: "{{ route('ai.generateLessonPlan') }}",
                    type: 'POST',
                    data: {
                        standard_id: standardId,
                        subject_name: subjectName,
                        chapter_name: chapterName,
                        topic_name: topicName,
                        content_category: contentCategory,
                        content_type: contentType,
                        booklist_data: fileNames,
                        _token: '{{ csrf_token() }}'
                    },
                    success: function(response) {
                        console.log(response.prompt);
                        // $("#test123").val("Prompt");
                        // $('#prompt').val(response.prompt);
                        // if (response.file_url) {
                        //      $('#upload_div1').empty();
                        //     var downloadLink = $('<a>', {
                        //         href: response.file_url,
                        //         text: 'Download ' + contentType.toUpperCase(),
                        //         target: '_blank',
                        //         download: ''
                        //     });
                        //     $('#upload_div1').append(downloadLink);
                        //     console.log(contentType.toUpperCase() + ' URL:', response.file_url);
                        //     downloadLink.css({
                        //         display: 'block',
                        //         margin: '10px 0',
                        //         color: 'blue',
                        //         textDecoration: 'underline'
                        //     });
                        // } else {
                        //     console.error(contentType.toUpperCase() + ' URL not found in response.');
                        //     alert('An error occurred while generating the ' + contentType.toUpperCase() + '.');
                        // }
                    },
                    error: function(xhr) {
                        console.error(xhr);
                        alert('An error occurred while generating the ' + contentType.toUpperCase() + '.');
                    }
                });
            }
        } else {
            console.log('Content type or category is not selected.');
        }
    }
    function processAIDataNew(){
        var standardId = "{{ $data['breadcrum_data']->standard_id ?? '' }}";
        var subjectName = "{{ $data['breadcrum_data']->subject_name ?? '' }}";
        var chapterName = "{{ $data['breadcrum_data']->chapter_name ?? '' }}";
        var topicName = "{{ $data['breadcrum_data']->topic_name ?? '' }}";
        var contentType = $('#contentType').val();
        var contentCategory = $('#content_category').val();
        let fileNames=[];
        $('.book-file-names').each(function(){
            fileNames.push($(this).val());
        });
        var promptNew = $('#prompt').val();
        console.log('Generating Data...');
                $.ajax({
                    url: "{{ route('ai.generateLessonPlanNew') }}",
                    type: 'POST',
                    data: {
                        standard_id: standardId,
                        subject_name: subjectName,
                        chapter_name: chapterName,
                        topic_name: topicName,
                        content_category: contentCategory,
                        content_type: contentType,
                        booklist_data: fileNames,
                        prompt: promptNew,
                        _token: '{{ csrf_token() }}'
                    },
                    success: function(response) {
                        console.log(response.prompt);
                        $("#test123").val("Prompt");
                        $('#prompt').val(response.prompt);
                        $('#upload_div1').empty();
                        if (response.file_url) {
                            var downloadLink = $('<a>', {
                                href: response.file_url,
                                text: 'Download ' + contentType.toUpperCase(),
                                target: '_blank',
                                download: ''
                            });
                            $('#upload_div1').append(downloadLink);
                            console.log(contentType.toUpperCase() + ' URL:', response.file_url);
                            downloadLink.css({
                                display: 'block',
                                margin: '10px 0',
                                color: 'blue',
                                textDecoration: 'underline'
                            });
                        } else {
                            console.error(contentType.toUpperCase() + ' URL not found in response.');
                            alert('An error occurred while generating the ' + contentType.toUpperCase() + '.');
                        }
                    },
                    error: function(xhr) {
                        console.error(xhr);
                        alert('An error occurred while generating the ' + contentType.toUpperCase() + '.');
                    }
                });
    }  
</script>
@include('includes.footer')
@endsection
