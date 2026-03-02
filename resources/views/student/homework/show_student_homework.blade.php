@extends('layout')
@section('container')
<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Student Homework</h4>
            </div>
        </div>
        
        @php
            $grade_id = $standard_id = $division_id = $subject_id = '';
            if(isset($data['grade_id'])){
                $grade_id = $data['grade_id'];
                $standard_id = $data['standard_id'];
                $division_id = $data['division_id'];
                $subject_id = $data['subject_id'];
            }
        @endphp
        
        <div class="card">
            <div class="panel-body">
                @if ($sessionData = Session::get('data'))
                    <div class="alert alert-success alert-block">
                        <button type="button" class="close" data-dismiss="alert">×</button>
                        <strong>{{ $sessionData['message'] }}</strong>
                    </div>
                @endif
                
                <form action="{{ route('student_homework.create') }}">
                    @csrf
                    <div class="row">
                        {{ App\Helpers\SearchChain('3','required','grade,std,div',$grade_id,$standard_id,$division_id) }}
                        
                        <div class="col-md-3 form-group">
                            <label for="subject">Select Subject:</label>
                            <select name="subject" id="subject" class="form-control" required>
                                <option value="">Select Subject</option>
                            </select>
                        </div>
                        
                        <div class="col-md-2 form-group">
                            <br>
                            <input type="submit" name="submit" value="Search" onclick="return validateData();" class="btn btn-success">
                        </div>
                    </div>
                </form>
            </div>
        </div>
        
        @if(isset($data['student_data']))
            @php $student_data = $data['student_data']; $finalData = $data; @endphp
            
            <div class="card">
                <div class="panel-body">
                    <form method="POST" enctype="multipart/form-data" action="{{ route('student_homework.store') }}" onsubmit="return validateForm();">
                        @csrf
                        <div class="row">
                            <div class="col-md-12 mb-4">
                                <div class="col-md">
                                    <!-- Toggle Switch -->
                                    <div class="custom-control custom-switch">
                                        <input type="checkbox" class="custom-control-input" id="toggleSwitch" onchange="handleToggleChange()">
                                        <label class="custom-control-label" for="toggleSwitch">Send homework from system</label><br>
                                    </div>
                                    
                                    <!-- Questions Selection Div -->
                                    <div id="helloMessage" class="font-weight-bold mt-3" style="display:none;">
                                        <div class="row">
                                            <div class="col-md-4 form-group">
                                                <label for="chapterList">Select Chapter:</label>
                                                <select name="chapter_id[]" id="chapterList" class="form-control resize" multiple onchange="getQuestionLists();">
                                                    <option value="">Select Chapter</option>
                                                    @foreach($data['chaptersList'] as $value)
                                                        <option value="{{$value->id}}">{{$value->chapter_name}}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="col-md-4 form-group">
                                                <label for="questionTypes">Select Question Types:</label>
                                                <select name="question_types[]" id="questionTypes" class="form-control resize" multiple onchange="getQuestionLists();">
                                                    <option value="">Select Question Types</option>
                                                    @foreach($data['questionTypes'] as $key=>$value)
                                                        <option value="{{$key}}">{{$value}}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>
                                        
                                        <!-- Questions Display -->
                                        <div id="questionsList" class="row mt-3">
                                            <div class="col-md-12">
                                                <div class="d-flex justify-content-between align-items-center mb-2">
                                                    <h5>Available Questions:</h5>
                                                    <div>
                                                        <button type="button" class="btn btn-sm btn-info" onclick="checkAllQuestions(true)">Select All</button>
                                                        <button type="button" class="btn btn-sm btn-warning" onclick="checkAllQuestions(false)">Deselect All</button>
                                                    </div>
                                                </div>
                                                <div id="questionsContainer" class="table-responsive" style="height: 300px; overflow: auto;"></div>
                                                <div class="mt-4 text-center">
                                                    <button type="button" class="btn btn-secondary" onclick="previewPDF()">Preview PDF</button>
                                                    <button type="button" class="btn btn-primary" onclick="generateAndAttachPDF()">Generate & Attach PDF</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-3 form-group">
                                <label>Title</label>
                                <input type="text" id="title" name="title" class="form-control" onkeyup="updatePromptFromFields()" onchange="updatePromptFromFields()">
                            </div>
                            
                            <div class="col-md-4 form-group">
                                <label>Description</label>
                                <textarea name="description" id="description" class="form-control" onkeyup="updatePromptFromFields()" onchange="updatePromptFromFields()"></textarea>
                            </div>
                            
                            <div class="col-md-3 form-group">
                                <label>Submission Date</label>
                                <input type="text" id="submission_date" name="submission_date" class="form-control mydatepicker" autocomplete="off">
                            </div>
                            
                            <div class="col-md-3 form-group">
                                <label>Homework Image</label>
                                <input type="file" id="image" name="image" class="form-control" accept=".pdf,.jpg,.jpeg,.png">
                                <small class="text-muted" id="pdfFileName"></small>
                            </div>

                            <!-- Hidden input for storing selected questions prompt -->
                            <input type="hidden" name="prompt" id="promptField" value="">

                            <!-- Readonly textarea for previewing prompt -->
                            <div class="col-md-12 form-group">
                                <label>Prompt Preview (Readonly):</label>
                                <textarea id="promptPreview" class="form-control" rows="8" readonly placeholder="Prompt will appear here based on your selections..."></textarea>
                                <small class="text-muted">
                                    <span id="promptTypeIndicator">Showing prompt from: </span>
                                </small>
                            </div>
                            
                            <div class="col-lg-12 col-sm-12 col-xs-12">
                                <div class="table-responsive">
                                    <table id="example" class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th><input id="checkall" onchange="checkAll(this);" type="checkbox"></th>
                                                <th>{{App\Helpers\get_string('studentname','request')}}</th>
                                                <th>{{App\Helpers\get_string('grno','request')}}</th>
                                                <th>{{App\Helpers\get_string('standard','request')}}</th>
                                                <th>{{App\Helpers\get_string('division','request')}}</th>
                                                <th>Gender</th>
                                                <th>Mobile</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($student_data as $data)
                                                <tr>
                                                    <td><input id="{{$data['id']}}" value="{{$data['id']}}" name="students[]" type="checkbox"></td>
                                                    <td>{{App\Helpers\sortStudentName("",$data['first_name'],$data['middle_name'],$data['last_name'])}}</td>
                                                    <td>{{$data['enrollment_no']}}</td>
                                                    <td>{{$data['standard_name']}}</td>
                                                    <td>{{$data['division_name']}}</td>
                                                    <td>{{$data['gender']}}</td>
                                                    <td>{{$data['mobile']}}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                                
                                <div class="col-md-12 form-group text-center">
                                    @if(isset($finalData['division_id']) && is_array($finalData['division_id']))
                                        @foreach($finalData['division_id'] as $value)
                                            <input type="hidden" name="division_id[]" value="{{ $value }}">
                                        @endforeach
                                    @endif
                                    
                                    @if(isset($finalData['standard_id']) && is_array($finalData['standard_id']))
                                        @foreach($finalData['standard_id'] as $value)
                                            <input type="hidden" name="standard_id[]" value="{{$value}}">
                                        @endforeach
                                    @endif
                                    
                                    <input type="hidden" name="subject_id" @if(isset($subject_id)) value="{{$subject_id}}" @endif>
                                    <input type="hidden" name="pdf_generated" id="pdfGenerated" value="0">
                                    <input type="submit" name="submit" value="Submit" class="btn btn-success">
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        @endif
    </div>
