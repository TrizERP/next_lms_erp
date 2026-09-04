<style>
    .dot {
        height: 13px;
        width: 13px;
        background-color: #bbb;
        border-radius: 50%;
        display: inline-block;
    }
    .square {
        height: 12px;
        width: 12px;
        background-color: #bbb;
        display: inline-block;
    }
    .table td, .table th {
        padding: 18px;
    }
    #questionpaper tbody tr th thead th {
        border-color: #ffffff !important;
    }
    tbody tr th th {
        color: #ffffff;
    }

    @media print { 
        table {
            border: solid #000 !important;
            border-width: 1px 0 0 1px !important;
        }
        th, td {
            border: solid #000 !important;
            border-width: 0 1px 1px 0 !important;
        }    
    }
    .table-bordered {
        border: 1px solid #dee2e6;
    }
    .table {
        width: 100%;
        margin-bottom: 1rem;
        color: #212529;
    }
    .table-striped tbody tr:nth-of-type(odd) {
        background-color: rgba(0, 0, 0, 0.05);
    }
    .section-header {
        background: #25bdea;
        color: white;
        padding: 10px 15px;
        margin: 20px 0 10px 0;
        border-radius: 4px;
    }
    .answer-key-section {
        background: #f8f9fa;
        padding: 20px;
        margin-top: 30px;
        border: 2px solid #dee2e6;
    }
    .answer-key-table {
        background: white;
    }
    .set-badge {
        background: #667eea;
        color: white;
        padding: 5px 15px;
        border-radius: 20px;
        font-size: 14px;
        margin-bottom: 10px;
        display: inline-block;
    }
    .instructions {
        background: #fff3cd;
        padding: 15px;
        margin-bottom: 20px;
        border: 1px solid #ffeeba;
        border-radius: 4px;
    }
    /*
     * Dompdf cannot reliably page-break inside a <table> nested inside
     * another <table>'s cell (rows silently overflow the page instead of
     * flowing to a new one). The question list below is therefore built
     * from block-level <div>s — Dompdf paginates those correctly — and
     * only the small per-question answer list stays a single, unnested
     * <table>.
     */
    .paper-header-table {
        width: 100%;
        margin-bottom: 10px;
    }
    .question-block {
        page-break-inside: avoid;
        margin-bottom: 12px;
    }
    .question-title {
        text-align: left;
        background: #303030;
        color: #ffffff;
        padding: 10px;
    }
    .answer-table {
        width: 100%;
        margin: 0;
    }
    .answer-table td {
        text-align: left;
        padding: 8px 12px;
    }
