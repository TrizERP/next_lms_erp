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
            <button class="btn btn-info mr-2" onclick="$('#conceptMasteryModal').modal('show')">
                Concept Mastery Matrix
            </button>
            <button class="btn btn-success mr-2" onclick="$('#adaptivePracticeModal').modal('show')">
               Adaptive Practice
            </button>
            @if(isset($data['exam_type']) && $data['exam_type']=="PAL")
            <a href="{{route('pal.index')}}" class="btn btn-primary">Back To PAL</a>
            @else
            <a href="{{route('question_paper.index')}}" class="btn btn-primary">Back To Exams</a>
            @endif
        </div>
    </div>
<style>
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

#practiceHistoryModal .practice-history-summary h4,
#practiceHistoryModal .practice-history-section-title,
#practiceHistoryModal .practice-history-weekly-table th,
#practiceHistoryModal .practice-history-weekly-table td,
#practiceHistoryModal .practice-history-list .list-group-item,
#practiceHistoryModal .practice-history-list strong {
    color: #1f2937;
}

#practiceHistoryModal .practice-history-section-title {
    font-size: 1.05rem;
    font-weight: 700;
    margin-bottom: 0.75rem;
}

#practiceHistoryModal .practice-history-weekly-wrap {
    border: 1px solid #d7e3f4;
    border-radius: 12px;
    overflow: hidden;
    background: #ffffff;
    box-shadow: 0 8px 24px rgba(15, 23, 42, 0.06);
}

#practiceHistoryModal .practice-history-weekly-table {
    margin-bottom: 0;
}

#practiceHistoryModal .practice-history-weekly-table thead th {
    background: #eef4ff;
    border-bottom: 1px solid #d7e3f4;
    font-weight: 700;
    white-space: nowrap;
}

#practiceHistoryModal .practice-history-weekly-table tbody td {
    font-weight: 600;
    background: #ffffff;
}

#practiceHistoryModal .practice-history-weekly-table tbody tr:nth-child(even) td {
    background: #f8fbff;
}

