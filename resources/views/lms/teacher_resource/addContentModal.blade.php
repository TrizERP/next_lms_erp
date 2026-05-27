<!-- Modal: Add ContentSuggestionModal -->
<div class="modal fade right modal-scrolling" id="ContentSuggestionModal" tabindex="-1"
     role="dialog" aria-labelledby="myModalLabel" style="display: none;" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-side modal-bottom-right modal-notify modal-info"
         role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="heading">Content Suggestion</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">x</span>
                </button>
            </div>
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
            <div class="modal-footer flex-center justify-content-center">
                <input type="button" id="submit" name="submit" value="Save" class="btn btn-success"
                       onclick="get_data_link();">
            </div>
        </div>
    </div>
</div>

<!-- AI Prompt Builder Popup -->
<div class="modal fade" id="aiPromptBuilderModal" tabindex="-1" role="dialog" aria-labelledby="aiPromptBuilderModalLabel" aria-hidden="true" data-backdrop="static" data-keyboard="false">
    <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header bg-light">
                <h5 class="modal-title" id="aiPromptBuilderModalLabel">
                    <i class="mdi mdi-robot mr-1"></i>AI Prompt Builder
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div id="aiPromptPreviewSection" class="alert alert-warning d-none">
                    <h6 class="alert-heading mb-0"><i class="mdi mdi-pencil-outline mr-1"></i>AI Prompt (Editable)</h6>
                    <textarea class="form-control" id="aiGeneratedContent" rows="8" placeholder=" prompt will appear here..."></textarea>
                    <small class="text-muted mt-1">You can edit the prompt before generating slides</small>
                </div>

                <div class="form-group mb-3">
                    <div class="advanced-toggle-wrapper">
                        <input type="checkbox" id="aiAdvancedMappingToggle">
                        <label for="aiAdvancedMappingToggle" class="advanced-toggle">
                            <span class="toggle-text on">Advanced Mapping</span>
                            <span class="toggle-text off">Advanced Mapping</span>
                        </label>
                    </div>
                </div>

                <div id="aiAdvancedMappingSection" class="d-none">
                    <div id="aiMappingRows">
                        <div class="assessment-mapping-row row align-items-center mb-3">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Mapping Type</label>
                                    <select class="load_map_value cust-select form-control mb-0" name="ai_mapping_type[]" data-new="1">
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
                                    <label>Mapping Value</label>
                                    <select class="cust-select form-control map-value mb-0" name="ai_mapping_value[]" data-new="1">
                                        <option value="">Select Mapping Value</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <label>&nbsp;</label>
                                <button type="button" class="btn btn-success btn-sm d-block" id="addAiMappingRow">
                                    <i class="mdi mdi-plus"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="aiSlideCount">Slide Count</label>
                    <input type="number" class="form-control" id="aiSlideCount" value="10" min="1" max="50">
                </div>

                <div class="form-group">
                    <button type="button" class="btn btn-success mt-2" id="generateSlideFromAiContentBtn">
                        <i class="mdi mdi-file-pdf mr-1"></i>Generate Slide
                    </button>
                    <button type="button" class="btn btn-secondary mt-2 ml-2" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Success Modal -->
<!-- Success Modal -->
<div class="modal fade" id="successModal" tabindex="-1" role="dialog" aria-labelledby="successModalLabel" aria-hidden="true" data-backdrop="static" data-keyboard="false">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="successModalLabel">
                    <i class="mdi mdi-check-circle mr-1"></i>Success!
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p>Slide generated successfully!</p>
                <p><strong>File Type:</strong> <span id="successFileType">LINK</span></p>
                <p><strong>Title:</strong> <span id="successTitle"></span></p>
                <div class="form-group">
                    <label for="successLink">Link:</label>
                    <input type="text" class="form-control" id="successLink" readonly>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal" id="successModalClose">Close</button>
                <button type="button" class="btn btn-primary" id="successModalDone">Done</button>
            </div>
        </div>
    </div>
</div>

