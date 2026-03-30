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
                        <i class="fas fa-robot"></i>
                    </div>
                    <div class="header-text">
                        <h1>Create Interactive Video</h1>
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
            <form action="{{route('h5p_interactive_video.store')}}" method="post" enctype="multipart/form-data" id="videoForm">
                @csrf
                <input type="hidden" name="chapter_id" value="{{$data['chapter_id']}}">
                <input type="hidden" name="subject_id" value="{{$data['subject_id']}}">
                <input type="hidden" name="standard_id" value="{{$data['standard_id']}}">
                <div class="card">
                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label for="title">Title</label>
                            <input type="text" class="form-control" id="title" name="title" required>
                        </div>
                        <div class="col-md-6 form-group">
                            <label for="video_path">Upload Video</label>
                            <input type="file" name="video_path" id="videoInput" class="form-control" accept="video/mp4,video/mov,video/avi,video/mkv" required>
                            <small class="text-muted">Supported formats: MP4, MOV, AVI, MKV (Max size: 500MB)</small>
                        </div>
                        <div class="col-md-12 video-preview-container">
                            <video id="video-preview" controls style="width: 100%; max-height: 400px; display: none;" class="mt-4"></video>
                        </div>
                    </div>
                </div>
                <div class="card mt-4">
                    <h4 class="mb-3"><i class="fas fa-comment-dots me-2"></i>Interactive Points</h4>
                    <div id="interactions-container">
                        <div class="row mb-2 cloneRow" style="border-bottom: 1px solid #ddd;" data-interaction-id="0">
                            <div class="col-md-3">
                                <label class="form-label required-field">Time (mm:ss or seconds)</label>
                                <input type="text" name="interactions[0][time]" class="form-control time-input" required placeholder="e.g., 30, 11:50, 1:30:00">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label required-field">Type</label>
                                <select name="interactions[0][interaction_type]" class="form-control interaction-type" required>
                                    <option value="multiple_choice">Multiple Choice</option>
                                    <option value="true_false">True/False</option>
                                    <option value="text_input">Text Input</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label required-field">Question</label>
                                <input type="text" name="interactions[0][question]" class="form-control" required placeholder="Enter your question">
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
                                <div class="options-container mt-3" id="options-container-0"></div>
                            </div>
                            <hr>
                        </div>
                    </div>
                </div>
                <div class="text-center">
                    <input type="submit" value="Submit" class="btn btn-success">
                </div>
            </form>
        </div>

    </div>
</div>

@include('includes.lmsfooterJs')
<script>
    let interactionCount = 1;
    let optionCounters = {};

    $(document).ready(function() {
        const $videoPreview = $('#video-preview');
        const $videoInput = $('#videoInput');

        $videoPreview.hide();

        // Initialize first interaction with default options (Multiple Choice)
        initializeInteractionOptions(0, 'multiple_choice');

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
                $videoPreview.attr('src', blobURL).show();

                $videoPreview.on('loadedmetadata', function() {
                    console.log('Video loaded, duration:', this.duration);
                });

                $videoPreview.on('error', function() {
                    alert('Failed to load video. Please try another file.');
                    $videoPreview.hide();
                    URL.revokeObjectURL(blobURL);
                });

                $videoPreview.on('loadeddata', function() {
                    URL.revokeObjectURL(blobURL);
                });
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

    function initializeInteractionOptions(interactionId, type) {
        optionCounters[interactionId] = 4; // Start with 4 options for multiple choice
        const $container = $(`#options-container-${interactionId}`);
        
        if (type === 'multiple_choice') {
            renderMultipleChoice($container, interactionId);
        } else if (type === 'true_false') {
            renderTrueFalse($container, interactionId);
        } else {
            renderTextInput($container, interactionId);
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
        optionCounters[newId] = 4; // Default to 4 options for multiple choice
        if (selectedType === 'multiple_choice') {
            renderMultipleChoice($optionsContainer, newId);
        } else if (selectedType === 'true_false') {
            renderTrueFalse($optionsContainer, newId);
        } else {
            renderTextInput($optionsContainer, newId);
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
            renderMultipleChoice($optionsContainer, interactionId);
        } else if (value === 'true_false') {
            renderTrueFalse($optionsContainer, interactionId);
        } else {
            renderTextInput($optionsContainer, interactionId);
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

    function renderMultipleChoice($container, interactionId) {
        if (!optionCounters[interactionId]) optionCounters[interactionId] = 4;
        
        const optionCount = optionCounters[interactionId];
        
        // Build options HTML with grid layout for 4 options (2x2)
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
            optionsHtml += `
                <div class="option-item">
                    <div class="input-group">
                        <span class="input-group-text">${optionLetter}</span>
                        <input type="text" name="interactions[${interactionId}][options][]" class="form-control" placeholder="Enter option ${optionLetter}" required>
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
        
        // Build correct answer dropdown options with option letters as values and option text as display
        for (let i = 1; i <= optionCount; i++) {
            const optionLetter = String.fromCharCode(64 + i);
            optionsHtml += `<option value="${i}">${optionLetter}</option>`;
        }
        
        optionsHtml += `
                </select>
            </div>
        `;
        $container.html(optionsHtml);
    }

    function renderTrueFalse($container, interactionId) {
        const html = `
            <div class="option-group mb-3">
                <label class="form-label required-field">Correct Answer</label>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="interactions[${interactionId}][correct_answer]" value="1" id="true-${interactionId}" required>
                    <label class="form-check-label" for="true-${interactionId}">True</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="interactions[${interactionId}][correct_answer]" value="2" id="false-${interactionId}" required>
                    <label class="form-check-label" for="false-${interactionId}">False</label>
                </div>
            </div>
        `;
        $container.html(html);
    }

    function renderTextInput($container, interactionId) {
        const html = `
            <div class="option-group mb-3">
                <label class="form-label">Correct Answer (Optional)</label>
                <input type="text" name="interactions[${interactionId}][correct_answer]" class="form-control" placeholder="Enter expected answer (optional)">
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
        
        // Show all remove buttons (except first 4)
        $optionsGrid.find('.btn-remove-option').show();
        
        // Show add button if it was hidden
        const $addButton = $(`#options-list-${interactionId}`).closest('.option-group').find('.btn-add-option');
        if ($addButton.length === 0) {
            const $buttonHtml = `<button type="button" class="btn btn-outline-primary btn-sm btn-add-option mt-2"><i class="fas fa-plus"></i> Add Option</button>`;
            $(`#options-list-${interactionId}`).after($buttonHtml);
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
                $(this).find('input').attr('name', `interactions[${interactionId}][options][]`);
            });
            
            // Hide remove button if only 4 options left
            if ($optionsGrid.children().length === 4) {
                $optionsGrid.find('.btn-remove-option').hide();
                // Remove add button if needed
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