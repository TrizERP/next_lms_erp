<!-- Assessment Preview Modal -->
<div class="modal fade" id="assessmentPreviewModal" tabindex="-1" role="dialog" aria-labelledby="assessmentPreviewModalLabel" aria-hidden="true" data-backdrop="static" data-keyboard="false">
    <div class="modal-dialog modal-xl modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <!-- Header Section -->
            <div>
               
                <button type="button" class="close text-primary" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>

            <!-- Modal Body -->
            <div class="modal-body">
                <!-- Job Role Information -->
               

                <!-- Question Mapping Settings Section -->
                <div class="card mb-4">
                    <div class="card-header bg-light">
                        <h6 class="mb-0"><i class="mdi mdi-tune mr-2"></i>Question Mapping Settings</h6>
                    </div>
                    <div class="card-body">
                        <form id="mappingSettingsForm" method="POST" action="{{ route('assessment_question.store') }}">
                            @csrf
                            <input type="hidden" name="grade_id" id="grade_id" value="{{ $data['grade_id'] ?? '' }}">
                            <input type="hidden" name="standard_id" id="standard_id" value="{{ $data['standard_id'] ?? '' }}">
                            <input type="hidden" name="subject_id" id="subject_id" value="{{ $data['subject_id'] ?? '' }}">
                            <input type="hidden" name="chapter_id" id="chapter_id" value="{{ $data['chapter_id'] ?? '' }}">
                            <input type="hidden" name="topic_id" id="topic_id" value="{{ $data['topic_id'] ?? '' }}">
                            <div id="mappingRowsContainer">
                                <!-- Mapping Row 1 -->
                                <div class="mapping-row mb-3" data-row="1">
                                    <div class="row align-items-end">
                                        <div class="col-md-2">
                                            <div class="form-group mb-0">
                                                <label for="mapping_type_1">Mapping Type</label>
                                                <select class="form-control mapping-type load_map_value" name="mapping_type[]" id="mapping_type_1" data-row="1">
                                                    <option value="">Select Mapping Type</option>
                                                    @if(isset($data['lms_mapping_type']))
                                                        @foreach($data['lms_mapping_type'] as $key => $value)
                                                        <option value="{{$value->id}}">{{$value->name}}</option>
                                                        @endforeach
                                                    @endif
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group mb-0">
                                                <label for="mapping_value_1">Mapping Value</label>
                                                <select class="form-control mapping-value" name="mapping_value[]" id="mapping_value_1" data-row="1">
                                                    <option value="">Select Mapping Value</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group mb-0">
                                                <label for="reason_1">Reason</label>
                                                <textarea class="form-control" name="reasons[]" id="reason_1" rows="2" placeholder="Auto-filled based on selection"></textarea>
                                            </div>
                                        </div>
                                        <div class="col-md-2">
                                            <div class="form-group mb-0">
                                                <label for="questions_1">Questions</label>
                                                <input type="number" class="form-control" name="questions[]" id="questions_1"  min="1" max="100">
                                            </div>
                                        </div>
                                        <div class="col-md-1">
                                            <div class="form-group mb-0">
                                                <label for="marks_1">Marks</label>
                                                <input type="number" class="form-control" name="marks[]" id="marks_1" >
                                            </div>
                                        </div>
                                       
                                        
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Add Mapping Button -->
                            <div class="mt-2">
                                <button type="button" class="btn btn-success btn-sm" id="addMappingBtn">
                                    <i class="mdi mdi-plus"></i> Add Mapping
                                </button>
                               
                            </div>
                            <input type="hidden" id="generated_questions_data" name="generated_questions" value="">
                        </form>
                    </div>
                </div>

                <!-- Generate Question Button -->
                <div class="text-center mb-4">
                    <button type="button" class="btn btn-primary btn-lg" id="generateQuestionsBtn">
                        <i class="mdi mdi-robot mr-2"></i>Generate Question (AI)
                    </button>
                </div>

                <!-- Tabs Section -->
                <div class="card">
                    <div class="card-header bg-light">
                        <ul class="nav nav-tabs card-header-tabs" id="questionTabs" role="tablist">
                            <li class="nav-item">
                                <a class="nav-link active" id="questions-tab" data-toggle="tab" href="#questionsContent" role="tab" aria-controls="questionsContent" aria-selected="true">
                                    <i class="mdi mdi-help-circle-outline mr-1"></i> Questions
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" id="answerkey-tab" data-toggle="tab" href="#answerkeyContent" role="tab" aria-controls="answerkeyContent" aria-selected="false">
                                    <i class="mdi mdi-key-outline mr-1"></i> Answer Key
                                </a>
                            </li>
                        </ul>
                    </div>
                    <div class="card-body">
                        <div class="tab-content" id="questionTabsContent">
                            <!-- Questions Tab -->
                            <div class="tab-pane fade show active" id="questionsContent" role="tabpanel" aria-labelledby="questions-tab">
                                <div id="questionsContainer">
                                    <div class="text-center py-5 text-muted">
                                        <i class="mdi mdi-file-question-outline mdi-48px mb-3"></i>
                                        <p class="mb-0">No questions available.</p>
                                        <p class="small">Click 'Generate Question (AI)' to create questions.</p>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Answer Key Tab -->
                            <div class="tab-pane fade" id="answerkeyContent" role="tabpanel" aria-labelledby="answerkey-tab">
                                <div id="answerkeyContainer">
                                    <div class="text-center py-5 text-muted">
                                        <i class="mdi mdi-key-variant mdi-48px mb-3"></i>
                                        <p class="mb-0">No answer key available.</p>
                                        <p class="small">Generate questions to see the answer key.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer Navigation -->
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" disabled>
                    <i class="mdi mdi-arrow-left mr-1"></i> Previous
                </button>
                <button type="button" class="btn btn-primary" id="nextBtn">
                    Next <i class="mdi mdi-arrow-right ml-1"></i>
                </button>
            </div>
        </div>
    </div>
