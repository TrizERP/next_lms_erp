{{--@include('includes.lmsheadcss')
@include('includes.header')
@include('includes.sideNavigation')--}}
@extends('lmslayout')
@section('container')
<link href="../../plugins/bower_components/bootstrap-tagsinput/dist/bootstrap-tagsinput.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/gh/gitbrent/bootstrap4-toggle@3.6.1/css/bootstrap4-toggle.min.css" rel="stylesheet">
<style>
    #overlay {
        position: fixed;
        width: 100%;
        height: 100%;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: rgba(0, 0, 0, 0.5);
        z-index: 2;
        cursor: pointer;
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
                        <button type="button" class="btn btn-success ml-2" id="openAiPromptBuilderBtn">
                            <i class="mdi mdi-robot mr-1"></i>AI
                        </button>
                    </div>

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
                                </div>
                            </div>
                        </div>

                        <div class="addButtonCheckbox">
                            <div class="row align-items-center">
                                {{-- Slide count section empty --}}
                            </div>
                        </div>

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
                                    <option value="link">Link</option>
                                </select>
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
                                <button type="button" id="refreshPrompt" style="cursor: pointer;">Refresh</button>
                            </div>
                        </div>
                    </div>
                    <div class="row align-items-center">
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
                    <button class="btn btn-primary" type="submit">Save</button>
                </form>
            </div>
        </div>
    </div>
</div>

@include('includes.lmsfooterJs')
<script src="{{asset('/plugins/bower_components/bootstrap-tagsinput/dist/bootstrap-tagsinput.min.js')}}"></script>
<script src="https://cdn.jsdelivr.net/gh/gitbrent/bootstrap4-toggle@3.6.1/js/bootstrap4-toggle.min.js"></script>

<!-- Include Modals and Scripts -->
@include('lms.addContentModal')