</style>
<div id="page-wrapper">
    <div class="container-fluid">
    <div class="row">
        <div class="white-box">
            <div class="panel-body">
                <div class="col-lg-12 col-sm-12 col-xs-12">
                    <table class="table table-striped table-bordered paper-header-table" style="background:#25bdea;padding: 15px;">
                        <thead>
                            <tr>
                                <th colspan="2" class="text-center">
                                    <h2>{{$data['questionpaper_data']['paper_name']}}</h2>
                                    @if(isset($data['questionpaper_data']['set_name']))
                                        <span class="set-badge">{{$data['questionpaper_data']['set_name']}}</span>
                                    @endif
                                </th>
                            </tr>
                            <tr>
                                <th>Total Marks: {{$data['questionpaper_data']['total_marks']}}</th>
                                <th class="text-left">Total Questions: {{$data['questionpaper_data']['total_ques']}}
                                @if( $data['questionpaper_data']['timelimit_enable'] == 1 )
                                <span style="float:right;">({{$data['questionpaper_data']['time_allowed']}} mins)</span>
                                @endif
                                </th>
                            </tr>
                        </thead>
                    </table>

                    @if(isset($data['questionpaper_data']['paper_desc']) && !empty($data['questionpaper_data']['paper_desc']))
                    <div class="instructions">
                        <strong>Instructions:</strong> {{$data['questionpaper_data']['paper_desc']}}
                    </div>
                    @endif

                    <div id="questionpaper">
                        @php
                        $i = 1;
                        $sectionLabels = ['A', 'B', 'C', 'D', 'E'];
                        $currentSection = 0;
                        $questionNumber = 1;
                        @endphp

                        @if(isset($data['sections']) && count($data['sections']) > 0)
                            {{-- Display with sections --}}
                            @foreach($data['sections'] as $sectionIdx => $section)
                            <div class="section-header">
                                <strong>Section {{ $sectionLabels[$sectionIdx] ?? chr(65 + $sectionIdx) }}</strong>
                                <span style="float:right;">[{{ $section['total_marks'] }} marks]</span>
                            </div>
                            @foreach($section['questions'] as $quesid => $quesarr)
                            <div class="question-block">
                                <div class="question-title">
                                    {{$questionNumber++}}) &nbsp;&nbsp; {!! $quesarr['question_title'] !!}
                                    <span style="float:right;">({{$quesarr['points']}})</span>
                                </div>
                                <table class="table table-striped table-bordered answer-table">
                                    @if(isset($data['answer_arr'][$quesarr['id']]))
                                        @foreach($data['answer_arr'][$quesarr['id']] as $ansid => $ansarr)
                                            <tr>
                                                @php
                                                if($quesarr['multiple_answer'] == 1)
                                                {
                                                    $btnclass = "square";
                                                }
                                                else{
                                                    $btnclass = "dot";
                                                }
                                                @endphp
                                                <td><div class="{{$btnclass}}"></div>
                                                {{$ansarr['answer']}}</td>
                                            </tr>
                                        @endforeach
                                    @endif
                                </table>
                            </div>
                            @endforeach
                            @endforeach
                        @else
                            {{-- Display without sections (legacy format) --}}
                            @foreach($data['question_arr'] as $quesid => $quesarr)
                            <div class="question-block">
                                <div class="question-title">
                                    {{$i++}}) &nbsp;&nbsp; {!! $quesarr['question_title'] !!}
                                    <span style="float:right;">({{$quesarr['points']}})</span>
                                </div>
                                <table class="table table-striped table-bordered answer-table">
                                    @if(isset($data['answer_arr'][$quesarr['id']]))
                                        @foreach($data['answer_arr'][$quesarr['id']] as $ansid => $ansarr)
                                            <tr>
                                                @php
                                                if($quesarr['multiple_answer'] == 1)
                                                {
                                                    $btnclass = "square";
                                                }
                                                else{
                                                    $btnclass = "dot";
                                                }
                                                @endphp
                                                <td><div class="{{$btnclass}}"></div>
                                                {{$ansarr['answer']}}</td>
                                            </tr>
                                        @endforeach
                                    @endif
                                </table>
                            </div>
                            @endforeach
                        @endif
                    </div>
                </div>

                {{-- Answer Key Section (Optional) --}}
                @if(isset($data['show_answer_key']) && $data['show_answer_key'])
                <div class="answer-key-section">
                    <h3><i class="fa fa-key"></i> Answer Key</h3>
                    <table class="table table-bordered answer-key-table">
                        <thead>
                            <tr>
                                <th>Q.No.</th>
                                <th>Answer</th>
                                <th>Marks</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php 
                            $aq = 1; 
                            $answerQuestions = isset($data['sections']) && count($data['sections']) > 0 
                                ? array_collapse(array_column($data['sections'], 'questions'))
                                : $data['question_arr'];
                            @endphp
                            @foreach($answerQuestions as $quesid => $quesarr)
                            <tr>
                                <td>{{$aq++}}</td>
                                <td>
                                    @if(isset($data['answer_arr'][$quesarr['id']]))
                                        @php $firstAnswer = true; @endphp
                                        @foreach($data['answer_arr'][$quesarr['id']] as $ansid => $ansarr)
                                            @if($ansarr['correct_answer'] == 1)
                                                @if(!$firstAnswer), @endif
                                                {{$ansarr['answer']}}
                                                @php $firstAnswer = false; @endphp
                                            @endif
                                        @endforeach
                                    @else
                                        -
                                    @endif
                                </td>
                                <td>{{$quesarr['points']}}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @endif
                
            </div>
        </div>
    </div>
    </div>
</div>
