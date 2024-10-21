{{--@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')--}}

@extends('layout')
@section('container')
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
 br{
    display:  block !important;
}
</style>
<div id="page-wrapper">
    <div class="container-fluid">
    <div class="row">
        <div class="white-box">
            <div class="panel-body">
                <center>
                <button class="btn btn-info mb-5" id="printpaper" onclick="printData();">Print</button>
                </center>
                <br>
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
                                <td style="text-align:left;background: #303030;color: #ffffff;">{{$i++}}) &nbsp;&nbsp; {!!$quesarr['question_title']!!}
                                <span style="float:right;">({{$quesarr['points']}}) 
                                    <span style="padding:0px 10px" onclick="mapValueModel({{$quesarr['id']}});"><i class="fa fa-ellipsis-v" aria-hidden="true"></i></span> 
                                </span> 
                               
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

@include('includes.footerJs')
<script src="//cdn.mathjax.org/mathjax/latest/MathJax.js">
 MathJax.Hub.Config({
   extensions: ["mml2jax.js"],
   jax: ["input/MathML", "output/HTML-CSS"]
 });
</script>


<script type="text/javascript">
function printData()
{
   var divToPrint=document.getElementById("questionpaper");
   // divToPrint.addClass('dot');
   // divToPrint.addClass('square');
   newWin= window.open("");
   newWin.document.write(divToPrint.outerHTML);
   newWin.print();
   newWin.close();
}
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
@endsection
