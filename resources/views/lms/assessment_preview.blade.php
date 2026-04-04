<!-- Assessment Preview Modal -->
<div class="modal fade" id="assessmentPreviewModal" tabindex="-1" role="dialog" aria-labelledby="assessmentPreviewModalLabel" aria-hidden="true" data-backdrop="static" data-keyboard="false">
    <div class="modal-dialog modal-xl modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <!-- Header -->
            <div>
                <button type="button" class="close text-primary" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>

            <!-- Modal Body -->
            <div class="modal-body">
                <!-- Simplified Question Generation Settings -->
                <div class="card mb-4">
                    <div class="card-header bg-light">
                        <h6 class="mb-0"><i class="mdi mdi-cog-outline mr-2"></i>Question Generation Settings</h6>
                    </div>
                    <div class="card-body">
                        <form id="mappingSettingsForm" method="POST" action="{{ route('assessment_question.store') }}">
                            @csrf
                            <input type="hidden" name="grade_id" id="grade_id" value="{{ request()->get('grade_id', $data['grade_id'] ?? old('grade_id', '')) }}">
                            <input type="hidden" name="standard_id" id="standard_id" value="{{ request()->get('standard_id', $data['standard_id'] ?? old('standard_id', '')) }}">
                            <input type="hidden" name="subject_id" id="subject_id" value="{{ request()->get('subject_id', $data['subject_id'] ?? old('subject_id', '')) }}">
                            <input type="hidden" name="chapter_id" id="chapter_id" value="{{ request()->get('chapter_id', $data['chapter_id'] ?? old('chapter_id', '')) }}">
                            <input type="hidden" name="topic_id" id="topic_id" value="{{ request()->get('topic_id', $data['topic_id'] ?? old('topic_id', '')) }}">

                            <!-- Simplified Form: Question Type + Total Questions -->
                            <div class="row mb-4">
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="question_type_id">Question Type <span class="text-danger">*</span></label>
                                        <select class="form-control" name="question_type_id" id="question_type_id" required>
                                            <option value="">Select Question Type</option>
                                            @if(isset($data['questiontype_data']))
                                                @foreach($data['questiontype_data'] as $value)
                                                    <option value="{{$value->id}}">{{ucwords($value->question_type)}}</option>
                                                @endforeach
                                            @endif
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="total_questions">Total Questions <span class="text-danger">*</span></label>
                                        <input type="number" class="form-control" name="total_questions" id="total_questions" min="1" max="50" value="2" required>
                                        <small class="text-muted">Auto-distributed by difficulty</small>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>&nbsp;</label>
                                        <button type="button" class="btn btn-info btn-block" id="previewPromptBtn">
                                            <i class="mdi mdi-pencil-outline mr-1"></i> Preview Prompt
                                        </button>
                                    </div>
                                </div>
                                <!-- <div class="col-md-3">
                                    <div class="form-group">
                                        <label>&nbsp;</label>
                                        <button type="button" class="btn btn-secondary btn-block" id="previewDistributionBtn">
                                            <i class="mdi mdi-chart-bar mr-1"></i> Preview Distribution
                                        </button>
                                    </div>
                                </div> -->
                            </div>

<!-- Prompt Preview Section (Editable) -->
<div id="promptPreviewSection" class="alert alert-warning d-none">
    <h6 class="alert-heading mb-0"><i class="mdi mdi-pencil-outline mr-1"></i>AI Prompt (Editable)</h6>
    <textarea class="form-control" id="aiPrompt" rows="8" placeholder="AI prompt will appear here..."></textarea>
    <small class="text-muted mt-1">You can edit the prompt before generating questions</small>
    <div class="d-flex justify-content-center mb-2">
        <button type="button" class="btn btn-sm btn-primary" id="generateFromPromptBtn">
            <i class="mdi mdi-robot mr-1"></i> Generate Questions (AI)
        </button>
    </div>