<script type="text/javascript">
    // Track selected mapping types to prevent duplicates
    var selectedMappingTypes = [];
    
    // Function to update selected mapping types list
    function updateSelectedMappingTypes() {
        selectedMappingTypes = [];
        $('select[name="mapping_type[]"]').each(function() {
            var val = $(this).val();
            if (val && val !== '') {
                selectedMappingTypes.push(val);
            }
        });
    }
    
    // Function to validate no duplicate mapping types in advanced mode
    function validateNoDuplicateMappingTypes() {
        if ($("#toggle_basic_advanced").prop("checked") == false) {
            var mappingTypes = [];
            var hasDuplicate = false;
            var duplicateValue = '';
            
            $('select[name="mapping_type[]"]').each(function() {
                var val = $(this).val();
                if (val && val !== '') {
                    if (mappingTypes.includes(val)) {
                        hasDuplicate = true;
                        duplicateValue = $(this).find('option:selected').text();
                        return false;
                    }
                    mappingTypes.push(val);
                }
            });
            
            if (hasDuplicate) {
                alert('Each Mapping Type can only be selected once. "' + duplicateValue + '" is already selected.');
                return false;
            }
        }
        return true;
    }
    
    // Modify show_basic_advanced_div function
    function show_basic_advanced_div() {
        if ($("#toggle_basic_advanced").prop("checked") == true) {
            $(".basic_advanced_div").hide();
            // Clear selected mapping types when hiding
            selectedMappingTypes = [];
        } else {
            $(".basic_advanced_div").show();
            updateSelectedMappingTypes();
        }
    }
    
    // Modify addNewRow function to enforce single value per type
    function addNewRow() {
        // First validate that we don't have duplicate mapping types
        if (!validateNoDuplicateMappingTypes()) {
            return;
        }
        
        // Get the HTML structure from existing rows
        var mappingTypeOptions = '';
        var data_new = 1;
        
        $('select[name="mapping_type[]"]').each(function() {
            data_new = parseInt($(this).attr('data-new'));
            mappingTypeOptions = $(this).html();
        });
        
        data_new = parseInt(data_new) + 1;
        
        var htmlcontent = '';
        htmlcontent += '<div class="clearfix"></div><div class="addButtonCheckbox" style="display: flex; margin-right: -15px; margin-left: -15px; flex-wrap: wrap;">';
        htmlcontent += '<div class="col-md-4 my-2"><div class="form-group mb-0"><label for="topicType">Mapping Type</label><select class="load_map_value form-control cust-select" name="mapping_type[]" data-new=' + data_new + ' onchange="validateMappingTypeSelection(this)">' + mappingTypeOptions + '</select></div></div>';
        htmlcontent += '<div class="col-md-4 my-2"><div class="form-group mb-0"><label for="topicType2">Mapping Value</label><select class="form-control cust-select" name="mapping_value[]" data-new=' + data_new + '><option value="">Select Mapping Value</option></select></div></div>';
        htmlcontent += '<div class="col-md-4 mt-0 mb-3" style="padding-top:30px;"><a href="javascript:void(0);" onclick="removeNewRow(this);" class="d-inline btn btn-danger"><i class="mdi mdi-minus"></i></a></div></div>';
        
        $('.addButtonCheckbox:last').after(htmlcontent);
        updateSelectedMappingTypes();
    }
    
    // Validate mapping type selection to prevent duplicates
    function validateMappingTypeSelection(selectElement) {
        var selectedValue = $(selectElement).val();
        var selectedText = $(selectElement).find('option:selected').text();
        
        if (!selectedValue || selectedValue === '') {
            updateSelectedMappingTypes();
            return;
        }
        
        // Check for duplicates in advanced mode only
        if ($("#toggle_basic_advanced").prop("checked") == false) {
            var duplicateFound = false;
            
            $('select[name="mapping_type[]"]').each(function() {
                if ($(this).attr('data-new') !== $(selectElement).attr('data-new')) {
                    if ($(this).val() === selectedValue) {
                        duplicateFound = true;
                        return false;
                    }
                }
            });
            
            if (duplicateFound) {
                alert('Mapping Type "' + selectedText + '" is already selected. Please choose a different Mapping Type.');
                $(selectElement).val('');
                updateSelectedMappingTypes();
                return;
            }
        }
        
        updateSelectedMappingTypes();
        
        // Load mapping values
        var data_new = $(selectElement).attr('data-new');
        var path = "{{ route('ajax_LMS_MappingValue') }}";
        
        $.ajax({
            url: path,
            data: 'mapping_type=' + selectedValue,
            success: function(result) {
                var e = $('select[name="mapping_value[]"][data-new=' + data_new + ']');
                $(e).find('option').remove().end();
                $(e).append($('<option>', { value: '', text: 'Select Mapping Value' }));
                for (var i = 0; i < result.length; i++) {
                    $(e).append($("<option></option>").val(result[i]['id']).html(result[i]['name']));
                }
            }
        });
    }
    
    // Modify removeNewRow function
    function removeNewRow(element) {
        $(element).closest('.addButtonCheckbox').remove();
        updateSelectedMappingTypes();
    }
    
    // Override the existing change event handler for load_map_value
    $(document).off('change', '.load_map_value').on('change', '.load_map_value', function() {
        validateMappingTypeSelection(this);
    });
    
    // Form submission validation
    $(document).ready(function() {
        // Add form submission validation
        $('form').on('submit', function(e) {
            // If in advanced mode, validate mapping types
            if ($("#toggle_basic_advanced").prop("checked") == false) {
                if (!validateNoDuplicateMappingTypes()) {
                    e.preventDefault();
                    return false;
                }
                
                // Also validate that if mapping type is selected, mapping value is also selected
                var hasIncompleteMapping = false;
                $('select[name="mapping_type[]"]').each(function() {
                    var mappingType = $(this).val();
                    var $row = $(this).closest('.addButtonCheckbox');
                    var mappingValue = $row.find('select[name="mapping_value[]"]').val();
                    
                    if (mappingType && mappingType !== '' && (!mappingValue || mappingValue === '')) {
                        alert('Please select Mapping Value for the selected Mapping Type.');
                        hasIncompleteMapping = true;
                        return false;
                    }
                });
                
                if (hasIncompleteMapping) {
                    e.preventDefault();
                    return false;
                }
            }
        });
        
        // Initialize mapping type tracking
        updateSelectedMappingTypes();
    });
    
    // Update the existing addNewRow function reference in HTML
    // Make sure your HTML uses the updated function names
</script>
@include('includes.footer')
@endsection

