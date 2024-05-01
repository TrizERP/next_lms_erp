@extends('layout')
@section('container')
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-MrcW6ZMFYlzcLA8Nl+NtUVF0sA7MsXsP1UyJoMp4YLEuNSfAP+JcXn/tWtIaxVXM" crossorigin="anonymous"></script>
<link rel="stylesheet" href="{{asset('admin_dep/css/lmsDashboard.css')}}">
<div id="page-wrapper">
   <div class="container-fluid">
      <div class="row bg-title">
         <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
            <h4 class="page-title">
               LMS Dashboard
            </h4>
         </div>
      </div>
   </div>
   @php 
   $colours = [0=>"FE8C00",1=>"396AFC",2=>"BF5AE0",3=>"6C08FF",4=>"2B5876",5=>"B3AC4D",6=>"8CC63E",7=>"B0B0B0",8=>"CCBF08",9=>"396AFC",10=>"BF5AE0",11=>"FF8008"];
   $colours2 = [0=>"396AFC",1=>"BF5AE0",2=>"FF8008",3=>"ADE5FC",4=>"B3AC4D",5=>"8CC63E",6=>"B0B0B0",7=>"CCBF08",8=>"396AFC",9=>"BF5AE0",10=>"FF8008",11=>"FE8C00"];
   @endphp
   <!-- main div  -->
   <div class="lmsmain">
      <!-- card 1 start  -->
      <div class="lmscard">
         <div class="card" style="border-radius:16px">
            <div class="cardHead">
               <h4>Past Standard</h4>
            </div>
            <div class="cardSelect">
               <div class="row">
                  @if(!empty($data['standardData']))
                  @foreach($data['standardData'] as $key=>$value)
                  @if(($key+1) < $data['standardCount'])
                  <form action="{{route('lmsdashboard.index')}}" method="get" id="previousData{{$key}}">
                     <input type="hidden" name="syear" value="{{$value->syear}}">
                     <input type="hidden" name="enrollment_no" value="{{$value->enrollment_no}}">
                     <input type="hidden" name="stdname" value="{{$value->standardName}}">
                     <div class="col-md-2 circleContent">
                        <a onclick="getPreviousData('{{$value->enrollment_no}}',{{$key}},{{$value->syear}},'{{$value->standardName}}');">
                           <div class="circle1 {{ (isset($data['standard_name']) && $data['standard_name'] == $value->standardName) ? 'active' : '' }}" data-val="{{ $key }}">
                              <div class="circle1-content">{{$value->standardName}}</div>
                           </div>
                        </a>
                     </div>
                  </form>
                  @endif
                  @endforeach
                  @endif
               </div>
            </div>
            <div class="cardData" id="#cardData">
               @if(!empty($data['selectedData']['previousdata']['overallresult']) && isset($data['selectedData']['previousdata']['overallresult']))
               <div class="bar-graph">
                  @foreach($data['selectedData']['previousdata']['overallresult'] as $key=>$value)
                  @php 
                  $per = ($value['totalmarks'] !=0) ? round(($value['totalobtain'] * 100) / $value['totalmarks'],0) : 0;
                  @endphp
                  <style>
                     .circular-bar{{$key}}::before,
                     .circular-bar{{$key}}::after {
                     border-color: transparent transparent #{{$colours[$key]}};
                     }
                  </style>
                  <div class="bar" style="height: {{$per}}%; background-color: #{{$colours[$key]}};width:30% !important">
                     <div class="bar-label">{{$value['subjectname']}}</div>
                     <div class="circular-bar circular-bar{{$key}}" style="background-color: #{{$colours[$key]}};color:#fff">{{$per}}%</div>
                  </div>
                  @endforeach
               </div>
               @else
               <center> No Marks Found</center>
               @endif
            </div>
         </div>
      </div>
      <!-- card 1 end  -->
      <!-- card 2 start  -->
      <div class="lmscard">
         <div class="card">
            <div class="cardHead">
               <h4>Last Standard @if(isset($data['standard_name'])) {{$data['standard_name']}} @endif</h4>
            </div>
            <div class="SelectPreSub row">
               @if(!empty($data['selectedData']['previousdata']['overallresult']) && isset($data['selectedData']['previousdata']['overallresult']))
               @foreach($data['selectedData']['previousdata']['overallresult'] as $key=>$value)
               <a class="btn" style="background:#{{$colours[$key]}};color:#fff;margin:4px" data-bs-toggle="collapse" href="#collapseExample_{{$value['subjectname']}}" role="button" aria-expanded="false" aria-controls="collapseExample_{{$value['subjectname']}}" >
               {{$value['subjectname']}}
               </a>
               @endforeach
               @endif
            </div>
            <div class="cardData" id="#cardData2" style="padding-top:0px !important;">
               @if(!empty($data['selectedData']['previousdata']['standarddata']) && isset($data['selectedData']['previousdata']['standarddata'][0]['subjectdata']))
               @foreach($data['selectedData']['previousdata']['standarddata'][0]['subjectdata'] as $key=>$value)
               <div class="collapse subject_col" id="collapseExample_{{$value['title']}}">
                  <div class="card card-body p-4">
                     @if(!empty($value['examdata']))
                     @foreach($value['examdata'] as $examdataKey => $examdataVal)
                     <div class="examDetails d-flex flex-wrap"  style="width:100%">
                        <div class="examhead" style="width:20%">
                           <p style="font-size:0.8rem">{{$examdataVal['title']}}</p>
                        </div>
                        @php 
                        $examdataper = ($examdataVal['marks'] !=0) ? round(($examdataVal['obtain'] * 100) / $examdataVal['marks'],0) : 0;
                        @endphp
                        <div class="examProgress" style="width:80%">
                           <div class="progress" style="height:16px">
                              <div class="progress-bar progress-bar{{$examdataKey}}" role="progressbar" aria-valuenow="{{$examdataper}}" aria-valuemin="0" aria-valuemax="100" style="width: {{$examdataper}}%;background-color: #{{$colours2[$examdataKey]}};color:#fff;border-radius:10px">{{$examdataper}}%</div>
                           </div>
                        </div>
                     </div>
                     @endforeach
                     @endif
                  </div>
               </div>
               @endforeach
               @else
               <center> No Marks Found</center>
               @endif
            </div>
         </div>
      </div>
      <!-- card 2 end  -->
      <!-- card 3 start  -->
      <div class="lmscard col-md-12">
         <div class="card border-radius-2">
            <div class="cardHead">
               <h4>Current Standard</h4>
            </div>
            <div class="circleDiv d-flex flex-wrap" style="width:100%">
               <!-- current div 1  -->
               <div class="currentDiv1" style="width:50%;padding-right:30px">
                  <div class="circularDivs row">
                     @if(!empty($data['selectedCurrentData']['currentdata']['standarddata']['subjectdata']) && isset($data['selectedCurrentData']['currentdata']['standarddata']['subjectdata']))
                     @foreach($data['selectedCurrentData']['currentdata']['standarddata']['subjectdata'] as $key=>$value)
                     @php  
                        $exam = $value['examdata'];
                        $getObtain = array_column($exam, 'obtain');
                        $totalObtain = array_sum($getObtain);
                        $getMarks = array_column($exam, 'total');
                        $totalMarks = array_sum($getMarks);
                        $per = ($totalMarks!=0) ? round(($totalObtain * 100) / $totalMarks,2) : 0;
                        $wavePer = ($per!=0) ? ($per+100) : 0;
                     @endphp
                     <style>
                        .wave{{$key}} {
                        background: #{{$colours2[$key]}}; 
                        }
                        .circle{{$key}}{
                        border : #{{$colours2[$key]}}; 
                        }
                        .wave{{$key}}{
                           height : {{$wavePer}}% !important;
                        }
                        .wave{{$key}}:before,
                        .wave{{$key}}:after{
                        height : {{$wavePer}}% !important;
                        }
                     </style>
                     <div class="progress-circle col-md-2">
                        <a data-bs-toggle="collapse" href="#collapseExample2_{{$value['subject_id']}}" aria-expanded="false" aria-controls="collapseExample2_{{$value['subject_id']}}">
                        <div class="subjectName">
                           <h4 style="opacity: 1000;z-index: 1000;color:black">{{$value['title']}}</h4>
                           <div class="circle circle{{$key}} d-block">
                              <div class="wave wave{{$key}}"></div>
                           </div>
                        </div>
                          
                        </a>
                     </div>
                     @endforeach
                     @endif
                  </div>
                  <div class="circularDivData cardData"  style="padding-top:0px !important;">
                     @if(!empty($data['selectedCurrentData']['currentdata']['subjectdata']) && isset($data['selectedCurrentData']['currentdata']['subjectdata']))
                     @foreach($data['selectedCurrentData']['currentdata']['subjectdata'] as $sub_id=>$value)   
                     <div class="collapse subject_col" id="collapseExample2_{{$sub_id}}">
                        <div class="card card-body p-4">
                           <h4>{{$value['subjectdata']}}</h4>
                           @if(isset($value['chapterdata']))
                           <table class="table table-borderless">
                              <tr>
                                 <th style="width:70%">chapter</th>
                                 <th style="width:10%">Part 1</th>
                                 <th style="width:10%">Part 2</th>
                                 <th style="width:10%">Part 3</th>
                              </tr>
                              @foreach($value['chapterdata'] as $ch=>$chVal)
                              <tr class="trsub"  onclick="activeTr('tr{{$ch}}',{{$ch}})" id="tr{{$ch}}">
                                 <td style="width:70%" >{{$chVal['title']}}</td>
                                 <td style="width:10%" >80%</td>
                                 <td style="width:10%" >20%</td>
                                 <td style="width:10%"  >0%</td>
                              </tr>
                              @endforeach
                           </table>
                           @endif
                        </div>
                     </div>
                     @endforeach
                     @endif
                  </div>
               </div>
               <!-- cutrrent div  1 end  -->
               <!-- current div 2  -->
               <div class="currentDiv2" style="width:50%">
                  @if(!empty($data['selectedCurrentData']['currentdata']['subjectdata']) && isset($data['selectedCurrentData']['currentdata']['subjectdata']))
                  @foreach($data['selectedCurrentData']['currentdata']['subjectdata'] as $sub_id=>$value)   
                  <!-- get chapter data  -->
                  @if(isset($value['chapterdata']))
                  @foreach($value['chapterdata'] as $ch=>$chVal)
                  <div class="chapdata" id="collapseExample3_{{$ch}}" style="padding-top:0px !important;margin:0px 33px;">
                     <div class="chapter_title">
                        <h4>{{$chVal['title']}}</h4>
                     </div>
                     <div class="knowledgeDiv">
                        <div class="knowledge">
                           <h4>Knowledge</h4>
                        </div>
                        @php 
                        $chtotal = (isset($chVal['totalmark'])) ? $chVal['totalmark'] : 0;
                        $chobt = (isset($chVal['totalobtain'])) ? $chVal['totalobtain'] : 0;
                        $chPer = ($chtotal!=0) ? ($chobt * 100) / $chtotal : 0;
                        $i = [1=>'#FDEE21',2=>'#FDEE21',3=>'#FDEE21',4=>'#8A8A8A',5=>'#8A8A8A'];
                        @endphp
                        <div class="stars">
                           @foreach($i as $ikey=> $ival)
                           <span class="mdi mdi-star star{{$ikey}} star" style="color:{{$ival}}"></span>
                           @endforeach
                        </div>
                     </div>
                     <div class="skillDiv">
                        <div class="skill">
                           <h4>Skill</h4>
                        </div>
                        @php 
                           $tabColors = [1=>'#ED1B24',2=>'#F8931F',3=>'#FDEE21',4=>'#8CC63E',5=>'#8A8A8A']
                        @endphp
                        <div class="skillTabs">
                              <div class="skillfle d-flex">
                                 @foreach($tabColors as $tkey=>$tval)
                                 <style>
                                    .colortabs1{
                                       border-radius:10px 0px 0px 10px;
                                    }
                                    .colortabs5{
                                       border-radius:0px 10px 10px 0px;
                                    }
                                 </style>
                                    <div class="colortabs colortabs{{$tkey}}" style="background:{{$tval}}"></div>
                                 @endforeach
                              </div>
                        </div>
                     </div>
                     <!-- slikk div end  -->
                    
                     <div class="emojiDiv">
                        <div class="skill">
                           <h4>Level</h4>
                        </div>
                        <div class="emojis">
                     @php 
                        $emoji = [1=>"mdi-emoticon-dead",2=>"mdi-emoticon-sad",3=>"mdi-emoticon-happy",4=>"mdi-emoticon",5=>"mdi-emoticon"];
                     @endphp
                        @foreach($emoji as $ekey => $eval)
                        <span class="mdi {{$eval}}" style="color:{{$tabColors[$ekey]}}"></span>
                        @endforeach
                        </div>
                     </div>
                     <!-- emoji div end  -->
                     <div class="outcomeDiv">
                        <div class="skill">
                           <h4>Outcome</h4>
                        </div>
                        <div class="outcomData">
                              <span class="mdi mdi-check"></span>
                              <span class="mdi mdi-check"></span>
                              <span class="mdi mdi-check"></span>
                        </div>
                     </div>
                  </div>
                  @endforeach
                  @endif
                  <!-- end chapoter data  -->
                  @endforeach
                  @endif
               </div>
               <!-- cutrrent div 2 end  -->
            </div>
         </div>
      </div>
      <!-- card 3 end  -->

       <!-- card 4 start  -->
       <div class="lmscard" style="width:35%">
         <div class="card border-radius-2">
            <div class="cardHead">
               <h4>Recommendation</h4>
            </div>
            <div class="cardData" style="padding:10px 10px;overflow-y:scroll;">
            @if(!empty($data['selectedCurrentData']['currentdata']['subjectdata']) && isset($data['selectedCurrentData']['currentdata']['subjectdata']))
                  @foreach($data['selectedCurrentData']['currentdata']['subjectdata'] as $sub_id=>$value)   
                  <!-- get recommendation data data  -->
                     @if(isset($value['chapterdata']))
                     @foreach($value['chapterdata'] as $ch=>$chVal)
                        <div class="recommendation" id="recommendation_{{$ch}}">
                           @if(isset($chVal['recommendation']))
                              @foreach($chVal['recommendation'] as $rkey => $rval)
                              <div class="recommendationDiv">
                                 <a href="{{$rval['link']}}" class="d-flex" target="_blank">
                                    <div style="width:90%">{{$rval['title']}}</div>
                                    <div style="width:10%"><span class="mdi mdi-arrow-right-drop-circle-outline"></span></div>
                                 </a>
                              </div>
                              @endforeach
                           @endif
                        </div>
                     @endforeach
                     @endif
                  @endforeach
            @endif
            </div>

         </div>
      </div>
       <!-- card 4 end  -->

       <!-- card 5 -->
       <div class="lmscard" style="width:65%">
         <div class="card border-radius-2" style="height:370px">
          <!-- make 2 divs -->
            <div class="chDiv d-flex" style="width:100%">
            <!-- chapter progress -->
               <div class="chprogress" style="width:80%">
                  <div class="cardHead">
                     <h4>Chapter Progress</h4>
                  </div>
                  <div class="progressData">
                  @if(!empty($data['selectedCurrentData']['currentdata']['subjectdata']) && isset($data['selectedCurrentData']['currentdata']['subjectdata']))
                     @foreach($data['selectedCurrentData']['currentdata']['subjectdata'] as $sub_id=>$value)   
                     <!-- get recommendation data data  -->
                        @if(isset($value['chapterdata']))
                        @foreach($value['chapterdata'] as $ch=>$chVal)  
                           @if(!empty($chVal['chapterprogress']))
                              @foreach($chVal['chapterprogress'] as $chp=>$chpVal)  
                                    <div class="curveData" id="curveData_{{$ch}}">
                                       curveData
                                    </div>
                              @endforeach 
                           @endif
                        @endforeach 
                        @endif 
                     @endforeach
                  @endif
                  </div>
               </div>
               <!--chapter progress end -->

               <!-- chapter rank -->
               <div class="chrank" style="width:20%">
                  <div class="cardHead">
                     <h4>Chapter Rank</h4>
                  </div>
                  @if(!empty($data['selectedCurrentData']['currentdata']['subjectdata']) && isset($data['selectedCurrentData']['currentdata']['subjectdata']))
                     @foreach($data['selectedCurrentData']['currentdata']['subjectdata'] as $sub_id=>$value)   
                     <!-- get recommendation data data  -->
                        @if(isset($value['chapterdata']))
                        @foreach($value['chapterdata'] as $ch=>$chVal)
                        <div class="rankData" id="rankData_{{$ch}}" style="border-left:1px solid #ddd">
                        <div class="jursey">
                           <div class="rankContainer">
                              <img class="rankImg" src="{{asset('/admin_dep/images/chapterRank.png')}}" alt="chapterRank">
                              <h4 class="rankText">{{($chVal['chapterrank']) ? $chVal['chapterrank'] : 0 }}</h4>
                           </div>
                        </div>

                        </div>

                        @endforeach 
                        @endif 
                     @endforeach
                  @endif
               </div>
               <!-- chapter rank end -->
            </div>

         </div>
      </div>
       <!-- card 5 end-->

   </div>
