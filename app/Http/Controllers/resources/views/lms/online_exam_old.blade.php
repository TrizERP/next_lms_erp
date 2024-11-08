@include('includes.lmsheadcss')

<div id="page-wrapper">
    <div class="container-fluid">                
    <div class="row">
        <div class="white-box">                    
            <!-- <h1><p id="showtimer"></p></h1> -->
            <h1 class="h1 mb-3">Online Question Paper: {{$data['questionpaper_data']['paper_name']}}</h1>
            <div class="panel-body">             
               
                <div class="col-lg-12 col-sm-12 col-xs-12" style="overflow:auto;">
                    <form id="online_exam" method="post" action="{{ route('online_exam.store') }}">
                    {{ method_field('POST') }}
                    @csrf
                    <table id="questionpaper" class="table table-striped table-bordered" style="width:100%">
                    <tr><th>
                        <table class="table table-striped table-bordered" style="width:100%">
                            <thead>
                                <tr>
                                    <th colspan="2"></th>                                
                                </tr>
                                <tr>
                                    <th>Total Marks: {{$data['questionpaper_data']['total_marks']}}</th>                                
                                    <th>Total Questions: {{$data['questionpaper_data']['total_ques']}}
                                    @if( $data['questionpaper_data']['timelimit_enable'] == 1 )
                                    <span style="float:right;">({{$data['questionpaper_data']['time_allowed']}} mins)</span>
                                    @endif
                                    </th>                                                                
                                </tr>
                            </thead>
                        </table>
                    </th></tr>
                    <tr><td>
                        <input type="hidden" name="questionpaper_time" id="questionpaper_time" value="{{$data['questionpaper_data']['time_allowed']}}">
                        <input type="hidden" name="questionpaper_id" id="questionpaper_id" value="{{$data['questionpaper_data']['id']}}">
                        <table class="table table-striped table-bordered" style="width:100%">                     
                            @php 
                            $i = 1; 
                           
                            @endphp                           
                            @foreach($data['question_arr'] as $quesid => $quesarr)
                            <tr>                                
                                <td style="text-align:left;">{{$i++}}) &nbsp;&nbsp; {{$quesarr['question_title']}}
                                <span style="float:right;">({{$quesarr['points']}})</span>
                                </td>
                            </tr>
                            <tr>
                                @if($quesarr['question_type_id'] == "2") <!--Narrative Question-->
                                <td>
                                <table class="table table-striped table-bordered" style="width:100%">                                                                    
                                        <tr>
                                            <td style="text-align:left;">
                                                <textarea type="text" rows="4" placeholder="Enter Answer" class="form-control" name="answer_narrative[{{$quesarr['id']}}]"></textarea>                                                                                                 
                                            </td>
                                        </tr>                                
                                </table>
                                </td>
                                @elseif($quesarr['question_type_id'] == "1") <!--Multple Option Question-->
                                <td>
                                <table class="table table-striped table-bordered" style="width:100%">                                    
                                    @if(isset($data['answer_arr'][$quesarr['id']]))                     
                                        @foreach($data['answer_arr'][$quesarr['id']] as $ansid => $ansarr)
                                            <tr>
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
                                                <td style="text-align:left;">
                                                    <input type="{{$type}}" name="{{$name}}" value="{{$ansarr['id']}}##{{$ansarr['correct_answer']}}">                                                    
                                                    {{$ansarr['answer']}}
                                                </td>
                                            </tr>
                                        @endforeach
                                    @endif
                                </table>
                                </td>
                            </tr>
                                @endif
                            @endforeach                        
                        </table>
                    </td></tr>                                        
                    </table>
                    

                    <button class="btn btn-primary" type="submit">Submit</button>
                    </form>
                </div>
                
            </div>
        </div>
    </div>
    </div>
</div>

@include('includes.lmsfooterJs')
<script>


// Set the date we're counting down to
// var countDownDate = new Date("Jan 7, 2021 15:57:25").getTime();

// var min_to_add = $("#questionpaper_time").val();

// var dt = new Date("<?php echo Session::get('quiz'); ?>");
// console.log("session_time"+dt);
// dt.setMinutes( dt.getMinutes() + parseInt(min_to_add) );
// var countDownDate = dt.getTime();
// console.log("countDownDate"+countDownDate);
// // Update the count down every 1 second
// var x = setInterval(function() {

//   // Get today's date and time
//   var now = new Date().getTime();
//     console.log("now"+now);
//   // Find the distance between now and the count down date
//   var distance = (countDownDate - now);
//   console.log("distance"+distance);
    
//   // Time calculations for days, hours, minutes and seconds
//   var days = Math.floor(distance / (1000 * 60 * 60 * 24));
//   var hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
//   var minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
//   var seconds = Math.floor((distance % (1000 * 60)) / 1000);

//   console.log("days"+days);
//   console.log("hours"+hours);
//   console.log("minutes"+minutes);
//   console.log("seconds"+seconds);
    
//   // Output the result in an element with id="demo"
//   document.getElementById("showtimer").innerHTML = hours + "h "+ minutes + "m " + seconds + "s ";
    
//   // If the count down is over, write some text 
//   if (distance < 0) {
//     clearInterval(x);
//     document.getElementById("showtimer").innerHTML = "EXPIRED";
//     <?php Session::forget("quiz"); ?>
//   }
// }, 1000);


</script>

@include('includes.footer')
