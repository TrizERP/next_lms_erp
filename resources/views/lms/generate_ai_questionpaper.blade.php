{{-- AI Question Paper Generator View --}}
@extends('lmslayout')
@section('container')
<style>
    .step-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 15px 20px;
        border-radius: 8px 8px 0 0;
        margin-bottom: 0;
    }
    .step-content {
        border: 1px solid #e0e0e0;
        border-top: none;
        padding: 20px;
        margin-bottom: 20px;
        background: #fff;
    }
    .step-number {
        display: inline-block;
        width: 30px;
        height: 30px;
        line-height: 30px;
        text-align: center;
        background: rgba(255,255,255,0.3);
        border-radius: 50%;
        margin-right: 10px;
    }
    .distribution-slider {
        margin: 10px 0;
    }
    .distribution-value {
        font-weight: bold;
        color: #667eea;
    }
    .available-count {
        font-size: 12px;
        color: #666;
    }
    .validation-message {
        padding: 10px 15px;
        border-radius: 4px;
        margin-bottom: 15px;
    }
    .validation-success {
        background: #d4edda;
        color: #155724;
        border: 1px solid #c3e6cb;
    }
    .validation-error {
        background: #f8d7da;
        color: #721c24;
        border: 1px solid #f5c6cb;
    }
    .section-card {
        border: 1px solid #e0e0e0;
        border-radius: 8px;
        padding: 15px;
        margin-bottom: 10px;
        background: #f9f9f9;
    }
    .question-preview {
        border: 1px solid #ddd;
        padding: 10px;
        margin-bottom: 10px;
        border-radius: 4px;
        background: #fff;
    }
    .difficulty-badge {
        padding: 3px 8px;
        border-radius: 12px;
        font-size: 11px;
        font-weight: bold;
    }
    .difficulty-easy { background: #28a745; color: white; }
    .difficulty-medium { background: #ffc107; color: #333; }
    .difficulty-hard { background: #dc3545; color: white; }
    .set-card {
        border: 2px solid #667eea;
        border-radius: 8px;
        padding: 15px;
        margin-bottom: 15px;
        background: #f8f9ff;
    }
    .set-header {
        font-weight: bold;
        color: #667eea;
        margin-bottom: 10px;
        font-size: 16px;
    }
    .loading-spinner {
        display: inline-block;
        width: 20px;
        height: 20px;
        border: 3px solid rgba(255,255,255,0.3);
        border-radius: 50%;
        border-top-color: #fff;
        animation: spin 1s ease-in-out infinite;
    }
    @keyframes spin {
        to { transform: rotate(360deg); }
    }
    .percentage-group {
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .percentage-group label {
        min-width: 80px;
    }
    .percentage-group input {
        flex: 1;
    }
    .percentage-group .percentage-display {
        min-width: 40px;
        text-align: right;
    }
    .total-percentage {
        font-weight: bold;
        padding: 5px 10px;
        border-radius: 4px;
    }
    .total-valid { background: #d4edda; color: #155724; }
    .total-invalid { background: #f8d7da; color: #721c24; }
</style>

<div class="content-main flex-fill">
    <div class="row">
        <div class="col-md-6">
            <h1 class="h4 mb-3">
                <i class="fa fa-magic"></i> Generate AI Question Paper
            </h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb bg-transparent p-0">
                    <li class="breadcrumb-item"><a href="{{route('course_master.index')}}">LMS</a></li>
                    <li class="breadcrumb-item"><a href="{{route('question_paper.index')}}">Exam</a></li>
                    <li class="breadcrumb-item active" aria-current="page">AI Generator</li>
                </ol>
            </nav>
        </div>
        <div class="col-md-6 text-md-right">
            <a href="{{route('question_paper.index')}}" class="btn btn-secondary">
                <i class="fa fa-arrow-left"></i> Back to Exams
            </a>
        </div>
    </div>

    <div class="container-fluid">
        <div class="card border-0">
            <div class="card-body">
                @if ($sessionData = Session::get('data'))
                    <div class="alert @if ($sessionData['status_code'] == 1) alert-success @else alert-danger @endif alert-block mb-3">
                        <button type="button" class="close" data-dismiss="alert">×</button>
                        <strong>{{ $sessionData['message'] }}</strong>
                    </div>
                @endif

                <form id="aiPaperForm" action="{{route('ai_paper.save')}}" method="post">
                    @csrf
                    
                    <!-- Step 1: Basic Configuration -->
                    <div class="step-header">
                        <span class="step-number">1</span>
                        Basic Configuration
                    </div>
                    <div class="step-content">
                        <div class="row">
                            <div class="col-md-12 form-group">
                                <div class="row align-items-center">
                                    {{ App\Helpers\SearchChain('4','','grade,std','','') }}
                                    
                                    <div class="col-md-4 form-group">
                                        <label for="subject">Select Subject:</label>
                                        <select name="subject" id="subject" class="form-control mb-0" required>
                                            <option value="">Select Subject</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-4 form-group">
                                <label for="paper_name">Paper Name <i class="fa fa-asterisk" style="color:red;font-size:6px;"></i></label>
                                <input type="text" id="paper_name" name="paper_name" class="form-control" required>
                            </div>
                            
                            <div class="col-md-4 form-group">
                                <label for="paper_desc">Description <i class="fa fa-asterisk" style="color:red;font-size:6px;"></i></label>
                                <input type="text" id="paper_desc" name="paper_desc" class="form-control" required>
                            </div>
                            
                            <div class="col-md-4 form-group">
                                <label for="exam_type">Exam Type</label>
                                <select name="exam_type" id="exam_type" class="form-control">
                                    <option value="online">Online</option>
                                    <option value="offline">Offline</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Step 2: Chapter Selection -->
                    <div class="step-header">
                        <span class="step-number">2</span>
                        Chapter Selection
                    </div>
                    <div class="step-content">
                        <div class="row">
                            <div class="col-md-6 form-group">
                                <label for="chapters">Select Chapters:</label>
                                <select name="chapters[]" id="chapters" class="form-control" multiple>
                                    <option value="">Select Subject First</option>
                                </select>
                                <small class="text-muted">Hold Ctrl/Cmd to select multiple chapters. Leave empty for all chapters.</small>
                            </div>
                            
                            <div class="col-md-6 form-group">
                                <label for="question_types">Question Types:</label>
                                <select name="question_types[]" id="question_types" class="form-control" multiple>
                                    @if(isset($data['questiontype_data']))
                                        @foreach($data['questiontype_data'] as $type)
                                            <option value="{{$type['id']}}" selected>{{$type['question_type']}}</option>
                                        @endforeach
                                    @endif
                                </select>
                                <small class="text-muted">Leave all selected for any question type</small>
                            </div>
                        </div>
                    </div>

                    <!-- Step 3: Difficulty Distribution -->
                    <div class="step-header">
                        <span class="step-number">3</span>
                        Difficulty Distribution (Depth of Knowledge)
                    </div>
                    <div class="step-content">
                        <div id="difficultyValidation"></div>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Easy (%)</label>
                                    <div class="percentage-group">
                                        <input type="range" class="form-control difficulty-slider" name="difficulty_easy" 
                                               id="difficulty_easy" min="0" max="100" value="30" oninput="updateDifficultyTotal()">
                                        <span class="percentage-display" id="difficulty_easy_val">30%</span>
                                    </div>
                                    <span class="available-count" id="easy_available">Available: 0</span>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Medium (%)</label>
                                    <div class="percentage-group">
                                        <input type="range" class="form-control difficulty-slider" name="difficulty_medium" 
                                               id="difficulty_medium" min="0" max="100" value="50" oninput="updateDifficultyTotal()">
                                        <span class="percentage-display" id="difficulty_medium_val">50%</span>
                                    </div>
                                    <span class="available-count" id="medium_available">Available: 0</span>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Hard (%)</label>
                                    <div class="percentage-group">
                                        <input type="range" class="form-control difficulty-slider" name="difficulty_hard" 
                                               id="difficulty_hard" min="0" max="100" value="20" oninput="updateDifficultyTotal()">
                                        <span class="percentage-display" id="difficulty_hard_val">20%</span>
                                    </div>
                                    <span class="available-count" id="hard_available">Available: 0</span>
                                </div>
                            </div>
                        </div>
                        <div class="row mt-2">
                            <div class="col-md-12">
                                <span class="total-percentage" id="difficulty_total">Total: 100%</span>
                            </div>
                        </div>
                        <input type="hidden" name="difficulty_distribution" id="difficulty_distribution">
                    </div>

                    <!-- Step 4: Bloom's Taxonomy Distribution -->
                    <div class="step-header">
                        <span class="step-number">4</span>
                        Bloom's Taxonomy Distribution
                    </div>
                    <div class="step-content">
                        <div id="taxonomyValidation"></div>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Remember (%)</label>
                                    <div class="percentage-group">
                                        <input type="range" class="form-control taxonomy-slider" name="taxonomy_remember" 
                                               id="taxonomy_remember" min="0" max="100" value="17" oninput="updateTaxonomyTotal()">
                                        <span class="percentage-display" id="taxonomy_remember_val">17%</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Understand (%)</label>
                                    <div class="percentage-group">
                                        <input type="range" class="form-control taxonomy-slider" name="taxonomy_understand" 
                                               id="taxonomy_understand" min="0" max="100" value="17" oninput="updateTaxonomyTotal()">
                                        <span class="percentage-display" id="taxonomy_understand_val">17%</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Apply (%)</label>
                                    <div class="percentage-group">
                                        <input type="range" class="form-control taxonomy-slider" name="taxonomy_apply" 
                                               id="taxonomy_apply" min="0" max="100" value="17" oninput="updateTaxonomyTotal()">
                                        <span class="percentage-display" id="taxonomy_apply_val">17%</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Analyze (%)</label>
                                    <div class="percentage-group">
                                        <input type="range" class="form-control taxonomy-slider" name="taxonomy_analyze" 
                                               id="taxonomy_analyze" min="0" max="100" value="17" oninput="updateTaxonomyTotal()">
                                        <span class="percentage-display" id="taxonomy_analyze_val">17%</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Evaluate (%)</label>
                                    <div class="percentage-group">
                                        <input type="range" class="form-control taxonomy-slider" name="taxonomy_evaluate" 
                                               id="taxonomy_evaluate" min="0" max="100" value="16" oninput="updateTaxonomyTotal()">
                                        <span class="percentage-display" id="taxonomy_evaluate_val">16%</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Create (%)</label>
                                    <div class="percentage-group">
                                        <input type="range" class="form-control taxonomy-slider" name="taxonomy_create" 
                                               id="taxonomy_create" min="0" max="100" value="16" oninput="updateTaxonomyTotal()">
                                        <span class="percentage-display" id="taxonomy_create_val">16%</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="row mt-2">
                            <div class="col-md-12">
                                <span class="total-percentage" id="taxonomy_total">Total: 100%</span>
                            </div>
                        </div>
                        <input type="hidden" name="taxonomy_distribution" id="taxonomy_distribution">
                    </div>

                    <!-- Step 5: Question & Marks Configuration -->
                    <div class="step-header">
                        <span class="step-number">5</span>
                        Question & Marks Configuration
                    </div>
                    <div class="step-content">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="total_questions">Total Questions <i class="fa fa-asterisk" style="color:red;font-size:6px;"></i></label>
                                    <input type="number" id="total_questions" name="total_questions" class="form-control" 
                                           min="1" max="200" value="20" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="marks_per_question">Marks per Question (Avg)</label>
                                    <input type="number" id="marks_per_question" name="marks_per_question" 
                                           class="form-control" min="0.5" max="10" step="0.5" value="1">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="total_marks">Total Marks (Auto)</label>
                                    <input type="number" id="total_marks" name="total_marks" class="form-control" readonly>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Step 6: Section Configuration -->
                    <div class="step-header">
                        <span class="step-number">6</span>
                        Section Configuration
                    </div>
                    <div class="step-content">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="num_sections">Number of Sections</label>
                                    <select id="num_sections" class="form-control" onchange="generateSections()">
                                        <option value="1" selected>1 Section</option>
                                        <option value="2">2 Sections (A, B)</option>
                                        <option value="3">3 Sections (A, B, C)</option>
                                        <option value="4">4 Sections (A, B, C, D)</option>
                                        <option value="5">5 Sections (A, B, C, D, E)</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="generate_sets">Generate Multiple Sets</label>
                                    <select id="generate_sets" class="form-control">
                                        <option value="1" selected>1 Set</option>
                                        <option value="2">2 Sets (A, B)</option>
                                        <option value="3">3 Sets (A, B, C)</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div id="sectionsContainer">
                            <div class="section-card">
                                <div class="row">
                                    <div class="col-md-4">
                                        <label>Section Name</label>
                                        <input type="text" class="form-control" value="A" readonly>
                                    </div>
                                    <div class="col-md-4">
                                        <label>Questions in Section</label>
                                        <input type="number" class="form-control section-questions" value="20" min="1">
                                    </div>
                                    <div class="col-md-4">
                                        <label>Est. Marks</label>
                                        <input type="number" class="form-control section-marks" value="20" min="1" readonly>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Step 7: Exam Settings -->
                    <div class="step-header">
                        <span class="step-number">7</span>
                        Exam Settings
                    </div>
                    <div class="step-content">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="time_allowed">Time Allowed (mins)</label>
                                    <input type="number" id="time_allowed" name="time_allowed" class="form-control" value="60">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="attempt_allowed">Attempt Allowed</label>
                                    <select id="attempt_allowed" name="attempt_allowed" class="form-control">
                                        <option value="1" selected>1</option>
                                        <option value="2">2</option>
                                        <option value="3">3</option>
                                        <option value="unlimited">Unlimited</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="open_date">Open Date</label>
                                    <input type="date" id="open_date" name="open_date" class="form-control">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="close_date">Close Date</label>
                                    <input type="date" id="close_date" name="close_date" class="form-control">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="row mt-3">
                        <div class="col-md-12 text-center">
                            <button type="button" class="btn btn-info" onclick="validateAndPreview()">
                                <i class="fa fa-eye"></i> Validate & Preview
                            </button>
                            <button type="button" class="btn btn-warning" onclick="regeneratePaper()">
                                <i class="fa fa-refresh"></i> Regenerate
                            </button>
                            <button type="submit" class="btn btn-success" id="saveBtn" onclick="return validateForm()">
                                <i class="fa fa-save"></i> Save Question Paper
                            </button>
                        </div>
                    </div>

                    <!-- Preview Section -->
                    <div id="previewSection" class="mt-4" style="display:none;">
                        <div class="step-header" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%);">
                            <span class="step-number">8</span>
                            Generated Question Paper Preview
                        </div>
                        <div class="step-content">
                            <div id="previewContent"></div>
                            <div class="text-center mt-3">
                                <button type="button" class="btn btn-primary" onclick="exportPDF()">
                                    <i class="fa fa-file-pdf-o"></i> Export PDF
                                </button>
                                <button type="button" class="btn btn-secondary" onclick="exportHTML()">
                                    <i class="fa fa-file-code-o"></i> Export HTML
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

@include('includes.lmsfooterJs')

<script>
$(document).ready(function() {
    // Initialize
    updateDifficultyTotal();
    updateTaxonomyTotal();
    calculateTotalMarks();
    
    // Standard change handler
    $("#standard").change(function() {
        var std_id = $("#standard").val();
        var path = "{{ route('ajax_LMS_StandardwiseSubject') }}";
        $('#subject').find('option').remove().end().append('<option value="">Select Subject</option>').val('');
        
        $.ajax({
            url: path,
            data: 'std_id=' + std_id,
            success: function(result) {
                for(var i = 0; i < result.length; i++) {
                    $("#subject").append($("<option></option>").val(result[i].subject_id).html(result[i].display_name));
                }
            }
        });
    });
    
    // Subject change handler - load chapters
    $("#subject").change(function() {
        var subject = $("#subject").val();
        var standard = $("#standard").val();
        
        if (subject && standard) {
            loadChapters(subject, standard);
            validateQuestions();
        }
    });
    
    // Chapter change handler
    $("#chapters").change(function() {
        validateQuestions();
    });
});

// Load chapters for selected subject
function loadChapters(subjectId, standardId) {
    var getchapter_path = "{{ route('ajax_LMS_SubjectwiseChapter') }}";
    $('#chapters').find('option').remove().end().append('<option value="">Select Chapter</option>').val('');
    
    $.ajax({
        url: getchapter_path,
        data: 'sub_id=' + subjectId + '&std_id=' + standardId,
        success: function(result) {
            for(var i = 0; i < result.length; i++) {
                $("#chapters").append($("<option></option>").val(result[i].id).html(result[i].chapter_name));
            }
        }
    });
}

// Update difficulty total
function updateDifficultyTotal() {
    var easy = parseInt($("#difficulty_easy").val());
    var medium = parseInt($("#difficulty_medium").val());
    var hard = parseInt($("#difficulty_hard").val());
    
    $("#difficulty_easy_val").text(easy + "%");
    $("#difficulty_medium_val").text(medium + "%");
    $("#difficulty_hard_val").text(hard + "%");
    
    var total = easy + medium + hard;
    var totalEl = $("#difficulty_total");
    totalEl.text("Total: " + total + "%");
    
    if (total === 100) {
        totalEl.removeClass('total-invalid').addClass('total-valid');
    } else {
        totalEl.removeClass('total-valid').addClass('total-invalid');
    }
    
    // Update hidden field
    var dist = [
        {id: 101, name: 'Easy', percentage: easy},
        {id: 102, name: 'Medium', percentage: medium},
        {id: 103, name: 'Hard', percentage: hard}
    ];
    $("#difficulty_distribution").val(JSON.stringify(dist));
}

// Update taxonomy total
function updateTaxonomyTotal() {
    var remember = parseInt($("#taxonomy_remember").val());
    var understand = parseInt($("#taxonomy_understand").val());
    var apply = parseInt($("#taxonomy_apply").val());
    var analyze = parseInt($("#taxonomy_analyze").val());
    var evaluate = parseInt($("#taxonomy_evaluate").val());
    var create = parseInt($("#taxonomy_create").val());
    
    $("#taxonomy_remember_val").text(remember + "%");
    $("#taxonomy_understand_val").text(understand + "%");
    $("#taxonomy_apply_val").text(apply + "%");
    $("#taxonomy_analyze_val").text(analyze + "%");
    $("#taxonomy_evaluate_val").text(evaluate + "%");
    $("#taxonomy_create_val").text(create + "%");
    
    var total = remember + understand + apply + analyze + evaluate + create;
    var totalEl = $("#taxonomy_total");
    totalEl.text("Total: " + total + "%");
    
    if (total === 100) {
        totalEl.removeClass('total-invalid').addClass('total-valid');
    } else {
        totalEl.removeClass('total-valid').addClass('total-invalid');
    }
    
    // Update hidden field
    var dist = [
        {id: 201, name: 'Remember', percentage: remember},
        {id: 202, name: 'Understand', percentage: understand},
        {id: 203, name: 'Apply', percentage: apply},
        {id: 204, name: 'Analyze', percentage: analyze},
        {id: 205, name: 'Evaluate', percentage: evaluate},
        {id: 206, name: 'Create', percentage: create}
    ];
    $("#taxonomy_distribution").val(JSON.stringify(dist));
}

// Calculate total marks
function calculateTotalMarks() {
    var questions = parseInt($("#total_questions").val()) || 0;
    var marks = parseFloat($("#marks_per_question").val()) || 1;
    $("#total_marks").val(questions * marks);
}

// Generate sections
function generateSections() {
    var numSections = parseInt($("#num_sections").val());
    var totalQuestions = parseInt($("#total_questions").val()) || 20;
    var letters = ['A', 'B', 'C', 'D', 'E'];
    var marksPerQuestion = parseFloat($("#marks_per_question").val()) || 1;
    
    var container = $("#sectionsContainer");
    container.html('');
    
    var questionsPerSection = Math.floor(totalQuestions / numSections);
    var remainingQuestions = totalQuestions % numSections;
    
    for (var i = 0; i < numSections; i++) {
        var sectionQuestions = questionsPerSection + (i < remainingQuestions ? 1 : 0);
        var sectionMarks = sectionQuestions * marksPerQuestion;
        
        var html = `
            <div class="section-card">
                <div class="row">
                    <div class="col-md-4">
                        <label>Section Name</label>
                        <input type="text" class="form-control section-name" value="${letters[i]}" readonly>
                    </div>
                    <div class="col-md-4">
                        <label>Questions in Section</label>
                        <input type="number" class="form-control section-questions" value="${sectionQuestions}" 
                               min="1" onchange="updateSectionMarks(this)">
                    </div>
                    <div class="col-md-4">
                        <label>Est. Marks</label>
                        <input type="number" class="form-control section-marks" value="${sectionMarks}" min="1" readonly>
                    </div>
                </div>
            </div>
        `;
        container.append(html);
    }
}

// Update section marks
function updateSectionMarks(input) {
    var sectionCard = $(input).closest('.section-card');
    var questions = parseInt($(input).val()) || 0;
    var marksPerQuestion = parseFloat($("#marks_per_question").val()) || 1;
    sectionCard.find('.section-marks').val(questions * marksPerQuestion);
}

// Validate questions availability
function validateQuestions() {
    var standardId = $("#standard").val();
    var subjectId = $("#subject").val();
    var chapterIds = $("#chapters").val() || [];
    var totalQuestions = parseInt($("#total_questions").val()) || 0;
    var questionTypes = $("#question_types").val() || [];
    
    if (!standardId || !subjectId) {
        return;
    }
    
    $.ajax({
        url: "{{ route('ai_paper.validate_questions') }}",
        type: 'POST',
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        data: {
            standard_id: standardId,
            subject_id: subjectId,
            chapter_ids: chapterIds,
            total_questions: totalQuestions,
            question_types: questionTypes,
            difficulty_distribution: JSON.parse($("#difficulty_distribution").val()),
            taxonomy_distribution: JSON.parse($("#taxonomy_distribution").val())
        },
        success: function(response) {
            if (response.status_code === 1) {
                // Update available counts
                $("#easy_available").text("Available: " + (response.difficulty_counts[101]?.count || 0));
                $("#medium_available").text("Available: " + (response.difficulty_counts[102]?.count || 0));
                $("#hard_available").text("Available: " + (response.difficulty_counts[103]?.count || 0));
                
                $("#difficultyValidation").html(
                    '<div class="validation-message validation-success">' +
                    'Total Available: ' + response.total_questions_available + ' questions, ' + 
                    response.total_marks_available + ' marks</div>'
                );
            } else {
                $("#difficultyValidation").html(
                    '<div class="validation-message validation-error">' +
                    response.warnings.join('<br>') + '</div>'
                );
            }
        }
    });
}

// Validate and preview
function validateAndPreview() {
    if (!validateForm()) {
        return;
    }
    
    var standardId = $("#standard").val();
    var subjectId = $("#subject").val();
    
    if (!standardId || !subjectId) {
        alert("Please select Standard and Subject");
        return;
    }
    
    // Gather form data
    var chapterIds = $("#chapters").val() || [];
    var questionTypes = $("#question_types").val() || [];
    var totalQuestions = parseInt($("#total_questions").val());
    var totalMarks = parseInt($("#total_marks").val());
    var marksPerQuestion = parseFloat($("#marks_per_question").val());
    var generateSets = parseInt($("#generate_sets").val());
    
    // Gather sections
    var sections = [];
    $(".section-card").each(function() {
        sections.push({
            name: $(this).find(".section-name").val(),
            questions: parseInt($(this).find(".section-questions").val()),
            marks: parseInt($(this).find(".section-marks").val())
        });
    });
    
    $.ajax({
        url: "{{ route('ai_paper.preview') }}",
        type: 'POST',
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        data: {
            standard_id: standardId,
            subject_id: subjectId,
            chapter_ids: chapterIds,
            total_questions: totalQuestions,
            total_marks: totalMarks,
            marks_per_question: marksPerQuestion,
            question_types: questionTypes,
            difficulty_distribution: JSON.parse($("#difficulty_distribution").val()),
            taxonomy_distribution: JSON.parse($("#taxonomy_distribution").val()),
            sections: sections,
            generate_sets: generateSets
        },
        beforeSend: function() {
            $("#previewContent").html('<div class="text-center"><i class="fa fa-spinner fa-spin fa-3x"></i><br>Generating question paper...</div>');
            $("#previewSection").show();
        },
        success: function(response) {
            if (response.status_code === 1) {
                displayPreview(response);
            } else {
                $("#previewContent").html(
                    '<div class="alert alert-danger">' + response.message + '</div>'
                );
            }
        }
    });
}

// Display preview
function displayPreview(response) {
    var html = '';
    
    response.sets.forEach(function(set) {
        html += '<div class="set-card">';
        html += '<div class="set-header">' + set.set_name + '</div>';
        
        set.sections.forEach(function(section) {
            html += '<div class="mb-3">';
            html += '<h5>Section ' + section.name + ' [' + section.total_marks + ' marks]</h5>';
            
            section.questions.forEach(function(q, idx) {
                html += '<div class="question-preview">';
                html += '<strong>Q' + (idx + 1) + '.</strong> ' + q.question_title.substring(0, 100) + '...';
                html += ' <span class="badge badge-primary">' + q.points + ' marks</span>';
                html += ' <span class="badge badge-secondary">' + q.question_type + '</span>';
                html += '</div>';
            });
            
            html += '</div>';
        });
        
        html += '<div class="text-right"><strong>Total: ' + set.total_questions + ' questions, ' + set.total_marks + ' marks</strong></div>';
        html += '</div>';
        
        // Store question IDs for saving
        html += '<input type="hidden" name="selected_question_ids" value="' + set.question_ids.join(',') + '">';
    });
    
    $("#previewContent").html(html);
}

// Regenerate paper
function regeneratePaper() {
    validateAndPreview();
}

// Validate form
function validateForm() {
    var standardId = $("#standard").val();
    var subjectId = $("#subject").val();
    var paperName = $("#paper_name").val();
    var paperDesc = $("#paper_desc").val();
    var totalQuestions = parseInt($("#total_questions").val());
    var difficultyTotal = parseInt($("#difficulty_total").text().replace('Total: ', '').replace('%', ''));
    var taxonomyTotal = parseInt($("#taxonomy_total").text().replace('Total: ', '').replace('%', ''));
    
    if (!standardId) {
        alert("Please select Standard");
        return false;
    }
    if (!subjectId) {
        alert("Please select Subject");
        return false;
    }
    if (!paperName) {
        alert("Please enter Paper Name");
        return false;
    }
    if (!paperDesc) {
        alert("Please enter Description");
        return false;
    }
    if (!totalQuestions || totalQuestions < 1) {
        alert("Please enter valid number of questions");
        return false;
    }
    if (difficultyTotal !== 100) {
        alert("Difficulty distribution must equal 100%");
        return false;
    }
    if (taxonomyTotal !== 100) {
        alert("Bloom's Taxonomy distribution must equal 100%");
        return false;
    }
    
    return true;
}

// Export PDF (placeholder)
function exportPDF() {
    alert("PDF export will be available soon!");
}

// Export HTML (placeholder)
function exportHTML() {
    alert("HTML export will be available soon!");
}

// Event listeners
$("#total_questions, #marks_per_question").on("change", function() {
    calculateTotalMarks();
    generateSections();
});

$(".difficulty-slider, .taxonomy-slider").on("change", function() {
    validateQuestions();
});
</script>

@include('includes.footer')
@endsection