</div>

                            <!-- Distribution Preview Section -->
                            <div id="distributionPreviewSection" class="alert alert-info d-none">
                                <h6 class="alert-heading"><i class="mdi mdi-information-outline mr-1"></i>Question Distribution Preview</h6>
                                <div class="table-responsive mt-2">
                                    <table class="table table-sm table-bordered" id="distributionTable" style="filter:none !important">
                                        <thead class="thead-light">
                                            <tr>
                                                <th>Mapping Type</th>
                                                <th>Value</th>
                                                <th>Difficulty</th>
                                                <th>Questions</th>
                                                <th>Marks/Q</th>
                                                <th>Total Marks</th>
                                            </tr>
                                        </thead>
                                        <tbody id="distributionTableBody">
                                            <!-- Populated via AJAX -->
                                        </tbody>
                                        <tfoot>
                                            <tr class="font-weight-bold">
                                                <td colspan="3" class="text-right">Total:</td>
                                                <td id="totalQuestionsPreview">0</td>
                                                <td></td>
                                                <td id="totalMarksPreview">0</td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>

                            <input type="hidden" id="generated_questions_data" name="generated_questions" value="">
                            <input type="hidden" id="distribution_data" name="distribution" value="">
                        </form>
                    </div>
                </div>

                <!-- Generate Question Button -->
                <!--<div class="text-center mb-4">
                    <button type="button" class="btn btn-primary btn-lg" id="generateQuestionsBtn">
                        <i class="mdi mdi-robot mr-2"></i>Generate Question (AI)
                    </button>
                </div>-->

                <!-- Tabs Section -->
                <div class="card">
                    <div class="card-header bg-light">
                        <ul class="nav nav-tabs card-header-tabs" id="questionTabs" role="tablist">
                            <li class="nav-item">
                                <a class="nav-link active" id="questions-tab" data-toggle="tab" href="#questionsContent" role="tab">
                                    <i class="mdi mdi-help-circle-outline mr-1"></i> Questions
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" id="answerkey-tab" data-toggle="tab" href="#answerkeyContent" role="tab">
                                    <i class="mdi mdi-key-outline mr-1"></i> Answer Key
                                </a>
                            </li>
                        </ul>
                    </div>
                    <div class="card-body">
                        <div class="tab-content">
                            <!-- Questions Tab -->
                            <div class="tab-pane fade show active" id="questionsContent">
                                <div id="questionsContainer">
                                    <div class="text-center py-5 text-muted">
                                        <i class="mdi mdi-file-question-outline mdi-48px mb-3"></i>
                                        <p class="mb-0">No questions available.</p>
                                        <p class="small">Click 'Generate Question (AI)' to create questions.</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Answer Key Tab -->
                            <div class="tab-pane fade" id="answerkeyContent">
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

            <!-- Footer -->
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-success" id="saveQuestionsBtn">
                    <i class="mdi mdi-content-save mr-1"></i> Save Questions
                </button>
            </div>
        </div>
    </div>
</div>