</div>

<style>
/* Modal Custom Styles */
#assessmentPreviewModal .modal-xl {
    max-width: 90%;
    width: 90%;
}

#assessmentPreviewModal .modal-header {
    border-bottom: 2px solid #3498db;
}

#assessmentPreviewModal .modal-footer {
    border-top: 2px solid #e9ecef;
}

#assessmentPreviewModal .card-header {
    border-bottom: 1px solid #e9ecef;
}

#assessmentPreviewModal .mapping-row {
    padding: 15px;
    background-color: #f8f9fa;
    border-radius: 8px;
    border: 1px solid #dee2e6;
}

#assessmentPreviewModal .remove-mapping:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

/* Question Display Styles */
.question-item {
    margin-bottom: 15px;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    overflow: hidden;
}

.question-header {
    padding: 15px;
    background-color: #f8f9fa;
    cursor: pointer;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.question-header:hover {
    background-color: #e9ecef;
}

.question-content {
    padding: 15px;
    display: none;
}

.question-content.show {
    display: block;
}

.question-options {
    margin-top: 15px;
}

.option-item {
    padding: 10px;
    margin-bottom: 8px;
    border: 1px solid #dee2e6;
    border-radius: 4px;
    background-color: #fff;
}

.option-item.correct {
    border-color: #28a745;
    background-color: #d4edda;
}

.question-meta {
    margin-top: 15px;
    padding-top: 15px;
    border-top: 1px solid #dee2e6;
    font-size: 0.875rem;
    color: #6c757d;
}

.question-meta span {
    margin-right: 20px;
}

/* Loading Spinner */
.generate-loading {
    display: inline-block;
    width: 20px;
    height: 20px;
    border: 3px solid rgba(255,255,255,.3);
    border-radius: 50%;
    border-top-color: #fff;
    animation: spin 1s ease-in-out infinite;
    margin-right: 8px;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

/* Tab Styles */
.nav-tabs .nav-link {
    border: none;
    color: #6c757d;
    font-weight: 500;
}

.nav-tabs .nav-link.active {
    color: #3498db;
    border-bottom: 3px solid #3498db;
    background-color: transparent;
}

.nav-tabs .nav-link:hover {
    border: none;
    border-bottom: 3px solid #dee2e6;
}
</style>

<script>
$(document).ready(function() {
    var mappingRowCount = 1;
    
    // Open modal on button click
    $('#openAssessmentPreview').on('click', function(e) {
        e.preventDefault();
        $('#assessmentPreviewModal').modal('show');
    });
    
    // Close modal
    $('#assessmentPreviewModal').on('hidden.bs.modal', function() {
        // Reset form if needed
    });
    
    // Add Mapping Row
    $('#addMappingBtn').on('click', function() {
        mappingRowCount++;
        
        var mappingTypeOptions = $('#mapping_type_1').html();
        
        var newRow = `
            <div class="mapping-row mb-3" data-row="${mappingRowCount}">
                <div class="row align-items-end">
                    <div class="col-md-2">
                        <div class="form-group mb-0">
                            <label for="mapping_type_${mappingRowCount}">Mapping Type</label>
                            <select class="form-control mapping-type" name="mapping_type[]" id="mapping_type_${mappingRowCount}" data-row="${mappingRowCount}">
                                ${mappingTypeOptions}
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group mb-0">
                            <label for="mapping_value_${mappingRowCount}">Mapping Value</label>
                            <select class="form-control mapping-value" name="mapping_value[]" id="mapping_value_${mappingRowCount}" data-row="${mappingRowCount}">
                                <option value="">Select</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group mb-0">
                            <label for="reason_${mappingRowCount}">Reason</label>
                            <textarea class="form-control" name="reasons[]" id="reason_${mappingRowCount}" rows="2"s placeholder="Auto-filled based on selection"></textarea>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group mb-0">
                            <label for="questions_${mappingRowCount}">Questions</label>
                            <input type="number" class="form-control" name="questions[]" id="questions_${mappingRowCount}" min="1" max="100">
                        </div>
                    </div>
                    <div class="col-md-1">
                        <div class="form-group mb-0">
                            <label for="marks_${mappingRowCount}">Marks</label>
                            <input type="number" class="form-control" name="marks[]" id="marks_${mappingRowCount}">
                        </div>
                    </div>
                    <div class="col-md-1">
                        <button type="button" class="btn btn-danger btn-sm remove-mapping" data-row="${mappingRowCount}">
                            <i class="mdi mdi-close"></i>
                        </button>
                    </div>
                </div>
            </div>
        `;
        
        $('#mappingRowsContainer').append(newRow);
        
        // Enable remove button for existing rows
        $('.remove-mapping').prop('disabled', false);
    });
    
    // Remove Mapping Row
    $(document).on('click', '.remove-mapping', function() {
        var rowId = $(this).data('row');
        $('.mapping-row[data-row="' + rowId + '"]').remove();
        
        // Disable remove button if only one row remains
        if ($('.mapping-row').length === 1) {
            $('.remove-mapping').prop('disabled', true);
        }
    });
    
    // Mapping Type Change - Load Mapping Values
    $(document).on('change', '.mapping-type', function() {
        var rowId = $(this).data('row');
        var mappingType = $(this).val();
        console.log('Mapping Type Changed:', mappingType, 'rowId:', rowId);
        
        if (mappingType) {
            var path = "{{ route('ajax_LMS_MappingValue') }}";
            console.log('AJAX URL:', path);
            $.ajax({
                url: path,
                data: 'mapping_type=' + mappingType,
                success: function(result) {
                    console.log('Mapping Values Result:', result);
                    var $mappingValueSelect = $('select[name="mapping_value[]"][data-row="' + rowId + '"]');
                    $mappingValueSelect.find('option').remove().end().append('<option value="">Select Mapping Value</option>');
                    
                    for (var i = 0; i < result.length; i++) {
                        $mappingValueSelect.append($("<option></option>").val(result[i]['id']).html(result[i]['name']));
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error loading mapping values:', error, xhr.responseText);
                }
            });
        } else {
            $('select[name="mapping_value[]"][data-row="' + rowId + '"]').find('option').remove().end().append('<option value="">Select Mapping Value</option>');
        }
    });
    
    // Mapping Value Change - Auto-fill Reason
    $(document).on('change', '.mapping-value', function() {
        var rowId = $(this).data('row');
        var mappingValue = $(this).find('option:selected').text();
        var mappingType = $('select[name="mapping_type[]"][data-row="' + rowId + '"]').find('option:selected').text();
        
        if (mappingValue && mappingType) {
            $('#reason_' + rowId).val(mappingType + ' - ' + mappingValue);
        } else {
            $('#reason_' + rowId).val('');
        }
    });
    
    // Generate Questions Button - Call AI based on mapping selections
    $('#generateQuestionsBtn').on('click', function() {
    var $btn = $(this);
    var originalText = $btn.html();
    
    // Show loading state
    $btn.prop('disabled', true);
    $btn.html('<span class="generate-loading"></span> Generating...');
    
    // Collect mapping data
    var mappings = [];
    $('.mapping-row').each(function() {
        var rowId = $(this).data('row');
        mappings.push({
            mapping_type: $('#mapping_type_' + rowId).val(),
            mapping_value: $('#mapping_value_' + rowId).val(),
            reason: $('#reason_' + rowId).val(),
            questions: $('#questions_' + rowId).val(),
            marks: $('#marks_' + rowId).val()
        });
    });
    
    // Get context data
    var standard = $('#standard_id').val() || 'standard';
    var subject = $('#subject_id').val() || 'subject';
    var chapter = $('#chapter_id').val() || 'chapter';
    var topic = $('#topic_id').val() || '';
    
    // Build prompt
    var questionCount = mappings[0]?.questions || 1;
    var prompt = "get " + questionCount + " question for standard '" + standard + "' and subject '" + subject + "' and chapter '" + chapter + "'";
    
    if (topic) {
        prompt += " and for topic '" + topic + "'";
    }
    
    // Call the AI endpoint
    $.ajax({
        url: "{{ route('chat') }}",
        type: 'POST',
        data: {
            _token: "{{ csrf_token() }}",
            standard: standard,
            subject_id: subject,
            chapter_id: chapter,
            topic_id: topic,
            question_prompt: prompt,
            search: 'question',
            mappings: mappings
        },
        success: function(result) {
            console.log('AI Response:', result);
            
            // Handle the response based on your API structure
            var questionText = '';
            
            if (Array.isArray(result)) {
                questionText = result[0];
            } else if (result.questions && Array.isArray(result.questions)) {
                questionText = result.questions[0];
            } else if (typeof result === 'string') {
                questionText = result;
            } else {
                questionText = JSON.stringify(result);
            }
            
            // Auto map depth/bloom/learning
            check_input_from_ai(questionText);
            
            // Display question
            var generatedQuestions = [{
                id: 1,
                question: questionText,
                options: [
                    { text: "Option A", correct: true },
                    { text: "Option B", correct: false },
                    { text: "Option C", correct: false },
                    { text: "Option D", correct: false }
                ],
                question_title: questionText,
                mappings: mappings,
                points: mappings[0]?.marks || 1
            }];
            
            displayQuestions(generatedQuestions);
            displayAnswerKey(generatedQuestions);
            $('#generated_questions_data').val(JSON.stringify(generatedQuestions));
            
            $btn.prop('disabled', false).html(originalText);
        },
        error: function(xhr, status, error) {
            console.error('Error generating questions:', error);
            console.error('Response:', xhr.responseText);
            alert('Error generating questions. Please try again.');
            $btn.prop('disabled', false).html(originalText);
        }
    });
});
    
    // Display Questions Function
    function displayQuestions(questions) {
        var container = $('#questionsContainer');
        container.empty();
        
        if (questions.length === 0) {
            container.html(`
                <div class="text-center py-5 text-muted">
                    <i class="mdi mdi-file-question-outline mdi-48px mb-3"></i>
                    <p class="mb-0">No questions available.</p>
                    <p class="small">Click 'Generate Question (AI)' to create questions.</p>
                </div>
            `);
            return;
        }
        
        questions.forEach(function(q, index) {
            var optionsHtml = '';
            q.options.forEach(function(opt, optIndex) {
                var optionLetter = String.fromCharCode(65 + optIndex);
                optionsHtml += `
                    <div class="option-item">
                        <strong>${optionLetter}.</strong> ${opt.text}
                        ${opt.correct ? '<span class="badge badge-success ml-2">Correct</span>' : ''}
                    </div>
                `;
            });
            
            var questionHtml = `
                <div class="question-item">
                    <div class="question-header" onclick="toggleQuestion(${q.id})">
                        <div>
                            <strong>Q${index + 1}.</strong> ${q.question.substring(0, 80)}${q.question.length > 80 ? '...' : ''}
                        </div>
                        <i class="mdi mdi-chevron-down" id="icon-${q.id}"></i>
                    </div>
                    <div class="question-content" id="question-${q.id}">
                        <div class="question-text mb-3">
                            <strong>Question:</strong> ${q.question}
                        </div>
                        <div class="question-options">
                            <strong>Options:</strong>
                            ${optionsHtml}
                        </div>
                        <div class="question-meta">
                            <span><i class="mdi mdi-tag-outline"></i> Mapping Type: ${q.mapping_type}</span>
                            <span><i class="mdi mdi-tag"></i> Mapping Value: ${q.mapping_value}</span>
                            <span><i class="mdi mdi-text-box-outline"></i> Reason: ${q.reason}</span>
                            <span><i class="mdi mdi-folder-outline"></i> Domain: ${q.domain_category}</span>
                        </div>
                    </div>
                </div>
            `;
            
            container.append(questionHtml);
        });
    }
    
    // Display Answer Key Function
    function displayAnswerKey(questions) {
        var container = $('#answerkeyContainer');
        container.empty();
        
        if (questions.length === 0) {
            container.html(`
                <div class="text-center py-5 text-muted">
                    <i class="mdi mdi-key-variant mdi-48px mb-3"></i>
                    <p class="mb-0">No answer key available.</p>
                    <p class="small">Generate questions to see the answer key.</p>
                </div>
            `);
            return;
        }
        
        var answerKeyHtml = '<table class="table table-bordered"><thead><tr><th>Q.No.</th><th>Question</th><th>Correct Answer</th></tr></thead><tbody>';
        
        questions.forEach(function(q, index) {
            var correctOption = q.options.find(opt => opt.correct);
            answerKeyHtml += `
                <tr>
                    <td>${index + 1}</td>
                    <td>${q.question.substring(0, 60)}${q.question.length > 60 ? '...' : ''}</td>
                    <td><span class="badge badge-success">${correctOption ? correctOption.text : 'N/A'}</span></td>
                </tr>
            `;
        });
        
        answerKeyHtml += '</tbody></table>';
        container.html(answerKeyHtml);
    }
    
    // Toggle Question Collapse
    window.toggleQuestion = function(questionId) {
        $('#question-' + questionId).toggleClass('show');
        $('#icon-' + questionId).toggleClass('mdi-chevron-down mdi-chevron-up');
    };
    
    // Save Questions Button - Submit to controller
    $('#mappingSettingsForm').on('submit', function(e) {
        e.preventDefault();
        
        var generatedData = $('#generated_questions_data').val();
        if (!generatedData) {
            alert('Please generate questions first before saving.');
            return false;
        }
        
        var $btn = $('#saveQuestionsBtn');
        var originalText = $btn.html();
        
        $btn.prop('disabled', true);
        $btn.html('<span class="generate-loading"></span> Saving...');
        
        var formData = $(this).serialize();
        
        $.ajax({
            url: $(this).attr('action'),
            type: 'POST',
            data: formData,
            success: function(result) {
                console.log('Save result:', result);
                alert('Questions saved successfully!');
                $btn.prop('disabled', false);
                $btn.html(originalText);
                
                // Reset the form
                $('#generated_questions_data').val('');
                $('#questionsContainer').html(`
                    <div class="text-center py-5 text-muted">
                        <i class="mdi mdi-file-question-outline mdi-48px mb-3"></i>
                        <p class="mb-0">No questions available.</p>
                        <p class="small">Click 'Generate Question (AI)' to create questions.</p>
                    </div>
                `);
                $('#answerkeyContainer').html(`
                    <div class="text-center py-5 text-muted">
                        <i class="mdi mdi-key-variant mdi-48px mb-3"></i>
                        <p class="mb-0">No answer key available.</p>
                        <p class="small">Generate questions to see the answer key.</p>
                    </div>
                `);
            },
            error: function(xhr, status, error) {
                console.error('Error saving questions:', error);
                alert('Error saving questions. Please try again.');
                $btn.prop('disabled', false);
                $btn.html(originalText);
            }
        });
    });
});
</script>
<script>
/* ---------- AI AUTO MAPPING HELPERS ---------- */

// Select mapping value by text
function selectMappingValue(rowId, valueText) {
    $('select[name="mapping_value[]"][data-row="' + rowId + '"] option').each(function () {
        if ($(this).text().trim() === valueText.trim()) {
            $(this).prop('selected', true);
        }
    });
}

// Ensure mapping row exists
function ensureMappingRow(rowId) {
    if ($('.mapping-row[data-row="' + rowId + '"]').length === 0) {
        $('#addMappingBtn').click();
    }
}

// AI analysis → auto fill mapping
function check_input_from_ai(questionText) {

    var standard = $('#standard_id').val();

    var data = {
        question: questionText,
        standard: standard,
        type_depth: 9,
        type_bloom: 82,
        type_learning: "learn"
    };

    var path = "{{ route('chat') }}";

    $.ajax({
        url: path,
        data: data,
        success: function (result) {

            let parsed;
            try {
                parsed = JSON.parse(result);
            } catch (e) {
                parsed = result;
            }

            let depthVal, bloomVal, reasonDepth, reasonBloom, learning;

            if (Array.isArray(parsed)) {
                depthVal     = parsed[0]?.question_depth;
                bloomVal     = parsed[0]?.question_bloom;
                reasonDepth  = parsed[0]?.reason_depth;
                reasonBloom  = parsed[0]?.reason_bloom;
                learning     = parsed[0]?.question_learning;
            } else {
                depthVal     = parsed.question_depth;
                bloomVal     = parsed.question_bloom;
                reasonDepth  = parsed.reason_depth;
                reasonBloom  = parsed.reason_bloom;
                learning     = parsed.question_learning;
            }

            /* ---------- ROW 1 : DEPTH ---------- */
            $('#mapping_type_1').val(9).trigger('change');
            setTimeout(function () {
                selectMappingValue(1, depthVal);
                $('#reason_1').val(reasonDepth);
            }, 400);

            /* ---------- ROW 2 : BLOOM ---------- */
            ensureMappingRow(2);
            $('#mapping_type_2').val(82).trigger('change');
            setTimeout(function () {
                selectMappingValue(2, bloomVal);
                $('#reason_2').val(reasonBloom);
            }, 600);

            /* ---------- LEARNING OUTCOME ---------- */
            $('textarea[name="learning_outcome"]').val(learning);
        }
    });
}
</script>
