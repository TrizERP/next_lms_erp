@extends('lmslayout') @section('container')
<div id="page-wrapper">
	<div class="container-fluid">
		<div class="row bg-title">
			<div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
				<h4 class="page-title">PAL Subjects</h4>
			</div>
		</div>
		
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
                                        <div class="col-md-4 mb-2 chp-details" data-toggle="collapse" href="#quiz_{{$j}}" role="button" aria-expanded="false" aria-controls="quiz_{{$j}}">
                                            <div class="count">{{$j}}</div>
                                            <div class="title">
                                                {{$chapter_name}}
                                            </div>
                                           <div class="title">
    <span class="chapter-name">{{$chapter_name}}</span>
        @if(isset($data['perChapterQuiz'][$chapter_id]) && $data['perChapterQuiz'][$chapter_id] > 0)
    <div class="chapter-btns">
        <button class="btn btn-primary btn-sm"
            onclick="suggestedContent({{$data['studentDetails']['grade_id']}},{{$subject_id}},{{$chapter_id}},{{$data['studentDetails']['standard_id']}}, true)">
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
                                                $per= (($examValue['total_right'] * 100) / $total) ?? 0
                                            @endphp
                                            <div class="col-md-4 progress_bar">
                                            <p style="margin-bottom:0px !important">Progress</p>
                                            <div class="progress">
                                                <div class="progress-bar" role="progressbar" style="width: {{$per}}%" aria-valuenow="{{$per}}" aria-valuemin="0" aria-valuemax="100"></div>
                                                </div>
                                            </div>
                                            <div class="col-md-4 marks text-right">
                                                {{$examValue['total_right']}} / {{$total}}
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
<script>
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
    return category === 'Misconception Content' ? 'Misconception Content' : 'Teacher Content';
}

function renderSuggestedContentSections(contentData, prefix, defaultLevel) {
    var grouped = {
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

    var html = '<div class="suggested-content-container">';

    $.each(['Teacher Content', 'Misconception Content'], function(sectionIndex, sectionLabel) {
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
    html += '<span class="teacher-category-badge">' + escapeHtml(content.teacher_content_category || 'Misconception Content') + '</span>';
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

function suggestedContent(grade_id,subject_id,chapter_id,standard_id, loadFromDb = false){
       if (chapter_id !== '' && chapter_id !== 'undefined') {
         $('#suggestedContentModal').modal('show');
         $('#suggestedContentModal .modal-body').html('<div class="text-center"><i class="fa fa-spinner fa-spin"></i> Loading content...</div>');
         
         // If loadFromDb is true, fetch from suggested_content table
         if (loadFromDb) {
             $.ajax({
                 url: '/lms/get-suggested-content',
                 type: 'GET',
                 data: {
                     standard_id: standard_id,
                     subject_id: subject_id,
                     chapter_id: chapter_id,
                     grade_id: grade_id,
                     type: 'AJAX'
                 },
                 success: function(response) {
                     console.log("Suggested Content from DB Response:", response);
                     
                     if(response.status === 0) {
                         $('#suggestedContentModal .modal-body').html('<p class="text-danger">' + response.message + '</p>');
                         return;
                     }
                     
                     var html = renderSuggestedContentSections(response.content_data, 'db', response.student_level);
                     $('#suggestedContentModal .modal-body').html(html);
                 },
                 error: function(xhr, status, error) {
                     console.error("Error loading suggested content from DB:", error);
                     $('#suggestedContentModal .modal-body').html('<p class="text-danger">Error loading content. Please try again.</p>');
                 }
             });
         } else {
             // Original logic for getting suggested content from PAL generation
             $.ajax({
                 url: '/lms/suggested-content',
                 type: 'GET',
                 data: {
                     standard_id: standard_id,
                     subject_id: subject_id,
                     chapter_id: chapter_id,
                     grade_id: grade_id,
                     type: 'AJAX'
                 },
                 success: function(response) {
                     console.log("Suggested Content Response:", response);
                     
                     if(response.status === 0) {
                         $('#suggestedContentModal .modal-body').html('<p class="text-danger">' + response.message + '</p>');
                         return;
                     }
                     
                     var html = renderSuggestedContentSections(response.content_data, 'generated', response.student_level);
                     $('#suggestedContentModal .modal-body').html(html);
                     /*
                                         html += '<div class="content-item card mb-2">';
                                         html += '<div class="card-body">';
                                         html += '<h6>';
 
                                         // 👉 LINK CLICKABLE BANAVO
                                         if(content.file_type == 'link'){
                                             html += '<a href="' + content.filename + '" target="_blank">';
                                             html += (content.title || content.content_title || 'Untitled');
                                             html += '</a>';
                                         }else{
                                             html += (content.title || content.content_title || 'Untitled');
                                         }
 
                                         // 👉 LEVEL BADGE ADD
                                         html += '<span class="badge badge-secondary ml-2">Suggested</span>';
 
                                         html += '</h6>';

                                         if(content.content_link) {
                                             // html += '<a href="' + content.content_link + '" target="_blank" class="btn btn-sm btn-primary">View Content</a>';
                                             if(content.content_link){
                                                 html += '<a href="' + content.content_link + '" target="_blank" class="btn btn-sm btn-success mt-2">';
                                                 html += '<i class="fa fa-external-link"></i> Open Content';
                                                 html += '</a>';
                                             }
                                         }
                                         html += '</div></div>';
                                     });
                                     html += '</div>';
                                 }
                             });
                         });
                     } else {
                         html += '<p>No content available for this chapter.</p>';
                     }
                     
                     html += '</div>';
                     $('#suggestedContentModal .modal-body').html(html);
                     */
                 },
                 error: function(xhr, status, error) {
                     console.error("Error loading suggested content:", error);
                     $('#suggestedContentModal .modal-body').html('<p class="text-danger">Error loading content. Please try again.</p>');
                 }
             });
         }
     }
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