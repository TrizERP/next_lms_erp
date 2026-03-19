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
                <!-- Question Mapping Settings -->
                <div class="card mb-4">
                    <div class="card-header bg-light">
                        <h6 class="mb-0"><i class="mdi mdi-tune mr-2"></i>Question Mapping Settings</h6>
                    </div>
                    <div class="card-body">
                        <form id="mappingSettingsForm" method="POST" action="{{ route('assessment_question.store') }}">
                            @csrf
                            <input type="hidden" name="grade_id" id="grade_id" value="{{ request()->get('grade_id', $data['grade_id'] ?? old('grade_id', '')) }}">
                            <input type="hidden" name="standard_id" id="standard_id" value="{{ request()->get('standard_id', $data['standard_id'] ?? old('standard_id', '')) }}">
                            <input type="hidden" name="subject_id" id="subject_id" value="{{ request()->get('subject_id', $data['subject_id'] ?? old('subject_id', '')) }}">
                            <input type="hidden" name="chapter_id" id="chapter_id" value="{{ request()->get('chapter_id', $data['chapter_id'] ?? old('chapter_id', '')) }}">
                            <input type="hidden" name="topic_id" id="topic_id" value="{{ request()->get('topic_id', $data['topic_id'] ?? old('topic_id', '')) }}">

                            <!-- Question Type -->
                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <div class="form-group mb-0">
                                        <label for="question_type_id">Question Type</label>
                                        <select class="form-control" name="question_type_id" id="question_type_id">
                                            <option value="">Select Question Type</option>
                                            @if(isset($data['questiontype_data']))
                                                @foreach($data['questiontype_data'] as $value)
                                                    <option value="{{$value->id}}">{{ucwords($value->question_type)}}</option>
                                                @endforeach
                                            @endif
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div id="mappingRowsContainer">
                                <!-- Mapping Row 1 -->
                                <div class="mapping-row mb-3" data-row="1">
                                    <div class="row align-items-end">
                                        <div class="col-md-2">
                                            <div class="form-group mb-0">
                                                <label for="mapping_type_1">Mapping Type</label>
                                                <select class="form-control mapping-type" name="mapping_type[]" id="mapping_type_1" data-row="1">
                                                    <option value="">Select Mapping Type</option>
                                                    @if(isset($data['lms_mapping_type']))
                                                        @foreach($data['lms_mapping_type'] as $value)
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
                                                <input type="number" class="form-control" name="questions[]" id="questions_1" min="1" max="100" value="1">
                                            </div>
                                        </div>
                                        <div class="col-md-2">
                                            <div class="form-group mb-0">
                                                <label for="marks_1">Marks</label>
                                                <input type="number" class="form-control" name="marks[]" id="marks_1" value="1">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Add Mapping Button - Commented as not required -->
                            <!-- <div class="mt-2">
                                <button type="button" class="btn btn-success btn-sm" id="addMappingBtn">
                                    <i class="mdi mdi-plus"></i> Add Mapping
                                </button>
                            </div> -->
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
    #assessmentPreviewModal .mapping-row { padding: 15px; background: #f8f9fa; border-radius: 8px; border: 1px solid #dee2e6; }
    
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
</style>

