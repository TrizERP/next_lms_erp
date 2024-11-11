{{--@include('includes.lmsheadcss')
@include('includes.header')
@include('includes.sideNavigation')--}}
@extends('lmslayout')
@section('container')
<!-- Content main Section -->
<div class="content-main flex-fill">
    <div class="row">
        <div class="col-md-6">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb bg-transparent p-0">
                    <li class="breadcrumb-item">LMS</li>
                    <li class="breadcrumb-item"><a href="{{route('question_paper.index')}}">Question Paper</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Attempted Exam Result</li>
                </ol>
            </nav>
        </div>
    </div>

    <div class="container-fluid mb-5">
        <div id="accordion" class="accordion">
            @if(isset($data['attempted_data']) && count($data['attempted_data']) > 0)
            @php $k=1; @endphp
            @foreach($data['attempted_data'] as $key => $val)
            @php
            $attempt_id = $val['id'];
            @endphp
            <div class="accordion-card">
                <div class="card-header" id="heading-{{$key}}">
                    <a class="" data-toggle="collapse" data-target="#attempt{{$key}}" aria-expanded="true" aria-controls="attempt{{$key}}">
                        <div class="col-md-9">
                            <div class="noti-title"><b>Subject :</b> {{$data['questionpaper_data']['subject_name']}}</div>
                            <div class="noti-des d-flex align-items-center mb-2">
                                <div class="mr-3"><b>Attempt :</b> {{$key+1}}</div>
                                <!-- <div class="mr-3"><b>Standard :</b> CBSE - 1</div> -->
                            </div>
                            <div class="noti-des d-flex align-items-center mb-2">
                                <div class="mr-3"><b>Paper Name :</b> {{$data['questionpaper_data']['paper_name']}}</div>
                                 <div class="mr-3"><b>Duration :</b> {{$data['questionpaper_data']['time_allowed']}} mins</div>
                            </div>
                            <div class="noti-des d-flex align-items-center">
                                <div class="mr-3"><b>Date :</b> {{$val['start_time']}} </div>
                            </div>
                        </div>
                    </a>
                   <!--  <a class="" data-toggle="collapse" data-target="#attempt{{$key}}" aria-expanded="true" aria-controls="attempt{{$key}}">
                        <div class="h4">Subject - </div>
                        <div class="h4">Paper Name - {{$data['questionpaper_data']['paper_name']}}</div>
                        <div class="h4">Attempt - {{$key+1}}</div>
                        <p><span class="h4">Date : </span>{{$val['start_time']}}</p>
                        <div class="h4">Duration - {{$data['questionpaper_data']['time_allowed']}} mins</div>
                    </a> -->
                </div>
                <div id="attempt{{$key}}" class="collapse show" aria-labelledby="heading-{{$key}}" data-parent="#accordion">
                    <div class="card-body">
                        <div class="row justify-content-center py-3">
                            <div class="col-md-3 text-center my-2">
                                <div class="answer-box right">{{$val['obtain_marks']}}/{{$data['questionpaper_data']['total_marks']}}</div>
                                <div class="h4 mb-0">Total Marks</div>
                            </div>
                            <div class="col-md-3 text-center my-2">
                                <div class="answer-box wrong">{{$val['total_right']}}/{{$data['questionpaper_data']['total_ques']}}</div>
                                <div class="h4 mb-0">Right Answer</div>
                            </div>
                            <div class="col-md-3 text-center my-2">
                                <div class="answer-box uttemp">{{$val['total_wrong']}}/{{$data['questionpaper_data']['total_ques']}}</div>
                                <div class="h4 mb-0">Wrong Answer</div>
                            </div>
                        </div>

                        <!-- START Progress Report -->
                        <div class="row justify-content-center py-3">
                            <div class="col-md-8">

                                @php
                                    $css_array = array("bg-success","bg-danger","bg-dark","bg-info","bg-warning");
                                    $i = 0;
                                @endphp

                                <div id="accordion-{{$key}}" class="accordion">
                                    <div class="accordion-card">
                                        <div class="card-header row" id="headingTwo">

                                            @if(isset($data['parent_mapping_arr'][$attempt_id]) && count($data['parent_mapping_arr'][$attempt_id]) > 0)
                                                @php $s = 1; @endphp
                                                @php $m = 1; $j=9999; @endphp
                                                @foreach($data['parent_mapping_arr'][$attempt_id] as $skey => $sval)
                                                    <div class="col-12">
                                                        <div class="btn btn-success my-1" data-toggle="collapse" data-target="#parent_mapping_{{$key}}_{{$s}}" aria-expanded="false" aria-controls="parent_mapping_{{$key}}_{{$s}}">{{$skey}} ( {{$sval}} )</div>
                                                    </div>
                                                    <div class="col-12">
                                                        <div id="parent_mapping_{{$key}}_{{$m}}" class="collapse" aria-labelledby="headingTwo" data-parent="#accordion-{{$key}}">

                                                            <div id="accordion{{$j}}_{{$key}}" class="accordion">
                                                                <div class="accordion-card">
                                                                    <div class="card-header" id="headingThree">
                                                                        @foreach($data['final_progressbar_data'][$attempt_id][$skey] as $pkey => $pval)

                                                                            <span class="font-weight-bold">{{$pval['parent_name']}} - {{$pval['name']}} ( Total Questions : {{$pval['total_question']}})</span>
                                                                            <div class="progress mb-4" data-toggle="collapse" data-target="#show_questions_{{$pval['id']}}{{$val['id']}}" aria-expanded="false" aria-controls="show_questions_{{$pval['id']}}{{$val['id']}}">

                                                                                @if($pval['obtained_percentage'] == 0)
                                                                                <div class="progress-bar progress-bar-striped {{$css_array[$i]}}" role="progressbar"
                                                                                    style="width:{{$pval['obtained_percentage']}}%" aria-valuenow="10" aria-valuemin="0" aria-valuemax="100" onclick="show_question_block('{{$pval['ques_list']}}','{{$pval['id']}}{{$val['id']}}','{{$val['id']}}');">
                                                                                    ( 0% )
                                                                                </div>
                                                                                @else

                                                                                <div class="progress-bar progress-bar-striped {{$css_array[$i]}}" role="progressbar"
                                                                                    style="width:{{$pval['obtained_percentage']}}%" aria-valuenow="10" aria-valuemin="0" aria-valuemax="100" onclick="show_question_block('{{$pval['ques_list']}}','{{$pval['id']}}{{$val['id']}}','{{$val['id']}}');">
                                                                                    ( {{$pval['obtained_percentage']}}% )
                                                                                </div>
                                                                                @endif

                                                                            </div>

                                                                            <!-- START Show Questions -->
                                                                            <div id="show_questions_{{$pval['id']}}{{$val['id']}}" class="collapse border border-success mb-4 d-none" aria-labelledby="headingTwo" data-parent="#accordion{{$j}}_{{$key}}">
                                                                            </div>
                                                                            <!-- END Show Questions -->
                                                                            @php
                                                                               if($i == 4){$i = 0;}
                                                                               $i++;
                                                                            @endphp
                                                                        @endforeach

                                                                         @php $j--; @endphp
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                @php $s++; @endphp
                                                @php $m++; @endphp
                                                @endforeach
                                            @endif
                                        </div>



                                    </div>
                                </div>


                            </div>
                        </div>
                        <!-- END Progress Report -->

                    </div>
                </div>
            </div>
            @php $k++; @endphp
            @endforeach
            @else
            <div class="card">
                No records
            </div>
            @endif
        </div>
    </div>

</div>
@include('includes.lmsfooterJs')
<script type="text/javascript">

function show_question_block(ques_list,divid,online_exam_id)
{
    var path = "{{ route('ajax_getQuestionList') }}";
    var question_list = "";
    $.ajax({url:path,data:'ques_list='+ques_list+'&online_exam_id='+online_exam_id,
        success:function(result){
            question_list += "<table class='table table-bordered'>";
            for(var i=0;i < result.length;i++){
                if(result[i]['ans_status'] == 'right')
                {
                    status = "  <span style='color:green;font-size: 22px;'><center>&#10004;</center></span>";
                }
                else
                {
                    status = "  <span style='color:red;'><center>&#10060;</center></span>";
                }
                question_list += "<tr>";
                question_list += "<td style='width:3% !important;'>"+(i+1)+")</td>";
                question_list += "<td style='width:70% !important;'>"+result[i]['question_title']+"</td>";
                question_list += "<td style='width:5% !important;'>"+status+"</td>";
                question_list += "</tr>";
            }
            question_list += "</table>";
            $("#show_questions_"+divid).html(question_list);
            $("#show_questions_"+divid).removeClass("d-none");

        }
    });

}

</script>
@include('includes.footer')
@endsection
