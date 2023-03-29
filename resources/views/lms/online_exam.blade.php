@include('includes.lmsheadcss')
<link rel="stylesheet" href="../../../tooltip/enjoyhint/jquery.enjoyhint.css">
<style>
html {
  scroll-behavior: smooth !important;
}
 br{
    display:  block !important;
}
</style>

<!-- Content main Section -->
<div class="content-main flex-fill">
    <div class="container-fluid mb-5">
        <div class="course-grid-tab tab-pane fade show active" id="grid" role="tabpanel" aria-labelledby="grid-tab">
            <ul class="nav nav-pills mb-3" id="pills-tab" role="tablist">
                <li class="nav-item">
                  <a class="nav-link active" id="chat-tab" data-toggle="pill" href="#chat" role="tab" aria-controls="chat-tab" aria-selected="false">Online Question Paper: {{$data['questionpaper_data']['paper_name']}}</a>
                </li>
            </ul>
            <form id="online_exam" method="post" action="{{ route('online_exam.store') }}">
            {{ method_field('POST') }}
            @csrf

            <input type="hidden" name="hid_session_quiz" id="hid_session_quiz" value="{{ request()->session()->get('session_quiz') }}">
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
                                    <!-- <a href="#" class="btn btn-outline-primary mb-3">Start a new Preview</a>-->
                                    <div class="color-primary mb-2">Total Marks : {{$data['questionpaper_data']['total_marks']}}</div> 
                                    <div class="color-primary mb-2">(Total {{$data['questionpaper_data']['time_allowed']}} mins)</div> 
                                    <div class="text-secondary">Time Left: <p id="showtimer"></p></div>                                  
                                </div>
                            </div>
                        </div>
                    </div>
                    <input type="hidden" name="questionpaper_time" id="questionpaper_time" value="{{$data['questionpaper_data']['time_allowed']}}">
                    <input type="hidden" name="questionpaper_id" id="questionpaper_id" value="{{$data['questionpaper_data']['id']}}">
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
                                            <div class="text-secondary mb-2">Marked out of <b>{{$quesarr['points']}}</b></div>
                                            <!-- <div class="text-secondary mb-2">{{$quesarr['points']}}</div> -->
                                            @if(isset($quesarr['hint_text']))
                                            <div class="text-secondary"><i data-toggle="tooltip" title="{{$quesarr['hint_text']}}" class="mdi mdi-alert-circle"></i></div><!--mdi-flag-outline-->
                                            @endif
                                        </div>
                                    </div>
                                </div>
                                <div class="col-10">
                                    <div class="card border-0 rounded">
                                        <div class="card-body">
                                            <!-- <a href="javascript:void(0)" class="float-right" data-container="body" data-toggle="popover" data-placement="left" data-content="Vivamus sagittis lacus vel augue laoreet rutrum faucibus." data-trigger="hover">
                                                <i class="mdi mdi-alert-circle-outline"></i>
                                            </a> -->
                                            <div class="quiz-title">{!!$quesarr['question_title']!!}</div>
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
<script type="text/javascript">
$(document).ready(function(){
    $('[data-toggle="tooltip"]').tooltip();   
});
</script>

<script src="//cdn.mathjax.org/mathjax/latest/MathJax.js"> 
 MathJax.Hub.Config({ 
   extensions: ["mml2jax.js"], 
   jax: ["input/MathML", "output/HTML-CSS"] 
 }); 
</script> 


<script>


//Set the date we're counting down to
//var countDownDate = new Date("Jan 7, 2021 15:57:25").getTime();

var min_to_add = $("#questionpaper_time").val();
var session_date = $("#hid_session_quiz").val();
// alert(session_date);
var dt = new Date(session_date);//new Date("<?php echo request()->session()->get('quiz'); ?>");
// console.log("session_time"+dt);
dt.setMinutes( dt.getMinutes() + parseInt(min_to_add) );
var countDownDate = dt.getTime();
// console.log("countDownDate"+countDownDate);
// Update the count down every 1 second
var x = setInterval(function() {

  // Get today's date and time
  var now = new Date().getTime();
    // console.log("now"+now);
  // Find the distance between now and the count down date
  var distance = (countDownDate - now);
  // console.log("distance"+distance);
    
  // Time calculations for days, hours, minutes and seconds
  var days = Math.floor(distance / (1000 * 60 * 60 * 24));
  var hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
  var minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
  var seconds = Math.floor((distance % (1000 * 60)) / 1000);

  // console.log("days"+days);
  // console.log("hours"+hours);
  // console.log("minutes"+minutes);
  // console.log("seconds"+seconds);
    
  // Output the result in an element with id="demo"
  document.getElementById("showtimer").innerHTML = hours + "h "+ minutes + "m " + seconds + "s ";
    
  // If the count down is over, write some text 
  if (distance < 0) {
    clearInterval(x);
    document.getElementById("showtimer").innerHTML = "EXPIRED";
    alert("Your Exam time is exipred");
    $("#online_exam").submit();
    @php
    request()->session()->forget("session_quiz");
    @endphp
    window.close();
  }
}, 1000);


</script>

@include('includes.footer')
