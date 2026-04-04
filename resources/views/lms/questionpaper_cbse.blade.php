<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $data['questionpaper_data']['paper_name'] ?? 'Question Paper' }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Times New Roman', Times, serif;
            font-size: 14px;
            line-height: 1.6;
            color: #000;
            background: #fff;
            padding: 20px;
        }
        
        .cbse-paper {
            max-width: 800px;
            margin: 0 auto;
            border: 2px solid #000;
            padding: 40px;
        }
        
        .school-header {
            text-align: center;
            border-bottom: 2px solid #000;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        
        .school-name {
            font-size: 24px;
            font-weight: bold;
            text-transform: uppercase;
            margin-bottom: 8px;
        }
        
        .exam-title {
            font-size: 18px;
            font-weight: bold;
            text-transform: uppercase;
            margin-bottom: 15px;
        }
        
        .paper-info {
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 15px;
            font-size: 14px;
            margin-bottom: 10px;
        }
        
        .paper-info-item {
            display: flex;
            gap: 5px;
        }
        
        .paper-info-label {
            font-weight: bold;
        }
        
        .set-number {
            background: #000;
            color: #fff;
            padding: 5px 20px;
            font-size: 14px;
            margin-top: 10px;
            display: inline-block;
        }
        
        .general-instructions {
            border: 1px solid #000;
            padding: 15px;
            margin-bottom: 25px;
        }
        
        .instructions-title {
            font-weight: bold;
            text-decoration: underline;
            margin-bottom: 10px;
            font-size: 16px;
        }
        
        .instructions-list {
            padding-left: 25px;
        }
        
        .instructions-list li {
            margin-bottom: 5px;
        }
        
        .question-container {
            margin-top: 30px;
        }
        
        .section {
            margin-bottom: 30px;
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #000;
            padding-bottom: 8px;
            margin-bottom: 20px;
        }
        
        .section-title {
            font-weight: bold;
            font-size: 16px;
            text-transform: uppercase;
        }
        
        .section-marks {
            font-weight: bold;
        }
        
        .question {
            margin-bottom: 20px;
            padding-left: 10px;
        }
        
        .question-number {
            font-weight: bold;
            margin-right: 8px;
        }
        
        .question-text {
            display: inline;
        }
        
        .question-marks {
            float: right;
            font-weight: bold;
        }
        
        .mcq-options {
            margin-top: 10px;
            margin-left: 25px;
        }
        
        .mcq-option {
            margin-bottom: 8px;
            display: flex;
            align-items: flex-start;
            gap: 10px;
        }
        
        .option-letter {
            font-weight: bold;
            min-width: 20px;
        }
        
        .answer-lines {
            margin-top: 30px;
            page-break-inside: avoid;
        }
        
        .answer-line {
            border-bottom: 1px solid #999;
            height: 30px;
            margin-bottom: 10px;
        }
        
        .case-study {
            border: 2px solid #000;
            padding: 20px;
            margin: 20px 0;
            background: #f9f9f9;
        }
        
        .case-study-title {
            font-weight: bold;
            text-decoration: underline;
            margin-bottom: 15px;
        }
        
        .case-study-passage {
            margin-bottom: 15px;
            line-height: 1.8;
        }
        
        .case-question {
            margin-left: 20px;
            margin-top: 15px;
        }
        
        .footer {
            margin-top: 40px;
            border-top: 1px solid #000;
            padding-top: 15px;
            display: flex;
            justify-content: space-between;
            font-size: 12px;
        }
        
        .qb-code {
            font-weight: bold;
        }
        
        @media print {
            body {
                padding: 0;
            }
            .cbse-paper {
                border: none;
                padding: 20px;
            }
            .mcq-option {
                page-break-inside: avoid;
            }
            .question {
                page-break-inside: avoid;
            }
            .section {
                page-break-inside: avoid;
            }
        }
        
        .question-type-badge {
            display: inline-block;
            background: #e9ecef;
            padding: 2px 8px;
            border-radius: 3px;
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
            margin-left: 10px;
            vertical-align: middle;
        }
        
        .difficulty-easy { color: #28a745; }
        .difficulty-medium { color: #ffc107; }
        .difficulty-hard { color: #dc3545; }
        
        table.question-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        table.question-table td {
            padding: 8px 0;
            vertical-align: top;
        }
        
        .space-lines {
            margin-top: 40px;
        }
        
        .space-line {
            border-bottom: 1px dotted #999;
            height: 25px;
            margin-bottom: 5px;
        }
    </style>
</head>
<body>
    <div class="cbse-paper">
        {{-- School Header --}}
        <div class="school-header">
            <div class="school-name">{{ $data['school_name'] ?? 'SCHOOL NAME' }}</div>
            <div class="exam-title">Final Examination {{ date('Y') }}</div>
            <div class="paper-info">
                <div class="paper-info-item">
                    <span class="paper-info-label">Class:</span>
                    <span>{{ $data['questionpaper_data']['standard_name'] ?? 'Class' }}</span>
                </div>
                <div class="paper-info-item">
                    <span class="paper-info-label">Subject:</span>
                    <span>{{ $data['questionpaper_data']['subject_name'] ?? $data['questionpaper_data']['paper_name'] }}</span>
                </div>
                <div class="paper-info-item">
                    <span class="paper-info-label">Time:</span>
                    <span>{{ $data['questionpaper_data']['time_allowed'] ?? 60 }} Minutes</span>
                </div>
                <div class="paper-info-item">
                    <span class="paper-info-label">Max. Marks:</span>
                    <span>{{ $data['questionpaper_data']['total_marks'] ?? 100 }}</span>
                </div>
            </div>
            @if(isset($data['questionpaper_data']['set_name']))
            <div class="set-number">{{ $data['questionpaper_data']['set_name'] }}</div>
            @endif
        </div>
        
        {{-- General Instructions --}}
        <div class="general-instructions">
            <div class="instructions-title">GENERAL INSTRUCTIONS:</div>
            <ol class="instructions-list">
                <li>Check all the pages of the question paper carefully. In case any page is missing or torn, ask the invigilator to get a fresh copy.</li>
                <li>Read the instructions given on the first page of each section carefully.</li>
                <li>All questions are compulsory unless otherwise stated.</li>
                <li>Internal choices have been provided in some questions. A student has to attempt only one of the choices in such questions.</li>
                <li>Question numbers 1-20 are of Multiple Choice Questions (MCQs). Each question carries 1 mark with four options (a), (b), (c), (d).</li>
                <li>Question numbers 21-25 are Short Answer Questions (SA). Each question carries 2 marks.</li>
                <li>Question numbers 26-29 are Long Answer Questions (LA). Each question carries 5 marks.</li>
                <li>Question number 30 is Case-based Question. It carries 5 marks.</li>
                <li>Use of calculators/electronic devices is not allowed.</li>
                <li>Write your Roll Number on the answer book. Do not write your name or any other mark except Roll Number.</li>
            </ol>
        </div>
        
        {{-- Questions --}}
        <div class="question-container">
            @php
            $sectionLabels = ['A', 'B', 'C', 'D', 'E'];
            $questionNumber = 1;
            $currentSection = 0;
            @endphp
            
            @if(isset($data['sections']) && count($data['sections']) > 0)
                @foreach($data['sections'] as $sectionIdx => $section)
                <div class="section">
                    <div class="section-header">
                        <span class="section-title">SECTION {{ $sectionLabels[$sectionIdx] ?? chr(65 + $sectionIdx) }}</span>
                        <span class="section-marks">[{{ $section['total_marks'] }} Marks]</span>
                    </div>
                    
                    @foreach($section['questions'] as $quesid => $quesarr)
                    @php
                    $questionType = $quesarr['question_type'] ?? 'Theory';
                    $isMCQ = in_array(strtolower($questionType), ['mcq', 'multiple choice', 'objective']);
                    $isShortAnswer = in_array(strtolower($questionType), ['short answer', 'sa', 'very short', '2 mark']);
                    $isLongAnswer = in_array(strtolower($questionType), ['long answer', 'la', '5 mark', 'essay']);
                    $isCaseStudy = in_array(strtolower($questionType), ['case study', 'case based', 'application']);
                    @endphp
                    
                    @if($isCaseStudy)
                    <div class="case-study">
                        <div class="case-study-title">CASE STUDY {{ $questionNumber }}:</div>
                        <div class="case-study-passage">{!! $quesarr['question_title'] !!}</div>
                @else
                    <div class="question">
                        <table class="question-table">
                            <tr>
                                <td width="50">
                                    <span class="question-number">{{ $questionNumber }}.</span>
                                </td>
                                <td>
                                    <span class="question-text">{!! $quesarr['question_title'] !!}</span>
                                    @if(isset($quesarr['question_type']))
                                    <span class="question-type-badge">{{ $quesarr['question_type'] }}</span>
                                    @endif
                                </td>
                                <td width="50" align="right">
                                    <span class="question-marks">({{ $quesarr['points'] }})</span>
                                </td>
                            </tr>
                        </table>
                        
                        @if($isMCQ && isset($data['answer_arr'][$quesarr['id']]))
                        <div class="mcq-options">
                            @foreach($data['answer_arr'][$quesarr['id']] as $ansid => $ansarr)
                            <div class="mcq-option">
                                <span class="option-letter">{{ chr(65 + $ansid) }})</span>
                                <span>{{ $ansarr['answer'] }}</span>
                            </div>
                            @endforeach
                        </div>
                        @endif
                    </div>
                    @endif
                    
                    @php $questionNumber++; @endphp
                    @endforeach
                </div>
                @endforeach
            @else
                {{-- Single Section (Legacy Format) --}}
                <div class="section">
                    <div class="section-header">
                        <span class="section-title">SECTION A</span>
                        <span class="section-marks">[{{ $data['questionpaper_data']['total_marks'] ?? 100 }} Marks]</span>
                    </div>
                    
                    @foreach($data['question_arr'] as $quesid => $quesarr)
                    @php
                    $questionType = $quesarr['question_type'] ?? 'Theory';
                    $isMCQ = in_array(strtolower($questionType), ['mcq', 'multiple choice', 'objective']);
                    @endphp
                    
                    <div class="question">
                        <table class="question-table">
                            <tr>
                                <td width="50">
                                    <span class="question-number">{{ $questionNumber }}.</span>
                                </td>
                                <td>
                                    <span class="question-text">{!! $quesarr['question_title'] !!}</span>
                                    @if(isset($quesarr['question_type']))
                                    <span class="question-type-badge">{{ $quesarr['question_type'] }}</span>
                                    @endif
                                </td>
                                <td width="50" align="right">
                                    <span class="question-marks">({{ $quesarr['points'] }})</span>
                                </td>
                            </tr>
                        </table>
                        
                        @if($isMCQ && isset($data['answer_arr'][$quesarr['id']]))
                        <div class="mcq-options">
                            @foreach($data['answer_arr'][$quesarr['id']] as $ansid => $ansarr)
                            <div class="mcq-option">
                                <span class="option-letter">{{ chr(65 + $ansid) }})</span>
                                <span>{{ $ansarr['answer'] }}</span>
                            </div>
                            @endforeach
                        </div>
                        @endif
                    </div>
                    
                    @php $questionNumber++; @endphp
                    @endforeach
                </div>
            @endif
        </div>
        
        {{-- Answer Space (for offline exam) --}}
        @if(isset($data['show_answer_space']) && $data['show_answer_space'])
        <div class="space-lines">
            <div style="font-weight: bold; margin-bottom: 15px; text-decoration: underline;">ANSWERS:</div>
            @for($i = 1; $i <= ($data['questionpaper_data']['total_ques'] ?? 30); $i++)
            <div class="space-line"></div>
            @endfor
        </div>
        @endif
        
        {{-- Footer --}}
        <div class="footer">
            <div>
                <span class="qb-code">QB Code:</span> {{ $data['questionpaper_data']['id'] ?? 'N/A' }}
            </div>
            <div>
                Page {{ $data['questionpaper_data']['paper_name'] ?? '1' }} of 1
            </div>
            <div>
                {{ date('d-M-Y') }}
            </div>
        </div>
    </div>
</body>
</html>