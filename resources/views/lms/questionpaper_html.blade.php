<style>
.dot {
    height: 13px;
    width: 13px;
    background-color: #bbb;
    border-radius: 50%;
    display: inline-block;
}
.square {
    height: 12px;
    width: 12px;
    background-color: #bbb;
    display: inline-block;
}
.table td, .table th {
    padding: 18px;
}
#questionpaper tbody tr th thead th {
    border-color: #ffffff !important;
}
tbody tr th th {
    color: #ffffff;
}

@media print { 
	table {
        border: solid #000 !important;
        border-width: 1px 0 0 1px !important;
    }
    th, td {
        border: solid #000 !important;
        border-width: 0 1px 1px 0 !important;
    }    
    
 }
.table-bordered {
    border: 1px solid #dee2e6;
}
.table {
    width: 100%;
    margin-bottom: 1rem;
    color: #212529;
}
.table-striped tbody tr:nth-of-type(odd) {
    background-color: rgba(0, 0, 0, 0.05);
}
</style>
<div id="page-wrapper">
    <div class="container-fluid">                
    <div class="row">
        <div class="white-box">    
            <div class="panel-body">                             
                <div class="col-lg-12 col-sm-12 col-xs-12" style="overflow:auto;">
                    <table id="questionpaper" class="table table-striped table-bordered" style="width:100%">
                    <tr>
                        <th style="background:#25bdea;padding: 15px;">
                            <table class="table table-striped table-bordered" style="width:100%">
                                <thead>
                                    <tr>
                                        <th colspan="2" class="text-center">Question Paper: {{$data['questionpaper_data']['paper_name']}}</th>                                
                                    </tr>
                                    <tr>
                                        <th>Total Marks: {{$data['questionpaper_data']['total_marks']}}</th>                                
                                        <th class="text-left">Total Questions: {{$data['questionpaper_data']['total_ques']}}
                                        @if( $data['questionpaper_data']['timelimit_enable'] == 1 )
                                        <span style="float:right;">({{$data['questionpaper_data']['time_allowed']}} mins)</span>
                                        @endif
                                        </th>                                                                
                                    </tr>
                                </thead>
                            </table>
                        </th>
                    </tr>
                    <tr><td style="background:#ffffff;">
                        <table class="table table-striped table-bordered" style="width:100%">                     
                            @php $i = 1; @endphp                           
                            @foreach($data['question_arr'] as $quesid => $quesarr)
                            <tr>                                
                                <td style="text-align:left;background: #303030;color: #ffffff;">{{$i++}}) &nbsp;&nbsp; {{$quesarr['question_title']}}
                                <span style="float:right;">({{$quesarr['points']}})</span>
                                </td>
                            </tr>
                            <tr>
                                <td>
                                <table class="table table-striped table-bordered" style="width:100%">
                                    @if(isset($data['answer_arr'][$quesarr['id']]))                     
                                        @foreach($data['answer_arr'][$quesarr['id']] as $ansid => $ansarr)
                                            <tr>
                                                @php
                                                if($quesarr['multiple_answer'] == 1)
                                                {
                                                    $btnclass = "square";
                                                }
                                                else{                                                
                                                    $btnclass = "dot";
                                                }
                                                @endphp
                                                <td style="text-align:left;"><div class="{{$btnclass}}"></div>
                                                {{$ansarr['answer']}}</td>
                                            </tr>
                                        @endforeach
                                    @endif
                                </table>
                                </td>
                            </tr>
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