@extends('lmslayout') @section('container')
<div id="page-wrapper">
	<div class="container-fluid">
		<div class="row bg-title">
			<div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
				<h4 class="page-title">PAL Subjects</h4>
			</div>
		</div>
	<style>
/* Misconception Modal Styles - CRYSTAL CLEAR FIX */
#misconceptionModal .modal-dialog {
    max-width: 850px;
}

#misconceptionModal .modal-content {
    border-radius: 12px;
    overflow: hidden;
}

#misconceptionModal .modal-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-bottom: none;
    padding: 15px 20px;
}

#misconceptionModal .modal-header h5 {
    font-weight: 600;
    margin: 0;
}

#misconceptionModal .modal-header .close {
    color: white;
    opacity: 0.8;
}

#misconceptionModal .modal-body {
    max-height: 70vh;
    overflow-y: auto;
    padding: 20px;
    background: #f5f7fa;
}

/* Question Card */
.misconception-question-card {
    transition: all 0.3s ease;
    cursor: pointer;
    border-left: 3px solid transparent;
    margin-bottom: 15px;
}

.misconception-question-card:hover {
    transform: translateX(5px);
    box-shadow: 0 2px 12px rgba(0,0,0,0.1);
    border-left-color: #4e73df;
}

.misconception-question-card.border-primary {
    border-left-color: #4e73df !important;
    box-shadow: 0 4px 15px rgba(78, 115, 223, 0.15);
}

/* Question Row */
.question-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
}

.question-text {
    flex: 1;
    font-size: 14px;
    color: #333;
    line-height: 1.5;
}

.wrong-count {
    white-space: nowrap;
    display: flex;
    align-items: center;
    gap: 10px;
}

.wrong-count .badge {
    font-size: 12px;
    padding: 6px 12px;
    border-radius: 20px;
}

.wrong-count .badge i {
    margin-right: 4px;
}

.toggle-icon {
    font-size: 12px;
    color: #6c757d;
    transition: transform 0.3s ease;
}

/* ========== CRITICAL FIX: MAPPING DETAILS CLEAR DISPLAY ========== */
.mapping-details {
    margin-top: 15px !important;
}

.mapping-details .card {
    background-color: #ffffff !important;
    border: 1px solid #e3e6f0;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.05);
}

.mapping-details .card-body {
    padding: 0 !important;
}

/* Clean Mapping Header */
.mapping-details h6 {
    background: #f8f9fc;
    padding: 12px 15px;
    margin: 0 !important;
    color: #4e73df;
    font-size: 13px;
    font-weight: 700;
    letter-spacing: 0.5px;
    border-bottom: 1px solid #e3e6f0;
}

.mapping-details h6 i {
    margin-right: 8px;
}

/* CRYSTAL CLEAR TABLE STYLES - OVERRIDE BOOTSTRAP */
.mapping-details .table {
    margin-bottom: 0 !important;
    background: white;
    border-collapse: separate;
    border-spacing: 0;
    filter : blur(0px) !important;
    text-align: center;
}

.mapping-details .table thead th {
    background-color: #f1f5f9;
    font-weight: 700 !important;
    font-size: 12px !important;
    text-transform: uppercase;
    letter-spacing: 0.8px !important;
    padding: 12px 15px !important;
    border-bottom: 2px solid #e2e8f0;
    color: #1e293b !important;
    text-align: center;
}

.mapping-details .table tbody td {
    padding: 14px 15px !important;
    vertical-align: middle;
    border-bottom: 1px solid #eef2f6;
    font-size: 13px !important;
    text-align: center;
}

.mapping-details .table tbody tr:last-child td {
    border-bottom: none;
}

/* Mapping Type Column - BOLD & CLEAR */
.mapping-details .table tbody td:first-child {
    font-weight: 700 !important;
    color: #1e293b !important;
    background-color: #fafcff !important;
    width: 40%;
    border-right: 1px solid #eef2f6;
}

/* Mapping Value Column - CLEAR TEXT */
.mapping-details .table tbody td:last-child {
    font-weight: 500 !important;
    color: #2d3748 !important;
    background-color: #ffffff !important;
    width: 60%;
}

/* Ensure text is sharp */
.mapping-details .table tbody td {
    -webkit-font-smoothing: antialiased;
    -moz-osx-font-smoothing: grayscale;
}

/* Responsive fix */
@media (max-width: 576px) {
    .mapping-details .table thead th,
    .mapping-details .table tbody td {
        padding: 10px 12px !important;
        font-size: 12px !important;
    }
}
    	.title {  
    display: flex;
    justify-content: space-between;
    /* gap: 20px; */
    align-items: center;
    width: 100%;
}

.chapter-name {
    font-weight: 500;
}

.chapter-btns {
    display: flex;
    flex-direction: row;
    gap: 10px;              /* PERFECT GAP */
    align-items: center;
}

.chapter-btns .btn {
    font-size: 12px;
    padding: 6px 12px;
    white-space: nowrap;    /* text break na thay */
}
        </style>
		@if ($sessionData = Session::get('data'))
        @if (isset($sessionData['status_code']))
            <div class="col-md-12 alert alert-{{ $sessionData['status_code'] == 1 ? 'success' : 'danger' }} alert-block">
                <button type="button" class="close" data-dismiss="alert">×</button>
                <strong>{!! $sessionData['message'] !!}</strong>
            </div>
        @endif @endif
			<!-- csubjects  -->
			<div class="container-fluid mb-5">
				<div class="coursr-chp-list" id="cource-chap-list">
                    @php $i=1; @endphp
                    @foreach($data['subjectList'] as $subject_id=>$subject_name)
                        <div class="row card single-chp mb-2" style="">
                            <div class="col-md-4 mb-2 chp-details" data-toggle="collapse" href="#subject_{{$subject_id}}" role="button" aria-expanded="false" aria-controls="subject_{{$subject_id}}">
                                <div class="count">{{$i++}}</div>
                                <div class="title">
                                    {{$subject_name}}
                                </div>
                            </div>
                        </div>
                        <!-- // sub div -->
                        <div class="collapse" id="subject_{{$subject_id}}">
                            @php $j=1; @endphp                                
                            @if(!empty($data['chapterList'][$subject_id]))
                                @foreach($data['chapterList'][$subject_id] as $chapter_id=>$chapter_name)
                                    <div class="row card single-chp mb-2" style="left:5% !important;">
                                        <div class="col-md-12 mb-2 chp-details" data-toggle="collapse" href="#quiz_{{$j}}" role="button" aria-expanded="false" aria-controls="quiz_{{$j}}">
                                            <div class="count">{{$j}}</div>
                                           <div class="title">
    <span class="chapter-name">{{$chapter_name}}</span>
        @if(isset($data['perChapterQuiz'][$chapter_id]) && $data['perChapterQuiz'][$chapter_id] > 0)
    <div class="chapter-btns">
        <button class="btn btn-primary btn-sm"
            onclick="suggestedContent({{$data['studentDetails']['grade_id']}},{{$subject_id}},{{$chapter_id}},{{$data['studentDetails']['standard_id']}})">
            Suggested Content
        </button>
