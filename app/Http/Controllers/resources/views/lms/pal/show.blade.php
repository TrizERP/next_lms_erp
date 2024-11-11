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

function generateExam(grade_id,subject_id,chapter_id,standard_id,enrollment_no){
      if (chapter_id !== '' && chapter_id !== 'undefined') {
        window.location.href = '/lms/pal/create?subject_id='+subject_id+'&chapter_id='+chapter_id+'&grade_id='+grade_id+'&standard_id='+standard_id+'&enrollment_no='+enrollment_no;
    }
}

</script>

@include('includes.footer')
@endsection