#practiceHistoryModal .practice-history-list .list-group-item {
    border-color: #d7e3f4;
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
                                                                    <span style="background-color:#e8e83b;">&nbsp;&nbsp;{{$ansarr['feedback']}}&nbsp;&nbsp;</span>
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
                                                    if($quesarr['multiple_answer'] == 1) //Multiple answer
                                                    {
                                                        $btnclass = "square";
                                                        $type = "checkbox";
                                                        $name = "answer[".$quesarr['id']."][".$ansarr['id']."][]";
                                                        $div_wrong_class = $wrong_class = $checked = "";
                                                        $given_ans_arr = explode(",",$data['online_answer_data'][$quesarr['id']]['GIVEN_ANSWER']);
                                                        $actual_ans_arr = explode(",",$data['online_answer_data'][$quesarr['id']]['ACTUAL_ANSWER']);
                                                        if($ansarr['correct_answer'] == 1)
                                                        {
                                                            $checked = "checked=checked";
                                                        }
                                                        if( in_array($ansarr['id'] , $given_ans_arr) && $ansarr['correct_answer'] == 0)
                                                        {
                                                            $wrong_class = "text-danger";
                                                            $div_wrong_class = "wrong-answer";
                                                            $checked = "checked=checked";
                                                        }
                                                    }
                                                    else{ //Single answer
                                                        $btnclass = "dot";
                                                        $type = "radio";
                                                        $name = "answer[".$quesarr['id']."][".$ansarr['id']."]";
                                                        $div_wrong_class = $wrong_class = $checked = "";
                                                        if($ansarr['correct_answer'] == 1)
                                                        {
                                                            $checked = "checked=checked";
                                                        }
                                                        if($ansarr['id'] == $data['online_answer_data'][$quesarr['id']]['GIVEN_ANSWER'] && $ansarr['correct_answer'] == 0)
                                                        {
                                                            $wrong_class = "text-danger";
                                                            $div_wrong_class = "wrong-answer";
                                                            $checked = "checked=checked";
                                                        }

                                                    }
                                                    @endphp

                                                    <li>
                                                        <div class="custom-control custom-{{$type}} custom-control-inline {{$div_wrong_class}}">
                                                            <input {{$checked}} type="{{$type}}" name="{{$name}}" value="{{$ansarr['correct_answer']}}" class="custom-control-input">
                                                            <label class="custom-control-label {{$wrong_class}}" for="customRadioInline1">
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
<!-- ============================================================ -->
<!-- PHASE 2: CONCEPT MASTERY MATRIX (MODAL)                     -->
<!-- ============================================================ -->
<div class="modal" id="conceptMasteryModal" tabindex="-1" role="dialog" aria-labelledby="conceptMasteryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white d-flex justify-content-between align-items-center">
                <h5 class="modal-title mb-0" id="conceptMasteryModalLabel">
                    <i class="mdi mdi-chart-bubble mr-2"></i>Concept Mastery Matrix
                </h5>
                <div class="d-flex align-items-center">
                    <button class="btn btn-light btn-sm mr-2" onclick="refreshMasteryMatrix()">
                        <i class="mdi mdi-refresh"></i> Refresh
                    </button>
                    <button class="btn btn-info btn-sm mr-2" onclick="showConceptDetailsModal()">
                        <i class="mdi mdi-eye"></i> View Details
                    </button>
                    <button type="button" class="close text-white ml-2" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            </div>
            <div class="modal-body">
                    @php
                        $masterySummary = [
                            'total_concepts' => 0,
                            'mastered' => 0,
                            'developing' => 0,
                            'needs_practice' => 0,
                            'not_started' => 0,
                        ];
                        $masteryRows = [];
                        $normalizedQuestions = json_decode(json_encode($data['question_arr'] ?? []), true) ?: [];
                        $normalizedAnswers = json_decode(json_encode($data['online_answer_data'] ?? []), true) ?: [];

                        if (!empty($normalizedQuestions)) {
                            $conceptGroups = [];

                            foreach ($normalizedQuestions as $questionIndex => $question) {
                                if (!is_array($question)) {
                                    continue;
                                }

                                $questionId = $question['id'] ?? $question['question_id'] ?? $questionIndex;
                                $conceptName = $question['concept_name'] ?? $question['concept'] ?? 'General Concept';
                                $conceptId = $question['concept_id'] ?? $conceptName;

                                if (!isset($conceptGroups[$conceptId])) {
                                    $conceptGroups[$conceptId] = [
                                        'concept_id' => $conceptId,
                                        'concept_name' => $conceptName,
                                        'questions' => [],
                                        'correct' => 0,
                                        'wrong' => 0,
                                        'attempted' => 0,
                                    ];
                                }

                                $answer = $normalizedAnswers[$questionId] ?? null;
                                $isAttempted = !empty($answer);
                                $isCorrect = $isAttempted && (($answer['RIGHT_WRONG'] ?? '') === 'right');

                                $conceptGroups[$conceptId]['questions'][] = $questionId;

                                if ($isAttempted) {
                                    $conceptGroups[$conceptId]['attempted']++;

                                    if ($isCorrect) {
                                        $conceptGroups[$conceptId]['correct']++;
                                    } else {
                                        $conceptGroups[$conceptId]['wrong']++;
                                    }
                                }
                            }

                            foreach ($conceptGroups as $concept) {
                                $total = $concept['attempted'];
                                $correct = $concept['correct'];
                                $wrong = $concept['wrong'];
                                $accuracy = $total > 0 ? round(($correct / $total) * 100) : 0;
                                $masteryLevel = $total > 0 ? min(100, $accuracy + ($correct * 2)) : 0;

                                if ($total === 0) {
                                    $status = 'not_started';
                                } elseif ($masteryLevel >= 70) {
                                    $status = 'mastered';
                                } elseif ($masteryLevel >= 40) {
                                    $status = 'developing';
                                } else {
                                    $status = 'needs_practice';
                                }

                                $masteryRows[] = [
                                    'concept_id' => $concept['concept_id'],
                                    'concept_name' => $concept['concept_name'],
                                    'mastery_level' => $masteryLevel,
                                    'accuracy' => $accuracy,
                                    'attempted' => $total,
                                    'correct' => $correct,
                                    'wrong' => $wrong,
                                    'status' => $status,
                                    'total_questions' => count($concept['questions']),
                                ];
                            }

                            usort($masteryRows, function ($a, $b) {
                                return $a['mastery_level'] <=> $b['mastery_level'];
                            });

                            $masterySummary['total_concepts'] = count($masteryRows);

                            foreach ($masteryRows as $row) {
                                if (isset($masterySummary[$row['status']])) {
                                    $masterySummary[$row['status']]++;
                                }
                            }
                        }
                    @endphp
                    <!-- Summary Stats -->
                    <div class="row mb-3" id="masterySummary">
                        <div class="col-md-3 col-6 text-center">
                            <div class="p-2 bg-light rounded">
                                <h5 class="mb-0 text-success" id="masteredCount">{{ $masterySummary['mastered'] }}</h5>
                                <small class="text-muted">Mastered Concepts</small>
                            </div>
                        </div>
                        <div class="col-md-3 col-6 text-center">
                            <div class="p-2 bg-light rounded">
                                <h5 class="mb-0 text-warning" id="developingCount">{{ $masterySummary['developing'] }}</h5>
                                <small class="text-muted">Developing</small>
                            </div>
                        </div>
                        <div class="col-md-3 col-6 text-center">
                            <div class="p-2 bg-light rounded">
                                <h5 class="mb-0 text-danger" id="needsPracticeCount">{{ $masterySummary['needs_practice'] }}</h5>
                                <small class="text-muted">Needs Practice</small>
                            </div>
                        </div>
                        <div class="col-md-3 col-6 text-center">
                            <div class="p-2 bg-light rounded">
                                <h5 class="mb-0 text-secondary" id="notStartedCount">{{ $masterySummary['not_started'] }}</h5>
                                <small class="text-muted">Not Started</small>
                            </div>
                        </div>
                    </div>

                    <!-- Filters -->
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <select class="form-control form-control-sm" id="masteryFilter" onchange="filterMastery()">
                                <option value="all">All Concepts</option>
                                <option value="mastered">✅ Mastered</option>
                                <option value="developing">🔄 Developing</option>
                                <option value="needs_practice">⚠️ Needs Practice</option>
                                <option value="not_started">⏳ Not Started</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <input type="text" class="form-control form-control-sm" id="conceptSearch"
                                   placeholder="🔍 Search concepts..." onkeyup="filterMastery()">
                        </div>
                        <div class="col-md-4 text-right">
                            <small class="text-muted" id="conceptCount">
                                {{ $masterySummary['total_concepts'] > 0 ? 'Total: ' . $masterySummary['total_concepts'] . ' concepts' : 'No concepts found' }}
                            </small>
                        </div>
                    </div>

                    <!-- Mastery Matrix Table -->
                    <div class="table-responsive">
                        <table class="table table-bordered " id="masteryMatrixTable">
                            <thead class="thead-light">
                                <tr>
                                    <th width="5%">No</th>
                                    <th width="25%">Concept</th>
                                    <th width="20%">Mastery Level</th>
                                    <th width="12%">Status</th>
                                    <th width="10%">Accuracy</th>
                                    <th width="12%">Attempts</th>
                                    <th width="16%">Action</th>
                                    
                                </tr>
                            </thead>
                            <tbody id="masteryMatrixBody">
                                @if(!empty($masteryRows))
                                    @foreach($masteryRows as $index => $row)
                                        @php
                                            $statusConfig = [
                                                'mastered' => ['badge' => 'badge-success', 'text' => 'Mastered'],
                                                'developing' => ['badge' => 'badge-warning', 'text' => 'Developing'],
                                                'needs_practice' => ['badge' => 'badge-danger', 'text' => 'Needs Practice'],
                                                'not_started' => ['badge' => 'badge-secondary', 'text' => 'Not Started'],
                                            ];
                                            $config = $statusConfig[$row['status']] ?? $statusConfig['not_started'];
                                            $progressColor = $row['status'] === 'mastered' ? 'bg-success' : ($row['status'] === 'developing' ? 'bg-warning' : ($row['status'] === 'needs_practice' ? 'bg-danger' : 'bg-secondary'));
                                        @endphp
                                        <tr class="concept-row" data-concept-id="{{ e($row['concept_id']) }}" data-status="{{ e($row['status']) }}" data-concept-name="{{ e(strtolower((string) $row['concept_name'])) }}" onclick='viewConceptDetails(@json($row["concept_id"]))'>
                                            <td>{{ $index + 1 }}</td>
                                            <td>
                                                <strong>{{ $row['concept_name'] }}</strong>
                                            </td>
                                            <td>
                                                <div class="mastery-progress">
                                                    <div class="mastery-progress-bar {{ $progressColor }}" style="width: {{ $row['mastery_level'] }}%;">
                                                        {{ $row['mastery_level'] }}%
                                                    </div>
                                                </div>
                                            </td>
                                            <td><span class="mastery-status-badge {{ $config['badge'] }}">{{ $config['text'] }}</span></td>
                                            <td>{{ $row['accuracy'] }}%</td>
                                            <td>
                                                {{ $row['attempted'] }} / {{ $row['total_questions'] }}
                                                {{-- <small class="text-muted d-block">{{ $row['correct'] }} correct</small> --}}
                                            </td>
                                            <td>
                                                <div class="action-btns">
                                                    <button class="btn btn-sm btn-info" onclick='event.stopPropagation(); viewConceptDetails(@json($row["concept_id"]))' title="View Details">
                                                        <i class="mdi mdi-eye"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-success" onclick='event.stopPropagation(); startPractice(@json($row["concept_id"]))' title="Practice">
                                                        <i class="mdi mdi-play"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-warning" onclick="event.stopPropagation(); showSuggestedContent('{{ $row['status'] }}')" title="Suggested Content">
                                                        <i class="mdi mdi-lightbulb-outline"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                @else
                                    <tr>
                                        <td colspan="7" class="text-center py-5">
                                            <i class="mdi mdi-information-outline mdi-48px text-muted"></i>
                                            <p class="mt-2 text-muted">No concepts found. Complete more quizzes to see your mastery levels.</p>
                                            <button class="btn btn-primary btn-sm mt-2" onclick="startNewQuiz()">
                                                <i class="mdi mdi-play"></i> Take a Quiz
                                            </button>
                                        </td>
                                    </tr>
                                @endif
                            </tbody>
                        </table>
                    </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- ============================================================ -->
<!-- CONCEPT DETAILS MODAL                                        -->
<!-- ============================================================ -->
<div class="modal" id="conceptDetailModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">
                    <i class="mdi mdi-information-outline mr-2"></i>Concept Details
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" id="conceptDetailBody">
                <div class="text-center py-5">
                    <i class="fa fa-spinner fa-spin fa-2x text-primary"></i>
                    <p class="mt-3">Loading concept details...</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                <button type="button" class="btn btn-success" onclick="practiceFromModal()">
                    <i class="mdi mdi-play"></i> Practice This Concept
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ============================================================ -->
<!-- SUGGESTED CONTENT MODAL (Enhance Existing)                   -->
<!-- ============================================================ -->
<div class="modal fade" id="suggestedModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title">
                    <i class="mdi mdi-lightbulb-outline mr-2"></i>Suggested Content
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" id="suggestedModalBody">
                <!-- Content loaded dynamically -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                <button type="button" class="btn btn-success" onclick="storeContent()">
                    <i class="mdi mdi-content-save"></i> Store Content
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ============================================================ -->
<!-- JAVASCRIPT                                                   -->
<!-- ============================================================ -->
<style>
/* Mastery Matrix Styles */
.mastery-progress {
    height: 22px;
    border-radius: 10px;
    background: #e9ecef;
    overflow: hidden;
    min-width: 100px;
}