</div>

<!-- PDF Preview Modal -->
<div class="modal fade" id="pdfPreviewModal" tabindex="-1" role="dialog" aria-labelledby="pdfPreviewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="pdfPreviewModalLabel">PDF Preview</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" id="pdfPreviewContent" style="max-height: 70vh; overflow-y: auto;">
                <!-- PDF preview content will be generated here -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="generateAndAttachPDF()">Attach to Homework</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    $(document).ready(function() {
        var standardId = $('#standard').val() || "{{ $standard_id ?? '' }}";
        if (standardId) getSubject(standardId);
        storeSelections();
        
        // Initialize accordion functionality
        $(document).on('click', '.accordion-btn', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            var $btn = $(this);
            var $panel = $btn.closest('.question-item').find('.accordion-panel');
            
            if ($panel.length) {
                $panel.slideToggle(200);
                $btn.toggleClass('active');
                
                if ($btn.hasClass('active')) {
                    $btn.removeClass('mdi-eye').addClass('mdi-eye-off');
                } else {
                    $btn.removeClass('mdi-eye-off').addClass('mdi-eye');
                }
            }
        });
        
        // Initialize prompt update
        setTimeout(function() {
            updatePromptBasedOnToggle();
        }, 500);
    });
    
    // Store selections for restoration
    var selectedChapters = [], selectedQuestionTypes = [];
    var allQuestions = []; // Store all questions for PDF generation
    
    function storeSelections() {
        selectedChapters = $('#chapterList').val() || [];
        selectedQuestionTypes = $('#questionTypes').val() || [];
    }
    
    function restoreSelections() {
        if (selectedChapters.length) $('#chapterList').val(selectedChapters);
        if (selectedQuestionTypes.length) $('#questionTypes').val(selectedQuestionTypes);
    }
    
    $(document).on('change', '#standard', function() {
        getSubject($(this).val());
    });
    
    $(document).on('change', '#subject', function() {
        updatePromptBasedOnToggle();
    });
    
    function getSubject(standard_id) {
        var sub = "{{ $subject_id ?? 0 }}";
        $.ajax({
            url: "{{ route('ajax_getHomeworkSubjects') }}",
            data: { standard_id: standard_id },
            type: 'GET',
            success: function(result) {
                var e = $('select[name="subject"]');
                e.find('option').remove().end().append($("<option></option>").val("").html('Select Subject'));
                
                $.each(result, function(i, v) {
                    var option = $("<option></option>").val(v.subject_id).html(v.display_name);
                    if (v.subject_id == sub) option.prop('selected', true);
                    e.append(option);
                });
                
                updatePromptBasedOnToggle();
            },
            error: function(xhr, status, error) {
                console.log('AJAX Error:', error);
            }
        });
    }
    
    // Handle toggle change
    function handleToggleChange() {
        var isChecked = $('#toggleSwitch').is(':checked');
        $('#helloMessage').toggle(isChecked);
        if (isChecked) {
            restoreSelections();
            if ($('#chapterList').val() && $('#chapterList').val().length) {
                getQuestionLists();
            }
        }
        updatePromptBasedOnToggle();
    }
    
    // Get questions
    function getQuestionLists() {
        $('#questionsContainer').empty();
        storeSelections();
        
        var chapterIds = $('#chapterList').val();
        var questionTypes = $('#questionTypes').val();
        
        if (!chapterIds || !chapterIds.length) {
            $('#questionsContainer').html('<p class="text-warning">Please select at least one chapter</p>');
            return;
        }
        
        $.ajax({
            url: "{{ route('ajaxQuestionLists') }}",
            data: {
                standard_id: $('#standard').val(),
                subject_id: $('#subject').val(),
                chapter_ids: chapterIds.filter(id => id).join(','),
                question_types: questionTypes ? questionTypes.join(',') : ''
            },
            type: 'GET',
            success: function(result) {
                allQuestions = result; // Store for PDF generation
                displayQuestions(result);
                restoreSelections();
            },
            error: function(xhr, status, error) {
                console.log('AJAX Error:', error);
                $('#questionsContainer').html('<p class="text-danger">Error loading questions</p>');
                restoreSelections();
            }
        });
    }
    
    // Display questions
    function displayQuestions(questions) {
        if (!questions || !questions.length) {
            $('#questionsContainer').html('<p class="text-info">No questions found</p>');
            return;
        }
        
        var html = '';
        for (var i = 0; i < questions.length; i++) {
            var q = questions[i];
            
            html += '<div class="question-item" data-question-id="' + q.id + '">';
            html += '<input type="checkbox" name="question_ids[]" value="' + q.id + '" class="question-checkbox" onchange="updatePromptBasedOnToggle()">';
            html += '<div style="flex:1;">';
            html += '<label class="question-label">' + decodeHTMLEntities(q.question_title || 'N/A') + '</label>';
            html += '<p class="question-meta">';
            html += 'Type: ' + (q.multiple_answer == 1 ? 'Multiple Choice' : 'Narrative') + ' | Marks: ' + (q.points || 'N/A');
            
            // Add eye button and answers panel for multiple choice questions
            if (q.multiple_answer == 1 && q.answers && q.answers.length) {
                html += '<button type="button" class="accordion-btn mdi mdi-eye"></button>';
                html += '<div class="accordion-panel">';
                html += '<ul class="answers-list">';
                
                // Add answers here
                for (var j = 0; j < q.answers.length; j++) {
                    var ans = q.answers[j];
                    var answerClass = ans.correct_answer == 1 ? 'correct-answer' : '';
                    html += '<li class="' + answerClass + '">' + decodeHTMLEntities(ans.answer) + (ans.correct_answer == 1 ? ' (Correct)' : '') + '</li>';
                }
                
                html += '</ul>';
                html += '</div>';
            }
            
            html += '</p>';
            html += '</div>';
            html += '</div>';
        }
        
        $('#questionsContainer').html(html);
    }
    
    // Check/uncheck all questions
    function checkAllQuestions(select) {
        $('.question-checkbox').prop('checked', select);
        updatePromptBasedOnToggle();
    }
    
    // Get selected questions sorted by type (MCQ first, then Narrative)
    function getSelectedQuestionsSorted() {
        var selectedIds = [];
        $('.question-checkbox:checked').each(function() {
            selectedIds.push($(this).val());
        });
        
        // Filter allQuestions to get only selected ones
        var selected = allQuestions.filter(function(q) {
            return selectedIds.includes(q.id.toString());
        });
        
        // Sort: MCQ first (multiple_answer == 1), then Narrative (multiple_answer == 0)
        return selected.sort(function(a, b) {
            // Sort by multiple_answer in descending order (1 comes before 0)
            return b.multiple_answer - a.multiple_answer;
        });
    }
    
    // Get standard and subject names
    function getStandardSubjectInfo() {
        var standardName = $('#standard option:selected').text() || 'Not Selected';
        var subjectName = $('#subject option:selected').text() || 'Not Selected';
        return {
            standard: standardName,
            subject: subjectName
        };
    }
    
    // Generate prompt from questions (when toggle is true)
    function generateQuestionsPrompt() {
        var selectedQuestions = getSelectedQuestionsSorted();
        var info = getStandardSubjectInfo();
        
        if (selectedQuestions.length === 0) {
            return "No questions selected.\n\nStandard: " + info.standard + "\nSubject: " + info.subject;
        }
        
        var prompt = "HOMEWORK QUESTIONS\n";
        prompt += "===================\n\n";
        prompt += "Standard: " + info.standard + "\n";
        prompt += "Subject: " + info.subject + "\n";
        prompt += "Total Questions: " + selectedQuestions.length + "\n\n";
        
        // Count MCQ and Narrative
        var mcqCount = selectedQuestions.filter(q => q.multiple_answer == 1).length;
        var narrativeCount = selectedQuestions.filter(q => q.multiple_answer == 0).length;
        
        prompt += "Multiple Choice Questions (MCQ): " + mcqCount + "\n";
        prompt += "Narrative Questions: " + narrativeCount + "\n\n";
        prompt += "QUESTIONS:\n";
        prompt += "----------\n\n";
        
        for (var i = 0; i < selectedQuestions.length; i++) {
            var q = selectedQuestions[i];
            prompt += "Q" + (i + 1) + ": " + stripHtmlTags(decodeHTMLEntities(q.question_title || 'N/A')) + "\n";
            prompt += "   Type: " + (q.multiple_answer == 1 ? 'Multiple Choice Question (MCQ)' : 'Narrative Question') + "\n";
            prompt += "   Marks: " + (q.points || 'N/A') + "\n";
            
            // Only show correct answers for MCQ questions (multiple_answer == 1) in prompt
            if (q.multiple_answer == 1 && q.answers && q.answers.length) {
                var correctAnswers = q.answers.filter(function(ans) { return ans.correct_answer == 1; });
                if (correctAnswers.length > 0) {
                    prompt += "   Correct Answer(s):\n";
                    correctAnswers.forEach(function(ans, index) {
                        var letter = String.fromCharCode(65 + index);
                        prompt += "      " + letter + ". " + stripHtmlTags(decodeHTMLEntities(ans.answer)) + "\n";
                    });
                }
            }
            
            prompt += "\n";
        }
        
        prompt += "===================\n";
        prompt += "End of Homework Questions\n";
        
        return prompt;
    }
    
    // Generate prompt from title and description (when toggle is false)
    function generateTitleDescriptionPrompt() {
        var title = $('#title').val().trim() || 'No title provided';
        var description = $('#description').val().trim() || 'No description provided';
        var info = getStandardSubjectInfo();
        
        var prompt = "HOMEWORK ASSIGNMENT\n";
        prompt += "===================\n\n";
        prompt += "Standard: " + info.standard + "\n";
        prompt += "Subject: " + info.subject + "\n\n";
        prompt += "Title: " + title + "\n\n";
        prompt += "Description:\n";
        prompt += "------------\n";
        prompt += description + "\n\n";
        prompt += "===================\n";
        prompt += "End of Homework Assignment\n";
        
        return prompt;
    }
    
    // Update prompt based on toggle state
    function updatePromptBasedOnToggle() {
        var isChecked = $('#toggleSwitch').is(':checked');
        var promptText = '';
        var indicatorText = '';
        
        if (isChecked) {
            promptText = generateQuestionsPrompt();
            indicatorText = 'Showing prompt from: <span class="prompt-indicator">Selected Questions (MCQ first, then Narrative)</span>';
        } else {
            promptText = generateTitleDescriptionPrompt();
            indicatorText = 'Showing prompt from: <span class="prompt-indicator">Title & Description</span>';
        }
        
        $('#promptField').val(promptText);
        $('#promptPreview').val(promptText);
        $('#promptTypeIndicator').html(indicatorText);
    }
    
    // Update prompt from fields (for title/description changes)
    function updatePromptFromFields() {
        if (!$('#toggleSwitch').is(':checked')) {
            updatePromptBasedOnToggle();
        }
    }
    
    // Get standard and subject names for title
    function getPaperTitle() {
        var standardName = $('#standard option:selected').text() || 'Standard';
        var subjectName = $('#subject option:selected').text() || 'Subject';
        return standardName + ' - ' + subjectName + ' Question Paper';
    }
        
    // Preview PDF
    function previewPDF() {
        var selectedQuestions = getSelectedQuestionsSorted();
        
        if (selectedQuestions.length === 0) {
            Swal.fire({
                icon: 'warning',
                title: 'No Questions Selected',
                text: 'Please select at least one question to preview the PDF'
            });
            return;
        }
        
        var previewHTML = generatePreviewHTML(selectedQuestions);
        $('#pdfPreviewContent').html(previewHTML);
        $('#pdfPreviewModal').modal('show');
    }
    
    // Validate form
    function validateForm() {
        if (!$('input[name="students[]"]:checked').length) {
            Swal.fire({
                icon: 'warning',
                title: 'No Students Selected',
                text: 'Please select at least one student'
            });
            return false;
        }
        
        if ($('#toggleSwitch').is(':checked')) {
            if (!$('#chapterList').val() || !$('#chapterList').val().length) {
                Swal.fire({
                    icon: 'warning',
                    title: 'No Chapter Selected',
                    text: 'Please select at least one chapter'
                });
                return false;
            }
            
            if (!$('input[name="question_ids[]"]:checked').length) {
                Swal.fire({
                    icon: 'warning',
                    title: 'No Questions Selected',
                    text: 'Please select at least one question'
                });
                return false;
            }
            
            // Check if PDF is generated when toggle is on
            if ($('#pdfGenerated').val() !== '1' && !$('#image').val()) {
                return Swal.fire({
                    icon: 'question',
                    title: 'No PDF Attached',
                    text: 'No PDF has been generated. Do you want to continue without attaching a PDF?',
                    showCancelButton: true,
                    confirmButtonText: 'Yes, continue',
                    cancelButtonText: 'No, go back'
                }).then((result) => {
                    if (result.isConfirmed) {
                        return true;
                    }
                    return false;
                });
            }
        }
        
        return true;
    }
    
    function validateData() {
        if (!$('#grade').find('option:selected').length) {
            Swal.fire({
                icon: 'warning',
                title: 'Validation Error',
                text: 'Please Select Atleast One Academic Section'
            });
            return false;
        }
        return true;
    }
    
    function checkAll(ele) {
        $('input[type="checkbox"]').prop('checked', $(ele).prop('checked'));
    }
    
    // Helper function to decode HTML entities
    function decodeHTMLEntities(text) {
        if (!text) return '';
        var textArea = document.createElement('textarea');
        textArea.innerHTML = text;
        return textArea.value;
    }
    
    // Helper function to strip HTML tags for plain text
    function stripHtmlTags(html) {
        if (!html) return '';
        var tmp = document.createElement('DIV');
        tmp.innerHTML = html;
        return tmp.textContent || tmp.innerText || '';
    }
</script>
@include('student.homework.homeworkPDFHtml')
@include('includes.footerJs')
@include('includes.footer')
@endsection