<style>
    .advanced-toggle-wrapper {
        display: inline-block;
    }

    #aiAdvancedMappingToggle {
        display: none;
    }

    .advanced-toggle {
        width: 140px;
        height: 36px;
        background: #ccc;
        border-radius: 20px;
        position: relative;
        display: inline-block;
        cursor: pointer;
        transition: 0.3s;
        font-size: 12px;
        font-weight: 600;
        overflow: hidden;
    }

    .advanced-toggle::before {
        content: '';
        position: absolute;
        width: 32px;
        height: 32px;
        background: #fff;
        border-radius: 50%;
        top: 2px;
        left: 2px;
        transition: 0.3s;
    }

    .toggle-text {
        position: absolute;
        width: 100%;
        text-align: center;
        top: 50%;
        transform: translateY(-50%);
        pointer-events: none;
    }

    .toggle-text.off {
        color: #000;
    }

    .toggle-text.on {
        color: #fff;
        opacity: 0;
    }

    #aiAdvancedMappingToggle:checked + .advanced-toggle {
        background: #28a745;
    }

    #aiAdvancedMappingToggle:checked + .advanced-toggle::before {
        transform: translateX(104px);
    }

    #aiAdvancedMappingToggle:checked + .advanced-toggle .on {
        opacity: 1;
    }

    #aiAdvancedMappingToggle:checked + .advanced-toggle .off {
        opacity: 0;
    }
</style>

<script type="text/javascript">
    var aiPromptRequestId = 0;

    function collectAssessmentMappings() {
        if (!$('#aiAdvancedMappingToggle').is(':checked')) {
            return {
                advanced_mapping: 0,
                mapping_type: [],
                mapping_value: [],
                reasons: []
            };
        }

        var mappingTypes = [];
        var mappingValues = [];
        var reasons = [];

        $('#aiPromptBuilderModal').find('.assessment-mapping-row').each(function() {
            mappingTypes.push($(this).find('.load_map_value').val());
            mappingValues.push($(this).find('.map-value').val());
            reasons.push('');
        });

        return {
            advanced_mapping: 1,
            mapping_type: mappingTypes,
            mapping_value: mappingValues,
            reasons: reasons
        };
    }

    function buildAiPromptContent() {
        return $('#aiGeneratedContent').val();
    }

    function refreshAiPrompt(showLoading) {
        aiPromptRequestId += 1;
        var requestId = aiPromptRequestId;
        var $btn = $('#generateAiPromptBtn');
        var originalText = $btn.data('original-text') || $btn.html();
        $btn.data('original-text', originalText);

        if (showLoading && $btn.length) {
            $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm mr-1"></span> Generating...');
        }

        $('#aiPromptPreviewSection').removeClass('d-none');
        if (!$('#aiGeneratedContent').val().trim()) {
            $('#aiGeneratedContent').val('Generating prompt...');
        }

        $.ajax({
            url: "{{ route('preview_distribution') }}",
            type: 'GET',
            data: $.extend({}, collectAssessmentMappings(), {
                question_type_id: $('#question_type_id').val() || '',
                total_questions: $('#total_questions').val() || 10,
                standard_id: $('#standard_id').val() || "{{ $data['breadcrum_data']->standard_id ?? $data['standard_id'] ?? '' }}",
                subject_id: $('#subject_id').val() || "{{ $data['breadcrum_data']->subject_id ?? '' }}",
                chapter_id: $('#chapter_id').val() || $('#hid_chapter_id').val() || "{{ $data['breadcrum_data']->chapter_id ?? '' }}",
                topic_id: $('#topic_id').val() || '',
                generate_prompt: true
            }),
            success: function(result) {
                if (requestId !== aiPromptRequestId) {
                    return;
                }

                if (result.status_code === 1) {
                    $('#aiGeneratedContent').val(result.prompt || '');
                } else {
                    alert(result.message || 'Error generating prompt');
                }
            },
            error: function(xhr) {
                console.error('Error:', xhr.responseText);
                alert('Error generating prompt');
            },
            complete: function() {
                if (showLoading && $btn.length) {
                    $btn.prop('disabled', false).html(originalText);
                }
            }
        });
    }

    function hasCompleteAiMappingSelection() {
        var hasCompleteSelection = false;
        if (!$('#aiAdvancedMappingToggle').is(':checked')) {
            return false;
        }
        $('#aiPromptBuilderModal').find('.assessment-mapping-row').each(function() {
            var typeValue = $(this).find('.load_map_value').val();
            var valueValue = $(this).find('.map-value').val();
            if (typeValue && valueValue) {
                hasCompleteSelection = true;
                return false;
            }
        });
        return hasCompleteSelection;
    }

    $(document).on('click', '#openAiPromptBuilderBtn', function() {
        $('#aiPromptBuilderModal').modal('show');
        refreshAiPrompt(true);
    });