.mastery-progress-bar {
    height: 100%;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    font-weight: 600;
    color: #fff;
    transition: width 0.8s ease;
}

.mastery-status-badge {
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
}

.concept-row {
    cursor: pointer;
    transition: all 0.2s ease;
    background: #fff;
}

.concept-row:hover {
    background-color: #f4f9ff !important;
}

.concept-row .action-btns {
    opacity: 1;
    transition: opacity 0.2s ease;
}

.concept-row:hover .action-btns {
    opacity: 1;
}

#conceptMasteryModal .modal-content {
    border: 0;
    overflow: hidden;
    box-shadow: 0 20px 50px rgba(15, 23, 42, 0.18);
    filter: none !important;
    backdrop-filter: none !important;
}

#conceptMasteryModal .modal-body {
    background: #ffffff;
    filter: none !important;
    opacity: 1 !important;
}

#conceptMasteryModal .table-responsive {
    overflow: visible;
}

#conceptMasteryModal .table {
    margin-bottom: 0;
    background: #fff;
    border-collapse: collapse;
    filter: none !important;
}

#conceptMasteryModal .table thead th {
    background: #e9eef5;
    color: #334155;
    font-weight: 700;
    border-color: #d6deea;
    vertical-align: middle;
    
}

#conceptMasteryModal .table tbody td {
    background: #fff;
    color: #1f2937;
    vertical-align: middle;
    border-color: #d6deea;
    filter: none !important;
}

#conceptMasteryModal .table tbody tr:hover td {
    background: #f8fbff;
}

#conceptMasteryModal .mastery-progress {
    background: #e5e7eb;
}

#conceptMasteryModal .mastery-progress-bar {
    min-width: 44px;
}

#conceptMasteryModal,
#conceptDetailModal {
    -webkit-font-smoothing: antialiased;
    -moz-osx-font-smoothing: grayscale;
}

#conceptMasteryModal *,
#conceptDetailModal * {
    filter: none !important;
    backdrop-filter: none !important;
}

/* Mapping Cards */
.question-mapping-card {
    margin-top: 15px;
    border: 1px solid #b9d9ff;
    border-radius: 10px;
    background: #f8fbff;
    overflow: hidden;
}

.question-mapping-card__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 10px 15px;
    background: linear-gradient(90deg, #2563eb, #0ea5e9);
    color: #fff;
    font-weight: 700;
}

.question-mapping-list {
    margin: 0;
    padding: 0;
    list-style: none;
}

.question-mapping-list__item {
    display: grid;
    grid-template-columns: minmax(200px, 30%) 1fr;
    gap: 12px;
    padding: 12px 15px;
    border-bottom: 1px solid #dbeafe;
    background: #fff;
}

.question-mapping-list__item:nth-child(even) {
    background: #f0f9ff;
}

.question-mapping-list__type {
    color: #0f172a;
    font-weight: 700;
    padding-left: 10px;
    border-left: 3px solid #2563eb;
}

.question-mapping-chip {
    display: inline-block;
    padding: 4px 12px;
    margin: 2px 4px;
    border-radius: 20px;
    background: #eff6ff;
    color: #1d4ed8;
    font-size: 12px;
    font-weight: 600;
}

/* Animations */
@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.concept-row {
    animation: fadeInUp 0.3s ease;
}
</style>

<script>
// ============================================================
// PHASE 2: MASTERY MATRIX - COMPLETE LOGIC
// ============================================================

let currentConceptId = null;
let masteryData = [];

function bootstrapMasteryMatrix() {
    if (window.jQuery) {
        window.jQuery(function() {
            setTimeout(loadMasteryMatrix, 200);
        });
        return;
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bootstrapMasteryMatrix, { once: true });
        return;
    }

    setTimeout(bootstrapMasteryMatrix, 50);
}

bootstrapMasteryMatrix();

/**
 * Load Mastery Matrix from the current exam results
 */
function loadMasteryMatrix() {
    const standard_id = '{{ $data["questionpaper_data"]["standard_id"] ?? 0 }}';
    const subject_id = '{{ $data["questionpaper_data"]["subject_id"] ?? 0 }}';
    const chapter_id = '{{ $data["questionpaper_data"]["paper_desc"] ?? 0 }}';
    const student_id = '{{ $data["online_exam_data"]["student_id"] ?? session("user_id") ?? 0 }}';

    console.log('Loading Mastery Matrix:', { standard_id, subject_id, chapter_id, student_id });

    // Use the existing exam result data to calculate mastery
    const examQuestions = @json($data['question_arr'] ?? []);
    const examAnswers = @json($data['online_answer_data'] ?? []);

    // If we have exam data, calculate mastery from it
    if (Object.keys(examQuestions).length > 0 && Object.keys(examAnswers).length > 0) {
        calculateMasteryFromExam(examQuestions, examAnswers);
        return;
    }

    // Otherwise fetch from API
    $.ajax({
        url: '/lms/mastery-matrix',
        type: 'GET',
        data: {
            student_id: student_id,
            standard_id: standard_id,
            subject_id: subject_id,
            chapter_id: chapter_id
        },
        success: function(response) {
            console.log('Mastery Matrix API Response:', response);
            if (response.status_code === 1) {
                renderMasteryMatrix(response.data, response.summary);
            } else {
                showError('Error loading mastery data: ' + (response.message || 'Unknown error'));
            }
        },
        error: function(xhr) {
            console.error('Error loading mastery matrix:', xhr);
            showError('Error loading mastery data. Please try again.');
        }
    });
}

/**
 * Calculate mastery from the current exam results (optimized - no extra API call)
 */
function calculateMasteryFromExam(examQuestions, examAnswers) {
    console.log('Calculating mastery from exam results...');

    // Group questions by concept
    const conceptMap = {};
    let totalQuestions = 0;
    let totalCorrect = 0;

    $.each(examQuestions, function(questionIndex, question) {
        const questionId = question.id || questionIndex;
        const conceptName = question.concept || question.concept_name || 'General Concept';
        const conceptId = question.concept_id || conceptName;

        if (!conceptMap[conceptId]) {
            conceptMap[conceptId] = {
                concept_id: conceptId,
                concept_name: conceptName,
                questions: [],
                correct: 0,
                wrong: 0,
                attempted: 0
            };
        }

        const isCorrect = examAnswers[questionId] && examAnswers[questionId].RIGHT_WRONG === 'right';
        const isAttempted = !!examAnswers[questionId];

        conceptMap[conceptId].questions.push(questionId);
        if (isAttempted) {
            conceptMap[conceptId].attempted++;
            if (isCorrect) {
                conceptMap[conceptId].correct++;
                totalCorrect++;
            } else {
                conceptMap[conceptId].wrong++;
            }
            totalQuestions++;
        }
    });

    // Build mastery data
    const masteryResults = [];
    $.each(conceptMap, function(conceptId, data) {
        const total = data.attempted;
        const correct = data.correct;
        const accuracy = total > 0 ? Math.round((correct / total) * 100) : 0;

        // Calculate mastery (weighted for recency)
        const masteryLevel = total > 0 ? Math.min(100, accuracy + (correct * 2)) : 0;

        let status = 'not_started';
        if (total === 0) {
            status = 'not_started';
        } else if (masteryLevel >= 70) {
            status = 'mastered';
        } else if (masteryLevel >= 40) {
            status = 'developing';
        } else {
            status = 'needs_practice';
        }

        masteryResults.push({
            concept_id: data.concept_id,
            concept_name: data.concept_name,
            mastery_level: masteryLevel,
            accuracy: accuracy,
            attempted: data.attempted,
            correct: data.correct,
            wrong: data.wrong,
            status: status,
            total_questions: data.questions.length
        });
    });

    // Sort by mastery level (lowest first)
    masteryResults.sort((a, b) => a.mastery_level - b.mastery_level);

    // Summary
    const summary = {
        total_concepts: masteryResults.length,
        mastered: masteryResults.filter(c => c.status === 'mastered').length,
        developing: masteryResults.filter(c => c.status === 'developing').length,
        needs_practice: masteryResults.filter(c => c.status === 'needs_practice').length,
        not_started: masteryResults.filter(c => c.status === 'not_started').length
    };

    masteryData = masteryResults;
    renderMasteryMatrix(masteryResults, summary);

    // Automatically generate and store suggested content for these mastery levels
    const standard_id = '{{ $data["questionpaper_data"]["standard_id"] ?? 0 }}';
    const subject_id = '{{ $data["questionpaper_data"]["subject_id"] ?? 0 }}';
    const chapter_id = '{{ $data["questionpaper_data"]["paper_desc"] ?? 0 }}';

    $.ajax({
        url: '/lms/store-mastery-suggested-content',
        type: 'POST',
        data: {
            _token: '{{ csrf_token() }}',
            mastery_results: masteryResults,
            standard_id: standard_id,
            subject_id: subject_id,
            chapter_id: chapter_id
        },
        success: function(response) {
            console.log("Mastery Suggested Content Stored", response);
        },
        error: function(xhr) {
            console.error("Error storing mastery suggested content", xhr);
        }
    });
}

