<style>
   .content {
        font-family: Calibri;
        width: 95%;
        margin: 20px auto;
        background-color: #d5ebf4;
        border-radius: 10px;
        padding: 20px;
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        font-size:20px;text-align: justify;
        line-height:1.3;
    }
    .header {
        text-align: center;
        margin-bottom: 20px;
    }
    .header h1 {
        font-size: 24px;
        margin: 10px 0;
    }
    .header img {
        width: 100px;
    }
    .columns {
        display: flex;
        justify-content: space-between;
        gap: 20px;
    }
    .column {
        background-color: #f7f9fa;
        border-radius: 8px;
        padding: 15px;
        flex: 1;
    }
    .column li {
        list-style-type: disc; /* ensures bullets are shown */
        margin-left: 20px; /* optional: adds space from the left */
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
        <div class="content">
            <div class="header">
                <img src="../../../storage{{$value->display_image}}" alt="">
                <h1>{{$data['boards'][$value->board_id]}} : {{$value->standard_name}} {{ strtoupper($value->subject_name) }} 
                <br/>
                @foreach($model_integration as $k => $v)
                    {{isset($data['model_integration'][$v]) ? ($k+1).') '.$data['model_integration'][$v] : '-'}}     
                @endforeach 

                CURRICULAM</h1>
            </div>
            <div class="columns">
                <div class="column">
                    <h2>OBJECTIVE</h2>
                    <p>{!! $value->objective !!}</p>
                </div>
                <!--<div class="column">
                    <h2>OUTCOME</h2>
                    <p>{!! $value->outcome !!}</p>
                </div>
                -->
            </div>
            <br/>
            <div class="columns">
                <div class="column">
                    <h2>CURRICULUM ALIGNMENT</h2>
                    <p>{!! $value->curriculum_alignment !!}</p>
                </div>
            </div>
            <br/>
            <div class="columns">
                <div class="column">
                    <h2>HOLISTIC CURRICULUM</h2>
                    <p>{!! $value->holistic_curriculum !!}</p>
                </div>
            </div>
            <br/>
            <div class="columns">
                <div class="column">
                    <h2>CHAPTER</h2>
                    <p>{!! $value->chapter !!}</p>
                </div>
                <div class="column">
                    <h2>ASSESSMENT TOOL</h2>
                    <p>{!! $value->assessment_tool !!}</p>
                </div>
            </div>
        </div>
      </div>
    </div>
  </div>
</div>
@endforeach
