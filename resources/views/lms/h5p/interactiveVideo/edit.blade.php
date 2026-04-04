@extends('layout')
@section('container')
<link rel="stylesheet" href="/admin_dep/css/h5pCSS.css">
<style>
    .option-row {
        margin-bottom: 10px;
    }
    .option-row .input-group {
        width: 100%;
    }
    .options-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 15px;
    }
    @media (max-width: 768px) {
        .options-grid {
            grid-template-columns: 1fr;
        }
    }
</style>
<div id="page-wrapper">
    <div class="container-fluid mb-5">
        @if($sessionData = Session::get('data'))
        <div class="alert alert-{{ $sessionData['status'] ? 'success' : 'danger' }} alert-block">
            <button type="button" class="close" data-dismiss="alert">×</button>
            <strong>{{ $sessionData['message'] }}</strong>
        </div>
        @endif

        <!-- Animated Header -->
        <div class="modern-header">
            <div class="header-content">
                <div class="header-left">
                    <div class="header-icon">
                        <i class="fas fa-edit"></i>
                    </div>
                    <div class="header-text">
                        <h1>Edit Interactive Video</h1>
                        <div class="header-badges">
                            <span class="header-badge">
                                <i class="fas fa-book-open"></i>
                                {{ App\Helpers\getTableFieldFromId('sub_std_map','display_name',$data['subject_id'],'subject_id') }}
                            </span>
                            <span class="header-badge">
                                <i class="fas fa-graduation-cap"></i>
                                {{ App\Helpers\getTableFieldFromId('standard','name',$data['standard_id']) }}
                            </span>
                            <span class="header-badge">
                                <i class="fas fa-chapter"></i>
                                {{ App\Helpers\getTableFieldFromId('chapter_master','chapter_name',$data['chapter_id']) }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="container">
            <form action="{{route('h5p_interactive_video.update',$data['video']->id)}}" method="post" enctype="multipart/form-data" id="videoForm">
                @csrf
                @method('PUT')
                <input type="hidden" name="chapter_id" value="{{$data['chapter_id']}}">
                <input type="hidden" name="subject_id" value="{{$data['subject_id']}}">
                <input type="hidden" name="standard_id" value="{{$data['standard_id']}}">
                <div class="card">
                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label for="title">Title</label>
                            <input type="text" class="form-control" id="title" name="title" value="{{ old('title', $data['video']->title) }}" required>
                        </div>
                        <div class="col-md-6 form-group">
                            <label for="videoInput">Upload New Video (Optional)</label>
                            <input type="file" name="video_file" id="videoInput" class="form-control" accept="video/mp4,video/mov,video/avi,video/mkv">
                            <small class="text-muted">Supported formats: MP4, MOV, AVI, MKV (Max size: 500MB). Leave empty to keep existing video.</small>
                        </div>
                        <div class="col-md-12 video-preview-container">
                            <div id="current-video-container" style="{{ $data['video']->video_path ? 'display: block;' : 'display: none;' }}">
                                <label class="form-label">Current Video</label>
                                <video id="current-video-preview" controls style="width: 100%; max-height: 400px;" class="mt-2 mb-3">
                                    <source src="{{ $data['video']->video_path }}" type="video/mp4">
                                    Your browser does not support the video tag.
                                </video>
                                <div class="text-muted small mb-3">Current video URL: {{ $data['video']->video_path }}</div>
                            </div>
                            <div id="new-video-container" style="display: none;">
                                <label class="form-label">New Video Preview</label>
                                <video id="new-video-preview" controls style="width: 100%; max-height: 400px;" class="mt-2 mb-3"></video>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card mt-4">
                    <h4 class="mb-3"><i class="fas fa-comment-dots me-2"></i>Interactive Points</h4>
                    <div id="interactions-container">
                        @foreach($data['video']->interactions as $index => $interaction)
                        <div class="row mb-2 cloneRow" style="border-bottom: 1px solid #ddd;" data-interaction-id="{{ $index }}">
                            <div class="col-md-3">
                                <label class="form-label required-field">Time (seconds)</label>
                                <input type="number" name="interactions[{{ $index }}][time]" class="form-control time-input" required value="{{ old("interactions.$index.time", $interaction->time) }}" step="0.1">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label required-field">Type</label>
                                <select name="interactions[{{ $index }}][interaction_type]" class="form-control interaction-type" required>
                                    <option value="multiple_choice" {{ $interaction->interaction_type == 'multiple_choice' ? 'selected' : '' }}>Multiple Choice</option>
                                    <option value="true_false" {{ $interaction->interaction_type == 'true_false' ? 'selected' : '' }}>True/False</option>
                                    <option value="text_input" {{ $interaction->interaction_type == 'text_input' ? 'selected' : '' }}>Text Input</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label required-field">Question</label>
                                <input type="text" name="interactions[{{ $index }}][question]" class="form-control" required placeholder="Enter your question" value="{{ old("interactions.$index.question", $interaction->question) }}">
                            </div>
                            <div class="col-md-3">
                                <button type="button" class="btn-remove-interaction btn btn-danger">
                                    <i class="fas fa-minus"></i>
                                </button>
                                <button type="button" class="btn-add-single btn btn-primary">
                                    <i class="fas fa-plus"></i>
                                </button>
                            </div>
                            <div class="col-12">
                                <div class="options-container mt-3" id="options-container-{{ $index }}"></div>
                            </div>
                            <hr>
                        </div>
                        @endforeach
                    </div>
                </div>
                <div class="text-center">
                    <input type="submit" value="Update" class="btn btn-success">
                </div>
            </form>
        </div>

    </div>
</div>

@include('includes.lmsfooterJs')
<script>
    let interactionCount = {{ count($data['video']->interactions) }};
    let optionCounters = {};
    
    // Parse existing interactions data
    const existingInteractions = @json($data['video']->interactions);
    
    // Function to parse options JSON
    function parseOptions(optionsJson, type) {
        if (!optionsJson) return [];
        
        try {
            const options = typeof optionsJson === 'string' ? JSON.parse(optionsJson) : optionsJson;
            if (type === 'multiple_choice' && options) {
                // Convert object to array
                return Object.values(options);
            }
            return [];
        } catch(e) {
            return [];
        }
    }

    $(document).ready(function() {
        const $currentVideoContainer = $('#current-video-container');
        const $newVideoContainer = $('#new-video-container');
        const $currentVideoPreview = $('#current-video-preview');
        const $newVideoPreview = $('#new-video-preview');
        const $videoInput = $('#videoInput');
        
        // Initialize all existing interactions with their options
        existingInteractions.forEach((interaction, index) => {
            if (interaction.interaction_type === 'multiple_choice') {
                const optionsArray = parseOptions(interaction.options, 'multiple_choice');
                optionCounters[index] = optionsArray.length || 4;
                initializeInteractionOptions(index, interaction.interaction_type, interaction);
            } else if (interaction.interaction_type === 'true_false') {
                optionCounters[index] = 2;
                initializeInteractionOptions(index, interaction.interaction_type, interaction);
            } else {
                optionCounters[index] = 0;
                initializeInteractionOptions(index, interaction.interaction_type, interaction);
            }
        });
        
        // Video preview for new upload
        $videoInput.on('change', function(event) {
            const file = event.target.files[0];
            if (file) {
                const validTypes = ['video/mp4', 'video/mov', 'video/avi', 'video/mkv', 'video/webm'];
                if (!validTypes.includes(file.type)) {
                    alert('Please select a valid video file (MP4, MOV, AVI, MKV, WEBM)');
                    $videoInput.val('');
                    return;
                }
                
                const maxSize = 500 * 1024 * 1024;
                if (file.size > maxSize) {
                    alert('File size must be less than 500MB');
                    $videoInput.val('');
                    return;
                }
                
                const blobURL = URL.createObjectURL(file);
                $newVideoPreview.attr('src', blobURL);
                
                // Hide current video container and show new video container
                $currentVideoContainer.hide();
                $newVideoContainer.show();
                
                $newVideoPreview.on('loadedmetadata', function() {
                    console.log('New video loaded, duration:', this.duration);
                });
                
                $newVideoPreview.on('error', function() {
                    alert('Failed to load video. Please try another file.');
                    $newVideoContainer.hide();
                    $currentVideoContainer.show();
                    $videoInput.val('');
                    URL.revokeObjectURL(blobURL);
                });
                
                $newVideoPreview.on('loadeddata', function() {
                    URL.revokeObjectURL(blobURL);
                });
            } else {
                // No file selected, show current video
                $newVideoContainer.hide();
                $currentVideoContainer.show();
            }
        });
        
        $('#videoForm').on('keypress', function(e) {
            if (e.target.type !== 'textarea' && e.key === 'Enter') {
                e.preventDefault();
            }
        });
        
        $(document).on('change', '.time-input', function() {
            handleTimeInputChange(this);
        });
        
        $(document).on('change', '.interaction-type', function() {
            const $row = $(this).closest('.cloneRow');
            const interactionId = $row.data('interaction-id');
            toggleOptions(this, interactionId);
        });
        
        $(document).on('click', '.btn-add-single', function() {
            cloneRow(this);
        });
        
        $(document).on('click', '.btn-remove-interaction', function() {
            removeInteraction(this);
        });
        
        $(document).on('click', '.btn-add-option', function() {
            const $row = $(this).closest('.cloneRow');
            const interactionId = $row.data('interaction-id');
            addOption(interactionId);
        });
        
        $(document).on('click', '.btn-remove-option', function() {
            const $row = $(this).closest('.cloneRow');
            const interactionId = $row.data('interaction-id');
            removeOption(this, interactionId);
        });
    });
    
    function initializeInteractionOptions(interactionId, type, interactionData = null) {
        const $container = $(`#options-container-${interactionId}`);
        
        if (type === 'multiple_choice') {
            renderMultipleChoice($container, interactionId, interactionData);
        } else if (type === 'true_false') {
            renderTrueFalse($container, interactionId, interactionData);
        } else {
            renderTextInput($container, interactionId, interactionData);
        }
    }
    
    function cloneRow(button) {
        const $currentRow = $(button).closest('.cloneRow');
        const $newRow = $currentRow.clone();
        const newId = interactionCount;
        
        // Reset input values
        $newRow.find('input[type="text"]').val('');
        $newRow.find('input[type="number"]').val('');
        $newRow.find('select').prop('selectedIndex', 0);
        
        // Update all input names with new index
        $newRow.find('input, select, button').each(function() {
            const name = $(this).attr('name');
            if (name) {
                const newName = name.replace(/\[\d+\]/, '[' + newId + ']');
                $(this).attr('name', newName);
            }
            
            // Update IDs for radio buttons if any
            const id = $(this).attr('id');
            if (id) {
                const newIdAttr = id.replace(/\d+/, newId);
                $(this).attr('id', newIdAttr);
            }
        });
        
        // Update for attribute on labels
        $newRow.find('label').each(function() {
            const forAttr = $(this).attr('for');
            if (forAttr) {
                const newFor = forAttr.replace(/\d+/, newId);
                $(this).attr('for', newFor);
            }
        });
        
        // Update options container ID
        const $optionsContainer = $newRow.find('.options-container');
        $optionsContainer.attr('id', `options-container-${newId}`).empty();
        
        // Set data-interaction-id
        $newRow.attr('data-interaction-id', newId);
        
        // Initialize options for the new row based on selected type
        const selectedType = $newRow.find('.interaction-type').val();
        optionCounters[newId] = 4;
        if (selectedType === 'multiple_choice') {
            renderMultipleChoice($optionsContainer, newId, null);
        } else if (selectedType === 'true_false') {
            renderTrueFalse($optionsContainer, newId, null);
        } else {
            renderTextInput($optionsContainer, newId, null);
        }
        
        // Insert new row after current row
        $currentRow.after($newRow);
        interactionCount++;
    }
    
    function toggleOptions(select, interactionId) {
        const $select = $(select);
        const value = $select.val();
        const $optionsContainer = $(`#options-container-${interactionId}`);
        
        $optionsContainer.empty();
        
        if (value === 'multiple_choice') {
            renderMultipleChoice($optionsContainer, interactionId, null);
        } else if (value === 'true_false') {
            renderTrueFalse($optionsContainer, interactionId, null);
        } else {
            renderTextInput($optionsContainer, interactionId, null);
        }
    }
    
    function handleTimeInputChange(inputElement) {
        const $input = $(inputElement);
        const originalValue = $input.val();
        const seconds = convertToSeconds(originalValue);
        
        if (seconds !== null) {
            $input.attr('data-seconds', seconds).val(seconds).addClass('is-valid').removeClass('is-invalid');
            setTimeout(() => $input.removeClass('is-valid'), 2000);
        } else if (originalValue !== '') {
            $input.addClass('is-invalid').removeClass('is-valid');
            setTimeout(() => $input.removeClass('is-invalid'), 2000);
        }
    }
    
    function renderMultipleChoice($container, interactionId, interactionData) {
        if (!optionCounters[interactionId]) optionCounters[interactionId] = 4;
        
        const optionCount = optionCounters[interactionId];
        let optionsValues = [];
        
        // Get existing options if editing
        if (interactionData && interactionData.options) {
            try {
                const optionsObj = typeof interactionData.options === 'string' ? JSON.parse(interactionData.options) : interactionData.options;
                optionsValues = Object.values(optionsObj);
                optionCounters[interactionId] = optionsValues.length;
            } catch(e) {
                optionsValues = [];
            }
        }
        
        // Build options HTML with grid layout for 4+ options (2x2 grid)
        let optionsHtml = `
            <div class="option-group mb-3">
                <label class="form-label">Options</label>
                <div id="options-list-${interactionId}" class="options-list">
                    <div class="options-grid">
        `;
        
        // Create options in grid format (2 columns)
        for (let i = 1; i <= optionCount; i++) {
            const removeBtnDisplay = i <= 4 ? 'style="display: none;"' : '';
            const optionLetter = String.fromCharCode(64 + i); // A, B, C, D, E, etc.
            const optionValue = optionsValues[i-1] || '';
            optionsHtml += `
                <div class="option-item">
                    <div class="input-group">
                        <span class="input-group-text">${optionLetter}</span>
                        <input type="text" name="interactions[${interactionId}][options][]" class="form-control" placeholder="Enter option ${optionLetter}" value="${optionValue}" required>
                        <button type="button" class="btn btn-outline-danger btn-remove-option" ${removeBtnDisplay}>
                            <i class="fas fa-minus"></i>
                        </button>
                    </div>
                </div>
            `;
        }
        
        optionsHtml += `
                    </div>
                </div>
                ${optionCount <= 4 ? '' : '<button type="button" class="btn btn-outline-primary btn-sm btn-add-option mt-2"><i class="fas fa-plus"></i> Add Option</button>'}
            </div>
            <div class="option-group mb-3">
                <label class="form-label required-field">Correct Answer</label>
                <select name="interactions[${interactionId}][correct_answer]" class="form-control correct-answer-select" id="correct-answer-${interactionId}" required>
                    <option value="">Select correct answer</option>
        `;
        
        // Build correct answer dropdown options with option letters as display and numeric key as value
        for (let i = 1; i <= optionCount; i++) {
            const optionLetter = String.fromCharCode(64 + i);
            let selected = '';
            if (interactionData && interactionData.correct_answer) {
                const correctAnswerValue = interactionData.correct_answer;
                // Check if correct_answer matches the option key
                if (correctAnswerValue == i) {
                    selected = 'selected';
                }
            }
            optionsHtml += `<option value="${i}" ${selected}>${optionLetter}</option>`;
        }
        
        optionsHtml += `
                </select>
            </div>
        `;
        $container.html(optionsHtml);
    }
    
    function renderTrueFalse($container, interactionId, interactionData) {
        let trueChecked = false;
        let falseChecked = false;
        
        if (interactionData && interactionData.correct_answer) {
            const correctAnswer = String(interactionData.correct_answer).toLowerCase();
            if (correctAnswer === 'true' || correctAnswer === '1') {
                trueChecked = true;
            } else if (correctAnswer === 'false' || correctAnswer === '2' || correctAnswer === '0') {
                falseChecked = true;
            }
        }
        
        const trueCheckedAttr = trueChecked ? 'checked' : '';
        const falseCheckedAttr = falseChecked ? 'checked' : '';
        
        const html = `
            <div class="option-group mb-3">
                <label class="form-label required-field">Correct Answer</label>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="interactions[${interactionId}][correct_answer]" value="1" id="true-${interactionId}" required ${trueCheckedAttr}>
                    <label class="form-check-label" for="true-${interactionId}">True</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="interactions[${interactionId}][correct_answer]" value="2" id="false-${interactionId}" required ${falseCheckedAttr}>
                    <label class="form-check-label" for="false-${interactionId}">False</label>
                </div>
            </div>
        `;
        $container.html(html);
    }
    
    function renderTextInput($container, interactionId, interactionData) {
        const correctAnswerValue = (interactionData && interactionData.correct_answer) ? interactionData.correct_answer : '';
        
        const html = `
            <div class="option-group mb-3">
                <label class="form-label">Correct Answer (Optional)</label>
                <input type="text" name="interactions[${interactionId}][correct_answer]" class="form-control" placeholder="Enter expected answer (optional)" value="${correctAnswerValue}">
                <small class="form-text text-muted">Leave blank if any answer is acceptable</small>
            </div>
        `;
        $container.html(html);
    }
    
    function addOption(interactionId) {
        const $optionsGrid = $(`#options-list-${interactionId} .options-grid`);
        const optionCount = $optionsGrid.children().length;
        const newOptionNumber = optionCount + 1;
        
        // Update counter
        optionCounters[interactionId] = optionCount + 1;
        
        const optionLetter = String.fromCharCode(64 + newOptionNumber);
        
        const $optionDiv = $(`
            <div class="option-item">
                <div class="input-group">
                    <span class="input-group-text">${optionLetter}</span>
                    <input type="text" name="interactions[${interactionId}][options][]" class="form-control" placeholder="Enter option ${optionLetter}" required>
                    <button type="button" class="btn btn-outline-danger btn-remove-option">
                        <i class="fas fa-minus"></i>
                    </button>
                </div>
            </div>
        `);
        $optionsGrid.append($optionDiv);
        
        // Show all remove buttons
        $optionsGrid.find('.btn-remove-option').show();
        
        // Show add button if it was hidden (when exactly 4 options)
        if (optionCount === 4) {
            const $addButton = $(`#options-list-${interactionId}`).closest('.option-group').find('.btn-add-option');
            if ($addButton.length === 0) {
                const $buttonHtml = `<button type="button" class="btn btn-outline-primary btn-sm btn-add-option mt-2"><i class="fas fa-plus"></i> Add Option</button>`;
                $(`#options-list-${interactionId}`).after($buttonHtml);
            }
        }
        
        updateCorrectAnswerDropdown(interactionId);
    }
    
    function removeOption(button, interactionId) {
        const $optionDiv = $(button).closest('.option-item');
        const $optionsGrid = $(`#options-list-${interactionId} .options-grid`);
        
        if ($optionsGrid.children().length > 4) {
            $optionDiv.remove();
            
            // Update counter
            optionCounters[interactionId] = $optionsGrid.children().length;
            
            // Update option letters
            $optionsGrid.children().each(function(index) {
                const optionLetter = String.fromCharCode(65 + index);
                $(this).find('.input-group-text').text(optionLetter);
                $(this).find('input').attr('placeholder', `Enter option ${optionLetter}`);
            });
            
            // Hide remove button if only 4 options left
            if ($optionsGrid.children().length === 4) {
                $optionsGrid.find('.btn-remove-option').hide();
                // Remove add button
                const $addButton = $(`#options-list-${interactionId}`).closest('.option-group').find('.btn-add-option');
                if ($addButton.length > 0) {
                    $addButton.remove();
                }
            }
            
            updateCorrectAnswerDropdown(interactionId);
        } else {
            Swal.fire('Warning', 'You must have at least 4 options for multiple choice questions', 'warning');
        }
    }
    
    function updateCorrectAnswerDropdown(interactionId) {
        const $optionsGrid = $(`#options-list-${interactionId} .options-grid`);
        const $correctAnswerSelect = $(`#correct-answer-${interactionId}`);
        const selectedValue = $correctAnswerSelect.val();
        
        $correctAnswerSelect.empty().append('<option value="">Select correct answer</option>');
        
        $optionsGrid.children().each(function(index) {
            const optionNumber = index + 1;
            const optionLetter = String.fromCharCode(65 + index);
            const $option = $('<option></option>').attr('value', optionNumber).text(optionLetter);
            if (selectedValue == optionNumber) {
                $option.attr('selected', true);
            }
            $correctAnswerSelect.append($option);
        });
    }
    
    function removeInteraction(button) {
        const $row = $(button).closest('.cloneRow');
        if ($('.cloneRow').length > 1) {
            $row.remove();
        } else {
            Swal.fire('Warning', 'You must have at least one interaction point', 'warning');
        }
    }
    
    function convertToSeconds(timeStr) {
        if (!timeStr) return null;
        const trimmed = timeStr.trim();
        // Check if it's already a number
        if (/^\d+$/.test(trimmed)) {
            return parseInt(trimmed, 10);
        }
        const parts = trimmed.split(':').map(Number);
        if (parts.some(isNaN)) return null;
        if (parts.length === 2) return parts[0] * 60 + parts[1];
        if (parts.length === 3) return parts[0] * 3600 + parts[1] * 60 + parts[2];
        return null;
    }
</script>
@include('includes.footer')
@endsection