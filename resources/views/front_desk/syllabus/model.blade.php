<style>
.VerticalText{
  writing-mode: vertical-rl;
  transform: rotate(180deg);
  width : fit-content;
  padding : 10px 0px;
}
.modeltable tr th, .modeltable tr td{
  text-align:center;
}
.curriculum-table tr th, .curriculum-table tr td{
  padding:20px 8px;
}
</style>
@foreach($data['data'] as $key=>$val)
<div class="modal fade" id="exampleModal_{{$val->id}}" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
  <div class="modal-dialog" role="document" style="max-width:1406px">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="exampleModalLabel"><b>Title :</b>&nbsp;{{$val->title}}</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
      @if($val->types=='Monthly')
        <table class="table" width="100%" >
          <tr> 
              <th width="25%"><b>Standard :</b></th>
              <td width="25%">{{$val->std_name}}</td>
              <th width="25%"><b>Subject:</b></th>
              <td width="25%" colspan="2">{{$val->display_name}}</td>
          </tr>
        </table>
        <table class="table modeltable" style="margin-top:20px">
          <tr>
            <th class="VerticalText" width="2%"><b>Month</b></th>
            <th class="VerticalText" width="2%"><b>No. of Days</b></th>
            <th class="VerticalText" width="2%"><b>No. of Periods</b></th>
            <th width="75%"><b>Main topic and sub topic to covered</b></th>
            <th width="17%"><b>Activities/Projects/Practical<br>Experiments to Held/Specific Assessment Tool(s) (Suggested)</b></th>
          </tr>
          <tr>
            <td class="VerticalText">{{$val->months}}</td>
            <td class="VerticalText">{{$val->no_of_days}}</td>
            <td class="VerticalText">{{$val->no_of_periods}}</td>
            <td>{!!$val->message!!}</td>
            <td>{{$val->assesment_tool}}</td>
          </tr>
        </table>

        <table class="table curriculum-table" style="margin-top:20px">
          <tr>
            <th colspan="2"><b>Curriculum Tile</b></th>
            <th><b>Progress Tracking</b></th>
          </tr>
          <tr>
            <td colspan="2">{{$val->curriculum_name}}</td>
            <td>{{$val->progress_tracking}}</td>
          </tr>
          <tr>
            <th><b>Syllabus Objectives</b></th>
            <th><b>Learning Outcomes</b></th>
            <th><b>Suggested Materials</b></th>
          </tr>
          <tr>
            <td>{{$val->objectives}}</td>
            <td>{{$val->learning_outcomes}}</td>
            <td>{{$val->suggested_materials}}</td>
          </tr>
        </table>
      @elseif($val->types=="Daily")
        <table class="table">
          <tr>
            <th><b>Standard :</b></th>
            <td>{{$val->std_name}}</td>
            <th><b>Subject:</b></th>
            <td>{{$val->display_name}}</td>
            <th><b>Date</b></th>
            <td>{{$val->date_}}</td>
          </tr>
          <tr>
            <th><b>Description:</b></th>
            <td colspan="5">{{$val->message}}</td>
          </tr>
        </table>
      @else
        <table class="table">
          <tr>
            <th><b>Standard :</b></th>
            <td>{{$val->std_name}}</td>
            <th><b>Subject:</b></th>
            <td>{{$val->display_name}}</td>
          </tr>
          <tr>
            <th><b>Description:</b></th>
            <td colspan="4">{{$val->message}}</td>
          </tr>
        </table>
      @endif 
      
      @if(isset($val->file_name))
        <div class="frameDiv" style="margin-top:20px">
          <h5><b>Attached File :</h5>
          <iframe 
              src="{{ Storage::disk('digitalocean')->url('public/syllabus/'.$val->file_name) }}" 
              frameborder="0" 
              width="100%" 
              height="1000px">
          </iframe>
        </div>
      @endif
      </div>
    </div>
  </div>
</div>
@endforeach
