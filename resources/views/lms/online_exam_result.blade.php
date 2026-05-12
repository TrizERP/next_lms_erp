@include('includes.lmsheadcss')
<link rel="stylesheet" href="../../../tooltip/enjoyhint/jquery.enjoyhint.css">
<meta http-equiv="cache-control" content="private, max-age=0, no-cache">
<meta http-equiv="pragma" content="no-cache">
<meta http-equiv="expires" content="0">
<!-- Content main Section -->
<div class="content-main flex-fill">
    <div class="row">
        <div class="col-md-6">
            <h1 class="h4 mb-3">Result of Practice : {{$data['questionpaper_data']['paper_name']}}</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb bg-transparent p-0">
                    <li class="breadcrumb-item"><a href="http://202.47.117.124/triz-lms">Home</a></li>                    
                    <li class="breadcrumb-item active" aria-current="page">Quiz</li>
                    <li class="breadcrumb-item active" aria-current="page">Result</li>
                </ol>
            </nav>
        </div>   
        <div class="col-md-6" align="right">
            @if(isset($data['exam_type']) && $data['exam_type']=="PAL")
            <a href="{{route('pal.index')}}" class="btn btn-primary">Back To PAL</a>
            @else
            <a href="{{route('question_paper.index')}}" class="btn btn-primary">Back To Exams</a>
            @endif
            <button class="btn btn-sm btn-info ml-2 pt-2 pb-2" onclick="showSuggestedContent('{{ strtolower($data['online_exam_data']['student_level'] ?? 'easy') }}')">
    Suggested Content
</button>
        </div>        
    </div>
<style>
    .correct-answer {
    background-color: #e6ffed;
    padding: 5px;
    border-radius: 6px;
}

.wrong-answer {
    background-color: #ffe6e6;
    padding: 5px;
    border-radius: 6px;
}

.text-success {
    color: #28a745 !important;
    font-weight: bold;
}

