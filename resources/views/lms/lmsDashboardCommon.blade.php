@php 
if(isset($data['lmsData'])){
   $data = json_decode($data['lmsData'],true);
}
@endphp
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
   $colours = [0=>"FE8C00",1=>"396AFC",2=>"9E43BF",3=>"6C08FF",4=>"2B5876",5=>"B3AC4D",6=>"8CC63E",7=>"B0B0B0",8=>"CCBF08",9=>"396AFC",10=>"BF5AE0",11=>"FF8008",12=>"396AFC",13=>"BF5AE0",14=>"FF8008",15=>"ADE5FC",16=>"B3AC4D",17=>"8CC63E",18=>"B0B0B0",19=>"CCBF08",20=>"396AFC",21=>"BF5AE0",22=>"FF8008",23=>"FE8C00"];

   $colours2 = [0=>"396AFC",1=>"BF5AE0",2=>"FF8008",3=>"9971d5",4=>"4686b1",5=>"8CC63E",6=>"B0B0B0",7=>"CCBF08",8=>"396AFC",9=>"BF5AE0",10=>"FF8008",11=>"FE8C00",12=>"396AFC",13=>"BF5AE0",14=>"FF8008",15=>"ADE5FC",16=>"B3AC4D",17=>"8CC63E",18=>"B0B0B0",19=>"CCBF08",20=>"396AFC",21=>"BF5AE0",22=>"FF8008",23=>"FE8C00"];
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
                  @if(!empty($data['previousData']['previousdata']['overallresult']))
                  @foreach($data['previousData']['previousdata']['overallresult'] as $key=>$value)
                     <div class="col-md-2 circleContent">
                        <a data-bs-toggle="collapse" href="#collapseExample_{{$key}}" aria-expanded="{{ $loop->last ? 'true' : 'false' }}" aria-controls="collapseExample_{{$key}}" onclick="PreviousCircle('{{$key}}')">
                           <div class="circle1 {{ $loop->last ? 'active' : '' }}" data-val="{{ $key }}">
                              <div class="circle1-content">{{$key}}</div>
                           </div>
                        </a>
                     </div>
                  @endforeach
                  @endif
               </div>
            </div>
            <div class="cardData" id="#cardData">
               @if(!empty($data['previousData']['previousdata']['overallresult']) && isset($data['previousData']['previousdata']['overallresult']))
                  @foreach($data['previousData']['previousdata']['overallresult'] as $key=>$value)
                     <div class="bar-graph collapse {{ $loop->last ? 'show' : '' }}"  id="collapseExample_{{$key}}">
                  @foreach($value as $key2=>$value2)
                        @php 
                        $per = ($value2['totalmarks'] !=0) ? round(($value2['totalobtain'] * 100) / $value2['totalmarks'],0) : 0;
                        @endphp
                        <style>
                           .circular-bar{{$key2}}::before,
                           .circular-bar{{$key2}}::after {
                           border-color: transparent transparent #{{$colours[$key2]}};
                           }
                        </style>
                        <div class="bar" style="height: {{$per}}%; background-color: #{{$colours[$key2]}};width:30% !important">
                           <div class="bar-label">{{$value2['subjectname']}}</div>
                           <div class="circular-bar circular-bar{{$key2}}" style="background-color: #{{$colours[$key2]}};color:#fff">{{$per}}%</div>
                        </div>
                  @endforeach

                     </div>
                  @endforeach
               @else
               <center> No Marks Found</center>
               @endif
            </div>
         </div>
      </div>
      <!-- card 1 end  -->
      <!-- card 2 start  -->
      <div class="lmscard">
         <div class="card" style="height:477px;border-radius:20px">
            <div class="cardHead">
               <h4>Last Standard <span id="lastStd"></span></h4>
            </div>
            <div class="SelectPreSub"> 
    @if(!empty($data['previousData']['previousdata']['overallresult']) && isset($data['previousData']['previousdata']['overallresult']))
        @foreach($data['previousData']['previousdata']['overallresult'] as $key=>$value)
            <div class="row PreSubcollapse collapse  {{ $loop->last ? 'show' : '' }}" id="collapseExample_{{$key}}" data-val="collapseExample_{{$key}}">
                @foreach($value as $key2=>$value2)
                    <a class="btn {{ $loop->first ? 'activeSub' : '' }}" style="background:#{{$colours[$key2]}};color:#fff;margin:4px"  data-bs-toggle="collapse" href="#collapseExample_{{$key}}_{{$value2['subjectname']}}" role="button" aria-expanded="false" aria-controls="collapseExample_{{$key}}_{{$value2['subjectname']}}" onclick="PreSubCollepse('{{$key}}_{{$value2['subjectname']}}', this)">
                    {{$value2['subjectname']}}
                    </a>
                @endforeach
            </div>
        @endforeach
    @endif