<style>
    #assessmentPreviewModal .modal-xl { max-width: 90%; width: 90%; }
    #assessmentPreviewModal .modal-header { border-bottom: 2px solid #3498db; }
    #assessmentPreviewModal .modal-footer { border-top: 2px solid #e9ecef; }
    
    .question-item { margin-bottom: 15px; border: 1px solid #dee2e6; border-radius: 8px; overflow: hidden; }
    .question-header { padding: 15px; background: #f8f9fa; cursor: pointer; display: flex; justify-content: space-between; align-items: center; }
    .question-header:hover { background: #e9ecef; }
    .question-content { padding: 15px; display: none; }
    .question-content.show { display: block; }
    .option-item { padding: 10px; margin-bottom: 8px; border: 1px solid #dee2e6; border-radius: 4px; background: #fff; }
    .option-item.correct { border-color: #28a745; background: #d4edda; }
    
    .generate-loading { display: inline-block; width: 20px; height: 20px; border: 3px solid rgba(255,255,255,.3); border-radius: 50%; border-top-color: #fff; animation: spin 1s ease-in-out infinite; margin-right: 8px; }
    @keyframes spin { to { transform: rotate(360deg); } }
    
    .nav-tabs .nav-link { border: none; color: #6c757d; font-weight: 500; }
    .nav-tabs .nav-link.active { color: #3498db; border-bottom: 3px solid #3498db; background: transparent; }
    
    .difficulty-easy { background-color: #28a745; color: white; }
    .difficulty-medium { background-color: #ffc107; color: black; }
    .difficulty-hard { background-color: #dc3545; color: white; }
</style>

<script>
$(document).ready(function() {
    let currentDistribution = null;
    let currentPrompt = '';
    let generatedQuestions = [];

    // Open modal
    $('#openAssessmentPreview').on('click', (e) => {
        e.preventDefault();
        $('#assessmentPreviewModal').modal('show');
    });

    // Preview Prompt - Generate and show editable prompt
    $('#previewPromptBtn').on('click', function() {
        const questionTypeId = $('#question_type_id').val();
        const totalQuestions = $('#total_questions').val();
        
        if (!questionTypeId) {
            alert('Please select a question type first');
            return;
        }
        
        if (!totalQuestions || totalQuestions < 1 || totalQuestions > 100) {
            alert('Please enter valid number of questions (1-100)');
            return;
        }

        const $btn = $(this);
        const originalText = $btn.html();
        $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm mr-1"></span> Generating...');

        $.ajax({
            url: "{{ route('preview_distribution') }}",
            type: 'GET',
            data: {
                question_type_id: questionTypeId,
                total_questions: totalQuestions,
                standard_id: $('#standard_id').val(),
                subject_id: $('#subject_id').val(),
                chapter_id: $('#chapter_id').val(),
                topic_id: $('#topic_id').val(),
                generate_prompt: true
            },
            success: function(result) {
                if (result.status_code === 1) {
                    currentDistribution = result.distribution;
                    $('#distribution_data').val(JSON.stringify(result.distribution));
                    
                    // Show prompt in editable textarea
                    $('#aiPrompt').val(result.prompt || '');
                    currentPrompt = result.prompt || '';
                    $('#promptPreviewSection').removeClass('d-none');
                    
                    // Show distribution too
                    const tbody = $('#distributionTableBody');
                    tbody.empty();
                    result.distribution.forEach(function(item) {
                        const difficultyClass = 'difficulty-' + item.difficulty.toLowerCase();
                        tbody.append(`
                            <tr>
                                <td>${item.mapping_type_name}</td>
                                <td>${item.mapping_value_name}</td>
                                <td><span class="badge ${difficultyClass}">${item.difficulty}</span></td>
                                <td>${item.questions}</td>
                                <td>${item.marks}</td>
                                <td>${item.total_marks}</td>
                            </tr>
                        `);
                    });
                    $('#totalQuestionsPreview').text(result.total_questions);
                    $('#totalMarksPreview').text(result.total_marks);
                    $('#distributionPreviewSection').removeClass('d-none');
                } else {
                    alert(result.message || 'Error generating prompt');
                }
            },
            error: function(xhr) {
                console.error('Error:', xhr.responseText);
                alert('Error generating prompt');
            },
            complete: function() {
                $btn.prop('disabled', false).html(originalText);
            }
        });
    });

    // Generate Questions from editable prompt
    $('#generateFromPromptBtn').on('click', function() {
        const questionTypeId = $('#question_type_id').val();
        const totalQuestions = $('#total_questions').val();
        const editedPrompt = $('#aiPrompt').val();
        
        if (!questionTypeId) {
            alert('Please select a question type');
            return;
        }
        
        if (!editedPrompt) {
            alert('Please generate a prompt first');
            return;
        }

        const $btn = $(this);
        const originalText = $btn.html();
        $btn.prop('disabled', true).html('<span class="generate-loading"></span> Generating...');

        const standardId = $('#standard_id').val();
        const subjectId = $('#subject_id').val();
        const chapterId = $('#chapter_id').val();
        const topicId = $('#topic_id').val();

        $.ajax({
            url: "{{ route('lms_chat') }}",
            type: 'POST',
            data: {
                _token: "{{ csrf_token() }}",
                standard: standardId,
                subject_id: subjectId,
                chapter_id: chapterId,
                topic_id: topicId,
                question_type_id: questionTypeId,
                total_questions: totalQuestions,
                custom_prompt: editedPrompt,
                standard_id: standardId,
                subject_id: subjectId,
                chapter_id: chapterId
            },
            success: function(result) {
                console.log('API Response:', result);
                
                let questionsData = [];
                
                if (result.ai_response && Array.isArray(result.ai_response) && result.ai_response.length > 0) {
                    questionsData = result.ai_response;
                } else if (result.questions && Array.isArray(result.questions) && result.questions.length > 0) {
                    questionsData = result.questions;
                }
                
                const isMCQ = questionTypeId == 1;
                const distribution = result.distribution || currentDistribution;
                
                generatedQuestions = questionsData.length ? questionsData.map((q, idx) => ({
                    id: idx + 1,
                    question_title: q.question || q.question_title || '',
                    question: q.question || q.question_title || '',
                    question_type: q.question_type || (isMCQ ? 'MCQ' : ''),
                    difficulty: q.difficulty || 'Medium',
                    options: q.options || (isMCQ ? [
                        { text: "Sample Option 1", correct: true },
                        { text: "Sample Option 2", correct: false },
                        { text: "Sample Option 3", correct: false },
                        { text: "Sample Option 4", correct: false }
                    ] : []),
                    correct_answer: q.correct_answer || '',
                    explanation: q.explanation || '',
                    marks: q.marks || q.points || 1,
                    points: q.marks || q.points || 1,
                    mapping_type_id: q.mapping_type_id || null,
                    mapping_value_id: q.mapping_value_id || null,
                    mappings: q.mappings || []
                })) : [];

                // Apply distribution
                if (distribution && generatedQuestions.length > 0) {
                    let qIndex = 0;
                    distribution.forEach((distItem) => {
                        for (let i = 0; i < distItem.questions && qIndex < generatedQuestions.length; i++) {
                            generatedQuestions[qIndex].mapping_type_id = distItem.mapping_type_id;
                            generatedQuestions[qIndex].mapping_value_id = distItem.mapping_value_id;
                            generatedQuestions[qIndex].difficulty = distItem.difficulty;
                            generatedQuestions[qIndex].marks = distItem.marks;
                            generatedQuestions[qIndex].points = distItem.marks;
                            generatedQuestions[qIndex].mappings = [{
                                mapping_type: distItem.mapping_type_id,
                                mapping_value: distItem.mapping_value_id,
                                reason: distItem.mapping_value_name + ' - ' + distItem.difficulty
                            }];
                            qIndex++;
                        }
                    });
                }

                displayEditableQuestions(generatedQuestions);
                displayAnswerKey(generatedQuestions);
                $('#generated_questions_data').val(JSON.stringify(generatedQuestions));
                
                if (distribution) {
                    $('#distribution_data').val(JSON.stringify(distribution));
                }
                
                $btn.prop('disabled', false).html(originalText);
                
                if (result.total_marks) {
                    alert(`Generated ${generatedQuestions.length} questions with total marks: ${result.total_marks}`);
                }
            },
            error: function(xhr) {
                console.error('Error:', xhr.responseText);
                alert('Error generating questions');
                $btn.prop('disabled', false).html(originalText);
            }
        });
    });

    // Preview Distribution
    $('#previewDistributionBtn').on('click', function() {
        const questionTypeId = $('#question_type_id').val();
        const totalQuestions = $('#total_questions').val();
        
        if (!questionTypeId) {
            alert('Please select a question type first');
            return;
        }
        
        if (!totalQuestions || totalQuestions < 1 || totalQuestions > 100) {
            alert('Please enter valid number of questions (1-100)');
            return;
        }

        const $btn = $(this);
        const originalText = $btn.html();
        $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm mr-1"></span> Loading...');

        $.ajax({
            url: "{{ route('preview_distribution') }}",
            type: 'GET',
            data: {
                question_type_id: questionTypeId,
                total_questions: totalQuestions,
                standard_id: $('#standard_id').val(),
                subject_id: $('#subject_id').val(),
                chapter_id: $('#chapter_id').val(),
                topic_id: $('#topic_id').val()
            },
            success: function(result) {
                if (result.status_code === 1) {
                    currentDistribution = result.distribution;
                    $('#distribution_data').val(JSON.stringify(result.distribution));
                    
                    const tbody = $('#distributionTableBody');
                    tbody.empty();
                    
                    result.distribution.forEach(function(item) {
                        const difficultyClass = 'difficulty-' + item.difficulty.toLowerCase();
                        tbody.append(`
                            <tr>
                                <td>${item.mapping_type_name}</td>
                                <td>${item.mapping_value_name}</td>
                                <td><span class="badge ${difficultyClass}">${item.difficulty}</span></td>
                                <td>${item.questions}</td>
                                <td>${item.marks}</td>
                                <td>${item.total_marks}</td>
                            </tr>
                        `);
                    });
                    
                    $('#totalQuestionsPreview').text(result.total_questions);
                    $('#totalMarksPreview').text(result.total_marks);
                    
                    $('#distributionPreviewSection').removeClass('d-none');
                } else {
                    alert(result.message || 'Error loading distribution');
                }
            },
            error: function(xhr) {
                console.error('Error:', xhr.responseText);
                alert('Error loading distribution preview');
            },
            complete: function() {
                $btn.prop('disabled', false).html(originalText);
            }
        });
    });

    // Display editable questions with delete buttons
    function displayEditableQuestions(questions) {
        const container = $('#questionsContainer').empty();
        if (!questions.length) {
            return container.html('<div class="text-center py-5 text-muted"><i class="mdi mdi-file-question-outline mdi-48px mb-3"></i><p class="mb-0">No questions available.</p></div>');
        }

        questions.forEach((q, i) => {
            const qIndex = i; // Capture index for delete function
            let optionsHtml = '';
            if (q.options?.length) {
                optionsHtml = '<div class="question-options mt-2"><strong>Options:</strong>';
                q.options.forEach((opt, optIdx) => {
                    const isCorrect = opt.correct ? 'correct' : '';
                    optionsHtml += `<div class="option-item ${isCorrect}">
                        <div class="d-flex align-items-center">
                            <span class="mr-2">${String.fromCharCode(65 + optIdx)}.</span>
                            <input type="text" class="form-control form-control-sm option-input" 
                                data-option-index="${optIdx}" data-question-index="${i}" 
                                value="${opt.text}" style="flex:1;">
                            <input type="checkbox" class="ml-2 correct-checkbox" 
                                data-option-index="${optIdx}" data-question-index="${i}" 
                                ${opt.correct ? 'checked' : ''}>
                        </div>
                    </div>`;
                });
                optionsHtml += '</div>';
            } else if (q.correct_answer) {
                optionsHtml = `<div class="mt-2"><strong>Answer:</strong> 
                    <input type="text" class="form-control answer-input" data-question-index="${i}" value="${q.correct_answer}">
                </div>`;
            }

            const difficultyClass = 'difficulty-' + (q.difficulty || 'Medium').toLowerCase();
            
            const mappingInfo = q.mappings && q.mappings.length > 0 
                ? q.mappings.map((m, idx) => `<span class="d-block"><i class="mdi mdi-tag-outline"></i> Mapping: ${m.reason || 'Auto-assigned'}</span>`).join('')
                : '<span class="text-muted"><i class="mdi mdi-tag-outline"></i> Auto-assigned mapping</span>';

            container.append(`
                <div class="question-item" data-question-index="${i}">
                    <div class="question-header" onclick="toggleQuestion(${q.id})">
                        <div>
                            <strong>Q${i+1}.</strong> 
                            <input type="text" class="question-edit-input" data-question-index="${i}" 
                                value="${(q.question || '')}" style="border:none; background:transparent; width:60%;">
                            <span class="badge badge-info ml-2">${q.question_type || 'Question'}</span> 
                            <span class="badge ${difficultyClass}">${q.difficulty || 'Medium'}</span>
                            <span class="badge badge-warning ml-2">${q.marks || q.points || 1} mark</span>
                        </div>
                        <div>
                            <button type="button" class="btn btn-danger btn-sm delete-question-btn mr-2" data-question-index="${i}" title="Delete Question">
                                <i class="mdi mdi-delete"></i>
                            </button>
                            <i class="mdi mdi-chevron-down" id="icon-${q.id}"></i>
                        </div>
                    </div>
                    <div class="question-content" id="question-${q.id}">
                        <div class="mb-2"><strong>Question:</strong></div>
                        <textarea class="form-control mb-2 question-textarea" data-question-index="${i}" rows="2">${q.question || ''}</textarea>
                        ${optionsHtml}
                        <div class="mt-2"><strong>Explanation:</strong></div>
                        <textarea class="form-control explanation-input" data-question-index="${i}" rows="2">${q.explanation || ''}</textarea>
                        <div class="question-meta mt-2">
                            ${mappingInfo}
                            <span><i class="mdi mdi-star"></i> Points: 
                                <input type="number" class="marks-input" data-question-index="${i}" value="${q.points || q.marks || 1}" min="1" max="10" style="width:60px;">
                            </span>
                        </div>
                    </div>
                </div>`);
        });

        // Add event listeners for editing
        attachEditListeners();
    }

    // Attach edit event listeners
    function attachEditListeners() {
        // Delete question buttons
        $('.delete-question-btn').on('click', function(e) {
            e.stopPropagation();
            const index = $(this).data('question-index');
            if (confirm('Are you sure you want to delete this question?')) {
                generatedQuestions.splice(index, 1);
                // Re-index questions
                generatedQuestions = generatedQuestions.map((q, idx) => ({...q, id: idx + 1}));
                displayEditableQuestions(generatedQuestions);
                displayAnswerKey(generatedQuestions);
                $('#generated_questions_data').val(JSON.stringify(generatedQuestions));
            }
        });

        // Question text input change
        $('.question-edit-input').on('change', function() {
            const index = $(this).data('question-index');
            generatedQuestions[index].question = $(this).val();
            generatedQuestions[index].question_title = $(this).val();
            $('#generated_questions_data').val(JSON.stringify(generatedQuestions));
        });

        // Question textarea change
        $('.question-textarea').on('change', function() {
            const index = $(this).data('question-index');
            generatedQuestions[index].question = $(this).val();
            generatedQuestions[index].question_title = $(this).val();
            $('#generated_questions_data').val(JSON.stringify(generatedQuestions));
        });

        // Option text change
        $('.option-input').on('change', function() {
            const qIndex = $(this).data('question-index');
            const optIndex = $(this).data('option-index');
            generatedQuestions[qIndex].options[optIndex].text = $(this).val();
            $('#generated_questions_data').val(JSON.stringify(generatedQuestions));
        });

        // Correct checkbox change
        $('.correct-checkbox').on('change', function() {
            const qIndex = $(this).data('question-index');
            const optIndex = $(this).data('option-index');
            generatedQuestions[qIndex].options.forEach((opt, idx) => {
                opt.correct = (idx === optIndex) ? $(this).is(':checked') : false;
            });
            $('#generated_questions_data').val(JSON.stringify(generatedQuestions));
            displayEditableQuestions(generatedQuestions);
            displayAnswerKey(generatedQuestions);
        });

        // Answer input change
        $('.answer-input').on('change', function() {
            const index = $(this).data('question-index');
            generatedQuestions[index].correct_answer = $(this).val();
            $('#generated_questions_data').val(JSON.stringify(generatedQuestions));
        });

        // Explanation change
        $('.explanation-input').on('change', function() {
            const index = $(this).data('question-index');
            generatedQuestions[index].explanation = $(this).val();
            $('#generated_questions_data').val(JSON.stringify(generatedQuestions));
        });

        // Marks change
        $('.marks-input').on('change', function() {
            const index = $(this).data('question-index');
            generatedQuestions[index].marks = parseInt($(this).val()) || 1;
            generatedQuestions[index].points = parseInt($(this).val()) || 1;
            $('#generated_questions_data').val(JSON.stringify(generatedQuestions));
        });
    }

    // Display answer key
    function displayAnswerKey(questions) {
        const container = $('#answerkeyContainer').empty();
        if (!questions.length) {
            return container.html('<div class="text-center py-5 text-muted"><i class="mdi mdi-key-variant mdi-48px mb-3"></i><p class="mb-0">No answer key available.</p></div>');
        }

        let html = '<table class="table table-bordered"><thead><tr><th>Q.No.</th><th>Question</th><th>Difficulty</th><th>Marks</th><th>Correct Answer</th></tr></thead><tbody>';
        questions.forEach((q, i) => {
            const correct = q.options?.find(opt => opt.correct)?.text || q.correct_answer || 'N/A';
            const difficultyClass = 'difficulty-' + (q.difficulty || 'Medium').toLowerCase();
            html += `<tr>
                <td>${i+1}</td>
                <td>${(q.question || '').substring(0, 60)}...</td>
                <td><span class="badge ${difficultyClass}">${q.difficulty || 'Medium'}</span></td>
                <td>${q.marks || q.points || 1}</td>
                <td><span class="badge badge-success">${correct}</span></td>
            </tr>`;
        });
        container.html(html + '</tbody></table>');
    }

    // Preview Distribution
    $('#previewDistributionBtn').on('click', function() {
        const questionTypeId = $('#question_type_id').val();
        const totalQuestions = $('#total_questions').val();
        
        if (!questionTypeId) {
            alert('Please select a question type first');
            return;
        }
        
        if (!totalQuestions || totalQuestions < 1 || totalQuestions > 100) {
            alert('Please enter valid number of questions (1-100)');
            return;
        }

        const $btn = $(this);
        const originalText = $btn.html();
        $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm mr-1"></span> Loading...');

        $.ajax({
            url: "{{ route('preview_distribution') }}",
            type: 'GET',
            data: {
                question_type_id: questionTypeId,
                total_questions: totalQuestions,
                standard_id: $('#standard_id').val(),
                subject_id: $('#subject_id').val(),
                chapter_id: $('#chapter_id').val(),
                topic_id: $('#topic_id').val()
            },
            success: function(result) {
                if (result.status_code === 1) {
                    currentDistribution = result.distribution;
                    $('#distribution_data').val(JSON.stringify(result.distribution));
                    
                    // Populate table
                    const tbody = $('#distributionTableBody');
                    tbody.empty();
                    
                    result.distribution.forEach(function(item) {
                        const difficultyClass = 'difficulty-' + item.difficulty.toLowerCase();
                        tbody.append(`
                            <tr>
                                <td>${item.mapping_type_name}</td>
                                <td>${item.mapping_value_name}</td>
                                <td><span class="badge ${difficultyClass}">${item.difficulty}</span></td>
                                <td>${item.questions}</td>
                                <td>${item.marks}</td>
                                <td>${item.total_marks}</td>
                            </tr>
                        `);
                    });
                    
                    $('#totalQuestionsPreview').text(result.total_questions);
                    $('#totalMarksPreview').text(result.total_marks);
                    
                    $('#distributionPreviewSection').removeClass('d-none');
                } else {
                    alert(result.message || 'Error loading distribution');
                }
            },
            error: function(xhr) {
                console.error('Error:', xhr.responseText);
                alert('Error loading distribution preview');
            },
            complete: function() {
                $btn.prop('disabled', false).html(originalText);
            }
        });
    });

    // Generate Questions - Simplified Mode
    $('#generateQuestionsBtn').on('click', function() {
        const $btn = $(this);
        if ($btn.prop('disabled')) return;
        
        const questionTypeId = $('#question_type_id').val();
        const totalQuestions = $('#total_questions').val();
        
        if (!questionTypeId) {
            alert('Please select a question type');
            return;
        }
        
        if (!totalQuestions || totalQuestions < 1) {
            alert('Please enter valid number of questions');
            return;
        }

        const originalText = $btn.html();
        $btn.prop('disabled', true).html('<span class="generate-loading"></span> Generating...');

        const gradeId = $('#grade_id').val();
        const standardId = $('#standard_id').val();
        const subjectId = $('#subject_id').val();
        const chapterId = $('#chapter_id').val();
        const topicId = $('#topic_id').val();

        $.ajax({
            url: "{{ route('lms_chat') }}",
            type: 'POST',
            data: {
                _token: "{{ csrf_token() }}",
                standard: standardId,
                subject_id: subjectId,
                chapter_id: chapterId,
                topic_id: topicId,
                question_type_id: questionTypeId,
                total_questions: totalQuestions,
                standard_id: standardId,
                subject_id: subjectId,
                chapter_id: chapterId
            },
            success: function(result) {
                console.log('API Response:', result);
                
                // Get questions from ai_response or questions array
                let questionsData = [];
                
                if (result.ai_response && Array.isArray(result.ai_response) && result.ai_response.length > 0) {
                    questionsData = result.ai_response;
                } else if (result.questions && Array.isArray(result.questions) && result.questions.length > 0) {
                    questionsData = result.questions;
                }
                
                const isMCQ = questionTypeId == 1;
                
                // Use distribution from response or saved distribution
                const distribution = result.distribution || currentDistribution;
                
                // Format questions with distribution info
                const questions = questionsData.length ? questionsData.map((q, idx) => ({
                    id: idx + 1,
                    question_title: q.question || q.question_title || '',
                    question: q.question || q.question_title || '',
                    question_type: q.question_type || (isMCQ ? 'MCQ' : ''),
                    difficulty: q.difficulty || 'Medium',
                    options: q.options || (isMCQ ? [
                        { text: "Sample Option 1", correct: true },
                        { text: "Sample Option 2", correct: false },
                        { text: "Sample Option 3", correct: false },
                        { text: "Sample Option 4", correct: false }
                    ] : []),
                    correct_answer: q.correct_answer || '',
                    explanation: q.explanation || '',
                    marks: q.marks || q.points || 1,
                    points: q.marks || q.points || 1,
                    mapping_type_id: q.mapping_type_id || null,
                    mapping_value_id: q.mapping_value_id || null,
                    mappings: q.mappings || []
                })) : [{
                    id: 1,
                    question_title: 'Sample question - please regenerate',
                    question: 'Sample question - please regenerate',
                    question_type: 'MCQ',
                    difficulty: 'Medium',
                    options: isMCQ ? [
                        { text: "First option", correct: true },
                        { text: "Second option", correct: false },
                        { text: "Third option", correct: false },
                        { text: "Fourth option", correct: false }
                    ] : [],
                    correct_answer: 'First option',
                    explanation: 'Sample explanation',
                    marks: 1,
                    points: 1,
                    mappings: []
                }];

                // If we have distribution, apply it to questions
                if (distribution && questions.length > 0) {
                    let qIndex = 0;
                    distribution.forEach((distItem) => {
                        for (let i = 0; i < distItem.questions && qIndex < questions.length; i++) {
                            questions[qIndex].mapping_type_id = distItem.mapping_type_id;
                            questions[qIndex].mapping_value_id = distItem.mapping_value_id;
                            questions[qIndex].difficulty = distItem.difficulty;
                            questions[qIndex].marks = distItem.marks;
                            questions[qIndex].points = distItem.marks;
                            questions[qIndex].mappings = [{
                                mapping_type: distItem.mapping_type_id,
                                mapping_value: distItem.mapping_value_id,
                                reason: distItem.mapping_value_name + ' - ' + distItem.difficulty
                            }];
                            qIndex++;
                        }
                    });
                }

                displayQuestions(questions);
                displayAnswerKey(questions);
                $('#generated_questions_data').val(JSON.stringify(questions));
                
                if (distribution) {
                    $('#distribution_data').val(JSON.stringify(distribution));
                }
                
                $btn.prop('disabled', false).html(originalText);
                
                // Show total marks info
                if (result.total_marks) {
                    alert(`Generated ${result.total_questions} questions with total marks: ${result.total_marks}`);
                }
            },
            error: function(xhr) {
                console.error('Error:', xhr.responseText);
                alert('Error generating questions');
                $btn.prop('disabled', false).html(originalText);
            }
        });
    });

    // Display questions
    function displayQuestions(questions) {
        const container = $('#questionsContainer').empty();
        if (!questions.length) {
            return container.html('<div class="text-center py-5 text-muted"><i class="mdi mdi-file-question-outline mdi-48px mb-3"></i><p class="mb-0">No questions available.</p></div>');
        }

        questions.forEach((q, i) => {
            let optionsHtml = '';
            if (q.options?.length) {
                optionsHtml = '<div class="question-options"><strong>Options:</strong>';
                q.options.forEach(opt => {
                    optionsHtml += `<div class="option-item ${opt.correct ? 'correct' : ''}">${opt.text} ${opt.correct ? '<span class="badge badge-success ml-2">✓ Correct</span>' : ''}</div>`;
                });
                optionsHtml += '</div>';
            } else if (q.correct_answer) {
                optionsHtml = `<p><strong>Answer:</strong> ${q.correct_answer}</p>`;
            }

            // Get difficulty badge class
            const difficultyClass = 'difficulty-' + (q.difficulty || 'Medium').toLowerCase();
            
            const mappingInfo = q.mappings && q.mappings.length > 0 
                ? q.mappings.map((m, idx) => `<span class="d-block"><i class="mdi mdi-tag-outline"></i> Mapping: ${m.reason || 'Auto-assigned'}</span>`).join('')
                : '<span class="text-muted"><i class="mdi mdi-tag-outline"></i> Auto-assigned mapping</span>';

            container.append(`
                <div class="question-item">
                    <div class="question-header" onclick="toggleQuestion(${q.id})">
                        <div>
                            <strong>Q${i+1}.</strong> ${(q.question || '').substring(0, 80)}... 
                            <span class="badge badge-info ml-2">${q.question_type || 'Question'}</span> 
                            <span class="badge ${difficultyClass}">${q.difficulty || 'Medium'}</span>
                            <span class="badge badge-warning ml-2">${q.marks || q.points || 1} marks</span>
                        </div>
                        <i class="mdi mdi-chevron-down" id="icon-${q.id}"></i>
                    </div>
                    <div class="question-content" id="question-${q.id}">
                        <div class="question-text mb-3"><strong>Question:</strong> ${q.question || ''}</div>
                        ${optionsHtml}
                        ${q.explanation ? `<div class="alert alert-info mt-2"><strong>Explanation:</strong> ${q.explanation}</div>` : ''}
                        <div class="question-meta">
                            ${mappingInfo}
                            <span><i class="mdi mdi-star"></i> Points: ${q.points || q.marks || 1}</span>
                        </div>
                    </div>
                </div>`);
        });
    }

    // Display answer key
    function displayAnswerKey(questions) {
        const container = $('#answerkeyContainer').empty();
        if (!questions.length) {
            return container.html('<div class="text-center py-5 text-muted"><i class="mdi mdi-key-variant mdi-48px mb-3"></i><p class="mb-0">No answer key available.</p></div>');
        }

        let html = '<table class="table table-bordered" style="filter:none !important"><thead><tr><th>Q.No.</th><th>Question</th><th>Difficulty</th><th>Marks</th><th>Correct Answer</th></tr></thead><tbody>';
        questions.forEach((q, i) => {
            const correct = q.options?.find(opt => opt.correct)?.text || q.correct_answer || 'N/A';
            const difficultyClass = 'difficulty-' + (q.difficulty || 'Medium').toLowerCase();
            html += `<tr>
                <td>${i+1}</td>
                <td>${(q.question || '').substring(0, 60)}...</td>
                <td><span class="badge ${difficultyClass}">${q.difficulty || 'Medium'}</span></td>
                <td>${q.marks || q.points || 1}</td>
                <td>${correct}</td>
            </tr>`;//<span class="badge badge-success"></span>
        });
        container.html(html + '</tbody></table>');
    }

    // Toggle question
    window.toggleQuestion = (id) => {
        $(`#question-${id}`).toggleClass('show');
        $(`#icon-${id}`).toggleClass('mdi-chevron-down mdi-chevron-up');
    };

    // Save questions
    $('#saveQuestionsBtn').on('click', function() {
        const data = $('#generated_questions_data').val();
        if (!data) return alert('Please generate questions first');

        let parsedData;
        try {
            parsedData = JSON.parse(data);
            console.log('Saving data:', parsedData);
        } catch {
            return alert('Invalid question data');
        }

        const $btn = $(this).html('<span class="generate-loading"></span> Saving...').prop('disabled', true);
        
        $.ajax({
            url: "{{ route('assessment_question.store') }}",
            type: 'POST',
            data: {
                _token: "{{ csrf_token() }}",
                generated_questions: data,
                grade_id: $('#grade_id').val(),
                standard_id: $('#standard_id').val(),
                subject_id: $('#subject_id').val(),
                chapter_id: $('#chapter_id').val(),
                topic_id: $('#topic_id').val(),
                question_type_id: $('#question_type_id').val()
            },
            success: function(res) {
                console.log('Save response:', res);
                if (res.status_code === 1) {
                    alert('Questions saved successfully!');
                    $('#generated_questions_data').val('');
                    $('#distribution_data').val('');
                    $('#assessmentPreviewModal').modal('hide');
                    setTimeout(() => window.location.reload(), 500);
                } else {
                    alert(res.message || 'Error saving');
                    $btn.prop('disabled', false).html('<i class="mdi mdi-content-save mr-1"></i> Save Questions');
                }
            },
            error: function(xhr) {
                console.error('Save error:', xhr.responseText);
                alert('Error saving questions: ' + (xhr.responseJSON?.message || 'Unknown error'));
                $btn.prop('disabled', false).html('<i class="mdi mdi-content-save mr-1"></i> Save Questions');
            }
        });
    });
});
</script>