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
                    <li class="breadcrumb-item active" aria-current="page">Offline Attempted Exam Result</li>
                </ol>
            </nav>
        </div>
    </div>

    <div class="container-fluid mb-5">
        <div id="accordion" class="accordion">

            @php $k=1; @endphp
            <div class="accordion-card">
                <div class="card-header" id="headingOne">
                    <a class="" data-toggle="collapse" data-target="#attempt1" aria-expanded="true" aria-controls="attempt1">
                        <div class="col-md-9">
                            <div class="noti-title"><b>Subject :</b> {{$data['questionpaper_data']['subject_name']}}</div>
                            <div class="noti-des d-flex align-items-center mb-2">
                                <div class="mr-3"><b>Attempt :</b> 1</div>
                                <!-- <div class="mr-3"><b>Standard :</b> CBSE - 1</div> -->
                            </div>
                            <div class="noti-des d-flex align-items-center mb-2">
                                <div class="mr-3"><b>Paper Name :</b> {{$data['questionpaper_data']['paper_name']}}</div>
                                 <div class="mr-3"><b>Duration :</b> {{$data['questionpaper_data']['time_allowed']}} mins</div>
                            </div>
                            <div class="noti-des d-flex align-items-center">
                                <div class="mr-3"><b>Date :</b>  </div>
                            </div>
                        </div>
                    </a>
                </div>
                <div id="attempt1" class="collapse show" aria-labelledby="headingOne" data-parent="#accordion">
                    <div class="card-body">
                        <div class="row justify-content-center py-3">
                            <div class="col-md-3 text-center my-2">
                                <div class="answer-box right">{{$data['questionpaper_data']['obtain_marks']}}/{{$data['questionpaper_data']['total_marks']}}</div>
                                <div class="h4 mb-0">Total Marks</div>
                            </div>
                            <div class="col-md-3 text-center my-2">
                                <div class="answer-box wrong">{{$data['questionpaper_data']['total_right']}}/{{$data['questionpaper_data']['total_ques']}}</div>
                                <div class="h4 mb-0">Right Answer</div>
                            </div>
                            <div class="col-md-3 text-center my-2">
                                <div class="answer-box uttemp">{{$data['questionpaper_data']['total_wrong']}}/{{$data['questionpaper_data']['total_ques']}}</div>
                                <div class="h4 mb-0">Wrong Answer</div>
                            </div>
                        </div>

                        <!-- START Progress Report -->
                        <div class="row justify-content-center py-3">
                            <div class="col-md-8">
                                <div id="accordion{{$k}}" class="accordion">
                                   <div class="accordion-card">
                                        <div class="card-header" id="headingTwo">
                                        @php
                                            $css_array = array("bg-success","bg-danger","bg-dark","bg-info","bg-warning");
                                            $i = 0;
                                            $offline_id = $data['questionpaper_data']['id'];
                                        @endphp
                                        @if(isset($data['progressbar_data']) && count($data['progressbar_data']) > 0)
                                            @foreach($data['progressbar_data'] as $pkey => $pval)

                                                <div class="progress mb-4" data-toggle="collapse" data-target="#show_questions_{{$pval['id']}}{{$offline_id}}" aria-expanded="false" aria-controls="show_questions_{{$pval['id']}}{{$offline_id}}">
                                                    <div class="progress-bar progress-bar-striped {{$css_array[$i]}}" role="progressbar"
                                                        style="width:{{$pval['obtained_percentage']}}%" aria-valuenow="10" aria-valuemin="0" aria-valuemax="100" onclick="show_question_block('{{$pval['ques_list']}}','{{$pval['id']}}{{$offline_id}}','{{$offline_id}}');">
                                                        {{$pval['name']}}( @if($pval['obtained_percentage'] == "") 0% @else{{$pval['obtained_percentage']}}% @endif)
                                                    </div>
                                                </div>

                                                <!-- START Show Questions -->
                                                <div id="show_questions_{{$pval['id']}}{{$offline_id}}" class="collapse border border-success mb-4 d-none" aria-labelledby="headingTwo" data-parent="#accordion{{$k}}">
                                                </div>
                                                <!-- END Show Questions -->
                                                @php
                                                   if($i == 4){$i = 0;}
                                                   $i++;
                                                @endphp
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
            for(var i=0;i < result.length;i++){
                if(result[i]['ans_status'] == 'right')
                {
                    status = "  <span style='color:green;font-size: 22px;'>&#10004;</span>";
                }
                else
                {
                    status = "  <span style='color:red;'>&#10060;</span>";
                }
                question_list += "<ul>";
                question_list += "<li>"+(i+1)+")  "+result[i]['question_title']+status+"</li>";
                question_list += "</ul>";
            }
            $("#show_questions_"+divid).html(question_list);
            $("#show_questions_"+divid).removeClass("d-none");

        }
    });

}

</script>
@include('includes.footer')
@endsection