</div>

            <div class="cardData" id="#cardData2" style="padding-top:0px !important;height:346px">
               @if(!empty($data['previousData']['previousdata']['standarddata']))
               @foreach($data['previousData']['previousdata']['standarddata'] as $key=>$value)
                  @if(isset($value['subjectdata']))
                  @foreach($value['subjectdata'] as $key2=>$value2)
                  <div class="collapse collapseExample_{{$value['standardname']}}_{{$value2['title']}} subject_col" id="collapseExample_{{$value['standardname']}}_{{$value2['title']}}">
                     <h4 style="margin-bottom:0px">{{$value2['title']}}</h4>
                     <hr style="margin-top:0.5px">
                     <div class="card card-body p-4">
                        @if(!empty($value2['examdata']))
                        @foreach($value2['examdata'] as $examdataKey => $examdataVal)
                        <div class="examDetails d-flex flex-wrap"  style="width:100%;padding:4px;">
                           <!-- Accessible label -->
                           <div class="examhead" style="width:20%" id="progress-label-{{$examdataKey}}" class="visually-hidden">
                              {{$examdataVal['title']}}
                           </div>
                           @php 
                           $examdataper = ($examdataVal['marks'] !=0) ? round(($examdataVal['obtain'] * 100) / $examdataVal['marks'],0) : 0;
                           @endphp
                           <div class="examProgress" style="width:80%">
                              <!-- Progress Bar -->
                              <div class="progress-bar progress-bar{{$examdataKey}}" role="progressbar" aria-valuenow="{{$examdataper}}" aria-valuemin="0" aria-valuemax="100"
                                 aria-labelledby="progress-label-{{$examdataKey}}"
                                 style="width: {{$examdataper}}%;background-color: #{{$colours2[$examdataKey]}};color:#fff;border-radius:10px">
                                 {{$examdataper}}%
                              </div>
                           </div>
                        </div>
                        @endforeach
                        @endif
                     </div>
                  </div>
                  @endforeach
                  @endif
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
                        .circleLast{{$key}}{
                           box-shadow : 0 0 0 5px #{{$colours2[$key]}}; 
                           border : 0px;
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
                        <a class="ProgressCircle {{$loop->first ? 'activeCircle' : ''}}" data-bs-toggle="collapse" data-val="{{$value['subject_id']}}" href="#collapseExample2_{{$value['subject_id']}}" aria-expanded="false" aria-controls="collapseExample2_{{$value['subject_id']}}" onclick="currentCircle('{{$value['subject_id']}}')">
                        <div class="subjectName">
                           <h4 style="opacity: 1000;z-index: 1000;color:black">{{$value['title']}}</h4>
                           <div class="circle circleLast{{$key}} d-block">
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
                     <div class="collapse CurrentTable  {{$loop->first ? 'show' : '' }}" id="collapseExample2_{{$sub_id}}" data-val="collapseExample2_{{$sub_id}}">
                        <div class="card card-body p-4" style="height:316px;overflow-y:scroll;padding:10px !important">
                          
                        <div style="display:flex">
                              <h4 style="padding:10px;margin-bottom:0px">{{$value['subjectdata']}}</h4> 
                              <a style="padding:10px;border-radius:10px;background:#f8931f" onclick="displayRegular();">Regular</a>&nbsp;&nbsp;
                              <a style="padding:10px;border-radius:10px;background:#8cc63e" onclick="displayPal({{$sub_id}},{{$data['currentStandard']}},{{$data['currentStudentId']}});">PAL</a>
                           </div>

                           @if(isset($value['chapterdata'][$sub_id]))
                           <table class="table table-borderless table-responsive" style="overflow-y:visible">
                              <thead>
                              <tr>
                                 <th style="width:70%">chapter</th>
                                 <th style="width:10%">Total</th>
                                 <th style="width:10%">Obtain</th>
                                 <th style="width:10%">Percentage</th>
                              </tr>
                              </thead>
                              <tbody class="hideOnPal">
                              @foreach($value['chapterdata'][$sub_id] as $ch=>$chVal)
                              @php 
                              $chtotal = (isset($chVal['totalmarks'])) ? $chVal['totalmarks'] : 0;
                              $chobt = (isset($chVal['totalobtain'])) ? $chVal['totalobtain'] : 0;
                              $chPer = ($chtotal!=0) ? ($chobt * 100) / $chtotal : 0;
                              $i = [1=>'#FDEE21',2=>'#FDEE21',3=>'#FDEE21',4=>'#8A8A8A',5=>'#8A8A8A'];
                              @endphp
                              <tr class="trsub"  onclick="activeTr('tr{{$ch}}',{{$ch}},{{$sub_id}},{{$chVal['chapter_id']}})" id="tr{{$ch}}_{{$sub_id}}" data-val="{{$ch}}" data-ch="{{$chVal['chapter_id']}}">
                                 <td style="width:70%">{{isset($chVal['title']) ? $chVal['title'] : '-'}}</td>
                                 <td style="width:10%">{{$chtotal}}</td>
                                 <td style="width:10%">{{$chobt}}</td>
                                 <td style="width:10%" >{{round($chPer)}}</td>
                              </tr>
                              @endforeach
                              </tbody>
                              <tbody class="showPal">
                             
                             </tbody>
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
                  {{-- @if(!empty($data['selectedCurrentData']['currentdata']['subjectdata']) && isset($data['selectedCurrentData']['currentdata']['subjectdata']))
                  @foreach($data['selectedCurrentData']['currentdata']['subjectdata'] as $sub_id=>$value)   
                  <!-- get chapter data  -->
                  @if(isset($value['chapterdata'][$sub_id]))
                  @foreach($value['chapterdata'][$sub_id] as $ch=>$chVal)
                  <div class="chapdata chapdata_{{$sub_id}}_{{$ch}}" id="collapseExample3_{{$sub_id}}_{{$ch}}" style="padding-top:0px !important;margin:0px 33px;">
                     <div class="chapter_title">
                        <h4 style="margin-bottom:0px">{{isset($chVal['title']) ? $chVal['title'] : '-'}}</h4>
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
                  @endif --}}

                  <!-- start new design -->
                     <div class="mapping_parts">
                      
                     </div>
                  <!-- end new design -->

               </div>
               <!-- cutrrent div 2 end  -->
            </div>
         </div>
      </div>
      <!-- card 3 end  -->

       <!-- card 4 start  -->
       {{--<div class="lmscard hideForPal" style="width:35%">
         <div class="card border-radius-2">
            <div class="cardHead">
               <h4>Recommendation</h4>
            </div>
            <div class="cardData" style="padding:10px 10px;overflow-y: scroll;">
            @if(!empty($data['selectedCurrentData']['currentdata']['subjectdata']) && isset($data['selectedCurrentData']['currentdata']['subjectdata']))
                  @foreach($data['selectedCurrentData']['currentdata']['subjectdata'] as $sub_id=>$value)   
                  <!-- get recommendation data data  -->
                     @if(isset($value['chapterdata'][$sub_id]))
                     @foreach($value['chapterdata'][$sub_id] as $ch=>$chVal)
                        <div class="recommendation" id="recommendation_{{$sub_id}}_{{$ch}}">
                           @if(isset($chVal['recommendation']))
                              @foreach($chVal['recommendation'] as $rkey => $rval)
                              <input type="hidden" name="recommendation"  id="input_{{$rkey}}_{{$sub_id}}_{{$ch}}" value="{{$rval}}">
                            
                              @endforeach
                              <div class="recommendationDiv" id="recommendationDiv_{{$sub_id}}_{{$ch}}">
                                 
                                 </div>
                           @endif
                        </div>
                     @endforeach
                     @endif
                  @endforeach
            @endif 
            </div>

         </div>
      </div> --}}
       <!-- card 4 end  -->

       <!-- card 5 -->
       <div class="lmscard hideForPal" style="width:100%">
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
                        @if(isset($value['chapterdata'][$sub_id]))
                        @foreach($value['chapterdata'][$sub_id] as $ch=>$chVal)  
                           @if(!empty($chVal['chapterprogress']))
                              @foreach($chVal['chapterprogress'] as $chp=>$chpVal)  
                                    <!-- get student percentage wise -->
                                    @php 
                                       $noPer = $per10=$per20 = $per40 = $per60 = $per80 = [];
                                    @endphp
                                       @if(isset($chpVal['students']))
                                          @foreach($chpVal['students'] as $studKey=>$studVal)  
                                          @php 
                                             $chtotal = $studVal['total'];
                                             $chprogress = $studVal['progress'];
                                             $chPer= ($chtotal!=0) ? round(($chprogress*100) / $chtotal,0) : 0;
                                             
                                             if($chPer > 80){
                                                $per80[]=$studVal['photo'];
                                             }else if($chPer > 60){
                                                $per60[]=$studVal['photo'];
                                             }else if($chPer > 40){
                                                $per40[]=$studVal['photo'];
                                             }else if($chPer > 20){
                                                $per20[]=$studVal['photo'];
                                             }else if($chPer > 10){
                                                $per10[]=$studVal['photo'];
                                             }
                                          @endphp
                                          @endforeach 
                                       @endif
                                    <div class="curveData" id="curveData_{{$sub_id}}_{{$ch}}">
                                       <div class="d-flex">
                                       <!-- img div -->
                                          <div class="d1" style="width:80%">
                                             @php
                                             if(empty($per80) && empty($per60) && empty($per40) && empty($per20) && empty($per10) && isset($data['studentData']->image)){
                                                $noPer[]="https://erp.triz.co.in/storage/student/".$data['studentData']->image;
                                             }
                                             @endphp

                                             <div class="node1" style="position: absolute; left: 0%;top: 50%;">
                                                <div class="studImg">
                                                   @if(!empty($noPer))
                                                      @foreach($noPer as $studImg)
                                                         <img src="{{$studImg}}" alt="students img">
                                                      @endforeach
                                                   @endif
                                                </div>
                                                <div class="node">
                                                  <span class="mdi mdi-checkbox-blank-circle"></span>
                                                </div>
                                             </div>

                                             <div class="node2" style="position: absolute; left: 11%; top: 23%;">
                                                <div class="studImg">
                                                   @if(!empty($per10))
                                                      @foreach($per10 as $studImg)
                                                         <img src="{{$studImg}}" alt="students img">
                                                      @endforeach
                                                   @endif
                                                </div>
                                                <div class="node">
                                                  <span class="mdi mdi-checkbox-blank-circle"></span>
                                                </div>
                                             </div>

                                             <div class="node3" style="position: absolute; left: 21%; top: 69%;">
                                                <div class="studImg">
                                                   @if(!empty($per20))
                                                      @foreach($per20 as $studImg)
                                                         <img src="{{$studImg}}" alt="students img">
                                                      @endforeach
                                                   @endif
                                                </div>
                                                <div class="node">
                                                  <span class="mdi mdi-checkbox-blank-circle"></span>
                                                </div>
                                             </div>

                                             <div class="node4" style="position: absolute; left: 34%; top: 11%;">
                                                <div class="studImg">
                                                   @if(!empty($per40))
                                                      @foreach($per40 as $studImg)
                                                         <img src="{{$studImg}}" alt="students img">
                                                      @endforeach
                                                   @endif
                                                </div>
                                                <div class="node">
                                                  <span class="mdi mdi-checkbox-blank-circle"></span>
                                                </div>
                                             </div>

                                             <div class="node5" style="position: absolute; left: 49%; top: 75%;">
                                                <div class="studImg">
                                                   @if(!empty($per60))
                                                      @foreach($per60 as $studImg)
                                                         <img src="{{$studImg}}" alt="students img">
                                                      @endforeach
                                                   @endif
                                                </div>
                                                <div class="node">
                                                  <span class="mdi mdi-checkbox-blank-circle"></span>
                                                </div>
                                             </div>

                                             <div class="node6" style="position: absolute; left: 61%; top: 24%;">
                                                <div class="studImg">
                                                   @if(!empty($per80))
                                                      @foreach($per80 as $studImg)
                                                         <img src="{{$studImg}}" alt="students img">
                                                      @endforeach
                                                   @endif
                                                </div>
                                                <div class="node">
                                                  <span class="mdi mdi-checkbox-blank-circle"></span>
                                                </div>
                                             </div>

                                             <img src="{{asset('admin_dep/images/chapterProgress.png')}}" alt="chapterProgress" style="width:100%;height: 255px;">
                                          </div>
                                       <!-- goal div -->
                                          <div class="d2" style="width:20%">
                                             <img src="{{asset('admin_dep/images/goalFlag.png')}}" alt="Goal Flag" style="padding:20px">
                                          </div>
                                          
                                      </div>
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
                        @if(isset($value['chapterdata'][$sub_id]))
                        @foreach($value['chapterdata'][$sub_id] as $ch=>$chVal)
                        <div class="rankData" id="rankData_{{$sub_id}}_{{$ch}}" style="border-left:1px solid #ddd">
                        <div class="jursey">
                           <div class="rankContainer">
                              <img class="rankImg" src="{{asset('/admin_dep/images/chapterRank.png')}}" alt="chapterRank">
                              <h4 class="rankText">{{ isset($chVal['chapterrank']) ? round($chVal['chapterrank']) : 0 }}</h4>
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