<div class="modal fade" id="misconceptionModal">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5>Wrong Questions</h5>
                <button class="close" data-dismiss="modal">&times;</button>
            </div>

            <div class="modal-body text-center">
               
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-primary" id="generateMisconceptionContentBtn" onclick="generateMisconceptionContent()">
                    <i class="fa fa-magic"></i> Generate Content
                </button>
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
            </div>

        </div>
    </div>
</div>
        <button class="btn btn-danger btn-sm"
            onclick="misconception({{$chapter_id}})">
            Misconception
        </button>
    </div>
    @endif
</div>
    


                                        </div>
                                    </div>  
                                    <!-- quiz lists -->
                                    @php 
                                    $k=1; 
                                    @endphp                                     
                                    
                                    @foreach($data['attemptExams'] as $examKey=>$examValue)
                                    @if($chapter_id == $examValue['paper_desc'])
                                    <div class="collapse" id="quiz_{{$j}}">
                                        <div class="row card single-chp mb-2" style="left:10% !important;width:90%">
                                            <div class="col-md-4 mb-2 chp-details" data-toggle="collapse" href="" role="button" aria-expanded="false" aria-controls="">
                                                <div class="title">
                                                <input type="hidden" name="quiz_no" id="quiz_no" data-val="{{$subject_id}}" value="{{$k}}">
                                                    <span>Quiz {{$k++}}</span>
                                                    
                                                </div>
                                            </div>
                                            @php
                                                $total = ($examValue['total_right'] + $examValue['total_wrong']);
                                                $per = $total > 0 ? ($examValue['total_right'] * 100) / $total : 0;
                                            @endphp
                                            <div class="col-md-4 progress_bar">
                                            <p style="margin-bottom:0px !important">Progress</p>
                                            <div class="progress">
                                                <div class="progress-bar" role="progressbar" style="width: {{$per}}%" aria-valuenow="{{$per}}" aria-valuemin="0" aria-valuemax="100"></div>
                                                </div>
                                            </div>
                                            <div class="col-md-4 marks text-right">
                                                {{$total > 0 ? $examValue['total_right'] . ' / ' . $total : 'Not Attempted'}}       
                                            </div>
                                        </div>  
                                    </div> 
                                    @endif                                     
                                    @endforeach      
                                    <div class="collapse" id="quiz_{{$j}}">
                                        <div class="row card single-chp mb-2" style="left:10% !important;width:90%">
                                            <div class="col-md-4 mb-2 chp-details" data-toggle="collapse" href="" role="button" aria-expanded="false" aria-controls="">
                                                <div class="title">
                                                    <span style="color:green" onclick="generateExam({{$data['studentDetails']['grade_id']}},{{$subject_id}},{{$chapter_id}},{{$data['studentDetails']['standard_id']}},{{$data['studentDetails']['enrollment_no']}})">Quiz {{$k}} <i class="fa fa-arrow-right" aria-hidden="true"></i> </span>
                                                </div>
                                            </div>
                                        </div>  
                                    </div> 
                                 
                                    @php $j++; @endphp                                     
                                    <!-- end quiz list  -->
                                @endforeach      
                            @endif 
                        </div>
                        <!-- end sub div -->
                    @endforeach
                <!-- main -->
				</div>
			</div>
			<!-- subjects end -->
	</div>
</div>
@include('includes.lmsfooterJs')
<div class="modal fade" id="suggestedContentModal" tabindex="-1" role="dialog" aria-labelledby="suggestedContentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="suggestedContentModalLabel">Suggested Content</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="text-center"><i class="fa fa-spinner fa-spin"></i> Loading content...</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
<style>
.content-category {
    background: #f8f9fb;
    border-radius: 12px;
    padding: 15px;
    margin-bottom: 20px;
    border-left: 5px solid #4e73df;
}

.content-item {
    border-radius: 10px;
    border: 1px solid #e3e6f0;
    transition: 0.3s;
}

.content-item:hover {
    transform: translateY(-3px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
}

.suggested-section-toggle {
    width: 100%;
    text-align: left;
    background: #f8f9fb;
    border: 1px solid #e3e6f0;
    border-left: 5px solid #4e73df;
    border-radius: 8px;
    padding: 12px 15px;
    margin-bottom: 10px;
    font-weight: 600;
    color: #2d3748;
}

.suggested-section-toggle:hover,
.suggested-section-toggle:focus {
    color: #2d3748;
    background: #eef3ff;
    outline: none;
}

.suggested-section-body {
    display: none;
    padding: 5px 0 15px;
}

.teacher-category-badge {
    display: inline-block;
    background: #edf2f7;
    color: #4a5568;
    border-radius: 12px;
    font-size: 11px;
    padding: 3px 8px;
    margin-bottom: 8px;
}

.content-item h6 {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
}

.content-item .content-title {
    flex: 1;
    min-width: 180px;
}

.content-item .content-meta {
    display: inline-flex;
    align-items: center;
    justify-content: flex-end;
    gap: 8px;
    flex-wrap: wrap;
    margin-left: auto;
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
    background: #22aeea;
    border-color: #22aeea;
    color: #e0f2fe;
    box-shadow: 0 1px 3px rgba(7, 151, 214, 0.18);
}
.badge.badge-info {
    background-color: #0799ddff;
}
.content-item .mapping-type-pill:hover,
.content-item .mapping-type-pill:focus {
    background: #f0fbff;
    color: #057faf;
}

.content-item .visit-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 5px;
    min-height: 32px;
    padding: 6px 10px;
    border-radius: 6px;
}

.pedagogy-engine-banner {
    background: linear-gradient(135deg, #1e3a5f 0%, #2d6a9f 100%);
    color: #fff;
    border-radius: 10px;
    padding: 14px 18px;
    margin-bottom: 18px;
}

.pedagogy-engine-banner .pedagogy-label {
    font-size: 18px;
    font-weight: 700;
    margin-bottom: 4px;
}

.pedagogy-engine-banner .pedagogy-reason {
    font-size: 13px;
    opacity: 0.92;
    margin-bottom: 0;
}

.pedagogy-engine-banner .pedagogy-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 10px;
}

.pedagogy-engine-banner .pedagogy-meta .badge {
    background: rgba(255,255,255,0.18);
    color: #fff;
    font-weight: 600;
}

.content-item .pedagogy-badge {
    background: #ede9fe;
    color: #5b21b6;
}

