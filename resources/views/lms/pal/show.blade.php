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

.content-item h6 {
    display: flex;
    justify-content: space-between;
    align-items: center;
}
</style>
<script>
function openContent(link){
    if(link){
        window.open(link, '_blank');
    }
}
function generateExam(grade_id,subject_id,chapter_id,standard_id,enrollment_no){
      if (chapter_id !== '' && chapter_id !== 'undefined') {
        window.location.href = '/lms/pal/create?subject_id='+subject_id+'&chapter_id='+chapter_id+'&grade_id='+grade_id+'&standard_id='+standard_id+'&enrollment_no='+enrollment_no;
    }
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
                     
                     var html = '<div class="suggested-content-container">';
                     
                     if(response.content_data && Object.keys(response.content_data).length > 0) {
                         $.each(response.content_data, function(chapterId, categories) {
                             $.each(categories, function(category, contents) {
                                 if(contents && contents.length > 0) {
                                     html += '<div class="content-category mb-3">';
                                     html += '<h5>' + category + '</h5>';
                                     $.each(contents, function(index, content) {
                                         html += '<div class="content-item card mb-2">';
                                         html += '<div class="card-body">';
                                         html += '<h6>';
                                         
                                         // Handle link type content
                                         if(content.file_type == 'link'){
                                             html += '<a href="' + content.filename + '" target="_blank">';
                                             html += (content.title || content.content_title || 'Untitled');
                                             html += '</a>';
                                         }else{
                                             html += (content.title || content.content_title || 'Untitled');
                                         }
                                         
                                         // Add badge
                                         html += '<span class="badge badge-secondary ml-2">Suggested</span>';
                                         
                                         html += '</h6>';
                                         
                                         if(content.description) {
                                             html += '<p>' + content.description + '</p>';
                                         }
                                         
                                         if(content.content_link){
                                             html += '<a href="' + content.content_link + '" target="_blank" class="btn btn-sm btn-success mt-2">';
                                             html += '<i class="fa fa-external-link"></i> Open Content';
                                             html += '</a>';
                                         }
                                         html += '</div></div>';
                                     });
                                     html += '</div>';
                                 }
                             });
                         });
                     } else {
                         html += '<p>No suggested content available for this chapter.</p>';
                     }
                     
                     html += '</div>';
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
                     
                     var html = '<div class="suggested-content-container">';
                     
                     if(response.content_data && Object.keys(response.content_data).length > 0) {
                         $.each(response.content_data, function(chapterId, categories) {
                             $.each(categories, function(category, contents) {
                                 if(contents && contents.length > 0) {
                                     html += '<div class="content-category mb-3">';
                                     html += '<h5>' + category + '</h5>';
                                     $.each(contents, function(index, content) {
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
                                         if(content.description) {
                                             html += '<p>' + content.description + '</p>';
                                         }
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
                 },
                 error: function(xhr, status, error) {
                     console.error("Error loading suggested content:", error);
                     $('#suggestedContentModal .modal-body').html('<p class="text-danger">Error loading content. Please try again.</p>');
                 }
             });
         }
     }
 }

function misconception(chapter_id){
    $('#misconceptionModal').modal('show');
    $('#misconceptionModal .modal-body').html('<div class="text-center py-4"><i class="fa fa-spinner fa-spin fa-2x text-primary"></i><p class="mt-2">Loading...</p></div>');

    $.ajax({
        url: '/lms/misconception',
        type: 'GET',
        data: { chapter_id: chapter_id },
        success: function(response){
            var html = '';

            if(response.data && response.data.length > 0){
                html += '<div class="misconception-questions">';
                
                $.each(response.data, function(i, q){
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
                            mappingHtml += '</tr>';
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
                html = '<div class="alert alert-success text-center py-5">';
                html += '<i class="fa fa-check-circle fa-4x mb-3" style="color: #28a745;"></i>';
                html += '<h5>Excellent Work!</h5>';
                html += '<p class="mb-0">No wrong answers found for this chapter.</p>';
                html += '</div>';
            }

            $('#misconceptionModal .modal-body').html(html);
        },
        error: function(xhr, status, error){
            console.error("Error loading misconception data:", error);
            $('#misconceptionModal .modal-body').html(
                '<div class="alert alert-danger text-center py-5">' +
                '<i class="fa fa-exclamation-triangle fa-3x mb-3"></i>' +
                '<h5>Error Loading Data</h5>' +
                '<p class="mb-0">Please try again later.</p>' +
                '</div>'
            );
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