/**
 * Render the Mastery Matrix
 */
function renderMasteryMatrix(data, summary) {
    const tbody = $('#masteryMatrixBody');
    tbody.empty();

    masteryData = data || [];

    if (!data || data.length === 0) {
        tbody.html(`
            <tr>
                <td colspan="7" class="text-center py-5">
                    <i class="mdi mdi-information-outline mdi-48px text-muted"></i>
                    <p class="mt-2 text-muted">No concepts found. Complete more quizzes to see your mastery levels.</p>
                    <button class="btn btn-primary btn-sm mt-2" onclick="startNewQuiz()">
                        <i class="mdi mdi-play"></i> Take a Quiz
                    </button>
                </td>
            </tr>
        `);
        return;
    }

    // Update summary
    if (summary) {
        $('#masteredCount').text(summary.mastered || 0);
        $('#developingCount').text(summary.developing || 0);
        $('#needsPracticeCount').text(summary.needs_practice || 0);
        $('#notStartedCount').text(summary.not_started || 0);
        $('#conceptCount').text(`Total: ${summary.total_concepts || data.length} concepts`);
    }

    // Render each concept
    let index = 1;
    data.forEach(concept => {
        const mastery = concept.mastery_level || 0;
        const status = concept.status || 'not_started';

        // Status config
        const statusConfig = {
            mastered: { badge: 'badge-success', text: '✅ Mastered', color: '#28a745' },
            developing: { badge: 'badge-warning', text: '🔄 Developing', color: '#ffc107' },
            needs_practice: { badge: 'badge-danger', text: '⚠️ Needs Practice', color: '#dc3545' },
            not_started: { badge: 'badge-secondary', text: '⏳ Not Started', color: '#6c757d' }
        };

        const config = statusConfig[status] || statusConfig.not_started;
        const progressColor = status === 'mastered' ? 'bg-success' :
                             status === 'developing' ? 'bg-warning' :
                             status === 'needs_practice' ? 'bg-danger' : 'bg-secondary';
        const conceptIdValue = JSON.stringify(concept.concept_id);

        const row = `
            <tr class="concept-row" data-concept-id="${concept.concept_id}" data-status="${status}"
                data-concept-name="${String(concept.concept_name || '').toLowerCase()}" onclick='viewConceptDetails(${conceptIdValue})'>
                <td>${index++}</td>
                <td>
                    <strong>${escapeHtml(concept.concept_name)}</strong>
                </td>
                <td>
                    <div class="mastery-progress">
                        <div class="mastery-progress-bar ${progressColor}" style="width: ${mastery}%;">
                            ${mastery}%
                        </div>
                    </div>
                </td>
                <td><span class="mastery-status-badge ${config.badge}">${config.text}</span></td>
                <td>${concept.accuracy || 0}%</td>
                <td>
                    ${concept.correct || 0} / ${concept.attempted || 0}
                     
                </td>
                <td>
                    <div class="action-btns">
                        <button class="btn btn-sm btn-info" onclick='event.stopPropagation(); viewConceptDetails(${conceptIdValue})' title="View Details">
                            <i class="mdi mdi-eye"></i>
                        </button>
                        <button class="btn btn-sm btn-success" onclick='event.stopPropagation(); startPractice(${conceptIdValue})' title="Practice">
                            <i class="mdi mdi-play"></i>
                        </button>
                        <button class="btn btn-sm btn-warning" onclick="event.stopPropagation(); showSuggestedContent('${status}')" title="Suggested Content">
                            <i class="mdi mdi-lightbulb-outline"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `;

        tbody.append(row);
    });

    // Apply filter if active
    filterMastery();
}

/**
 * Filter Mastery Matrix
 */
function filterMastery() {
    const filter = $('#masteryFilter').val();
    const search = $('#conceptSearch').val().toLowerCase();
    let visibleCount = 0;

    $('#masteryMatrixBody tr').each(function() {
        const status = $(this).data('status');
        const concept = $(this).data('concept-name') || '';

        let show = true;

        if (filter !== 'all' && status !== filter) {
            show = false;
        }

        if (search && !concept.includes(search)) {
            show = false;
        }

        $(this).toggle(show);
        if (show) visibleCount++;
    });

    $('#conceptCount').text(`Showing ${visibleCount} concepts`);
}

/**
 * View Concept Details
 */
function viewConceptDetails(conceptId) {
    currentConceptId = conceptId;
    $('#conceptDetailModal').modal('show');
    $('#conceptDetailBody').html(`
        <div class="text-center py-5">
            <i class="fa fa-spinner fa-spin fa-2x text-primary"></i>
            <p class="mt-3">Loading concept details...</p>
        </div>
    `);

    const student_id = '{{ $data["online_exam_data"]["student_id"] ?? session("user_id") ?? 0 }}';

    $.ajax({
        url: '/lms/concept-details',
        type: 'GET',
        data: {
            concept_id: conceptId,
            student_id: student_id
        },
        success: function(response) {
            if (response.status_code === 1) {
                renderConceptDetails(response.data);
            } else {
                $('#conceptDetailBody').html(`
                    <div class="alert alert-danger">${response.message || 'Error loading details'}</div>
                `);
            }
        },
        error: function() {
            // If API fails, use exam data
            const examQuestions = @json($data['question_arr'] ?? []);
            const examAnswers = @json($data['online_answer_data'] ?? []);
            renderConceptDetailsFromExam(conceptId, examQuestions, examAnswers);
        }
    });
}

/**
 * Render Concept Details from Exam Data (fallback)
 */
