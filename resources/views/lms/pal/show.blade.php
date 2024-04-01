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
        <div class="card">
			<div class="row mb-2">
				<div class="alert alert-{{ $sessionData['status_code'] == 1 ? 'success' : 'danger' }} alert-block">
					<button type="button" class="close" data-dismiss="alert">×</button>
					<strong>{!! $sessionData['message'] !!}</strong>
				</div>
	        </div>
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
                                <input type="hidden" name="subject" id="subject" data-val="{{$subject_id}}" value="{{$subject_id}}">
                                <input type="hidden" name="standard" id="standard" value="{{$data['studentDetails']['standard_id']}}" data-val="{{$subject_id}}"> 
                                <input type="hidden" name="grade_id" id="grade_id" value="{{$data['studentDetails']['grade_id']}}" data-val="{{$subject_id}}">                                
                                                               
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
                                        <div class="col-md-4 mb-2 chp-details" data-toggle="collapse" href="" role="button" aria-expanded="false" aria-controls="">
                                            <div class="count">{{$j++}}</div>
                                            <div class="title">
                                            <span onclick='generateExam({{$subject_id}})'>
                                                <input type="hidden" name="chapter" id="chapter" data-val="{{$subject_id}}" value="{{$chapter_id}}">
                                                {{$chapter_name}}
                                            </span>
                                            </div>
                                        </div>
                                    </div>                                        
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
function generateExam(subject_id){
    var chapter_id = $('#chapter[data-val="'+subject_id+'"]').val();
    var standard = $('#standard[data-val="'+subject_id+'"]').val();
    var grade_id = $('#grade_id[data-val="'+subject_id+'"]').val(); 
        
    if (chapter_id !== '' && chapter_id !== 'undefined') {
        window.location.href = '/lms/pal/create?subject_id='+subject_id+'&chapter_id='+chapter_id+'&grade_id='+grade_id+'&standard_id='+standard;
    }

}
</script>

@include('includes.footer')
@endsection