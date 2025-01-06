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
            <form id="online_exam" method="post" action="{{ route('pal.store') }}">
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
                                                <a href="#question-{{$quesarr['question_id']}}-tab">{{$i++}}</a>
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
                    <input type="hidden" name="total_marks" id="total_marks" value="{{$data['questionpaper_data']['total_marks']}}">
                    <input type="hidden" name="total_question" id="total_question" value="{{count($data['question_arr'])}}">
                    
                   {{-- <input type="hidden" name="questionpaper_id" id="questionpaper_id" value="{{$data['questionpaper_data']['id']}}"> --}} 
                   <!-- standard and subject and chapter id  -->
                   <input type="hidden" name="grade_id" id="grade_id" value="{{$data['grade_id']}}">                   
                   <input type="hidden" name="standard_id" id="standard_id" value="{{$data['standard_id']}}">
                   <input type="hidden" name="subject_id" id="subject_id" value="{{$data['subject_id']}}">
                   <input type="hidden" name="chapter_id" id="chapter_id" value="{{$data['chapter_id']}}">
                   <input type="hidden" name="paper_name" id="paper_name" value="{{$data['questionpaper_data']['paper_name']}}">

                    <div class="container-fluid mb-4">
                        <div class="quiz-box">
                            @php 
                            $i = 1;                           
                            @endphp                           
                            @foreach($data['question_arr'] as $quesid => $quesarr)
                           <input type="hidden" name="question_ids[]" id="question_ids" value="{{$quesarr['question_id']}}">                    
                            <div class="row mb-3" id="question-{{$quesarr['question_id']}}-tab">
                                <div class="col-2">
                                    <div class="quiz-box-count">
                                        <div class="count">{{$i++}}</div>
                                        <div class="quiz-con">
                                            <div class="text-secondary mb-2">Marked out of <b>1</b>  <span style="padding:0px 10px" onclick="mapValueModel({{$quesarr['question_id']}});"><i class="fa fa-ellipsis-v" aria-hidden="true"></i></span></div>
                                            <!-- <div class="text-secondary mb-2">1</div> -->
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
                                            <div class="quiz-title">{!!$quesarr['question_text']!!}</div>
                                          <input type="hidden" name="interestValue[{{$quesarr['question_id']}}]" id="interest_{{$quesarr['question_id']}}">
                                            <div class="quiz-option">
                                                 @if(isset($data['answer_arr'][$quesarr['question_id']]))                     
                                                    @foreach($data['answer_arr'][$quesarr['question_id']] as $ansid => $ansarr)
                                                    <ul>
                                                        @php
                                                            $btnclass = "square";
                                                            $type = "radio";
                                                            $name = "answer_multiple[".$quesarr['question_id']."][]";//[".$ansarr['id']."]";
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

<!-- Modal -->
<div class="modal fade" id="exampleModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
  <div class="modal-dialog" role="document" style="max-width:1000px !important">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="exampleModalLabel">Question Mapped Values</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
            <h4>Question - <span id="questionValue"></span></h4>
            <table class="table" style="filter:none !important">
                <thead>
                    <tr>
                        <th>Sr No.</th>
                        <th>Mapped Types</th>
                        <th class="text-left">Mapped Values</th>
                    </tr>
                </thead>
                <tbody id="tableBody">
                </tbody>
            </table>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>
@include('includes.lmsfooterJs')
<script type="text/javascript">
$(document).ready(function(){
    $('[data-toggle="tooltip"]').tooltip(); 
    @foreach($data['question_arr'] as $quesid => $quesarr)
        onloadData({{$quesarr['question_id']}});
    @endforeach
});
function onloadData(questionId){
    console.log('Function onloadData called with questionId:', questionId); // Debug log
    $.ajax({
        url : "{{route('question_mapped_value')}}",
        data : {question_id:questionId},
        type: 'GET',
        success : function(response){
            if (response.MappedData) {
                $.each(response.MappedData, function(index, mappedItem) {
                    $.each(mappedItem.mappedValue, function(subIndex, mappedSubItem) {
                        $('#interest_'+questionId).val(mappedSubItem.name);
                    });
                });
            }
        }
    });  
}

</script>

<script src="//cdn.mathjax.org/mathjax/latest/MathJax.js"> 
 MathJax.Hub.Config({ 
   extensions: ["mml2jax.js"], 
   jax: ["input/MathML", "output/HTML-CSS"] 
 }); 
</script> 


<script>
// added on 06-01-2025 for back restrictions
$(document).ready(function() {
// alert('hello');
    function disableBack() {
        window.history.forward()
    }
    window.onload = disableBack();
    window.onpageshow = function(e) {
        if (e.persisted)
            disableBack();
    }
});
// added on 06-01-2025 for back restrictions

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
function mapValueModel(questionId){
        $('#tableBody').empty(); 
        $('#questionValue').empty();

        $.ajax({
            url : "{{route('question_mapped_value')}}",
            data : {question_id:questionId},
            type: 'GET',
            success : function(response){
                console.log(response);
               // Check if question title exists
                if (response.questionTitle) {
                    // Append the question title to the modal
                    $('#questionValue').html(response.questionTitle);
                } else {
                    $('#questionValue').text('No question title found');
                }
                if (response.MappedData) {
                    $('#tableBody').empty(); 
                    $.each(response.MappedData, function(index, mappedItem) {
                        // Start building the table row with the mappedItem name
                        let row = `<tr>
                            <td>${index + 1}</td>
                            <td>${mappedItem.name}</td>
                            <td><ul>`;
                                // Loop through mappedValue within each mappedItem
                                $.each(mappedItem.mappedValue, function(subIndex, mappedSubItem) {
                                    row += `<li>${subIndex+1}) ${mappedSubItem.name}</li>`;
                                });
                        row += `</ul></td>
                        </tr>`;

                        // Append the complete row to the table body
                        $('#tableBody').append(row);
                    });
                }

                $('#exampleModal').modal('show');
            }
        })
    }

</script>

@include('includes.footer')