</div>
@include('includes.footerJs')
<script> 
   $('.chapdata').hide();
   $('.recommendation').hide();
   $('.rankData').hide();
   $('.curveData').hide();

   $('.circle').on('click',function(){
      $('.chapdata').hide();
      $('.rankData').hide();
   })

      function getPreviousData(enrollment_no,key,syear,standard_name){
          $('.circle1').removeClass('active');
          $('.circle1[data-val="'+key+'"]').toggleClass('active');
          // alert(enrollment_no);
         // Set the values of hidden inputs
          $('#previousData input[name="enrollment_no"]').val(enrollment_no);
          $('#previousData input[name="previousData"]').val(standard_name);
          $('#previousData input[name="syearData"]').val(syear);
          // Submit the form
          $('#previousData'+key).submit();
      
      }
      $('.collapse').on('show.bs.collapse', function () {
          $('.collapse.show').each(function(){
              if (this !== event.target) {
                  $(this).collapse('hide');
              }
          });
      });
</script>
<script>
   // Add click event listener to all buttons with class "btn"
   document.querySelectorAll('.btn').forEach(function(button) {
       button.addEventListener('click', function() {
           var targetId = this.getAttribute('data-bs-target');
      document.querySelectorAll('.collapse').forEach(function(collapse) {
               if (collapse.id !== targetId.slice(1)) { // Slice to remove the #
                   collapse.classList.remove('show');
               }
           });
       });
   });
   
   function activeTr(trsub,ch_id){
        $('.trsub').removeClass('activeChapter');
       $('#'+trsub).toggleClass('activeChapter');
       $('.chapdata').hide();
       $('.recommendation').hide();
       $('.rankData').hide();
       $('.curveData').hide();
       $('#collapseExample3_'+ch_id).show();
       $('#recommendation_'+ch_id).show();
       $('#rankData_'+ch_id).show();
       $('#curveData_'+ch_id).show();
       
   }
</script>
@include('includes.footer')
@endsection
