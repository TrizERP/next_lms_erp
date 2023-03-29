@include('includes.lmsheadcss')
<!-- Content main Section -->
<div class="content-main flex-fill">
    <div class="row">
        <div class="col-md-6">
            <h1 class="h4 mb-3">Result of Counselling Exam : {{$data['exam_data']['course_title']}}</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb bg-transparent p-0">
                    <li class="breadcrumb-item"><a href="http://202.47.117.124/triz-lms">Home</a></li>                                        
                    <li class="breadcrumb-item active" aria-current="page">Result</li>
                </ol>
            </nav>
        </div>        
    </div>

    <div class="container-fluid mb-5">
        <div class="course-grid-tab tab-pane fade show active" id="grid" role="tabpanel" aria-labelledby="grid-tab">                                
            <div class="card border-0 rounded mb-5">
                <div class="card-body">
                    <div class="row justify-content-center py-3">
                        <div class="col-md-3 text-center my-2">
                            <div class="answer-box right">{{$data['online_exam_data']['obtain_marks']}}/{{$data['exam_data']['total_marks']}}</div>
                            <div class="h4 mb-0">Total Marks</div>
                        </div>
                        <div class="col-md-3 text-center my-2">
                            <div class="answer-box wrong">{{$data['online_exam_data']['total_right']}}/{{$data['exam_data']['total_ques']}}</div>
                            <div class="h4 mb-0">Right Answer</div>
                        </div>
                        <div class="col-md-3 text-center my-2">
                            <div class="answer-box uttemp">{{$data['online_exam_data']['total_wrong']}}/{{$data['exam_data']['total_ques']}}</div>
                            <div class="h4 mb-0">Wrong Answer</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="container-fluid mb-4">
                <div class="quiz-box">

                    @php 
                    $i = 1;                                        
                    @endphp 

                    @if($data['exam_data']['result_show_ans'] == 1)<!--Show right answer block if result_show_ans is set to 1-->

                    @foreach($data['question_arr'] as $quesid => $quesarr)
                    @if(!isset($data['online_answer_data'][$quesarr['id']])) 
                   
                    <div class="row mb-3">
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
                                   <!--  <a href="javascript:void(0)" class="float-right" data-container="body" data-toggle="popover" data-placement="left" data-content="Vivamus sagittis lacus vel augue laoreet rutrum faucibus." data-trigger="hover">
                                        <i class="mdi mdi-alert-circle-outline"></i>
                                    </a> -->
                                    <div class="quiz-title">{{$quesarr['question_title']}}</div>
                                   
                                    <div class="quiz-option">
                                        <!-- <div class="title">Select One</div> -->
                                        @if($quesarr['question_type_id'] == "2") <!--Narrative Question-->
                                        <ul>
                                            <li>
                                                <!-- <div class="custom-control custom-radio custom-control-inline"> -->
                                                    <textarea type="text" rows="4" placeholder="Enter Answer" class="form-control" name="answer[{{$quesarr['id']}}]"></textarea>
                                                <!-- </div> -->
                                            </li>
                                        </ul>
                                        @elseif($quesarr['question_type_id'] == "1") <!--Multple Option Question-->
                                            <ul>
                                            @if(isset($data['answer_arr'][$quesarr['id']]))                     
                                                @foreach($data['answer_arr'][$quesarr['id']] as $ansid => $ansarr)
                                                    @php                                                                                                     
                                                    if($quesarr['multiple_answer'] == 1) //Multiple answer
                                                    {
                                                        $btnclass = "square";
                                                        $type = "checkbox";
                                                        $name = "answer[".$quesarr['id']."][".$ansarr['id']."][]";                                                                                                
                                                    }
                                                    else{ //Single answer                                                
                                                        $btnclass = "dot";
                                                        $type = "radio";
                                                        $name = "answer[".$quesarr['id']."][".$ansarr['id']."]";                                                       
                                                    }
                                                    @endphp
                                                    
                                                    <li>
                                                        <div class="custom-control custom-{{$type}} custom-control-inline">
                                                            <input type="{{$type}}" name="{{$name}}" value="{{$ansarr['correct_answer']}}" class="custom-control-input">
                                                            <label class="custom-control-label" for="customRadioInline1">{{$ansarr['answer']}}</label>
                                                        </div>
                                                    </li>                                                    
                                                    
                                                @endforeach
                                            @endif
                                            </ul>
                                        @endif                                        
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    @else                    
                    <div class="row mb-3">
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
                                   <!--  <a href="javascript:void(0)" class="float-right" data-container="body" data-toggle="popover" data-placement="left" data-content="Vivamus sagittis lacus vel augue laoreet rutrum faucibus." data-trigger="hover">
                                        <i class="mdi mdi-alert-circle-outline"></i>
                                    </a> -->
                                    <div class="quiz-title">{{$quesarr['question_title']}}</div>
                                    @if($data['online_answer_data'][$quesarr['id']]['RIGHT_WRONG'] == "right")
                                    <div class="alert alert-success text-light" role="alert">Chosen as right answer</div>
                                    @elseif($data['online_answer_data'][$quesarr['id']]['RIGHT_WRONG'] == "wrong")
                                    <div class="alert alert-danger" role="alert">Chosen as wrong answer</div>
                                    @endif
                                    <div class="quiz-option">
                                        <!-- <div class="title">Select One</div> -->
                                        @if($quesarr['question_type_id'] == "2") <!--Narrative Question-->
                                        <ul>
                                            <li>
                                                <!-- <div class="custom-control custom-radio custom-control-inline"> -->
                                                    <textarea type="text" rows="4" placeholder="Enter Answer" class="form-control" name="answer[{{$quesarr['id']}}]">@if(isset($data['online_answer_data'][$quesarr['id']]['GIVEN_ANSWER'])){{$data['online_answer_data'][$quesarr['id']]['GIVEN_ANSWER']}}@endif</textarea>
                                                <!-- </div> -->
                                            </li>
                                        </ul>
                                        @elseif($quesarr['question_type_id'] == "1") <!--Multple Option Question-->
                                            <ul>
                                            @if(isset($data['answer_arr'][$quesarr['id']]))                     
                                                @foreach($data['answer_arr'][$quesarr['id']] as $ansid => $ansarr)
                                                    @php                                                                                                     
                                                    if($quesarr['multiple_answer'] == 1) //Multiple answer
                                                    {
                                                        $btnclass = "square";
                                                        $type = "checkbox";
                                                        $name = "answer[".$quesarr['id']."][".$ansarr['id']."][]";
                                                        $div_wrong_class = $wrong_class = $checked = "";
                                                        $given_ans_arr = explode(",",$data['online_answer_data'][$quesarr['id']]['GIVEN_ANSWER']); 
                                                        $actual_ans_arr = explode(",",$data['online_answer_data'][$quesarr['id']]['ACTUAL_ANSWER']); 
                                                        if($ansarr['correct_answer'] == 1)
                                                        {
                                                            $checked = "checked=checked";
                                                        }
                                                        if( in_array($ansarr['id'] , $given_ans_arr) && $ansarr['correct_answer'] == 0)
                                                        {
                                                            $wrong_class = "text-danger";
                                                            $div_wrong_class = "wrong-answer";
                                                            $checked = "checked=checked";
                                                        }                                                        
                                                    }
                                                    else{ //Single answer                                                
                                                        $btnclass = "dot";
                                                        $type = "radio";
                                                        $name = "answer[".$quesarr['id']."][".$ansarr['id']."]";
                                                        $div_wrong_class = $wrong_class = $checked = "";                                                        
                                                        if($ansarr['correct_answer'] == 1)
                                                        {
                                                            $checked = "checked=checked";
                                                        }
                                                        if($ansarr['id'] == $data['online_answer_data'][$quesarr['id']]['GIVEN_ANSWER'] && $ansarr['correct_answer'] == 0)
                                                        {
                                                            $wrong_class = "text-danger";
                                                            $div_wrong_class = "wrong-answer";
                                                            $checked = "checked=checked";
                                                        }

                                                    }
                                                    @endphp
                                                    
                                                    <li>
                                                        <div class="custom-control custom-{{$type}} custom-control-inline {{$div_wrong_class}}">
                                                            <input {{$checked}} type="{{$type}}" name="{{$name}}" value="{{$ansarr['correct_answer']}}" class="custom-control-input">
                                                            <label class="custom-control-label {{$wrong_class}}" for="customRadioInline1">{{$ansarr['answer']}}</label>
                                                        </div>
                                                    </li>                                                    
                                                    
                                                @endforeach
                                            @endif
                                            </ul>
                                        @endif                                        
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    @endif
                    @endforeach

                    @endif
                </div>
            </div>
        </div>        
    </div>
</div>
@include('includes.lmsfooterJs')
@include('includes.footer')
