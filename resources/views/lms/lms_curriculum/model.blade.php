<style>
    .modeltable td, .modeltable th b{
        padding:6px;
    }
    .modeltable th{
        padding:14px 6px;
    }
    .modeltable th b{
        background: #e0edf1;
        color: #141313;
        border-radius: 10px;
        font-size: 1rem;
    }
</style>
@foreach($data['allData'] as $key=>$value)
@php 
    $model_integration=[];
    if(isset($value->model_integration)){
        $model_integration = explode(',',$value->model_integration);
    }
@endphp
<div class="modal fade" id="exampleModal_{{$value->id}}" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
  <div class="modal-dialog" role="document" style="max-width:1406px">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="exampleModalLabel"><b>{{$value->curriculum_name}}</b></h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <table class="tbale table-bordered modeltable" width="100%">
            <tr>
                <th><b>Standard :</b></th>
                <th colspan="2"><b>Board :</b></th>
            </tr>
            <tr>
                <td>{{$value->standard_name}}</td>
                <td colspan="2">{{$data['boards'][$value->board_id]}}</td>
            </tr>
            <tr>
                <th><b>Curriculum Alignment :</b></th>
                <th><b>Holistic Curriculum :</b></th>
                <th><b>Model Integration :</b></th>
            </tr>
            <tr>
                <td>{{$value->curriculum_alignment}}</td>
                <td>{{$value->holistic_curriculum}}</td>
                <td>@foreach($model_integration as $k => $v)
                {{isset($data['model_integration'][$v]) ? ($k+1).') '.$data['model_integration'][$v] : '-'}}<br>    
                @endforeach</td>
            </tr>
            <tr>
                <th><b>Objective :</b></th>
                <th><b>Chapter :</b></th>
                <th><b>Outcome :</b></th>
            </tr>
            <tr>
                <td>{{$value->objective}}</td>
                <td>{{$value->chapter}}</td>
                <td>{{$value->outcome}}</td>
            </tr>
            <tr>
                <th colspan="3"><b>Assessment Tool :</b></th>
            </tr>
            <tr>
                <td colspan="3">{{$value->assessment_tool}}.....</td>
            </tr>
        </table>
      </div>
    </div>
  </div>
</div>
@endforeach
