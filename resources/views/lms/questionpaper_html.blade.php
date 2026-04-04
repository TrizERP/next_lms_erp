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
</style>
<div id="page-wrapper">
    <div class="container-fluid">                
    <div class="row">
        <div class="white-box">    
            <div class="panel-body">                             
                <div class="col-lg-12 col-sm-12 col-xs-12" style="overflow:auto;">
                    <table id="questionpaper" class="table table-striped table-bordered" style="width:100%">
                    <tr>
                        <th style="background:#25bdea;padding: 15px;">
                            <table class="table table-striped table-bordered" style="width:100%">
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
                        </th>
                    </tr>
                    
                    @if(isset($data['questionpaper_data']['paper_desc']) && !empty($data['questionpaper_data']['paper_desc']))
                    <tr>
                        <td>
                            <div class="instructions">
                                <strong>Instructions:</strong> {{$data['questionpaper_data']['paper_desc']}}
                            </div>
                        </td>
                    </tr>
                    @endif
                    
                    <tr><td style="background:#ffffff;">
                        <table class="table table-striped table-bordered" style="width:100%">                     
                            @php 
                            $i = 1; 
                            $sectionLabels = ['A', 'B', 'C', 'D', 'E'];
                            $currentSection = 0;
                            $questionNumber = 1;
                            @endphp                           
                            
                            @if(isset($data['sections']) && count($data['sections']) > 0)
                                {{-- Display with sections --}}
                                @foreach($data['sections'] as $sectionIdx => $section)
                                <tr>
                                    <td colspan="2" class="section-header">
                                        <strong>Section {{ $sectionLabels[$sectionIdx] ?? chr(65 + $sectionIdx) }}</strong>
                                        <span style="float:right;">[{{ $section['total_marks'] }} marks]</span>
                                    </td>
                                </tr>
                                @foreach($section['questions'] as $quesid => $quesarr)
                                <tr>                                
                                    <td style="text-align:left;background: #303030;color: #ffffff;">
                                        {{$questionNumber++}}) &nbsp;&nbsp; {!! $quesarr['question_title'] !!}
                                        <span style="float:right;">({{$quesarr['points']}})</span>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                    <table class="table table-striped table-bordered" style="width:100%">
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
                                                    <td style="text-align:left;"><div class="{{$btnclass}}"></div>
                                                    {{$ansarr['answer']}}</td>
                                                </tr>
                                            @endforeach
                                        @endif
                                    </table>
                                    </td>
                                </tr>
                                @endforeach
                                @endforeach
                            @else
                                {{-- Display without sections (legacy format) --}}
                                @foreach($data['question_arr'] as $quesid => $quesarr)
                                <tr>                                
                                    <td style="text-align:left;background: #303030;color: #ffffff;">
                                        {{$i++}}) &nbsp;&nbsp; {!! $quesarr['question_title'] !!}
                                        <span style="float:right;">({{$quesarr['points']}})</span>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                    <table class="table table-striped table-bordered" style="width:100%">
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
                                                    <td style="text-align:left;"><div class="{{$btnclass}}"></div>
                                                    {{$ansarr['answer']}}</td>
                                                </tr>
                                            @endforeach
                                        @endif
                                    </table>
                                    </td>
                                </tr>
                                @endforeach
                            @endif                        
                        </table>
                    </td></tr>
                    </table>
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