function renderConceptDetailsFromExam(conceptId, examQuestions, examAnswers) {
    let conceptQuestions = [];
    let correct = 0;
    let wrong = 0;

    $.each(examQuestions, function(questionIndex, question) {
        const questionId = question.id || questionIndex;
        const questionConceptId = question.concept_id || question.concept || question.concept_name || 'General Concept';

        if (questionConceptId == conceptId) {
            const isCorrect = examAnswers[questionId] && examAnswers[questionId].RIGHT_WRONG === 'right';
            conceptQuestions.push({
                id: questionId,
                title: question.question_title,
                concept_name: question.concept || question.concept_name || 'General Concept',
                is_correct: isCorrect
            });
            if (examAnswers[questionId]) {
                if (isCorrect) correct++;
                else wrong++;
            }
        }
    });

    const total = conceptQuestions.length;
    const mastery = total > 0 ? Math.round((correct / total) * 100) : 0;

    let html = `
        <div class="row mb-3">
            <div class="col-md-8">
                <h4>Concept: ${escapeHtml(conceptQuestions.length > 0 ? conceptQuestions[0].concept_name || 'Concept' : 'Unknown')}</h4>
            </div>
            <div class="col-md-4 text-right">
                <span class="badge badge-info">${total} Questions</span>
            </div>
        </div>
        <div class="row mb-4">
            <div class="col-4 text-center">
                <h3 class="text-primary">${total}</h3>
                <small>Total Questions</small>
            </div>
            <div class="col-4 text-center">
                <h3 class="text-success">${correct}</h3>
                <small>Correct</small>
            </div>
            <div class="col-4 text-center">
                <h3 class="text-danger">${wrong}</h3>
                <small>Wrong</small>
            </div>
        </div>
        <div class="row mb-3">
            <div class="col-12">
                <div class="mastery-progress" style="height:30px;">
                    <div class="mastery-progress-bar ${mastery >= 70 ? 'bg-success' : mastery >= 40 ? 'bg-warning' : 'bg-danger'}"
                         style="width: ${mastery}%; font-size:14px;">
                        Mastery: ${mastery}%
                    </div>
                </div>
            </div>
        </div>
        <h6 class="mt-3"><i class="mdi mdi-format-list-bulleted"></i> Questions in this concept:</h6>
        <div class="list-group">
    `;

    conceptQuestions.forEach(q => {
        const icon = q.is_correct ? '✅' : '❌';
        const cls = q.is_correct ? 'text-success' : 'text-danger';
        html += `
            <div class="list-group-item d-flex justify-content-between align-items-center">
                <span>${escapeHtml(q.title.substring(0, 100))}</span>
                <span class="${cls}">${icon}</span>
            </div>
        `;
    });

    html += '</div>';
    $('#conceptDetailBody').html(html);
}

/**
 * Render Concept Details from API
 */
function renderConceptDetails(data) {
    const concept = data.concept;
    const wrongQuestions = data.wrong_questions || [];
    const total = data.total_attempts || 0;
    const correct = data.correct_attempts || 0;
    const wrong = data.wrong_attempts || 0;
    const mastery = total > 0 ? Math.round((correct / total) * 100) : 0;

    let html = `
        <div class="row mb-3">
            <div class="col-md-8">
                <h4>${escapeHtml(concept.name)}</h4>
                <p class="text-muted">${escapeHtml(concept.description || 'No description available')}</p>
            </div>
            <div class="col-md-4 text-right">
                <span class="badge badge-info">${data.total_questions || 0} Total Questions</span>
            </div>
        </div>
        <div class="row mb-4">
            <div class="col-3 text-center">
                <h3 class="text-primary">${total}</h3>
                <small>Attempts</small>
            </div>
            <div class="col-3 text-center">
                <h3 class="text-success">${correct}</h3>
                <small>Correct</small>
            </div>
            <div class="col-3 text-center">
                <h3 class="text-danger">${wrong}</h3>
                <small>Wrong</small>
            </div>
            <div class="col-3 text-center">
                <h3 class="${mastery >= 70 ? 'text-success' : mastery >= 40 ? 'text-warning' : 'text-danger'}">${mastery}%</h3>
                <small>Mastery</small>
            </div>
        </div>
        <div class="row mb-3">
            <div class="col-12">
                <div class="mastery-progress" style="height:30px;">
                    <div class="mastery-progress-bar ${mastery >= 70 ? 'bg-success' : mastery >= 40 ? 'bg-warning' : 'bg-danger'}"
                         style="width: ${mastery}%; font-size:14px;">
                        Mastery: ${mastery}%
                    </div>
                </div>
            </div>
        </div>
    `;

    // Wrong questions section
    if (wrongQuestions.length > 0) {
        html += `
            <h6 class="mt-3 text-danger"><i class="mdi mdi-alert-circle"></i> Misconceptions (${wrongQuestions.length} questions)</h6>
            <div class="list-group">
        `;
        wrongQuestions.forEach(q => {
            html += `
                <div class="list-group-item list-group-item-danger d-flex justify-content-between align-items-center">
                    <span>${escapeHtml(q.question_title || 'Question')}</span>
                    <span class="badge badge-danger">Wrong ${q.wrong_count || 1} times</span>
                </div>
            `;
        });
        html += '</div>';
    } else {
        html += `
            <div class="alert alert-success mt-3">
                <i class="mdi mdi-check-circle"></i> No wrong questions found for this concept!
            </div>
        `;
    }

    // Mapping information
    if (data.mapping && data.mapping.length > 0) {
        html += `
            <div class="question-mapping-card mt-3">
                <div class="question-mapping-card__header">
                    <span>Concept Mappings</span>
                    <span class="badge badge-light">${data.mapping.length} mappings</span>
                </div>
                <ul class="question-mapping-list">
        `;
        data.mapping.forEach(m => {
            html += `
                <li class="question-mapping-list__item">
                    <div class="question-mapping-list__type">${escapeHtml(m.type_name || 'Mapping')}</div>
                    <div class="question-mapping-list__values">
                        <span class="question-mapping-chip">${escapeHtml(m.value_name || 'Value')}</span>
                        ${m.reason ? `<span class="text-muted">${escapeHtml(m.reason)}</span>` : ''}
                    </div>
                </li>
            `;
        });
        html += '</ul></div>';
    }

    $('#conceptDetailBody').html(html);
}

/**
 * Start Practice for a concept
 */
function startPractice(conceptId) {
    const standard_id = '{{ $data["questionpaper_data"]["standard_id"] ?? 0 }}';
    const subject_id = '{{ $data["questionpaper_data"]["subject_id"] ?? 0 }}';
    const chapter_id = '{{ $data["questionpaper_data"]["paper_desc"] ?? 0 }}';

    // Close modal if open
    $('#conceptDetailModal').modal('hide');

    // Redirect to practice page with concept filter
    window.location.href = `/lms/pal/create?standard_id=${standard_id}&subject_id=${subject_id}&chapter_id=${chapter_id}&concept_id=${encodeURIComponent(conceptId)}`;
}

/**
 * Practice from Modal
 */
function practiceFromModal() {
    if (currentConceptId) {
        startPractice(currentConceptId);
    } else {
        alert('Please select a concept first');
    }
}

/**
 * Show Suggested Content
 */
function showSuggestedContent(level) {
    const standard_id = '{{ $data["questionpaper_data"]["standard_id"] ?? 0 }}';
    const subject_id = '{{ $data["questionpaper_data"]["subject_id"] ?? 0 }}';
    const chapter_id = '{{ $data["questionpaper_data"]["paper_desc"] ?? 0 }}';

    // Determine next level based on current mastery
    let nextLevel = 'medium';
    if (level === 'mastered') nextLevel = 'hard';
    else if (level === 'developing') nextLevel = 'medium';
    else if (level === 'needs_practice' || level === 'not_started') nextLevel = 'easy';

    // Call existing showSuggestedContent function
    window.showSuggestedContent(nextLevel);
}

/**
 * Start New Quiz
 */
function startNewQuiz() {
    const chapter_id = '{{ $data["questionpaper_data"]["paper_desc"] ?? 0 }}';
    const standard_id = '{{ $data["questionpaper_data"]["standard_id"] ?? 0 }}';
    const subject_id = '{{ $data["questionpaper_data"]["subject_id"] ?? 0 }}';
    const enrollment_no = '{{ $data["online_exam_data"]["student_id"] ?? 0 }}';

    if (chapter_id) {
        window.location.href = `/lms/pal/create?subject_id=${subject_id}&chapter_id=${chapter_id}&standard_id=${standard_id}&enrollment_no=${enrollment_no}`;
    }
}

/**
 * Refresh Mastery Matrix
 */
function refreshMasteryMatrix() {
    $('#masteryMatrixBody').html(`
        <tr>
            <td colspan="7" class="text-center py-3">
                <i class="fa fa-spinner fa-spin"></i> Refreshing...
            </td>
        </tr>
    `);
    loadMasteryMatrix();
}

/**
 * Show Concept Details Modal (from button)
 */