<script>
$(document).ready(function() {
    let mappingRowCount = 1;

    // Open modal
    $('#openAssessmentPreview').on('click', (e) => {
        e.preventDefault();
        $('#assessmentPreviewModal').modal('show');
    });

    // Add mapping row (if needed)
    $('#addMappingBtn').on('click', function() {
        mappingRowCount++;
        const options = $('#mapping_type_1').html();
        const newRow = `
            <div class="mapping-row mb-3" data-row="${mappingRowCount}">
                <div class="row align-items-end">
                    <div class="col-md-2">
                        <div class="form-group mb-0">
                            <label for="mapping_type_${mappingRowCount}">Mapping Type</label>
                            <select class="form-control mapping-type" name="mapping_type[]" id="mapping_type_${mappingRowCount}" data-row="${mappingRowCount}">${options}</select>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group mb-0">
                            <label for="mapping_value_${mappingRowCount}">Mapping Value</label>
                            <select class="form-control mapping-value" name="mapping_value[]" id="mapping_value_${mappingRowCount}" data-row="${mappingRowCount}"><option value="">Select</option></select>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group mb-0">
                            <label for="reason_${mappingRowCount}">Reason</label>
                            <textarea class="form-control" name="reasons[]" id="reason_${mappingRowCount}" rows="2" placeholder="Auto-filled"></textarea>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group mb-0">
                            <label for="questions_${mappingRowCount}">Questions</label>
                            <input type="number" class="form-control" name="questions[]" id="questions_${mappingRowCount}" min="1" max="100" value="1">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group mb-0">
                            <label for="marks_${mappingRowCount}">Marks</label>
                            <input type="number" class="form-control" name="marks[]" id="marks_${mappingRowCount}" value="1">
                        </div>
                    </div>
                    <div class="col-md-1">
                        <button type="button" class="btn btn-danger btn-sm remove-mapping" data-row="${mappingRowCount}"><i class="mdi mdi-close"></i></button>
                    </div>
                </div>
            </div>`;
        $('#mappingRowsContainer').append(newRow);
        $('.remove-mapping').prop('disabled', false);
    });

    // Remove mapping row
    $(document).on('click', '.remove-mapping', function() {
        $(`.mapping-row[data-row="${$(this).data('row')}"]`).remove();
        if ($('.mapping-row').length === 1) $('.remove-mapping').prop('disabled', true);
    });

    // Load mapping values
    $(document).on('change', '.mapping-type', function() {
        const rowId = $(this).data('row');
        const val = $(this).val();
        if (val) {
            $.get("{{ route('ajax_LMS_MappingValue') }}", { mapping_type: val }, (result) => {
                const $select = $(`select[name="mapping_value[]"][data-row="${rowId}"]`);
                $select.html('<option value="">Select Mapping Value</option>');
                result.forEach(item => $select.append(`<option value="${item.id}">${item.name}</option>`));
            });
        } else {
            $(`select[name="mapping_value[]"][data-row="${rowId}"]`).html('<option value="">Select Mapping Value</option>');
        }
    });

    // Auto-fill reason
    $(document).on('change', '.mapping-value', function() {
        const rowId = $(this).data('row');
        const mappingType = $(`select[name="mapping_type[]"][data-row="${rowId}"] option:selected`).text();
        const mappingValue = $(this).find('option:selected').text();
        $(`#reason_${rowId}`).val(mappingType && mappingValue ? `${mappingType} - ${mappingValue}` : '');
    });

    // Generate Questions
    $('#generateQuestionsBtn').on('click', function() {
        const $btn = $(this);
        if ($btn.prop('disabled')) return;
        
        const originalText = $btn.html();
        $btn.prop('disabled', true).html('<span class="generate-loading"></span> Generating...');

        // Collect mappings with marks
        const mappings = [];
        $('.mapping-row').each(function() {
            const rowId = $(this).data('row');
            const mappingType = $(`#mapping_type_${rowId}`).val();
            const mappingValue = $(`#mapping_value_${rowId}`).val();
            if (mappingType && mappingValue) {
                mappings.push({
                    mapping_type: mappingType,
                    mapping_value: mappingValue,
                    type_text: $(`#mapping_type_${rowId} option:selected`).text(),
                    value_text: $(`#mapping_value_${rowId} option:selected`).text(),
                    reason: $(`#reason_${rowId}`).val() || '',
                    questions: $(`#questions_${rowId}`).val() || 1,
                    marks: $(`#marks_${rowId}`).val() || 1
                });
            }
        });

        if (!mappings.length) {
            alert('Please select mapping type and value');
            $btn.prop('disabled', false).html(originalText);
            return;
        }

        // Build enhanced prompt with mappings and marks
        const gradeId = $('#grade_id').val();
        const standardId = $('#standard_id').val();
        const subjectId = $('#subject_id').val();
        const chapterId = $('#chapter_id').val();
        const topicId = $('#topic_id').val();
        const questionTypeId = $('#question_type_id').val();
        const isMCQ = questionTypeId == 1;

        let prompt = `Generate ${mappings[0].questions} ${isMCQ ? 'MCQ' : ''} questions for:\n`;
        prompt += `- Standard: ${standardId || 'standard'}\n- Subject: ${subjectId || 'subject'}\n- Chapter: ${chapterId || 'chapter'}\n`;
        if (topicId) prompt += `- Topic: ${topicId}\n`;
        prompt += `- Each question carries ${mappings[0].marks} marks\n`;
        prompt += `- Mappings:\n`;
        mappings.forEach((m, i) => {
            prompt += `  ${i+1}. ${m.type_text}: ${m.value_text} (${m.reason})\n`;
        });
        if (isMCQ) prompt += `- Format: Provide 4 options without A, B, C, D prefixes. Just the option text. Mark correct option with "correct: true".\n`;

        $.ajax({
            url: "{{ route('lms_chat') }}",
            type: 'POST',
            data: {
                _token: "{{ csrf_token() }}",
                standard: standardId,
                subject_id: subjectId,
                chapter_id: chapterId,
                topic_id: topicId,
                question_prompt: prompt,
                search: 'question',
                mappings: mappings,
                question_type_id: questionTypeId
            },
            success: (result) => {
                console.log('API Response:', result);
                
                // Get questions from ai_response first, if not available then from questions array
                let questionsData = [];
                
                if (result.ai_response && Array.isArray(result.ai_response) && result.ai_response.length > 0) {
                    questionsData = result.ai_response;
                } else if (result.questions && Array.isArray(result.questions) && result.questions.length > 0) {
                    questionsData = result.questions;
                }
                
                // Format the questions for display - MAKE SURE TO USE question_title field
                const questions = questionsData.length ? questionsData.map((q, idx) => ({
                    id: idx + 1,
                    question_title: q.question || q.question_title || '', // Use question_title for controller
                    question: q.question || q.question_title || '', // Keep for display
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
                    mappings: mappings,
                    points: mappings[0]?.marks || 1
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
                    mappings: mappings,
                    points: mappings[0]?.marks || 1
                }];

                displayQuestions(questions);
                displayAnswerKey(questions);
                $('#generated_questions_data').val(JSON.stringify(questions));
                $btn.prop('disabled', false).html(originalText);
            },
            error: (xhr) => {
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

            container.append(`
                <div class="question-item">
                    <div class="question-header" onclick="toggleQuestion(${q.id})">
                        <div><strong>Q${i+1}.</strong> ${q.question.substring(0, 80)}... <span class="badge badge-info ml-2">${q.question_type}</span> <span class="badge badge-secondary">${q.difficulty}</span></div>
                        <i class="mdi mdi-chevron-down" id="icon-${q.id}"></i>
                    </div>
                    <div class="question-content" id="question-${q.id}">
                        <div class="question-text mb-3"><strong>Question:</strong> ${q.question}</div>
                        ${optionsHtml}
                        ${q.explanation ? `<div class="alert alert-info mt-2"><strong>Explanation:</strong> ${q.explanation}</div>` : ''}
                        <div class="question-meta">
                            ${q.mappings.map((m, idx) => `<span class="d-block"><i class="mdi mdi-tag-outline"></i> Mapping ${idx+1}: ${m.reason}</span>`).join('')}
                            <span><i class="mdi mdi-star"></i> Points: ${q.points}</span>
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

        let html = '<table class="table table-bordered"><thead><tr><th>Q.No.</th><th>Question</th><th>Correct Answer</th></tr></thead><tbody>';
        questions.forEach((q, i) => {
            const correct = q.options?.find(opt => opt.correct)?.text || q.correct_answer || 'N/A';
            html += `<tr><td>${i+1}</td><td>${q.question.substring(0, 60)}...</td><td><span class="badge badge-success">${correct}</span></td></tr>`;
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
            console.log('Saving data:', parsedData); // Debug log
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
            success: (res) => {
                console.log('Save response:', res);
                if (res.status_code === 1) {
                    alert('Questions saved successfully!');
                    $('#generated_questions_data').val('');
                    $('#assessmentPreviewModal').modal('hide');
                    setTimeout(() => window.location.reload(), 500);
                } else {
                    alert(res.message || 'Error saving');
                    $btn.prop('disabled', false).html('<i class="mdi mdi-content-save mr-1"></i> Save Questions');
                }
            },
            error: (xhr) => {
                console.error('Save error:', xhr.responseText);
                alert('Error saving questions: ' + (xhr.responseJSON?.message || 'Unknown error'));
                $btn.prop('disabled', false).html('<i class="mdi mdi-content-save mr-1"></i> Save Questions');
            }
        });
    });
});
</script>