.pedagogy-mapping-details {
    margin-top: 14px;
}

.pedagogy-mapping-panel {
    border: 1px solid #dbeafe;
    border-radius: 18px;
    overflow: hidden;
    background: #f8fbff;
    box-shadow: 0 8px 22px rgba(59, 130, 246, 0.08);
}

.pedagogy-mapping-panel-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 14px 18px;
    background: linear-gradient(180deg, #1d9bf0 0%, #1681e3 100%);
    color: #fff;
}

.pedagogy-mapping-title {
    font-size: 16px;
    font-weight: 800;
    letter-spacing: 0.2px;
}

.pedagogy-mapping-count {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 34px;
    padding: 0 13px;
    border: 1px solid rgba(255, 255, 255, 0.45);
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.14);
    color: #fff;
    font-size: 12px;
    font-weight: 700;
    white-space: nowrap;
}

.pedagogy-mapping-list {
    background: #fff;
}

.pedagogy-mapping-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 14px;
    padding: 16px 18px 16px 20px;
    border-top: 1px solid #e5eefc;
    background: #fff;
    transition: background-color 0.2s ease, transform 0.2s ease;
}

.pedagogy-mapping-row:nth-child(even) {
    background: #f7fbff;
}

.pedagogy-mapping-row:hover {
    background: #eef8ff;
}

.pedagogy-mapping-row:first-child {
    border-top: none;
}