function showConceptDetailsModal() {
    // Find first concept with needs_practice or developing
    const firstConcept = masteryData.find(c => c.status === 'needs_practice' || c.status === 'developing');
    if (firstConcept) {
        viewConceptDetails(firstConcept.concept_id);
    } else if (masteryData.length > 0) {
        viewConceptDetails(masteryData[0].concept_id);
    } else {
        alert('No concepts available. Complete a quiz to see your mastery data.');
    }
}

/**
 * Utility: Escape HTML
 */
function escapeHtml(text) {
    if (!text) return '';
    return String(text)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

/**
 * Utility: Show Error
 */
function showError(message) {
    $('#masteryMatrixBody').html(`
        <tr>
            <td colspan="7" class="text-center text-danger py-4">
                <i class="mdi mdi-alert-circle mdi-36px"></i>
                <p class="mt-2">${escapeHtml(message)}</p>
                <button class="btn btn-primary btn-sm mt-2" onclick="loadMasteryMatrix()">
                    <i class="mdi mdi-refresh"></i> Retry
                </button>
            </td>
        </tr>
    `);
}
</script>

<!-- ============================================================
PHASE 3: ADAPTIVE PRACTICE UI (MODAL)
============================================================ -->
<div class="modal fade" id="adaptivePracticeModal" tabindex="-1" role="dialog" aria-labelledby="adaptivePracticeModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header bg-success text-white d-flex justify-content-between align-items-center">
                <h5 class="modal-title mb-0" id="adaptivePracticeModalLabel">
                    <i class="mdi mdi-brain mr-2"></i>Adaptive Practice
                </h5>
                <div class="d-flex align-items-center">
                    <button class="btn btn-light btn-sm mr-2" onclick="startAdaptivePractice()">
                        <i class="mdi mdi-play"></i> Start Practice
                    </button>
                    <button class="btn btn-info btn-sm mr-2" onclick="showPracticeHistory()">
                        <i class="mdi mdi-history"></i> History
                    </button>
                    <button type="button" class="close text-white ml-2" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            </div>
            <div class="modal-body">
        <!-- Practice Strategy Display -->
        <div id="practiceStrategy" class="alert alert-info d-none">
            <h6><i class="mdi mdi-lightbulb-on"></i> <span id="strategyTitle">Loading strategy...</span></h6>
            <p id="strategyDescription" class="mb-0"></p>
        </div>

        <!-- Spaced Repetition Schedule -->
        <div id="spacedRepetition" class="mb-3 d-none">
            <h6><i class="mdi mdi-clock-outline"></i> Concepts Due for Review</h6>
            <div id="reviewSchedule" class="row"></div>
        </div>

        <!-- Practice Questions Container -->
        <div id="practiceQuestionsContainer" class="d-none">
            <div id="practiceProgress" class="mb-3">
                <div class="d-flex justify-content-between">
                    <span>Question <span id="currentQuestionNum">1</span> of <span id="totalQuestionsCount">0</span></span>
                    <span>Score: <span id="practiceScore">0</span>%</span>
                </div>
                <div class="progress" style="height: 8px;">
                    <div class="progress-bar bg-success" id="practiceProgressBar" style="width: 0%;"></div>
                </div>
            </div>
            <div id="practiceQuestions"></div>
            <div class="text-center mt-3">
                <button class="btn btn-success" id="submitPracticeBtn" onclick="submitPractice()" disabled>
                    <i class="mdi mdi-check"></i> Submit Practice
                </button>
            </div>
        </div>

        <!-- Practice Results -->
        <div id="practiceResults" class="d-none">
            <div class="alert alert-success">
                <h5><i class="mdi mdi-check-circle"></i> Practice Complete!</h5>
                <div class="row mt-3">
                    <div class="col-3 text-center">
                        <h3 id="resultCorrect">0</h3>
                        <small>Correct</small>
                    </div>
                    <div class="col-3 text-center">
                        <h3 id="resultWrong">0</h3>
                        <small>Wrong</small>
                    </div>
                    <div class="col-3 text-center">
                        <h3 id="resultPercentage">0%</h3>
                        <small>Score</small>
                    </div>
                    <div class="col-3 text-center">
                        <h3 id="resultMastery">0%</h3>
                        <small>New Mastery</small>
                    </div>
                </div>
            </div>
            <div id="resultDetails"></div>
            <div class="text-center mt-3">
                <button class="btn btn-primary" onclick="startAdaptivePractice()">
                    <i class="mdi mdi-repeat"></i> Practice Again
                </button>
                <button class="btn btn-info" onclick="showSuggestedContentAfterPractice()">
                    <i class="mdi mdi-lightbulb-outline"></i> Suggested Content
                </button>
            </div>
        </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- ============================================================
PHASE 3: JAVASCRIPT
============================================================ -->
<script>
// ============================================================
// PHASE 3: ADAPTIVE PRACTICE - COMPLETE LOGIC
// ============================================================

let currentPracticeQuestions = [];
let currentPracticeAnswers = {};
let currentPracticeStrategy = null;
let currentQuestionIndex = 0;
let practiceScore = 0;
let practiceAttempted = 0;

/**
 * Start Adaptive Practice
 */
function startAdaptivePractice() {
    console.log('=== Starting Adaptive Practice ===');
    
    const student_id = getStudentId();
    const standard_id = '{{ $data["questionpaper_data"]["standard_id"] ?? 0 }}';
    const subject_id = '{{ $data["questionpaper_data"]["subject_id"] ?? 0 }}';
    const chapter_id = '{{ $data["questionpaper_data"]["paper_desc"] ?? 0 }}';
    const concept_id = 0; // Auto-detect from mastery
    
    // Show loading state
    $('#practiceStrategy').removeClass('d-none').html(`
        <h6><i class="mdi mdi-loading mdi-spin"></i> Analyzing your mastery...</h6>
    `);
    
    $.ajax({
        url: '/lms/adaptive-practice',
        type: 'GET',
        data: {
            student_id: student_id,
            standard_id: standard_id,
            subject_id: subject_id,
            chapter_id: chapter_id,
            concept_id: concept_id,
            question_count: 5
        },
        success: function(response) {
            console.log('Adaptive Practice Response:', response);
            
            if (response.status_code === 1) {
                currentPracticeQuestions = response.data.questions || [];
                currentPracticeStrategy = response.data.strategy || null;
                
                // Show strategy
                displayPracticeStrategy(currentPracticeStrategy);
                
                // Show spaced repetition
                loadSpacedRepetition();
                
                // Start practice
                if (currentPracticeQuestions.length > 0) {
                    startPracticeSession();
                } else {
                    $('#practiceQuestionsContainer').removeClass('d-none').html(`
                        <div class="alert alert-warning">
                            <i class="mdi mdi-alert"></i> No practice questions available. Try a different concept.
                        </div>
                    `);
                }
            } else {
                alert(response.message || 'Error generating practice questions');
            }
        },
        error: function(xhr) {
            console.error('Error:', xhr);
            alert('Error generating practice questions. Please try again.');
        }
    });
}

/**
 * Display Practice Strategy
 */
function displayPracticeStrategy(strategy) {
    if (!strategy) return;
    
    $('#practiceStrategy').removeClass('d-none');
    $('#strategyTitle').text(strategy.description || 'Practice Session');
    $('#strategyDescription').html(`
        <strong>Type:</strong> ${strategy.type.charAt(0).toUpperCase() + strategy.type.slice(1)} &bull;
        <strong>Difficulty:</strong> ${strategy.difficulty || 'Mixed'} &bull;
        <strong>Questions:</strong> ${currentPracticeQuestions.length}
        ${strategy.hints ? ' &bull; <span class="badge badge-info">Hints Available</span>' : ''}
    `);
}

/**
 * Start Practice Session
 */
function startPracticeSession() {
    currentQuestionIndex = 0;
    practiceScore = 0;
    practiceAttempted = 0;
    currentPracticeAnswers = {};
    
    $('#practiceQuestionsContainer').removeClass('d-none');
    $('#practiceResults').addClass('d-none');
    $('#submitPracticeBtn').prop('disabled', true);
    
    renderPracticeQuestion();
}

/**
 * Render Practice Question
 */
function renderPracticeQuestion() {
    if (currentQuestionIndex >= currentPracticeQuestions.length) {
        // All questions answered
        $('#submitPracticeBtn').prop('disabled', false);
        $('#currentQuestionNum').text(currentPracticeQuestions.length);
        return;
    }
    
    const question = currentPracticeQuestions[currentQuestionIndex];
    const total = currentPracticeQuestions.length;
    
    // Update progress
    $('#currentQuestionNum').text(currentQuestionIndex + 1);
    $('#totalQuestionsCount').text(total);
    $('#practiceProgressBar').css('width', ((currentQuestionIndex) / total * 100) + '%');
    
    let html = `
        <div class="card mb-3" id="question_${question.id}">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <span class="badge badge-primary">Q${currentQuestionIndex + 1}/${total}</span>
                    <span class="badge badge-${question.difficulty === 'Easy' ? 'success' : question.difficulty === 'Hard' ? 'danger' : 'warning'}">
                        ${question.difficulty || 'Medium'}
                    </span>
                </div>
                <h6 class="mt-2">${escapeHtml(question.question_title)}</h6>
                ${question.hint_text ? `<small class="text-muted"><i class="mdi mdi-lightbulb-outline"></i> Hint: ${escapeHtml(question.hint_text)}</small>` : ''}
                <div class="mt-3">
    `;
    
    // Render options based on question type
    if (question.question_type_id == 1) {
        // MCQ
        const options = question.options || [];
        const multipleAnswer = question.multiple_answer || 0;
        
        html += `<div class="options-container">`;
        options.forEach((opt, idx) => {
            const inputType = multipleAnswer ? 'checkbox' : 'radio';
            const name = `answer_${question.id}`;
            const value = opt.id || opt.answer_id || idx;
            
            html += `
                <div class="form-check mb-2">
                    <input class="form-check-input" type="${inputType}" 
                           name="${name}" value="${value}" 
                           id="opt_${question.id}_${idx}"
                           onchange="selectPracticeOption(${question.id}, '${inputType}', this)">
                    <label class="form-check-label" for="opt_${question.id}_${idx}">
                        ${escapeHtml(opt.answer || opt.text || 'Option ' + (idx + 1))}
                    </label>
                </div>
            `;
        });
        html += `</div>`;
    } else {
        // Narrative/Other question types
        html += `
            <div class="form-group">
                <textarea class="form-control" id="narrative_${question.id}" 
                          rows="4" placeholder="Type your answer here..."
                          onchange="selectNarrativeOption(${question.id}, this.value)"></textarea>
            </div>
        `;
    }
    
    html += `
                </div>
                ${question.concept_name ? `<small class="text-muted d-block mt-2">Concept: ${escapeHtml(question.concept_name)}</small>` : ''}
            </div>
        </div>
    `;
    
    $('#practiceQuestions').html(html);
    
    // Pre-select previous answer if exists
    if (currentPracticeAnswers[question.id]) {
        const answer = currentPracticeAnswers[question.id];
        if (Array.isArray(answer)) {
            answer.forEach(val => {
                $(`input[name="answer_${question.id}"][value="${val}"]`).prop('checked', true);
            });
        } else {
            $(`input[name="answer_${question.id}"][value="${answer}"]`).prop('checked', true);
        }
    }
}

/**
 * Select Practice Option
 */
function selectPracticeOption(questionId, type, element) {
    if (type === 'checkbox') {
        // Multiple answer
        const selected = [];
        $(`input[name="answer_${questionId}"]:checked`).each(function() {
            selected.push($(this).val());
        });
        currentPracticeAnswers[questionId] = selected;
    } else {
        // Single answer
        currentPracticeAnswers[questionId] = $(element).val();
    }
    
    // Check if current question is answered
    checkAllQuestionsAnswered();
}

/**
 * Select Narrative Option
 */
function selectNarrativeOption(questionId, value) {
    currentPracticeAnswers[questionId] = value;
    checkAllQuestionsAnswered();
}

/**
 * Check if all questions are answered
 */
function checkAllQuestionsAnswered() {
    let allAnswered = true;
    currentPracticeQuestions.forEach(q => {
        if (!currentPracticeAnswers[q.id]) {
            allAnswered = false;
        }
    });
    
    $('#submitPracticeBtn').prop('disabled', !allAnswered);
}

/**
 * Submit Practice
 */
function submitPractice() {
    console.log('=== Submitting Practice ===');
    console.log('Current Answers:', currentPracticeAnswers);
    console.log('Total Questions:', currentPracticeQuestions.length);
    
    // Check if all questions are answered
    var allAnswered = true;
    var unanswered = [];
    
    currentPracticeQuestions.forEach(function(q) {
        if (!currentPracticeAnswers[q.id] || currentPracticeAnswers[q.id] === '') {
            allAnswered = false;
            unanswered.push(q.id);
        }
    });
    
    if (!allAnswered) {
        alert('⚠️ Please answer all questions before submitting. (Question IDs: ' + unanswered.join(', ') + ')');
        return;
    }
    
    var studentId = getStudentId();
    console.log('Student ID:', studentId);
    
    if (!studentId || studentId === '0' || studentId === '') {
        alert('⚠️ Student ID not found. Please log in again.');
        return;
    }
    
    var $btn = $('#submitPracticeBtn');
    $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm mr-1"></span> Submitting...');
    
    // Prepare data
    var formData = {
        _token: $('meta[name="csrf-token"]').attr('content') || '{{ csrf_token() }}',
        student_id: studentId,
        practice_id: Date.now(),
        answers: currentPracticeAnswers
    };
    
    console.log('Sending Data:', formData);
    
    $.ajax({
        url: '/lms/submit-practice',
        type: 'POST',
        data: formData,
        dataType: 'json',
        timeout: 30000, // 30 second timeout
        success: function(response) {
            console.log('Practice Submitted Response:', response);
            
            if (response.status_code === 1) {
                displayPracticeResults(response.data);
                
                // Show success message
                var summary = response.data.summary || {};
                alert('✅ Practice submitted successfully!\n' +
                      'Correct: ' + summary.total_correct + '\n' +
                      'Wrong: ' + summary.total_wrong + '\n' +
                      'Score: ' + summary.percentage + '%');
                      
            } else {
                alert('❌ Error: ' + (response.message || 'Unknown error occurred'));
                $btn.prop('disabled', false).html('<i class="mdi mdi-check"></i> Submit Practice');
            }
        },
        error: function(xhr, status, error) {
            console.error('Submit Practice Error:', {
                status: status,
                error: error,
                response: xhr.responseText,
                statusCode: xhr.status
            });
            
            var errorMsg = 'Error submitting practice.\n';
            
            if (xhr.status === 419) {
                errorMsg += 'Session expired. Please refresh the page and try again.';
            } else if (xhr.status === 500) {
                errorMsg += 'Server error. Please check the console for details.';
            } else if (xhr.responseJSON && xhr.responseJSON.message) {
                errorMsg += xhr.responseJSON.message;
            } else {
                errorMsg += 'Please try again.';
            }
            
            alert('❌ ' + errorMsg);
            $btn.prop('disabled', false).html('<i class="mdi mdi-check"></i> Submit Practice');
        }
    });
}

/**
 * Display Practice Results
 */
function displayPracticeResults(data) {
    $('#practiceQuestionsContainer').addClass('d-none');
    $('#practiceResults').removeClass('d-none');
    
    const summary = data.summary || {};
    const mastery = data.updated_mastery || [];
    
    $('#resultCorrect').text(summary.total_correct || 0);
    $('#resultWrong').text(summary.total_wrong || 0);
    $('#resultPercentage').text(summary.percentage || 0 + '%');
    
    // Calculate average mastery
    let avgMastery = 0;
    if (mastery.length > 0) {
        avgMastery = mastery.reduce((sum, c) => sum + (c.mastery_level || 0), 0) / mastery.length;
    }
    $('#resultMastery').text(Math.round(avgMastery) + '%');
    
    // Show result details
    let detailsHtml = '<h6>Question Details:</h6><div class="list-group">';
    data.results.forEach((r, idx) => {
        const question = currentPracticeQuestions.find(q => q.id == r.question_id);
        const icon = r.correct ? '✅' : '❌';
        const cls = r.correct ? 'list-group-item-success' : 'list-group-item-danger';
        detailsHtml += `
            <div class="list-group-item ${cls} d-flex justify-content-between align-items-center">
                <span>Q${idx + 1}: ${escapeHtml(question ? question.question_title.substring(0, 50) : 'Question ' + r.question_id)}</span>
                <span>${icon} ${r.correct ? 'Correct' : 'Wrong'} (${r.points || 1} pts)</span>
            </div>
        `;
    });
    detailsHtml += '</div>';
    $('#resultDetails').html(detailsHtml);
    
    // Show next practice recommendation
    if (data.next_practice_recommendation) {
        const rec = data.next_practice_recommendation;
        $('#resultDetails').append(`
            <div class="alert alert-info mt-3">
                <strong><i class="mdi mdi-bullseye"></i> Next Step:</strong> ${rec.message}
            </div>
        `);
    }
}

/**
 * Load Spaced Repetition Schedule
 */
function loadSpacedRepetition() {
    const student_id = getStudentId();
    
    $.ajax({
        url: '/lms/spaced-repetition',
        type: 'GET',
        data: { student_id: student_id },
        success: function(response) {
            if (response.status_code === 1 && response.data) {
                renderSpacedRepetition(response.data);
            }
        },
        error: function(xhr) {
            console.error('Error loading spaced repetition:', xhr);
        }
    });
}

/**
 * Render Spaced Repetition Schedule
 */
function renderSpacedRepetition(schedule) {
    const hasContent = Object.values(schedule).some(arr => arr.length > 0);
    
    if (!hasContent) {
        $('#spacedRepetition').addClass('d-none');
        return;
    }
    
    $('#spacedRepetition').removeClass('d-none');
    
    let html = '';
    const sections = [
        { key: 'today', label: '🔴 Today', class: 'danger' },
        { key: 'tomorrow', label: '🟡 Tomorrow', class: 'warning' },
        { key: 'this_week', label: '🟢 This Week', class: 'info' },
        { key: 'next_week', label: '🔵 Next Week', class: 'secondary' }
    ];
    
    sections.forEach(section => {
        const items = schedule[section.key] || [];
        if (items.length > 0) {
            html += `
                <div class="col-md-3 col-6 mb-2">
                    <div class="card border-${section.class}">
                        <div class="card-body p-2 text-center">
                            <strong class="text-${section.class}">${section.label}</strong>
                            <div class="small">${items.map(item => escapeHtml(item.concept_name)).join(', ')}</div>
                        </div>
                    </div>
                </div>
            `;
        }
    });
    
    $('#reviewSchedule').html(html);
}

/**
 * Show Practice History
 */
function showPracticeHistory() {
    const student_id = getStudentId();
    
    $('#practiceHistoryModal').modal('show');
    $('#practiceHistoryModal .modal-body').html(`
        <div class="text-center"><i class="fa fa-spinner fa-spin"></i> Loading history...</div>
    `);
    
    $.ajax({
        url: '/lms/practice-history',
        type: 'GET',
        data: { student_id: student_id, limit: 20 },
        success: function(response) {
            if (response.status_code === 1) {
                renderPracticeHistory(response.data);
            } else {
                $('#practiceHistoryModal .modal-body').html(`
                    <div class="alert alert-danger">${response.message || 'Error loading history'}</div>
                `);
            }
        },
        error: function(xhr) {
            $('#practiceHistoryModal .modal-body').html(`
                <div class="alert alert-danger">Error loading practice history</div>
            `);
        }
    });
}

/**
 * Render Practice History
 */
function renderPracticeHistory(data) {
    const history = data.history || [];
    const weekly = data.weekly_progress || [];
    
    let html = `
        <div class="row mb-3 practice-history-summary">
            <div class="col-4 text-center">
                <h4>${data.total_practiced || 0}</h4>
                <small>Total Questions</small>
            </div>
            <div class="col-4 text-center">
                <h4>${data.accuracy || 0}%</h4>
                <small>Accuracy</small>
            </div>
            <div class="col-4 text-center">
                <h4>${history.filter(h => h.ans_status == 1).length || 0}</h4>
                <small>Correct Answers</small>
            </div>
        </div>
    `;
    
    if (weekly.length > 0) {
        html += `<h6 class="practice-history-section-title">Weekly Progress</h6><div class="table-responsive practice-history-weekly-wrap">`;
        html += `<table class="table table-sm table-bordered practice-history-weekly-table">`;
        html += `<thead><tr><th>Date</th><th>Attempted</th><th>Correct</th><th>Accuracy</th></tr></thead><tbody>`;
        weekly.forEach(day => {
            const acc = day.total > 0 ? Math.round(day.correct / day.total * 100) : 0;
            html += `<tr>
                <td>${day.date}</td>
                <td>${day.total}</td>
                <td>${day.correct}</td>
                <td>${acc}%</td>
            </tr>`;
        });
        html += `</tbody></table></div>`;
    }
    
    html += `<h6 class="mt-3 practice-history-section-title">Recent Practice</h6><div class="list-group practice-history-list">`;
    history.forEach(h => {
        const icon = h.ans_status == 1 ? '✅' : '❌';
        html += `
            <div class="list-group-item d-flex justify-content-between align-items-center">
                <div>
                    <strong>${escapeHtml(h.concept_name || 'General')}</strong>
                    <div class="small text-muted">${escapeHtml(h.question_title || '').substring(0, 80)}</div>
                </div>
                <div>
                    <span class="badge ${h.ans_status == 1 ? 'badge-success' : 'badge-danger'}">${icon} ${h.status_text || (h.ans_status == 1 ? 'Correct' : 'Wrong')}</span>
                    <div class="small text-muted">${h.created_at ? new Date(h.created_at).toLocaleDateString() : ''}</div>
                </div>
            </div>
        `;
    });
    html += '</div>';
    
    $('#practiceHistoryModal .modal-body').html(html);
}

/**
 * Show Suggested Content After Practice
 */
function showSuggestedContentAfterPractice() {
    // Get weak concepts from practice results
    const weakConcepts = currentPracticeQuestions
        .filter(q => {
            const answer = currentPracticeAnswers[q.id];
            // Check if answer was wrong (we don't have correct/incorrect info here)
            return true;
        })
        .map(q => q.concept_id)
        .filter(id => id);
    
    if (weakConcepts.length > 0) {
        showSuggestedContent('medium');
    } else {
        showSuggestedContent('medium');
    }
}

/**
 * Get Student ID
 */
function getStudentId() {
    // Try multiple sources
    var sources = [
        '{{ $data["online_exam_data"]["student_id"] ?? 0 }}',
        '{{ $data["online_exam_data"]["enrollment_no"] ?? 0 }}',
        '{{ session("user_id") ?? 0 }}',
        '{{ $data["questionpaper_data"]["student_id"] ?? 0 }}'
    ];
    
    for (var i = 0; i < sources.length; i++) {
        var id = sources[i];
        if (id && id !== '0' && id !== '') {
            console.log('Student ID found from source ' + i + ':', id);
            return id;
        }
    }
    
    // Try to get from hidden input
    var hiddenId = $('#student_id_hidden').val();
    if (hiddenId && hiddenId !== '0' && hiddenId !== '') {
        return hiddenId;
    }
    
    console.warn('No student ID found in any source');
    return '0';
}
</script>

<!-- ============================================================
PHASE 3: PRACTICE HISTORY MODAL
============================================================ -->
<div class="modal fade" id="practiceHistoryModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">
                    <i class="mdi mdi-history mr-2"></i>Practice History
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <!-- Dynamic content -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
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
@include('includes.footer')
