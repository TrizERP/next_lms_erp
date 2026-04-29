@extends('lmslayout') @section('container')
<div id="page-wrapper">
	<div class="container-fluid">
		<div class="row bg-title">
			<div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
				<h4 class="page-title">PAL Subjects</h4>
			</div>
		</div>
	<style>
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
                kesa laga mera majak 😁
            </div>
        </div>
    </div>
</div>
        <!-- <button class="btn btn-danger btn-sm"
            onclick="misconception({{$chapter_id}})">
            Misconception
        </button> -->
    </div>
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
                                    <div class="collapse" id="quiz_{{$j}}">
                                        <div class="row card single-chp mb-2" style="left:10% !important;width:90%">
                                            <div class="col-md-4 mb-2 chp-details" data-toggle="collapse" href="" role="button" aria-expanded="false" aria-controls="">
                                                <div class="title">
                                                    <span style="color:blue" onclick="suggestedContent({{$data['studentDetails']['grade_id']}},{{$subject_id}},{{$chapter_id}},{{$data['studentDetails']['standard_id']}})">Suggested Content <i class="fa fa-arrow-right" aria-hidden="true"></i> </span>
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
    $('#misconceptionModal .modal-body').html(' kesa laga mera majak 😁');

    $.ajax({
        url: '/lms/misconception', // controller route
        type: 'GET',
        data: {
            chapter_id: chapter_id
        },
        success: function(response){

            var html = '';

            if(response.data && response.data.length > 0){
                $.each(response.data, function(i, q){
                    html += `
                        <div class="card mb-2">
                            <div class="card-body">
                                <p><b>Q:</b> ${q.question}</p>
                                <p class="text-danger"><b>Your Answer:</b> ${q.user_answer}</p>
                                <p class="text-success"><b>Correct Answer:</b> ${q.correct_answer}</p>
                            </div>
                        </div>
                    `;
                });
            }else{
                html = '<p>No wrong questions found.</p>';
            }

            $('#misconceptionModal .modal-body').html(html);
        }
    });
}

</script>

@include('includes.footer')
@endsection