// Update the openAiPromptBuilderBtn click handler
$(document).on('click', '#openAiPromptBuilderBtn', function() {
    // Collect existing mappings from the form
    var existingMappings = [];
    $('.addButtonCheckbox').each(function() {
        var mappingType = $(this).find('select[name="mapping_type[]"]').val();
        var mappingValue = $(this).find('select[name="mapping_value[]"]').val();
        if (mappingType && mappingValue) {
            existingMappings.push({
                mapping_type_id: mappingType,
                mapping_value_id: mappingValue
            });
        }
    });
    
    // Store mappings in modal for use during generation
    $('#aiPromptBuilderModal').data('existingMappings', existingMappings);
    
    $('#aiPromptBuilderModal').modal('show');
    refreshAiPrompt(true);
});

// Update the generateSlideFromAiContentBtn to include mappings in request
$(document).on('click', '#generateSlideFromAiContentBtn', function() {
    var generatedContent = $('#aiGeneratedContent').val().trim();
    
    if (!generatedContent) {
        generatedContent = buildAiPromptContent();
        $('#aiGeneratedContent').val(generatedContent);
    }
    
    if (!generatedContent) {
        alert('Please enter or generate content first');
        return;
    }
    
    var button = $(this);
    var slideCount = parseInt($('#aiSlideCount').val(), 10) || 10;
    
    // Get existing mappings from the form
    var mappings = [];
    $('.addButtonCheckbox').each(function() {
        var mappingType = $(this).find('select[name="mapping_type[]"]').val();
        var mappingValue = $(this).find('select[name="mapping_value[]"]').val();
        if (mappingType && mappingValue) {
            mappings.push({
                mapping_type_id: mappingType,
                mapping_value_id: mappingValue
            });
        }
    });
    
    button.prop('disabled', true).html('<i class="fa fa-spinner fa-spin mr-1"></i>Generating Slide...');
    $("#overlay").css("display", "block");
    $("#overlay p").text('Generating slide PDF using Gamma AI...');
    
    $.ajax({
        url: "{{ route('generate-gamma-pdf') }}",
        type: 'POST',
        data: JSON.stringify({
            prompt: generatedContent,
            slide_count: slideCount,
            content_category: $('#content_category').val() || 'Classroom Presentation',
            title: $('#title').val() || 'Generated Presentation',
            mappings: mappings  // Include mappings in the request
        }),
        contentType: 'application/json',
        headers: {
            'Accept': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        success: function(response) {
            console.log('Gamma PDF Response:', response);
            $("#overlay").css("display", "none");
            button.prop('disabled', false).html('<i class="mdi mdi-file-pdf mr-1"></i>Generate Slide');
            
            if (response.success && response.data && response.data.file_url) {
                // Pass mappings back in response data
                if (mappings.length > 0) {
                    response.data.mappings = mappings;
                }
                autoFillGammaForm(response.data.file_url, response.data);
                $('#aiPromptBuilderModal').modal('hide');
                
                $('#successFileType').text('LINK');
                $('#successTitle').text(response.data.title || 'Gamma Presentation');
                $('#successLink').val(response.data.file_url);
                $('#successModal').modal('show');
            } else {
                alert(response.message || 'Slide generation failed.');
            }
        },
        error: function(xhr) {
            $("#overlay").css("display", "none");
            button.prop('disabled', false).html('<i class="mdi mdi-file-pdf mr-1"></i>Generate Slide');
            console.error(xhr.responseText);
            
            let errorMsg = 'An error occurred while generating the slide PDF.';
            try {
                const errorResponse = JSON.parse(xhr.responseText);
                if (errorResponse.message) {
                    errorMsg = errorResponse.message;
                }
            } catch(e) {}
            alert(errorMsg);
        }
    });
});
    $(document).on('change', '#aiAdvancedMappingToggle', function() {
        $('#aiAdvancedMappingSection').toggleClass('d-none', !$(this).is(':checked'));
        if ($(this).is(':checked')) {
            $('#aiPromptPreviewSection').find('.alert-heading').addClass('d-none');
        } else {
            $('#aiPromptPreviewSection').find('.alert-heading').removeClass('d-none');
        }
        refreshAiPrompt(true);
    });

    $(document).on('change', '#aiPromptBuilderModal .load_map_value', function(e) {
        e.stopPropagation();
        e.preventDefault();

        var mappingType = $(this).val();
        var dataNew = $(this).attr('data-new');
        var valueSelect = $('#aiPromptBuilderModal').find('select[name="ai_mapping_value[]"][data-new="' + dataNew + '"]');
        valueSelect.find('option').remove().end().append('<option value="">Select Mapping Value</option>');
        if (!mappingType) {
            refreshAiPrompt(true);
            return;
        }
        $.ajax({
            url: "{{ route('ajax_LMS_MappingValue') }}",
            data: { mapping_type: mappingType },
            success: function(result) {
                result.forEach(function(item) {
                    valueSelect.append($('<option></option>').val(item.id).html(item.name));
                });

                var firstValue = valueSelect.find('option[value!=""]').first().val();
                if (firstValue) {
                    valueSelect.val(firstValue);
                }
                refreshAiPrompt(true);
            }
        });
    });

    $(document).on('change', '#aiPromptBuilderModal .map-value', function() {
        refreshAiPrompt(true);
    });

    $(document).on('click', '#addAiMappingRow', function() {
        var dataNew = 1;
        var mappingTypeOptions = '';
        $('#aiPromptBuilderModal').find('select[name="ai_mapping_type[]"]').each(function() {
            dataNew = parseInt($(this).attr('data-new'), 10);
            mappingTypeOptions = $(this).html();
        });
        dataNew += 1;
        $('#aiMappingRows').append(`
            <div class="assessment-mapping-row row align-items-center mb-3">
                <div class="col-md-4">
                    <div class="form-group">
                        <label>Mapping Type</label>
                        <select class="load_map_value cust-select form-control mb-0" name="ai_mapping_type[]" data-new="${dataNew}">${mappingTypeOptions}</select>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label>Mapping Value</label>
                        <select class="cust-select form-control map-value mb-0" name="ai_mapping_value[]" data-new="${dataNew}">
                            <option value="">Select Mapping Value</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-2">
                    <label>&nbsp;</label>
                    <button type="button" class="btn btn-danger btn-sm d-block remove-ai-mapping-row">
                        <i class="mdi mdi-minus"></i>
                    </button>
                </div>
            </div>
        `);
        refreshAiPrompt(true);
    });

    $(document).on('click', '.remove-ai-mapping-row', function() {
        $(this).closest('.assessment-mapping-row').remove();
        refreshAiPrompt(true);
    });

    // Generate Slide from AI Content
     $(document).on('click', '#generateSlideFromAiContentBtn', function() {
        var generatedContent = $('#aiGeneratedContent').val().trim();
        
        if (!generatedContent) {
            generatedContent = buildAiPromptContent();
            $('#aiGeneratedContent').val(generatedContent);
        }
        
        if (!generatedContent) {
            alert('Please enter or generate content first');
            return;
        }
        
        var button = $(this);
        var slideCount = parseInt($('#aiSlideCount').val(), 10) || 10;
        button.prop('disabled', true).html('<i class="fa fa-spinner fa-spin mr-1"></i>Generating Slide...');
        $("#overlay").css("display", "block");
        $("#overlay p").text('Generating slide PDF using Gamma AI...');
        
        // Call your Laravel Gamma PDF endpoint
        $.ajax({
            url: "{{ route('generate-gamma-pdf') }}",
            type: 'POST',
            data: JSON.stringify({
                prompt: generatedContent,
                slide_count: slideCount
            }),
            contentType: 'application/json',
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            success: function(response) {
    console.log('Gamma PDF Response:', response);
    $("#overlay").css("display", "none");
    button.prop('disabled', false).html('<i class="mdi mdi-file-pdf mr-1"></i>Generate Slide');
    
    if (response.success && response.data && response.data.file_url) {
        // Auto-fill the form with Gamma generated PDF URL
        autoFillGammaForm(response.data.file_url, response.data);
        $('#aiPromptBuilderModal').modal('hide');
        
        // Also update the success modal if you're using it
        $('#successFileType').text('LINK');
        $('#successTitle').text(response.data.title || 'Gamma Presentation');
        $('#successLink').val(response.data.file_url);
        $('#successModal').modal('show');
    } else {
        alert(response.message || 'Slide generation failed.');
    }
},
            error: function(xhr) {
                $("#overlay").css("display", "none");
                button.prop('disabled', false).html('<i class="mdi mdi-file-pdf mr-1"></i>Generate Slide');
                console.error(xhr.responseText);
                
                let errorMsg = 'An error occurred while generating the slide PDF.';
                try {
                    const errorResponse = JSON.parse(xhr.responseText);
                    if (errorResponse.message) {
                        errorMsg = errorResponse.message;
                    }
                } catch(e) {}
                alert(errorMsg);
            }
        });
    });

    // Auto-fill form with Gamma PDF URL
    // Auto-fill form with Gamma PDF URL
// Auto-fill form with Gamma PDF URL and mappings
function autoFillGammaForm(fileUrl, responseData) {
    console.log('=== Auto-filling form with Gamma PDF ===');
    console.log('File URL:', fileUrl);
    console.log('Response Data:', responseData);
    
    // Set content type to link
    $('#contentType').val('link').trigger('change');
    
    // Show link div and hide upload div
    if (typeof restrict_filetype === 'function') {
        restrict_filetype('link');
    } else {
        $("#link_div").show();
        $("#upload_div").hide();
        $("#link").attr("required", "true");
        $("#filename").removeAttr('required');
        $("#upload_div1").hide();
    }
    
    // Set the Gamma PDF URL in link field
    $('#link').val(fileUrl);
    
    // Auto-fill title if empty
    if (!$('#title').val() || $('#title').val().trim() === '') {
        var title = responseData.gamma_url ? responseData.gamma_url.split('/').pop() : 'Gamma Generated Presentation';
        $('#title').val('Gamma Presentation - ' + title);
    }
    
    // Auto-fill description if empty
    if (!$('#description').val() || $('#description').val().trim() === '') {
        $('#description').val('AI generated presentation using Gamma API. ' + 
                             'Generation ID: ' + (responseData.generation_id || 'N/A'));
    }
    
    // Auto-select Content Category
    var contentCategory = responseData.content_category || 'Classroom Presentation';
    var $categorySelect = $('#content_category');
    var categoryFound = false;
    
    $categorySelect.find('option').each(function() {
        if ($(this).val() === contentCategory) {
            $(this).prop('selected', true);
            categoryFound = true;
            console.log('Content Category auto-selected:', contentCategory);
            return false;
        }
    });
    
    if (!categoryFound) {
        console.log('Content Category not found in dropdown:', contentCategory);
    }
    
    // ========== NEW: AUTO-FILL MAPPING TYPE AND MAPPING VALUE ==========
    // Check if responseData contains mapping information
    if (responseData.mappings && Array.isArray(responseData.mappings) && responseData.mappings.length > 0) {
        console.log('Auto-filling mappings:', responseData.mappings);
        
        // Clear existing mapping rows except the first one
        var $mappingRows = $('.addButtonCheckbox');
        var $firstRow = $mappingRows.first();
        
        // Remove all extra rows except the first one
        $mappingRows.each(function(index) {
            if (index > 0) {
                $(this).remove();
            }
        });
        
        // Clear the first row's selections
        $firstRow.find('select[name="mapping_type[]"]').val('');
        $firstRow.find('select[name="mapping_value[]"]').empty().append('<option value="">Select Mapping Value</option>');
        
        // Populate each mapping
        responseData.mappings.forEach(function(mapping, index) {
            if (index === 0) {
                // Use the existing first row
                autoFillMappingRow($firstRow, mapping);
            } else {
                // Add a new row and fill it
                addNewRow();
                // Wait a moment for the new row to be added
                setTimeout(function() {
                    var $newRow = $('.addButtonCheckbox').last();
                    autoFillMappingRow($newRow, mapping);
                }, 100);
            }
        });
    }
    // Also check for single mapping data if not in array format
    else if (responseData.mapping_type && responseData.mapping_value) {
        console.log('Auto-filling single mapping:', responseData.mapping_type, responseData.mapping_value);
        var $firstRow = $('.addButtonCheckbox').first();
        
        // Wait for mapping value to load via AJAX
        autoFillSingleMapping($firstRow, responseData.mapping_type, responseData.mapping_value);
    }
    // ========== END MAPPING AUTO-FILL ==========
    
    // Remove any existing gamma-related hidden inputs
    $('input[name="gamma_presentation_url"]').remove();
    $('input[name="gamma_presentation_name"]').remove();
    $('input[name="ibl_generated_url"]').remove();
    $('input[name="ibl_content_type"]').remove();
    $('input[name="generation_id"]').remove();
    $('input[name="content_category_hidden"]').remove();
    
    // Add hidden inputs for gamma presentation data
    $('form').append($('<input>', { 
        type: 'hidden', 
        name: 'gamma_presentation_url', 
        value: fileUrl 
    }));
    $('form').append($('<input>', { 
        type: 'hidden', 
        name: 'gamma_presentation_name', 
        value: $('#title').val() 
    }));
    $('form').append($('<input>', { 
        type: 'hidden', 
        name: 'ibl_generated_url', 
        value: fileUrl 
    }));
    $('form').append($('<input>', { 
        type: 'hidden', 
        name: 'ibl_content_type', 
        value: 'link' 
    }));
    $('form').append($('<input>', { 
        type: 'hidden', 
        name: 'generation_id', 
        value: responseData.generation_id || '' 
    }));
    $('form').append($('<input>', { 
        type: 'hidden', 
        name: 'content_category_hidden', 
        value: contentCategory 
    }));
    
    // Add mapping data as hidden inputs for server-side processing (optional)
    if (responseData.mappings) {
        $('form').append($('<input>', {
            type: 'hidden',
            name: 'auto_filled_mappings',
            value: JSON.stringify(responseData.mappings)
        }));
    }
    
    // Show success message
    showSuccessMessage(fileUrl, $('#title').val(), contentCategory);
    
    console.log('Form auto-filled successfully with Gamma PDF URL and mappings!');
}

// Helper function to auto-fill a single mapping row
function autoFillMappingRow($row, mapping) {
    var $typeSelect = $row.find('select[name="mapping_type[]"]');
    var $valueSelect = $row.find('select[name="mapping_value[]"]');
    
    // First, set the mapping type value
    if (mapping.mapping_type_id || mapping.mapping_type) {
        var mappingTypeId = mapping.mapping_type_id || mapping.mapping_type;
        $typeSelect.val(mappingTypeId).trigger('change');
        
        // Wait for the mapping values to load via AJAX (from the change event)
        setTimeout(function() {
            var mappingValueId = mapping.mapping_value_id || mapping.mapping_value;
            if (mappingValueId) {
                $valueSelect.val(mappingValueId);
                console.log('Mapping auto-filled:', mappingTypeId, '->', mappingValueId);
            }
        }, 500);
    }
}

// Helper function to auto-fill single mapping with AJAX loading
function autoFillSingleMapping($row, mappingTypeId, mappingValueId) {
    var $typeSelect = $row.find('select[name="mapping_type[]"]');
    var $valueSelect = $row.find('select[name="mapping_value[]"]');
    
    // Set mapping type
    $typeSelect.val(mappingTypeId).trigger('change');
    
    // When mapping values load, set the value
    $(document).one('mappingValuesLoaded', function() {
        $valueSelect.val(mappingValueId);
        console.log('Single mapping auto-filled:', mappingTypeId, '->', mappingValueId);
    });
}

// Modify the existing AJAX success handler to trigger mapping values loaded event
$(document).on('change', '.load_map_value', function () {
    var mapping_type = $(this).val();
    var data_new = $(this).attr('data-new');
    var path = "{{ route('ajax_LMS_MappingValue') }}";
    $.ajax({
        url: path,
        data: 'mapping_type=' + mapping_type,
        success: function (result) {
            var e = $('select[name="mapping_value[]"][data-new=' + data_new + ']');
            $(e).find('option').remove().end();
            $(e).append($('<option>', { value: '', text: 'Select Mapping Value' }));
            for (var i = 0; i < result.length; i++) {
                $(e).append($("<option></option>").val(result[i]['id']).html(result[i]['name']));
            }
            // Trigger event that mapping values are loaded
            $(document).trigger('mappingValuesLoaded');
        }
    });
});

    // Show success notification
   // Show success notification
function showSuccessMessage(url, title, category) {
    // Create temporary success alert
    var successDiv = $('<div class="alert alert-success alert-dismissible fade show" role="alert" style="position: fixed; top: 20px; right: 20px; z-index: 9999; min-width: 300px;">' +
        '<strong>Success!</strong> Gamma PDF generated successfully.<br>' +
        '<strong>Title:</strong> ' + title + '<br>' +
        '<strong>Category:</strong> ' + (category || 'Classroom Presentation') + '<br>' +
        '<strong>URL:</strong> <a href="' + url + '" target="_blank">Open PDF</a>' +
        '<button type="button" class="close" data-dismiss="alert" aria-label="Close">' +
        '<span aria-hidden="true">&times;</span></button></div>');
    
    $('body').append(successDiv);
    
    // Auto remove after 5 seconds
    setTimeout(function() {
        successDiv.alert('close');
    }, 5000);
}

    // Generate Gamma Presentation (simplified version)
    var isGammaGenerating = false;

    function generateGammaPresentation() {
        if (isGammaGenerating) {
            alert('A presentation is already being generated. Please wait...');
            return;
        }
        
        var prompt = buildAiPromptContent();
        
        if (!prompt || prompt.trim() === '') {
            alert('Please enter a prompt for the presentation');
            return;
        }
        
        isGammaGenerating = true;
        $('#aiSlideBtn').prop('disabled', true).html('<i class="fa fa-spinner fa-spin mr-1"></i>Generating...');
        
        $("#overlay").css("display", "block");
        $("#overlay p").text('Generating presentation using Gamma AI...');
        
        $.ajax({
            url: "{{ route('generate-gamma-pdf') }}",
            type: 'POST',
            data: JSON.stringify({
                prompt: prompt,
                slide_count: parseInt(slideCount, 10)
            }),
            contentType: 'application/json',
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            success: function(response) {
                console.log('Gamma Presentation Response:', response);
                $("#overlay").css("display", "none");
                resetGammaButton();
                
                if (response.success && response.data && response.data.file_url) {
                    autoFillGammaForm(response.data.file_url, response.data);
                } else {
                    alert(response.message || 'Failed to generate presentation');
                }
            },
            error: function(xhr) {
                $("#overlay").css("display", "none");
                resetGammaButton();
                console.error(xhr.responseText);
                
                let errorMsg = 'An error occurred while generating the presentation.';
                try {
                    const errorResponse = JSON.parse(xhr.responseText);
                    if (errorResponse.message) {
                        errorMsg = errorResponse.message;
                    }
                } catch(e) {}
                alert(errorMsg);
            }
        });
    }

    function resetGammaButton() {
        isGammaGenerating = false;
        $('#aiSlideBtn').prop('disabled', false).html('<i class="fa fa-search-plus mr-1"></i>AI');
    }

    // Document ready function - add this to your existing $(document).ready()
$(document).ready(function () {
    // AI Slide button click handler
    $('#aiSlideBtn').off('click').on('click', function(e) {
        e.preventDefault();
        generateGammaPresentation();
    });
    
    // Success Modal - Close button handler
    $('#successModalClose').off('click').on('click', function() {
        $('#successModal').modal('hide');
    });
    
    // Success Modal - Done button handler (closes the modal)
    $('#successModalDone').off('click').on('click', function() {
        $('#successModal').modal('hide');
    });
    
    // Optional: Also close when clicking the X button
    $('#successModal .close').off('click').on('click', function() {
        $('#successModal').modal('hide');
    });
});

    // Remove complex gamma polling functions and keep only essential ones
    function load_content_data() {
        // Your existing YouTube content loading function
        console.log('Loading content data...');
    }

    function get_data_link() {
        // Your existing get data link function
        console.log('Getting data link...');
    }
</script>