.pedagogy-mapping-row-accent {
    width: 4px;
    min-width: 4px;
    height: 38px;
    border-radius: 999px;
    background: linear-gradient(180deg, #2f6bff 0%, #6d28d9 100%);
    box-shadow: 0 0 0 3px rgba(47, 107, 255, 0.08);
}

.pedagogy-mapping-row-accent.accent-cyan {
    background: linear-gradient(180deg, #06b6d4 0%, #0ea5e9 100%);
}

.pedagogy-mapping-row-accent.accent-purple {
    background: linear-gradient(180deg, #8b5cf6 0%, #6d28d9 100%);
}

.pedagogy-mapping-row-accent.accent-blue {
    background: linear-gradient(180deg, #2563eb 0%, #3b82f6 100%);
}

.pedagogy-mapping-label {
    flex: 1;
    color: #314573ff;
    font-size: 14px;
    font-weight: 800;
    line-height: 1.35;
}

.pedagogy-mapping-value {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 36px;
    padding: 7px 15px;
    border: 1px solid #bfe7ff;
    border-radius: 999px;
    background: linear-gradient(180deg, #f9fdff 0%, #eef9ff 100%);
    color: #0f7498;
    font-size: 13px;
    font-weight: 700;
    text-align: center;
    box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.55);
}

.pedagogy-mapping-empty {
    padding: 18px;
    color: #64748b;
    text-align: center;
}

@media (max-width: 576px) {
    .pedagogy-mapping-panel-header,
    .pedagogy-mapping-row {
        flex-direction: column;
        align-items: flex-start;
    }

    .pedagogy-mapping-count {
        align-self: flex-start;
    }

    .pedagogy-mapping-value {
        width: 100%;
        justify-content: flex-start;
    }

    .pedagogy-mapping-label {
        width: 100%;
    }
}

@media (max-width: 767.98px) {
    .content-item h6 {
        align-items: flex-start;
        flex-direction: column;
    }

    .content-item .content-meta {
        justify-content: flex-start;
        margin-left: 0;
    }
}
</style>
<script>
function openContent(link){
    if(link){
        window.open(link, '_blank');
    }
}

function formatLevelLabel(level) {
    if(!level) return 'Medium';
    level = String(level).toLowerCase();
    return level.charAt(0).toUpperCase() + level.slice(1);
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

function getUniqueContents(contents) {
    var seen = {};
    var uniqueContents = [];

    $.each(contents || [], function(index, content) {
        var type = content.content_type || content.type || 'teacher_content';
        var identity = type === 'misconception_content'
            ? (content.question_id || content.generated_content || content.id || index)
            : (content.id || content.content_id || content.filename || content.title || content.content_title || index);
        var key = type + ':' + identity;

        if(seen[key]) {
            return;
        }

        seen[key] = true;
        uniqueContents.push(content);
    });

    return uniqueContents;
}

function getSuggestedSectionLabel(category) {
    if(category === 'Misconception Content') {
        return 'Misconception Content';
    }
    if(category === 'Pedagogy Content') {
        return 'Pedagogy Content';
    }
    return 'Teacher Content';
}

function buildPedagogyEngineBanner(pedagogy) {
    if(!pedagogy || !pedagogy.recommended) {
        return '';
    }

    var html = '<div class="pedagogy-engine-banner">';
    html += '<div class="pedagogy-label"><i class="fa fa-graduation-cap mr-2"></i>Recommended: ' + escapeHtml(pedagogy.label || pedagogy.recommended) + '</div>';
    if(pedagogy.reason) {
        html += '<p class="pedagogy-reason">' + escapeHtml(pedagogy.reason) + '</p>';
    }
    html += '<div class="pedagogy-meta">';
    html += '<span class="badge">Level Engine Active</span>';
    if(pedagogy.cognitive_load) {
        html += '<span class="badge">Cognitive Load: ' + escapeHtml(pedagogy.cognitive_load) + '</span>';
    }
    html += '</div></div>';

    return html;
}

function renderSuggestedContentSections(contentData, prefix, defaultLevel, pedagogy) {
    var grouped = {
        'Pedagogy Content': [],
        'Teacher Content': [],
        'Misconception Content': []
    };

    $.each(contentData || {}, function(chapterId, categories) {
        $.each(categories || {}, function(category, contents) {
            var sectionLabel = getSuggestedSectionLabel(category);
            $.each(getUniqueContents(contents), function(index, content) {
                grouped[sectionLabel].push(content);
            });
        });
    });

    var html = buildPedagogyEngineBanner(pedagogy);
    html += '<div class="suggested-content-container">';

    $.each(['Pedagogy Content', 'Teacher Content', 'Misconception Content'], function(sectionIndex, sectionLabel) {
        var contents = getUniqueContents(grouped[sectionLabel]);
        var sectionId = prefix + '_suggested_section_' + sectionIndex;

        html += '<div class="suggested-section mb-3">';
        html += '<button type="button" class="suggested-section-toggle" onclick="toggleSuggestedContentSection(\'' + sectionId + '\')">';
        html += '<i class="fa fa-chevron-right mr-2"></i>' + sectionLabel + ' <span class="badge badge-secondary ml-2">' + contents.length + '</span>';
        html += '</button>';
        html += '<div class="suggested-section-body" id="' + sectionId + '">';

        if(contents.length > 0) {
            $.each(contents, function(index, content) {
                html += buildSuggestedContentCard(content, index, prefix + '_' + sectionIndex, defaultLevel);
            });
        } else {
            html += '<p class="text-muted mb-0">No ' + sectionLabel.toLowerCase() + ' available for this chapter.</p>';
        }

        html += '</div></div>';
    });

    html += '</div>';

    return html;
}

function toggleSuggestedContentSection(sectionId) {
    var sectionBody = $('#' + sectionId);
    var sectionButton = sectionBody.prev('.suggested-section-toggle');
    var icon = sectionButton.find('.fa').first();

    sectionBody.slideToggle(250);
    icon.toggleClass('fa-chevron-right fa-chevron-down');
}

function buildSuggestedContentMappingHtml(content, mappingId) {
    var mappingHtml = '<div class="mapping-details suggested-content-mapping" style="display:none;" id="' + mappingId + '">';
    mappingHtml += '<div class="card mt-3">';

    if(content.mapping && content.mapping.length > 0) {
        mappingHtml += '<div class="table-responsive px-3 pb-3">';
        mappingHtml += '<table class="table table-bordered mb-0">';
        mappingHtml += '<thead><tr><th>MAPPING TYPE</th><th>MAPPING VALUE</th></tr></thead>';
        mappingHtml += '<tbody>';

        $.each(content.mapping, function(mi, mapping) {
            mappingHtml += '<tr>';
            mappingHtml += '<td>' + escapeHtml(mapping.type_name || '-') + '</td>';
            mappingHtml += '<td>' + escapeHtml(mapping.value_name || '-') + '</td>';
            mappingHtml += '</tr>';
        });

        mappingHtml += '</tbody></table></div>';
    } else {
        mappingHtml += '<div class="card-body text-center py-3">';
        mappingHtml += '<p class="text-muted mb-0"><i class="fa fa-info-circle"></i> No mapping information available</p>';
        mappingHtml += '</div>';
    }

    mappingHtml += '</div></div>';

    return mappingHtml;
}

function buildSuggestedContentCard(content, index, prefix, defaultLevel) {
    if(content.content_type === 'misconception_content') {
        return buildMisconceptionSuggestedContentCard(content, index, prefix);
    }

    var contentId = content.id || index;
    var pedagogyLabel = content.recommended_pedagogy || content.matched_pedagogy || '';
    var mappingId = prefix + '_content_mapping_' + contentId + '_' + index;
    var title = content.title || content.content_title || 'Untitled';
    var levelLabel = formatLevelLabel(content.student_level || defaultLevel || 'medium');
    var visitCount = content.content_visited || 0;
    var html = '<div class="content-item card mb-2" data-mapping-id="' + mappingId + '">';
    html += '<div class="card-body">';
    html += '<h6>';
    html += '<span class="content-title">';

    if(content.file_type == 'link'){
        html += '<a href="' + escapeHtml(content.filename) + '" target="_blank" onclick="trackContentVisit(' + contentId + ', ' + (content.standard_id || 'null') + ', ' + (content.subject_id || 'null') + ', ' + (content.chapter_id || 'null') + ')">' + escapeHtml(title) + '</a>';
    }else{
        html += escapeHtml(title);
    }

    html += '</span>';
    html += '<span class="content-meta">';
    html += '<button type="button" class="btn btn-sm content-pill mapping-type-pill" title="' + escapeHtml(getMappingTypeSummary(content)) + '" onclick="toggleSuggestedContentMapping(\'' + mappingId + '\')">';
    html += '<i class="fa fa-tags"></i> Mapping Types';
    html += '</button>';
    html += '<span class="content-pill level-badge">Level: ' + escapeHtml(levelLabel) + '</span>';
    if(pedagogyLabel) {
        html += '<span class="content-pill pedagogy-badge">' + escapeHtml(formatPedagogyLabel(pedagogyLabel)) + '</span>';
    }
    if(visitCount > 0) {
        html += '<span class="badge badge-info visit-badge" title="Times viewed">';
        html += '<i class="fa fa-eye"></i> ' + visitCount;
        html += '</span>';
    }
    html += '</span>';
    html += '</h6>';

    if(content.teacher_content_category) {
        html += '<span class="teacher-category-badge">' + escapeHtml(content.teacher_content_category) + '</span>';
    }

    // if(content.content_link){
    //     html += '<a href="' + escapeHtml(content.content_link) + '" target="_blank" class="btn btn-sm btn-success mt-2" onclick="trackContentVisit(' + contentId + ', ' + (content.standard_id || 'null') + ', ' + (content.subject_id || 'null') + ', ' + (content.chapter_id || 'null') + ')">';
    //     html += '<i class="fa fa-external-link"></i> Open Content';
    //     html += '</a>';
    // }

    html += buildSuggestedContentMappingHtml(content, mappingId);
    html += '</div></div>';

    return html;
}

function buildMisconceptionSuggestedContentCard(content, index, prefix) {
    var contentId = content.id || content.question_id || index;
    var mappingId = prefix + '_misconception_mapping_' + contentId + '_' + index;
    var title = content.title || content.content_title || 'Misconception Content';
    var levelLabel = formatLevelLabel(content.student_level || 'medium');
    var visitCount = content.content_visited || 0;
    var contentLink = content.filename || content.url || content.content_url || '';
    var html = '<div class="content-item card mb-2" data-mapping-id="' + mappingId + '">';
    html += '<div class="card-body">';
    html += '<h6>';
    html += '<span class="content-title">';
    if(contentLink) {
        html += '<a href="' + escapeHtml(contentLink) + '" target="_blank" onclick="trackContentVisit(' + contentId + ', ' + (content.standard_id || 'null') + ', ' + (content.subject_id || 'null') + ', ' + (content.chapter_id || 'null') + ', \'misconception\')">' + escapeHtml(title) + '</a>';
    } else {
        html += escapeHtml(title);
    }
    html += '</span>';
    html += '<span class="content-meta">';
    html += '<button type="button" class="btn btn-sm content-pill mapping-type-pill" onclick="toggleSuggestedContentMapping(\'' + mappingId + '\')">';
    html += '<i class="fa fa-tags"></i> Mapping Types';
    html += '</button>';
    html += '<span class="content-pill level-badge">Level: ' + escapeHtml(levelLabel) + '</span>';
    if(visitCount > 0) {
        html += '<span class="badge badge-info visit-badge" title="Times viewed">';
        html += '<i class="fa fa-eye"></i> ' + visitCount;
        html += '</span>';
    }
    html += '</span>';
    html += '</h6>';
    html += '<span class="teacher-category-badge">' + escapeHtml(content.teacher_content_category || ' ') + '</span>';
    html += buildSuggestedContentMappingHtml(content, mappingId);
    html += '</div></div>';

    return html;
}

function toggleMisconceptionContent(contentBodyId) {
    $('#' + contentBodyId).slideToggle(250);
}

function toggleSuggestedContentMapping(mappingId) {
    var mappingDiv = $('#' + mappingId);
    var card = $('.content-item[data-mapping-id="' + mappingId + '"]');

    if(mappingDiv.is(':visible')) {
        mappingDiv.slideUp(250);
        card.removeClass('border-primary');
    } else {
        $('.suggested-content-mapping').slideUp(250);
        $('.content-item').removeClass('border-primary');
        mappingDiv.slideDown(250);
        card.addClass('border-primary');
    }
}

function generateExam(grade_id,subject_id,chapter_id,standard_id,enrollment_no){
      if (chapter_id !== '' && chapter_id !== 'undefined') {
        window.location.href = '/lms/pal/create?subject_id='+subject_id+'&chapter_id='+chapter_id+'&grade_id='+grade_id+'&standard_id='+standard_id+'&enrollment_no='+enrollment_no;
    }
}

function trackContentVisit(contentId, standardId, subjectId, chapterId, contentType) {
    $.ajax({
        url: '/lms/increment-content-visit',
        type: 'POST',
        data: {
            content_id: contentId,
            standard_id: standardId,
            subject_id: subjectId,
            chapter_id: chapterId,
            content_type: contentType || 'pal_content',
            _token: $('meta[name="csrf-token"]').attr('content')
        },
        success: function(response) {
            console.log('Content visit tracked:', response);
        },
        error: function(xhr, status, error) {
            console.error('Error tracking content visit:', error);
        }
    });
}

function formatPedagogyLabel(pedagogyType) {
    if(!pedagogyType) return '';
    return String(pedagogyType).replace(/-/g, ' ').replace(/\b\w/g, function(char) {
        return char.toUpperCase();
    });
}

var pedagogySuggestedContentData = {};
var currentPedagogyStudentLevel = 'medium';
var currentPedagogyContext = {
    chapter_id: null,
    standard_id: null,
    subject_id: null,
    grade_id: null
};

function suggestedContent(grade_id, subject_id, chapter_id, standard_id) {
    if (chapter_id === '' || chapter_id === 'undefined') {
        return;
    }

    currentPedagogyContext = {
        chapter_id: chapter_id,
        standard_id: standard_id,
        subject_id: subject_id,
        grade_id: grade_id
    };

    $('#suggestedContentModal').modal('show');
    $('#suggestedContentModal .modal-body').html(`
        <div class="text-center py-5">
            <div class="spinner-border text-primary mb-3" role="status">
                <span class="sr-only">Loading...</span>
            </div>
            <p class="text-muted">🧠 Analyzing your learning profile with Pedagogy Engine...</p>
            <div class="progress" style="height: 6px; max-width: 300px; margin: 0 auto;">
                <div class="progress-bar progress-bar-striped progress-bar-animated" style="width: 100%;"></div>
            </div>
        </div>
    `);

    $.ajax({
        url: '/lms/pedagogy-suggested-content',
        type: 'GET',
        data: {
            standard_id: standard_id,
            subject_id: subject_id,
            chapter_id: chapter_id,
            grade_id: grade_id,
            type: 'AJAX'
        },
        success: function(response) {
            console.log('Pedagogy Suggested Content Response:', response);

            if (response.status_code !== 1 && response.status !== 1) {
                $('#suggestedContentModal .modal-body').html(`
                    <div class="alert alert-danger text-center py-5">
                        <i class="mdi mdi-alert-circle mdi-36px"></i>
                        <p class="mt-2">${escapeHtml(response.message || 'Unable to load suggested content.')}</p>
                        <button class="btn btn-primary btn-sm mt-2" onclick="suggestedContent(${grade_id}, ${subject_id}, ${chapter_id}, ${standard_id})">
                            <i class="mdi mdi-refresh"></i> Retry
                        </button>
                    </div>
                `);
                return;
            }

            pedagogySuggestedContentData = response.content_data || {};
            currentPedagogyStudentLevel = (response.student_level || 'medium').toString().toLowerCase();

            var html = renderPedagogyContent(
                response.content_data,
                response.pedagogy,
                response.student_profile,
                response.mastery_summary,
                response.teacher_insights,
                response.student_level
            );
            $('#suggestedContentModal .modal-body').html(html);
        },
        error: function(xhr, status, error) {
            console.error('Error loading pedagogy suggested content:', error);
            $('#suggestedContentModal .modal-body').html(`
                <div class="alert alert-danger text-center py-5">
                    <i class="mdi mdi-alert-circle mdi-36px"></i>
                    <p class="mt-2">Error loading content. Please try again.</p>
                    <button class="btn btn-primary btn-sm mt-2" onclick="suggestedContent(${grade_id}, ${subject_id}, ${chapter_id}, ${standard_id})">
                        <i class="mdi mdi-refresh"></i> Retry
                    </button>
                </div>
            `);
        }
    });
}

// ============================================================
// PEDAGOGY ENGINE - RENDER FUNCTIONS
// ============================================================

function renderPedagogyContent(contentData, pedagogy, studentProfile, masterySummary, teacherInsights, studentLevel) {
    var html = '';

    // ============================================================
    // 1. PEDAGOGY ENGINE HEADER
    // ============================================================
    html += `
        <div class="pedagogy-engine-header bg-gradient-primary text-white p-4 rounded mb-4">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4><i class="mdi mdi-brain mr-2"></i>Pedagogy Engine</h4>
                    <p class="mb-0 opacity-75">Personalized content based on your learning profile</p>
                </div>
                <div class="text-right">
                    <span class="badge badge-light p-2">
                        <i class="mdi mdi-account"></i> Level: ${formatLevelLabel(studentLevel)}
                    </span>
                </div>
            </div>
        </div>
    `;

    // ============================================================
    // 2. STUDENT PROFILE SUMMARY
    // ============================================================
    if (studentProfile) {
        html += `
            <div class="row mb-3">
                <div class="col-md-4">
                    <div class="card bg-light">
                        <div class="card-body p-2 text-center">
                            <small class="text-muted">Learning Style</small>
                            <h6 class="mb-0">${escapeHtml(studentProfile.learning_style || 'Visual')}</h6>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card bg-light">
                        <div class="card-body p-2 text-center">
                            <small class="text-muted">Learning Pace</small>
                            <h6 class="mb-0">${escapeHtml(studentProfile.learning_pace || 'Medium')}</h6>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card bg-light">
                        <div class="card-body p-2 text-center">
                            <small class="text-muted">Preferred Content</small>
                            <h6 class="mb-0">${studentProfile.preferred_content_types ? studentProfile.preferred_content_types.join(', ') : 'Mixed'}</h6>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    // ============================================================
    // 3. MASTERY SUMMARY
    // ============================================================
    if (masterySummary) {
        html += `
            <div class="row mb-3">
                <div class="col-3 text-center">
                    <span class="badge badge-success p-2 d-block">${masterySummary.mastered || 0}</span>
                    <small>Mastered</small>
                </div>
                <div class="col-3 text-center">
                    <span class="badge badge-warning p-2 d-block">${masterySummary.developing || 0}</span>
                    <small>Developing</small>
                </div>
                <div class="col-3 text-center">
                    <span class="badge badge-danger p-2 d-block">${masterySummary.needs_practice || 0}</span>
                    <small>Needs Practice</small>
                </div>
                <div class="col-3 text-center">
                    <span class="badge badge-info p-2 d-block">${masterySummary.due_for_review || 0}</span>
                    <small>Not Started</small>
                </div>
            </div>
        `;
    }

    // ============================================================
    // 4. PEDAGOGY RECOMMENDATIONS
    // ============================================================
    if (pedagogy && pedagogy.length > 0) {
        html += `
            <div class="pedagogy-recommendations mb-3">
                <h6><i class="mdi mdi-lightbulb-on"></i> Pedagogy Recommendations</h6>
        `;
        pedagogy.forEach(function(rec) {
            html += `
                <div class="alert alert-${rec.type === 'status' ? 'info' : rec.type === 'focus_area' ? 'danger' : 'warning'} alert-dismissible fade show p-2 mb-1">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <strong>${rec.label}</strong>
                            <div class="small">${rec.reason}</div>
                            ${rec.tip ? `<div class="small mt-1"><i class="mdi mdi-lightbulb-outline"></i> ${rec.tip}</div>` : ''}
                        </div>
                        ${rec.cognitive_load_display ? `<span class="badge badge-secondary">Cognitive Load: ${rec.cognitive_load_display}</span>` : ''}
                    </div>
                </div>
            `;
        });
        html += `</div>`;
    }

    // ============================================================
    // 5. TEACHER INSIGHTS
    // ============================================================
    if (teacherInsights && teacherInsights.length > 0) {
        html += `
            <div class="teacher-insights mb-3">
                <h6><i class="mdi mdi-account-tie"></i> Teacher Insights</h6>
        `;
        teacherInsights.forEach(function(insight) {
            html += `
                <div class="alert alert-${insight.priority === 'high' ? 'danger' : 'info'} p-2 mb-1">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <strong>${insight.title}</strong>
                            <div class="small">${insight.description}</div>
                        </div>
                        <span class="badge badge-${insight.priority === 'high' ? 'danger' : 'info'}">${insight.priority}</span>
                    </div>
                </div>
            `;
        });
        html += `</div>`;
    }

    // ============================================================
    // 6. CONTENT CATEGORIES
    // ============================================================
    if (contentData) {
        // Remediation Content
        if (contentData.remediation_content && contentData.remediation_content.length > 0) {
            html += `
                <div class="content-category remediation-content mb-3">
                    <h5><i class="mdi mdi-school"></i> Remediation Content</h5>
                    <p class="text-muted small">Building your foundation</p>
            `;
            contentData.remediation_content.forEach(function(content, index) {
                html += buildPedagogyContentCard(content, index, 'remediation');
            });
            html += `</div>`;
        }

        // Practice Content
        if (contentData.practice_content && contentData.practice_content.length > 0) {
            html += `
                <div class="content-category practice-content mb-3">
                    <h5><i class="mdi mdi-flag-checkered"></i> Practice Content</h5>
                    <p class="text-muted small">Reinforce your learning</p>
            `;
            contentData.practice_content.forEach(function(content, index) {
                html += buildPedagogyContentCard(content, index, 'practice');
            });
            html += `</div>`;
        }

        // Enrichment Content
        if (contentData.enrichment_content && contentData.enrichment_content.length > 0) {
            html += `
                <div class="content-category enrichment-content mb-3">
                    <h5><i class="mdi mdi-rocket"></i> Enrichment Content</h5>
                    <p class="text-muted small">Challenge yourself further</p>
            `;
            contentData.enrichment_content.forEach(function(content, index) {
                html += buildPedagogyContentCard(content, index, 'enrichment');
            });
            html += `</div>`;
        }

        // Misconception Content
        if (contentData.misconception_content && contentData.misconception_content.length > 0) {
            html += `
                <div class="content-category misconception-content mb-3">
                    <h5><i class="mdi mdi-bug"></i> Common Misconceptions</h5>
                    <p class="text-muted small">Avoid common mistakes</p>
            `;
            contentData.misconception_content.forEach(function(content, index) {
                html += buildPedagogyContentCard(content, index, 'misconception');
            });
            html += `</div>`;
        }
    }

    // ============================================================
    // 7. STORE CONTENT BUTTON
    // ============================================================
   

    return html;
}

// ============================================================
// BUILD PEDAGOGY CONTENT CARD
// ============================================================

function buildPedagogyContentCard(content, index, category) {
    var mappingId = 'pedagogy_mapping_' + index + '_' + Date.now();
    var title = content.title || content.content_title || 'Untitled';
    var levelLabel = formatLevelLabel(content.student_level || 'medium');
    var pedagogyType = content.pedagogy_type || category;
    var pedagogyLabel = content.recommended_pedagogy ? content.recommended_pedagogy.label : formatPedagogyLabel(pedagogyType);
    var learningTips = content.learning_tips || [];

    var badgeColor = category === 'remediation' ? 'danger' : 
                     category === 'practice' ? 'warning' : 
                     category === 'enrichment' ? 'success' : 'info';
    var contentLink = content.filename || content.url || content.content_url || content.content_link || '';
    var contentId = content.id || content.question_id || index;
    
    var titleHtml = '';
    if (contentLink) {
        titleHtml = `<a href="${escapeHtml(contentLink)}" target="_blank" onclick="trackContentVisit(${contentId}, ${content.standard_id || 'null'}, ${content.subject_id || 'null'}, ${content.chapter_id || 'null'}, '${category}')" style="color: inherit;">${escapeHtml(title)}</a>`;
    } else {
        titleHtml = escapeHtml(title);
    }

    var html = `
        <div class="content-item card mb-2" data-mapping-id="${mappingId}">
            <div class="card-body">
                <h6>
                    <span class="content-title">
                        <span class="badge badge-${badgeColor} mr-2">${category}</span>
                        ${titleHtml}
                    </span>
                    <span class="content-meta">
                        <span class="content-pill level-badge">Level: ${levelLabel}</span>
                        <span class="content-pill pedagogy-badge">${pedagogyLabel}</span>
                        <button type="button" class="btn btn-sm content-pill mapping-type-pill" 
                                onclick="togglePedagogyMapping('${mappingId}')" title="View Pedagogy Details">
                            <i class="mdi mdi-information-outline"></i> Pedagogy Details
                        </button>
                    </span>
                </h6>

                ${content.description ? `<p class="small text-muted">${escapeHtml(content.description)}</p>` : ''}

                <!-- Learning Tips -->
                ${learningTips.length > 0 ? `
                    <div class="mt-2">
                        <small class="text-muted">💡 Learning Tips:</small>
                        <ul class="list-unstyled small mb-0">
                            ${learningTips.map(function(tip) {
                                return `<li><i class="mdi mdi-chevron-right text-primary"></i> ${escapeHtml(tip)}</li>`;
                            }).join('')}
                        </ul>
                    </div>
                ` : ''}

                <!-- Pedagogy Mapping Details -->
                <div class="pedagogy-mapping-details mt-3" style="display:none;" id="${mappingId}">
                    ${renderPedagogyMappingDetails(content)}
                </div>
            </div>
        </div>
    `;

    return html;
}

function renderPedagogyMappingDetails(content) {
    var mappings = content.mapping || [];
    var countLabel = mappings.length === 1 ? '1 value' : mappings.length + ' values';
    var html = `
        <div class="pedagogy-mapping-panel">
            <div class="pedagogy-mapping-panel-header">
                <div class="d-flex align-items-center">
                    <i class="mdi mdi-tag-multiple mr-2"></i>
                    <span class="pedagogy-mapping-title">Mapping Values</span>
                </div>
                <span class="pedagogy-mapping-count">${countLabel}</span>
            </div>
    `;

    if (mappings.length > 0) {
        html += '<div class="pedagogy-mapping-list">';
        mappings.forEach(function(mapping, index) {
            var accentClass = index % 3 === 0 ? 'accent-blue' : (index % 3 === 1 ? 'accent-cyan' : 'accent-purple');
            html += `
                <div class="pedagogy-mapping-row">
                    <div class="pedagogy-mapping-row-accent ${accentClass}"></div>
                    <div class="pedagogy-mapping-label">${escapeHtml(mapping.type_name || 'Mapping Type')}</div>
                    <div class="pedagogy-mapping-value">${escapeHtml(mapping.value_name || '—')}</div>
                </div>
            `;
        });
        html += '</div>';
    } else {
        html += `
            <div class="pedagogy-mapping-empty">
                <i class="mdi mdi-information-outline mr-1"></i>
                No mapping information available
            </div>
        `;
    }

    html += '</div>';
    return html;
}

// ============================================================
// TOGGLE PEDAGOGY MAPPING
// ============================================================

function togglePedagogyMapping(mappingId) {
    var mappingDiv = $('#' + mappingId);
    
    if (mappingDiv.is(':visible')) {
        mappingDiv.slideUp(250);
    } else {
        $('.pedagogy-mapping-details').slideUp(250);
        mappingDiv.slideDown(250);
    }
}

// ============================================================
// STORE PEDAGOGY CONTENT
// ============================================================

function storePedagogyContent() {
    if (!pedagogySuggestedContentData || Object.keys(pedagogySuggestedContentData).length === 0) {
        alert('No content to store');
        return;
    }

    var chapterId = currentPedagogyContext.chapter_id || '{{ $data["questionpaper_data"]["paper_desc"] ?? 0 }}';
    var payloadContentData = {};
    payloadContentData[chapterId] = pedagogySuggestedContentData;

    var button = $('#storeContentBtn');
    button.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Storing...');

    $.ajax({
        url: '/lms/store-suggested-content',
        type: 'POST',
        data: {
            _token: '{{ csrf_token() }}',
            content_data: payloadContentData,
            student_level: currentPedagogyStudentLevel,
            sub_institute_id: '{{ session()->get("sub_institute_id") }}',
            standard_id: currentPedagogyContext.standard_id || '{{ $data["questionpaper_data"]["standard_id"] ?? 0 }}',
            subject_id: currentPedagogyContext.subject_id || '{{ $data["questionpaper_data"]["subject_id"] ?? 0 }}',
            chapter_id: chapterId,
            syear: '{{ session()->get("syear") }}'
        },
        success: function(response) {
            if (response.status_code === 1 || response.status === 1) {
                alert('✅ Content stored successfully! (' + (response.stored || 0) + ' items stored)');
                $('#suggestedContentModal').modal('hide');
            } else {
                alert('❌ Error storing content: ' + (response.message || 'Unknown error'));
            }
        },
        error: function(xhr) {
            console.error('Error:', xhr);
            alert('❌ Error storing content. Please try again.');
        },
        complete: function() {
            button.prop('disabled', false).html('<i class="fa fa-save"></i> Store Content');
        }
    });
}

// ============================================================
// UPDATE STORE CONTENT FUNCTION
// ============================================================

// Override the existing storeContent function
function storeContent() {
    storePedagogyContent();
}

// ============================================================
// FORMAT PEDAGOGY LABEL
// ============================================================

function formatPedagogyLabel(pedagogyType) {
    if (!pedagogyType) return '';
    var labels = {
        'remediation': '📚 Remediation',
        'practice': '✏️ Practice',
        'review': '🔄 Review',
        'enrichment': '🚀 Enrichment',
        'misconception': '🐛 Misconception'
    };
    return labels[pedagogyType] || pedagogyType.charAt(0).toUpperCase() + pedagogyType.slice(1);
}

var currentMisconceptionChapterId = null;
var lastMisconceptionGenerationMessage = '';

function misconception(chapter_id){
    currentMisconceptionChapterId = chapter_id;
    $('#misconceptionModal').modal('show');
    $('#misconceptionModal .modal-body').html('<div class="text-center py-4"><i class="fa fa-spinner fa-spin fa-2x text-primary"></i><p class="mt-2">Loading...</p></div>');
    $('#generateMisconceptionContentBtn').prop('disabled', true).html('<i class="fa fa-magic"></i> Generate Content');

    console.log('Fetching misconception data for chapter:', chapter_id);
    
    $.ajax({
        url: '/lms/misconception',
        type: 'GET',
        data: { chapter_id: chapter_id },
        success: function(response){
            console.log('Misconception API Response:', response);
            
            var html = '';
            
            // Check if we have data
            if(response.data && response.data.length > 0){
                console.log('Found ' + response.data.length + ' wrong questions');
                html += '<div class="misconception-questions">';
                
                $.each(response.data, function(i, q){
                    console.log('Question ' + (i+1) + ':', q.question);
                    
                    var mappingHtml = '';
                    
                    // Build mapping HTML with clear structure
                    if(q.mapping && q.mapping.length > 0){
                        mappingHtml = '<div class="mapping-details" style="display:none;" id="mapping_' + q.question_id + '">';
                        mappingHtml += '<div class="card">';
                        mappingHtml += '<h6><i class="fa fa-tags"></i> QUESTION MAPPING</h6>';
                        mappingHtml += '<div class="table-responsive">';
                        mappingHtml += '<table class="table table-bordered mb-0">';
                        mappingHtml += '<thead>';
                        mappingHtml += '<tr><th>MAPPING TYPE</th><th>MAPPING VALUE</th></tr>';
                        mappingHtml += '</thead>';
                        mappingHtml += '<tbody>';
                        
                        $.each(q.mapping, function(mi, mapping){
                            mappingHtml += '<tr>';
                            mappingHtml += '<td>' + escapeHtml(mapping.type_name || '—') + '</td>';
                            mappingHtml += '<td>' + escapeHtml(mapping.value_name || '—') + '</td>';
                            mappingHtml += '</tr>';
                        });
                        
                        mappingHtml += '</tbody>';
                        mappingHtml += '</table>';
                        mappingHtml += '</div>';
                        mappingHtml += '</div></div>';
                    } else {
                        mappingHtml = '<div class="mapping-details" style="display:none;" id="mapping_' + q.question_id + '">';
                        mappingHtml += '<div class="card">';
                        mappingHtml += '<div class="card-body text-center py-3">';
                        mappingHtml += '<p class="text-muted mb-0"><i class="fa fa-info-circle"></i> No mapping information available</p>';
                        mappingHtml += '</div></div></div>';
                    }
                    
                    html += `
                        <div class="card mb-3 misconception-question-card" data-question-id="${q.question_id}">
                            <div class="card-body">
                                <div class="question-row" onclick="toggleQuestionMapping(${q.question_id})">
                                    <div class="question-text">
                                        <strong>Question ${i+1}:</strong> ${escapeHtml(q.question)}
                                    </div>
                                    <div class="wrong-count">
                                        <span class="badge badge-danger">
                                            Wrong ${q.wrong_count} time${q.wrong_count > 1 ? 's' : ''}
                                        </span>
                                        ${q.content_generated ? '<span class="badge badge-success">Generated</span>' : '<span class="badge badge-warning">Pending</span>'}
                                        <i class="fa fa-chevron-down toggle-icon"></i>
                                    </div>
                                </div>
                                ${mappingHtml}
                            </div>
                        </div>
                    `;
                });
                
                html += '</div>';
            } else {
                console.log('No wrong questions found for this chapter');
                html = '<div class="alert alert-info text-center py-5">';
                html += '<i class="fa fa-info-circle fa-4x mb-3" style="color: #17a2b8;"></i>';
                html += '<h5>No Wrong Questions</h5>';
                html += '<p class="mb-0">There are no wrong answers recorded for this chapter yet.</p>';
                html += '<p class="mt-2 small">Take a quiz first to see your incorrect answers here.</p>';
                html += '</div>';
            }

            if(lastMisconceptionGenerationMessage) {
                html = lastMisconceptionGenerationMessage + html;
                lastMisconceptionGenerationMessage = '';
            }

            $('#misconceptionModal .modal-body').html(html);
            $('#generateMisconceptionContentBtn').prop('disabled', !(response.data && response.data.length > 0));
        },
        error: function(xhr, status, error){
            console.error("Error loading misconception data:", error);
            console.error("Response text:", xhr.responseText);
            $('#misconceptionModal .modal-body').html(
                '<div class="alert alert-danger text-center py-5">' +
                '<i class="fa fa-exclamation-triangle fa-3x mb-3"></i>' +
                '<h5>Error Loading Data</h5>' +
                '<p class="mb-0">Please try again later.</p>' +
                '<p class="mt-2 small text-muted">Error: ' + escapeHtml(error) + '</p>' +
                '</div>'
            );
        }
    });
}

function generateMisconceptionContent(){
    if(!currentMisconceptionChapterId) {
        return;
    }

    var button = $('#generateMisconceptionContentBtn');
    button.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Generating...');

    $.ajax({
        url: '/lms/misconception/generate-content',
        type: 'POST',
        data: {
            chapter_id: currentMisconceptionChapterId,
            _token: $('meta[name="csrf-token"]').attr('content')
        },
        success: function(response){
            if(!response || Number(response.status) !== 1) {
                var errorMessage = response && response.message
                    ? response.message
                    : 'Unable to generate misconception content. Please try again.';

                $('#misconceptionModal .modal-body').prepend(
                    '<div class="alert alert-danger alert-dismissible">' +
                    '<button type="button" class="close" data-dismiss="alert">&times;</button>' +
                    escapeHtml(errorMessage) +
                    '</div>'
                );
                button.prop('disabled', false).html('<i class="fa fa-magic"></i> Generate Content');
                return;
            }

            var generated = response.generated || 0;
            var skipped = response.skipped || 0;
            var message = (response.message || 'Misconception content generated successfully.') +
                '<br><strong>Generated:</strong> ' + generated +
                '<br><strong>Skipped:</strong> ' + skipped + ' (already exists)';

            lastMisconceptionGenerationMessage =
                '<div class="alert alert-success alert-dismissible">' +
                '<button type="button" class="close" data-dismiss="alert">&times;</button>' +
                message +
                '</div>';

            misconception(currentMisconceptionChapterId);
        },
        error: function(xhr){
            var message = 'Unable to generate misconception content. Please try again.';
            if(xhr.responseJSON && xhr.responseJSON.message) {
                message = xhr.responseJSON.message;
            }

            $('#misconceptionModal .modal-body').prepend(
                '<div class="alert alert-danger alert-dismissible">' +
                '<button type="button" class="close" data-dismiss="alert">&times;</button>' +
                escapeHtml(message) +
                '</div>'
            );
            button.prop('disabled', false).html('<i class="fa fa-magic"></i> Generate Content');
        }
    });
}

function toggleQuestionMapping(questionId){
    var mappingDiv = $('#mapping_' + questionId);
    var card = $('.misconception-question-card[data-question-id="' + questionId + '"]');
    var chevron = card.find('.toggle-icon');
    
    if(mappingDiv.is(':visible')) {
        mappingDiv.slideUp(250);
        chevron.removeClass('fa-chevron-up').addClass('fa-chevron-down');
        card.removeClass('border-primary');
    } else {
        $('.mapping-details').slideUp(250);
        $('.toggle-icon').removeClass('fa-chevron-up').addClass('fa-chevron-down');
        $('.misconception-question-card').removeClass('border-primary');
        
        mappingDiv.slideDown(250);
        chevron.removeClass('fa-chevron-down').addClass('fa-chevron-up');
        card.addClass('border-primary');
    }
}

function escapeHtml(text) {
    if(!text) return '';
    return String(text)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}
</script>

@include('includes.footer')
@endsection
