@include('includes.lmsheadcss')

<style>
html {
  scroll-behavior: smooth !important;
}
</style>

<!-- Content main Section -->
<div class="content-main flex-fill">
    <div class="container-fluid mb-5">
        <div class="course-grid-tab tab-pane fade show active" id="grid" role="tabpanel" aria-labelledby="grid-tab">
            <ul class="nav nav-pills mb-3" id="pills-tab" role="tablist">
                <li class="nav-item">
                  <a class="nav-link active" id="chat-tab" data-toggle="pill" href="#chat" role="tab" aria-controls="chat-tab" aria-selected="false">Counselling Test : {{$data['exam_data']['course_title']}}</a>
                </li>
            </ul>
            <form id="online_exam" method="post" action="{{ route('lmsCounsellingExam.store') }}">
            {{ method_field('POST') }}
            @csrf
            
            <input type="hidden" name="course_id" id="course_id" value="{{$data['exam_data']['course_id']}}">
            <div class="tab-content" id="pills-tabContent">
                <div class="tab-pane fade show active" id="chat" role="tabpanel" aria-labelledby="chat-tab">
                    <div class="card border-0 rounded mb-5">
                        <div class="card-body">
                            <div class="d-md-flex align-items-center justify-content-between">
                                <div class="quiz-labels">
                                    <div class="h4">Quiz Navigation</div>
                                    <ul class="quiz-navigation">
                                         @php 
                                        $i = 1; 
                                        @endphp                           
                                        @foreach($data['question_arr'] as $quesid => $quesarr)
                                            <li class="nav-item">
                                                <a href="#question-{{$quesarr['id']}}-tab">{{$i++}}</a>
                                            </li>
                                        @endforeach    
                                    </ul>
                                </div>
                                <div class="quiz-time">                                    
                                    <div class="color-primary mb-2">Total Question : {{$data['exam_data']['total_question']}}</div> 
                                    <div class="color-primary mb-2">Total Marks : {{$data['exam_data']['total_marks']}}</div>                                     
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="container-fluid mb-4">
                        <div class="quiz-box">
                            @php 
                            $i = 1;                           
                            @endphp                           
                            @foreach($data['question_arr'] as $quesid => $quesarr)
                            <div class="row mb-3" id="question-{{$quesarr['id']}}-tab">
                                <div class="col-2">
                                    <div class="quiz-box-count">
                                        <div class="count">{{$i++}}</div>
                                        <div class="quiz-con">
                                            <div class="text-secondary mb-2">Marked out of</div>
                                            <div class="text-secondary mb-2">{{$quesarr['points']}}</div>
                                            <!-- <div class="text-secondary"><i class="mdi mdi-flag-outline"></i></div> -->
                                        </div>
                                    </div>
                                </div>
                                <div class="col-10">
                                    <div class="card border-0 rounded">
                                        <div class="card-body">
                                            <!-- <a href="javascript:void(0)" class="float-right" data-container="body" data-toggle="popover" data-placement="left" data-content="Vivamus sagittis lacus vel augue laoreet rutrum faucibus." data-trigger="hover">
                                                <i class="mdi mdi-alert-circle-outline"></i>
                                            </a> -->
                                            <div class="quiz-title">{{$quesarr['question_title']}}</div>
                                            @if($quesarr['question_type_id'] == "2") <!--Narrative Question-->
                                            <div class="quiz-option">
                                                <textarea type="text" rows="4" placeholder="Enter Answer" class="form-control" name="answer_narrative[{{$quesarr['id']}}]"></textarea>
                                            </div>
                                            @elseif($quesarr['question_type_id'] == "1") <!--Multple Option Question-->
                                            <div class="quiz-option">
                                                 @if(isset($data['answer_arr'][$quesarr['id']]))                     
                                                    @foreach($data['answer_arr'][$quesarr['id']] as $ansid => $ansarr)
                                                    <ul>
                                                        @php
                                                        if($quesarr['multiple_answer'] == 1)
                                                        {
                                                            $btnclass = "square";
                                                            $type = "checkbox";
                                                            $name = "answer_multiple[".$quesarr['id']."][]";//[".$ansarr['id']."]";
                                                        }
                                                        else{                                                
                                                            $btnclass = "dot";
                                                            $type = "radio";
                                                            $name = "answer_single[".$quesarr['id']."]";
                                                        }
                                                        @endphp
                                                        <li>
                                                            <div>
                                                                <input type="{{$type}}" id="{{$name}}" name="{{$name}}" value="{{$ansarr['id']}}##{{$ansarr['correct_answer']}}">
                                                                {{$ansarr['answer']}}
                                                            </div>
                                                        </li>
                                                    </ul>
                                                    @endforeach
                                                @endif
                                            </div>
                                            @endif                         
                                        </div>
                                    </div>
                                </div>
                            </div>

                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
            <button class="btn btn-primary" type="submit">Submit</button>
            </form>
        </div>
    </div>
</div>


@include('includes.lmsfooterJs')
@include('includes.footer')