.text-danger {
    color: #dc3545 !important;
    font-weight: bold;
}
/* Category Box */
.content-category {
    background: #f8f9fb;
    border-radius: 12px;
    padding: 15px;
    margin-bottom: 20px;
    border-left: 5px solid #4e73df;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}

/* Category Title */
.content-category h5 {
    font-weight: 600;
    font-size: 18px;
    color: #2c3e50;
    margin-bottom: 12px;
    display: flex;
    align-items: center;
}

/* Add icon before title */
.content-category h5::before {
    content: "📘";
    margin-right: 8px;
}

/* Different color for Revision Notes */
.content-category:nth-child(2) {
    border-left: 5px solid #28a745;
}

.content-category:nth-child(2) h5::before {
    content: "📝";
}

/* Content Card */
.content-item {
    border-radius: 10px;
    border: 1px solid #e3e6f0;
    transition: 0.3s;
}

.content-item:hover {
    transform: translateY(-3px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
}

/* Title row */
.content-item h6 {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-weight: 600;
}

/* Badge */
.content-item .badge {
    font-size: 12px;
    padding: 5px 8px;
    border-radius: 6px;
    background: #4e73df;
    color: #fff;
    
}

.content-item h6 {
    gap: 12px;
}

.content-item .content-meta {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}

.content-item .btn-outline-info {
    border-radius: 6px;
    font-weight: 600;
}

.content-item .content-pill {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    min-height: 32px;
    padding: 6px 12px;
    border: 1px solid transparent;
    border-radius: 6px;
    font-size: 13px;
    font-weight: 700;
    line-height: 1.2;
    white-space: nowrap;
}

.content-item .level-badge {
    background: #e0f2fe;
    color: #075985;
}

.content-item .mapping-type-pill {
    background: #ffffff;
    border-color: #22aeea;
    color: #0797d6;
    box-shadow: 0 1px 3px rgba(7, 151, 214, 0.18);
}

.content-item .mapping-type-pill:hover,
.content-item .mapping-type-pill:focus {
    background: #f0fbff;
    color: #057faf;
}

.mapping-details .table th,
.mapping-details .table td {
    text-align: center !important;
    vertical-align: middle !important;
}

.question-mapping-card {
    margin-top: 18px;
    border: 1px solid #b9d9ff;
    border-radius: 10px;
    background: #f8fbff;
    overflow: hidden;
    box-shadow: 0 8px 22px rgba(37, 99, 235, 0.08);
}

.question-mapping-card__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 16px;
    background: linear-gradient(90deg, #2563eb, #0ea5e9);
    border-bottom: 0;
    color: #fff;
    font-weight: 700;
    letter-spacing: 0.2px;
}

.question-mapping-card__header span:first-child::before {
    content: "\f02c";
    font-family: FontAwesome;
    margin-right: 8px;
}

.question-mapping-card__count {
    font-size: 12px;
    color: #eaf6ff;
    background: rgba(255, 255, 255, 0.18);
    border: 1px solid rgba(255, 255, 255, 0.28);
    border-radius: 999px;
    padding: 4px 10px;
}

.question-mapping-list {
    margin: 0;
    padding: 0;
    list-style: none;
}

.question-mapping-list__item {
    display: grid;
    grid-template-columns: minmax(210px, 28%) 1fr;
    gap: 14px;
    padding: 14px 16px;
    border-bottom: 1px solid #dbeafe;
    background: #fff;
}

.question-mapping-list__item:nth-child(even) {
    background: #f0f9ff;
}

.question-mapping-list__item:last-child {
    border-bottom: 0;
}

.question-mapping-list__type {
    color: #0f172a;
    font-weight: 700;
    line-height: 1.45;
    position: relative;
    padding-left: 12px;
}

.question-mapping-list__type::before {
    content: "";
    position: absolute;
    left: 0;
    top: 4px;
    bottom: 4px;
    width: 4px;
    border-radius: 4px;
    background: #2563eb;
}

.question-mapping-list__item:nth-child(2n) .question-mapping-list__type::before {
    background: #0891b2;
}

.question-mapping-list__item:nth-child(3n) .question-mapping-list__type::before {
    background: #7c3aed;
}

.question-mapping-list__values {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
}

.question-mapping-chip {
    display: inline-flex;
    align-items: center;
    min-height: 34px;
    padding: 6px 12px;
    border: 1px solid #bfdbfe;
    border-radius: 999px;
    background: #eff6ff;
    color: #1d4ed8;
    font-size: 13px;
    font-weight: 700;
    box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.55);
}

.question-mapping-list__item:nth-child(2n) .question-mapping-chip {
    border-color: #a5f3fc;
    background: #ecfeff;
    color: #0e7490;
}

.question-mapping-list__item:nth-child(3n) .question-mapping-chip {
    border-color: #ddd6fe;
    background: #f5f3ff;
    color: #6d28d9;
}

@media (max-width: 767.98px) {
    .question-mapping-list__item {
        grid-template-columns: 1fr;
        gap: 8px;
    }
}
</style>
    <div class="container-fluid mb-5">
        <div class="course-grid-tab tab-pane fade show active" id="grid" role="tabpanel" aria-labelledby="grid-tab">                                
            <div class="card border-0 rounded mb-5">
                <div class="card-body">
                    <div class="row justify-content-center py-3">
                        <div class="col-md-3 text-center my-2">
                            <div class="answer-box right">{{$data['online_exam_data']['obtain_marks'] ?? 0}}/{{$data['questionpaper_data']['total_marks']}}</div>
                            <div class="h4 mb-0">Total Marks</div>
                        </div>
                        <div class="col-md-3 text-center my-2">
                            <div class="answer-box wrong">{{$data['online_exam_data']['total_right'] ?? 0 }}/{{$data['questionpaper_data']['total_ques']}}</div>
                            <div class="h4 mb-0">Right Answer</div>
                        </div>
                        <div class="col-md-3 text-center my-2">
                            <div class="answer-box uttemp">{{$data['online_exam_data']['total_wrong'] ?? 0 }}/{{$data['questionpaper_data']['total_ques']}}</div>
                            <div class="h4 mb-0">Wrong Answer</div>
                        </div>
                        <div class="col-md-3 text-center my-2">
    <div class="answer-box info">
        {{ ucfirst(strtolower($data['online_exam_data']['student_level'] ?? 'N/A')) }}
    </div>
    <div class="h4 mb-0">Current Level</div>
</div>
                    </div>
                </div>
            </div>
            <div class="container-fluid mb-4">
                <div class="quiz-box">

                    @php 
                    $i = 1;                                        
                    @endphp 

                    @if($data['questionpaper_data']['result_show_ans'] == 1)<!--Show right answer block if result_show_ans is set to 1-->

                    @foreach($data['question_arr'] as $quesid => $quesarr)
                    @if(!isset($data['online_answer_data'][$quesarr['id']])) 
                   
                    <div class="row mb-3">
                        <div class="col-2">
                            <div class="quiz-box-count">
                                <div class="count">{{$i++}}</div>
                                <div class="quiz-con">                                   
                                    <!-- <div class="text-secondary"><i class="mdi mdi-flag-outline"></i></div> -->
                                    <div class="text-secondary mb-2">Marked out of <b>{{$quesarr['points']}}</b></div>
                                    <!-- <div class="text-secondary mb-2">{{$quesarr['points']}}</div> -->
                                    @if(isset($quesarr['hint_text']))
                                    <div class="text-secondary"><i data-toggle="tooltip" title="{{$quesarr['hint_text']}}" class="mdi mdi-alert-circle"></i></div><!--mdi-flag-outline-->
                                    @endif
                                </div>
                            </div>
                        </div>
                        <div class="col-10">
                            <div class="card border-0 rounded">
                                <div class="card-body">
                                   <!--  <a href="javascript:void(0)" class="float-right" data-container="body" data-toggle="popover" data-placement="left" data-content="Vivamus sagittis lacus vel augue laoreet rutrum faucibus." data-trigger="hover">
                                        <i class="mdi mdi-alert-circle-outline"></i>
                                    </a> -->
                                    <div class="quiz-title">{!!$quesarr['question_title']!!}</div>
                                   
                                    <div class="quiz-option">
                                        <!-- <div class="title">Select One</div> -->
                                        @if($quesarr['question_type_id'] == "2") <!--Narrative Question-->
                                        <ul>
                                            <li>
                                                <!-- <div class="custom-control custom-radio custom-control-inline"> -->
                                                    <textarea type="text" rows="4" placeholder="Enter Answer" class="form-control" name="answer[{{$quesarr['id']}}]"></textarea>
                                                <!-- </div> -->
                                            </li>
                                        </ul>
                                        @elseif($quesarr['question_type_id'] == "1") <!--Multple Option Question-->
                                            <ul>
                                            @if(isset($data['answer_arr'][$quesarr['id']]))                     
                                                @foreach($data['answer_arr'][$quesarr['id']] as $ansid => $ansarr)
                                                    @php                                                                                                     
                                                    if($quesarr['multiple_answer'] == 1) //Multiple answer
                                                    {
                                                        $btnclass = "square";
                                                        $type = "checkbox";
                                                        $name = "answer[".$quesarr['id']."][".$ansarr['id']."][]";                                                                                                
                                                    }
                                                    else{ //Single answer                                                
                                                        $btnclass = "dot";
                                                        $type = "radio";
                                                        $name = "answer[".$quesarr['id']."][".$ansarr['id']."]";                                                       
                                                    }
                                                    @endphp
                                                    
                                                    <li>
                                                        <div class="custom-control custom-{{$type}} custom-control-inline">
                                                            <input type="{{$type}}" name="{{$name}}" value="{{$ansarr['correct_answer']}}" class="custom-control-input">
                                                            <label class="custom-control-label" for="customRadioInline1">
                                                                {{$ansarr['answer']}}
                                                                @if(isset($ansarr['feedback'])) 
                                                                    <span style="background-color:#e8e83b;" @if($ansarr['correct_answer']===1) style="color:green !important;" @endif>&nbsp;&nbsp;{{$ansarr['feedback']}}&nbsp;&nbsp;</span>
                                                                @endif
                                                            </label>
                                                        </div>
                                                    </li>                                                    
                                                    
                                                @endforeach
                                            @endif
                                            </ul>
                                        @endif                                        
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    @else                    
                    <div class="row mb-3">
                        <div class="col-2">
                            <div class="quiz-box-count">
                                <div class="count">{{$i++}}</div>
                                <div class="quiz-con">                                    
                                    <!-- <div class="text-secondary"><i class="mdi mdi-flag-outline"></i></div> -->
                                    <div class="text-secondary mb-2">Marked out of <b>{{$quesarr['points']}}</b></div>
                                    <!-- <div class="text-secondary mb-2">{{$quesarr['points']}}</div> -->
                                    @if(isset($quesarr['hint_text']))
                                    <div class="text-secondary"><i data-toggle="tooltip" title="{{$quesarr['hint_text']}}" class="mdi mdi-alert-circle"></i></div><!--mdi-flag-outline-->
                                    @endif
                                </div>
                            </div>
                        </div>
                        <div class="col-10">
                            <div class="card border-0 rounded">
                                <div class="card-body">
                                   <!--  <a href="javascript:void(0)" class="float-right" data-container="body" data-toggle="popover" data-placement="left" data-content="Vivamus sagittis lacus vel augue laoreet rutrum faucibus." data-trigger="hover">
                                        <i class="mdi mdi-alert-circle-outline"></i>
                                    </a> -->
                                    <div class="quiz-title">{!!$quesarr['question_title']!!}</div>
                                    @if($data['online_answer_data'][$quesarr['id']]['RIGHT_WRONG'] == "right")
                                    <div class="alert alert-success text-light" role="alert">Chosen as right answer</div>
                                    @elseif($data['online_answer_data'][$quesarr['id']]['RIGHT_WRONG'] == "wrong")
                                    <div class="alert alert-danger" role="alert">Chosen as wrong answer</div>
                                    @endif
                                    <div class="quiz-option">
                                        <!-- <div class="title">Select One</div> -->
                                        @if($quesarr['question_type_id'] == "2") <!--Narrative Question-->
                                        <ul>
                                            <li>
                                                <!-- <div class="custom-control custom-radio custom-control-inline"> -->
                                                    <textarea type="text" rows="4" placeholder="Enter Answer" class="form-control" name="answer[{{$quesarr['id']}}]">@if(isset($data['online_answer_data'][$quesarr['id']]['GIVEN_ANSWER'])){{$data['online_answer_data'][$quesarr['id']]['GIVEN_ANSWER']}}@endif</textarea>
                                                <!-- </div> -->
                                            </li>
                                        </ul>
                                        @elseif($quesarr['question_type_id'] == "1") <!--Multple Option Question-->
                                            <ul>
                                            @if(isset($data['answer_arr'][$quesarr['id']]))                     
                                                @foreach($data['answer_arr'][$quesarr['id']] as $ansid => $ansarr)
                                                   @php
$btnclass = ($quesarr['multiple_answer'] == 1) ? "square" : "dot";
$type = ($quesarr['multiple_answer'] == 1) ? "checkbox" : "radio";
$name = ($quesarr['multiple_answer'] == 1)
    ? "answer[".$quesarr['id']."][".$ansarr['id']."][]"
    : "answer[".$quesarr['id']."][".$ansarr['id']."]";

$div_wrong_class = "";
$wrong_class = "";
$correct_class = "";
$checked = "";

// get data safely
$given = $data['online_answer_data'][$quesarr['id']]['GIVEN_ANSWER'] ?? '';
$actual = $data['online_answer_data'][$quesarr['id']]['ACTUAL_ANSWER'] ?? '';

$given_arr = explode(',', $given);
$actual_arr = explode(',', $actual);

// RESET
$correct_class = "";

// ✅ IF USER SELECTED CORRECT ANSWER
if(in_array($ansarr['id'], $given_arr) && $ansarr['correct_answer'] == 1){
    $correct_class = "text-success";
}

// ❌ IF USER SELECTED WRONG ANSWER
if(in_array($ansarr['id'], $given_arr) && $ansarr['correct_answer'] == 0){
    $wrong_class = "text-danger";
    $div_wrong_class = "wrong-answer";
}

// 💡 SHOW CORRECT ANSWER ONLY WHEN WRONG
if(!in_array($ansarr['id'], $given_arr) && $ansarr['correct_answer'] == 1 
   && ($data['online_answer_data'][$quesarr['id']]['RIGHT_WRONG'] ?? '') == "wrong"){
    $correct_class = "text-success";
}

// ✅ USER SELECTED
if(in_array($ansarr['id'], $given_arr)){
    $checked = "checked";
}

// ❌ WRONG SELECTED
if(in_array($ansarr['id'], $given_arr) && $ansarr['correct_answer'] == 0){
    $wrong_class = "text-danger";
    $div_wrong_class = "wrong-answer";
}
@endphp
                                                    
                                                    <li>
                                                       <div class="custom-control custom-{{$type}} custom-control-inline {{$div_wrong_class}} {{ $ansarr['correct_answer'] == 1 ? 'correct-answer' : '' }}">
                                                            <input {{$checked}} type="{{$type}}" name="{{$name}}" value="{{$ansarr['correct_answer']}}" class="custom-control-input">
                                                            <label class="custom-control-label {{$wrong_class}} {{$correct_class}}" for="customRadioInline1">
                                                                {{$ansarr['answer']}}
                                                                @if(isset($ansarr['feedback']) && $ansarr['feedback'] !="") 
                                                                <span style="background-color:#e8e83b;">&nbsp;&nbsp;{{$ansarr['feedback']}}&nbsp;&nbsp;</span>
                                                                @endif
                                                            </label>
                                                        </div>
                                                    </li>                                                    
                                                    
                                                @endforeach
                                            @endif
                                            </ul>
                                            @if(isset($data['mapping_arr'][$quesarr['id']]) && count($data['mapping_arr'][$quesarr['id']]) > 0)
                                                @php
                                                    $questionMappings = $data['mapping_arr'][$quesarr['id']];
                                                    $mappingValueCount = 0;
                                                    foreach($questionMappings as $mappingValues) {
                                                        $mappingValueCount += is_array($mappingValues) ? count($mappingValues) : 1;
                                                    }
                                                @endphp
                                                <div class="question-mapping-card">
                                                    <div class="question-mapping-card__header">
                                                        <span>Mapping Values</span>
                                                        <span class="question-mapping-card__count">{{$mappingValueCount}} {{ $mappingValueCount == 1 ? 'value' : 'values' }}</span>
                                                    </div>
                                                    <ul class="question-mapping-list">
                                                        @foreach($questionMappings as $mapping_type => $mapping_values)
                                                            @php
                                                                $mapping_values = is_array($mapping_values) ? $mapping_values : [$mapping_values];
                                                            @endphp
                                                            <li class="question-mapping-list__item">
                                                                <div class="question-mapping-list__type">{{$mapping_type}}</div>
                                                                <div class="question-mapping-list__values">
                                                                    @foreach($mapping_values as $mapping_value)
                                                                        <span class="question-mapping-chip">{{$mapping_value}}</span>
                                                                    @endforeach
                                                                </div>
                                                            </li>
                                                        @endforeach
                                                    </ul>
                                                </div>
                                            @endif
                                            
                                        @endif  
                                        @if(($data['online_answer_data'][$quesarr['id']]['RIGHT_WRONG'] ?? '') == "wrong")
                                            <div class="alert alert-info mt-2">
                                               <strong>Correct Answer:</strong>

@php
$actual = $data['online_answer_data'][$quesarr['id']]['ACTUAL_ANSWER'] ?? '';
$actual_arr = explode(',', $actual);

$correctAnswers = [];

if(isset($data['answer_arr'][$quesarr['id']])){
    foreach($data['answer_arr'][$quesarr['id']] as $ans){
        if(in_array($ans['id'], $actual_arr)){
            $correctAnswers[] = $ans['answer'];
        }
    }
}
@endphp

{{ implode(', ', $correctAnswers) }}
                                            </div>
                                        @endif                                      
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    @endif
                    @endforeach

                    @endif
                   
                </div>
            </div>
        </div>    
    </div>
</div>
{{-- @if(!empty($data['rightInterest']))
<div class="card" style="padding:10px;margin:20px">
    <h5 style="background:#010101;color:#fff;border-radius:10px;padding:10px">Occupations</h5>
    <div class="occupationDiv" id="occupationDiv">

    </div>
</div>
@endif --}}
@include('includes.lmsfooterJs')
<script type="text/javascript">
$(document).ready(function(){
    $('[data-toggle="tooltip"]').tooltip();   
});
@if(!empty($data['rightInterest']))
    $(document).ready(function(){
        var rightInterest = @json($data['rightInterest']);
        const {
        Realistic = 0,
        Investigative = 0,
        Artistic = 0,
        Social = 0,
        Enterprising = 0,
        Conventional = 0
    } = rightInterest;

    $.ajax({
        url: '{{route("intrestEnterScores")}}',
        data : {Realistic:Realistic,Investigative:Investigative,Artistic:Artistic,Social:Social,Enterprising:Enterprising,Conventional:Conventional},
        type : 'GET',
        success : function(response){
            // console.log(response.career);
            if(response.career){
                console.log(response.career);
                var ul =`<div class="container-fluid mb-5">
                            <div class="coursr-chp-list" id="cource-chap-list">`;
                            // Loop through mappedValue within each mappedItem
                            var i = 1;
                            $.each(response.career, function(index, value) {
                            ul+=`<div class="row card single-chp mb-2">
                                        <div class="col-md-4 mb-2 chp-details">
                                            <div class="count">${i++}</div>
                                            <div class="title">
                                            ${value.title}
                                            </div>
                                        </div>
                                    </div>`;
                            });
                            ul += ` </div>
                                </div>`;
                 $('#occupationDiv').append(ul);
            }
        }
    })

})
@endif
function escapeHtml(text) {
    if(!text) return '';
    return String(text)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

function formatLevelLabel(level) {
    if(!level) return 'Medium';
    level = String(level).toLowerCase();
    return level.charAt(0).toUpperCase() + level.slice(1);
}

function getUniqueContents(contents) {
    var seen = {};
    var uniqueContents = [];

    $.each(contents || [], function(index, content) {
        var key = content.id || content.content_id || content.filename || content.title || content.content_title || index;

        if(seen[key]) {
            return;
        }

        seen[key] = true;
        uniqueContents.push(content);
    });

    return uniqueContents;
}

function getMappingTypeSummary(content) {
    if(!content.mapping || content.mapping.length === 0) {
        return 'Mapping Types';
    }

    var mappingTypes = [];
    $.each(content.mapping, function(index, mapping) {
        if(mapping.type_name && mappingTypes.indexOf(mapping.type_name) === -1) {
            mappingTypes.push(mapping.type_name);
        }
    });

    return mappingTypes.length ? mappingTypes.join(', ') : 'Mapping Types';
}

function buildSuggestedContentMappingHtml(content, mappingId) {
    var html = '<div class="mapping-details suggested-content-mapping mt-3" style="display:none;" id="' + mappingId + '">';

    if(content.mapping && content.mapping.length > 0) {
        html += '<div class="table-responsive">';
        html += '<table class="table table-bordered mb-0 text-center">';
        html += '<thead><tr><th class="text-center">Mapping Type</th><th class="text-center">Mapping Value</th></tr></thead><tbody>';

        $.each(content.mapping, function(index, mapping) {
            html += '<tr>';
            html += '<td class="text-center">' + escapeHtml(mapping.type_name || '-') + '</td>';
            html += '<td class="text-center">' + escapeHtml(mapping.value_name || '-') + '</td>';
            html += '</tr>';
        });

        html += '</tbody></table></div>';
    } else {
        html += '<div class="alert alert-info mb-0">No mapping information available</div>';
    }

    html += '</div>';
    return html;
}

function toggleSuggestedContentMapping(mappingId) {
    var mappingDiv = $('#' + mappingId);

    if(mappingDiv.is(':visible')) {
        mappingDiv.slideUp(250);
    } else {
        $('.suggested-content-mapping').slideUp(250);
        mappingDiv.slideDown(250);
    }
}

function buildSuggestedContentCard(content, index, level) {
    var mappingId = 'result_content_mapping_' + (content.id || index) + '_' + index;
    var title = content.title || content.content_title || 'Untitled';
    var levelLabel = formatLevelLabel(content.student_level || level);
    var html = '<div class="content-item card mb-2">';
    html += '<div class="card-body">';
    html += '<h6><span>';

    if(content.file_type == 'link'){
        html += '<a target="_blank" href="' + escapeHtml(content.filename) + '">' + escapeHtml(title) + '</a>';
    } else {
        html += escapeHtml(title);
    }

    html += '</span><span class="content-meta">';
    
    html += '</button></span></h6>';

    if(content.description) {
        html += '<p>' + escapeHtml(content.description) + '</p>';
    }

    if(content.content_link) {
        html += '<a href="' + escapeHtml(content.content_link) + '" target="_blank" class="btn btn-sm btn-primary">View Content</a>';
    }

    html += buildSuggestedContentMappingHtml(content, mappingId);
    html += '</div></div>';
    return html;
}

function showSuggestedContent(level){
     var standard_id = '{{$data["questionpaper_data"]["standard_id"] ?? ""}}';
     var subject_id = '{{$data["questionpaper_data"]["subject_id"] ?? ""}}';
     var chapter_id = '{{$data["questionpaper_data"]["paper_desc"] ?? ""}}';
 
     console.log("showSuggestedContent called", {standard_id, subject_id, chapter_id, level});
 
     var nextLevel = getNextLevel(level);
     currentStudentLevel = nextLevel;   // ✅ store globally
     $("#studentLevel").text(formatLevelLabel(nextLevel));
     $("#suggestedModal").modal("show");
 
     $("#suggestedModal .modal-body").html('<div class="text-center"><i class="fa fa-spinner fa-spin"></i> Loading content...</div>');
 
     $.ajax({
         url: '/lms/suggested-content',
         type: 'GET',
         data: {
             standard_id: standard_id,
             subject_id: subject_id,
             chapter_id: chapter_id,
             student_level: nextLevel,
             sub_institute_id: '{{ session()->get("sub_institute_id") }}'
         },
        success: function(response) {
            console.log("Suggested Content Response:", response);
             suggestedContentData = response.content_data;
            if(response.status === 0) {
                $("#suggestedModal .modal-body").html('<p class="text-danger">' + response.message + '</p>');
                return;
            }
            
            var html = '<div class="suggested-content-container">';
            // html += '<h4>Current Level: <span class="badge badge-primary">' + response.student_level + '</span></h4>';
            
            if(response.content_data && Object.keys(response.content_data).length > 0) {
                $.each(response.content_data, function(chapterId, categories) {
                    $.each(categories, function(category, contents) {
                        if(contents && contents.length > 0) {
                            html += '<div class="content-category mb-3">';
                            let categoryTitle = category.replace(/_/g, ' ');
categoryTitle = categoryTitle.charAt(0).toUpperCase() + categoryTitle.slice(1);

html += '<h5>' + categoryTitle + '</h5>';
                            $.each(getUniqueContents(contents), function(index, content) {
                                html += buildSuggestedContentCard(content, index, response.student_level || nextLevel);
                            });
                            html += '</div>';
                        }
                    });
                });
            } else {
                html += '<p>No content available for this level.</p>';
            }

            if(response.flashcards && response.flashcards.length > 0) {
                html += '<div class="flashcards-section mt-3">';
                html += '<h5>Flash Cards</h5>';
                $.each(response.flashcards, function(index, card) {
                    html += '<div class="flashcard-item card mb-2">';
                    html += '<div class="card-body">';
                    html += '<h6>' + (card.title || 'Flash Card') + '</h6>';
                    html += '</div></div>';
                });
                html += '</div>';
            }

            html += '</div>';
            $("#suggestedModal .modal-body").html(html);
        },
        error: function(xhr, status, error) {
            console.error("Error loading suggested content:", error);
            $("#suggestedModal .modal-body").html('<p class="text-danger">Error loading content. Please try again.</p>');
        }
    });
}
function getNextLevel(currentLevel){
    if(currentLevel === 'easy'){
        return 'medium';
    } else if(currentLevel === 'medium'){
        return 'hard';
    } else {
        return 'hard'; // default
    }
}
function storeContent(){

    if(!suggestedContentData || Object.keys(suggestedContentData).length === 0){
        alert("No content to store");
        return;
    }

    console.log("Sending Level:", currentStudentLevel); // ✅ debug

    $.ajax({
        url: '/lms/store-suggested-content',
        type: 'POST',
        data: {
        _token: '{{ csrf_token() }}',
        content_data: suggestedContentData,
        student_level: currentStudentLevel,
        sub_institute_id: '{{ session()->get("sub_institute_id") }}',
        standard_id: '{{$data["questionpaper_data"]["standard_id"]}}',
        subject_id: '{{$data["questionpaper_data"]["subject_id"]}}',
        chapter_id: '{{$data["questionpaper_data"]["paper_desc"]}}',
        syear: '{{ session()->get("syear") }}'
    },
        success: function(res){
            alert("Content Stored Successfully ✅");
        },
        error: function(err){
            console.log(err);
            alert("Error storing content ❌");
        }
    });
}
</script>
<!-- Suggested Content Modal -->
<div class="modal fade" id="suggestedModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">

      <div class="modal-header">
        <h5 class="modal-title">Suggested Content</h5>
        <button type="button" class="close" data-dismiss="modal">&times;</button>
       </div>
 
       <div class="modal-body">
        <h4>Suggested Level: <span id="studentLevel"></span></h4>
       </div>
       <div class="modal-footer">
         <button type="button" class="btn btn-success" onclick="storeContent()">
             Store Content
         </button>
         <button class="btn btn-secondary" data-dismiss="modal">Close</button>
       </div>
    </div>
  </div>
</div>
@include('includes.footer')
