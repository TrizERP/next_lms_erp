@include('includes.lmsheadcss')
<div id="page-wrapper">
    <div class="container-fluid">                
    <div class="row">
        <div class="white-box">           
            <h1 class="h1 mb-3">Result</h1>
            <h1 class="h1 mb-3">Online Question Paper: {{$data['questionpaper_data']['paper_name']}}</h1>
            <div class="panel-body">             
                
                <div class="col-lg-12 col-sm-12 col-xs-12" style="overflow:auto;">
                    
                    <table id="questionpaper" class="table table-striped table-bordered" style="width:100%">
                    <tr><th>
                        <table class="table table-striped table-bordered" style="width:100%">
                            <thead>
                                <tr>
                                    <th colspan="9"></th>                                
                                </tr>
                                <tr>
                                    <th>Total Marks: {{$data['questionpaper_data']['total_marks']}}</th>  
                                    <th>Obtain Marks: {{$data['online_exam_data']['obtain_marks']}}</th>  
                                    <th>Total Questions: {{$data['questionpaper_data']['total_ques']}}
                                    <th>Wrong Questions: {{$data['online_exam_data']['total_wrong']}}
                                    <th>Right Questions: {{$data['online_exam_data']['total_right']}}                                        
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
                                $data['online_exam_data'][$quesarr['id'][]]    
                                <span style="float:right;">({{$quesarr['points']}})</span>
                                </td>
                            </tr>
                            <tr>
                                @if($quesarr['question_type_id'] == "2") <!--Narrative Question-->
                                <td>
                                <table class="table table-striped table-bordered" style="width:100%">                                                                    
                                        <tr>
                                            <td style="text-align:left;">                                                
                                                <textarea type="text" rows="4" placeholder="Enter Answer" class="form-control" name="answer[{{$quesarr['id']}}]">@if(isset($data['online_answer_data'][$quesarr['id']][0]['narrative_answer'])){{$data['online_answer_data'][$quesarr['id']][0]['narrative_answer']}}@endif</textarea>
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
                                                    $name = "answer[".$quesarr['id']."][".$ansarr['id']."][]";
                                                }
                                                else{                                                
                                                    $btnclass = "dot";
                                                    $type = "radio";
                                                    $name = "answer[".$quesarr['id']."][".$ansarr['id']."]";
                                                }
                                                @endphp
                                                <td style="text-align:left;">
                                                    <input type="{{$type}}" name="{{$name}}" value="{{$ansarr['correct_answer']}}">                                                    
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
                    
                    
                </div>
                
            </div>
        </div>
    </div>
    </div>
</div>

@include('includes.lmsfooterJs')
@include('includes.footer')
