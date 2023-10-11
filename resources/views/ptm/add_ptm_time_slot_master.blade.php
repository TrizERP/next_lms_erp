@include('includes.headcss')
<link rel="stylesheet" href="/plugins/bower_components/clockpicker/dist/jquery-clockpicker.min.css">
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">
                @if(!isset($data))
                Add PTM Time Slot
                @else
                Edit PTM Time Slot
                @endif
                </h4>
            </div>            
        </div>        
        <div class="card">
            <div class="panel-body">
                @if ($message = Session::get('success'))
                <div class="alert alert-success alert-block">
                    <button type="button" class="close" data-dismiss="alert">×</button>
                    <strong>{{ $message }}</strong>
                </div>
                @endif
                <div class="col-lg-12 col-sm-12 col-xs-12">
                    <form action="@if (isset($data))
                          {{ route('add_ptm_time_slot_master.update',$data->id) }}
                          @else
                          {{ route('add_ptm_time_slot_master.store') }}
                          @endif" method="post">
                            @if(!isset($data))
                            {{ method_field("POST") }}
                            @else
                            {{ method_field("PUT") }}
                            @endif
                            @csrf
                    <div class="row justify-content-center">
                        <div class="col-md-6 form-group">
                            <label>Standard</label>                               
                            <select class="selectpicker form-control" name="standard_id" id="standard_id" onchange="getStandardwiseDivision(this.value);">                           
                                <option value="">Select Standard</option>
                                @if(!empty($menu))  
                                @foreach($menu as $key => $value)
                                    <option value="{{$value['id']}}" @if(isset($data->standard_id)){{$data->standard_id == $value['id'] ? 'selected' : '' }} @endif>{{$value['name']}}</option>
                                @endforeach
                                @endif                               
                            </select>
                        </div>
                        <div class="col-md-6 form-group">
                            <label>Division</label>                                                     
                            <select class="selectpicker form-control" data-style="form-control" name="division_id" id="division_id">
                                <option value="">Select Division</option>                                
                                @if(!empty($menu1))  
                                @foreach($menu1 as $key => $value)
                                    <option value="{{$value['id']}}" @if(isset($data->division_id)){{$data->division_id == $value['id'] ? 'selected' : '' }} @endif>{{$value['name']}}</option>
                                @endforeach
                                @endif                                                          
                            </select>
                        </div>
                        
                        <div class="col-md-6 form-group">
                            <label>PTM Title</label>
                            <input type="text" id='title' value="@if(isset($data->title)){{$data->title}}@endif" required name="title" class="form-control">
                        </div>

                        <div class="col-md-6 form-group">
                            <label>PTM Date</label>
                            <div class="input-daterange input-group" id="date-range">
                                <input type="text" required class="form-control mydatepicker" placeholder="dd/mm/yyyy" value="@if(isset($data->ptm_date)){{$data->ptm_date}}@endif" name="ptm_date" autocomplete="off">
                                <span class="input-group-addon"><i class="icon-calender"></i></span> 
                            </div>
                        </div>

                        <div class="col-md-8 addButtonCheckbox">
                            <div class="row align-items-center">
                                <div class="col-md-5 form-group">
                                    <label>From Time</label>
                                    <div class="input-group" data-placement="bottom" data-align="top" data-autoclose="true">
                                        <input type="text" id='from_time[]' required name="from_time[]" class="form-control batchname clockpicker" value="@if(isset($data->from_time)){{$data->from_time}}@endif">
                                        <span class="input-group-addon"><span class="glyphicon glyphicon-time"></span></span>
                                    </div>
                                </div>
                                <div class="col-md-5 form-group">
                                    <label>To Time</label>
                                    <div class="input-group" data-placement="bottom" data-align="top" data-autoclose="true">
                                        <input type="text" id='to_time[]' required name="to_time[]" class="form-control batchname clockpicker" value="@if(isset($data->to_time)){{$data->to_time}}@endif">
                                        <span class="input-group-addon"><span class="glyphicon glyphicon-time"></span></span>
                                    </div>
                                </div>
                                 @if(!isset($data))
                                <div class="col-md-1 form-group mt-4">
                                    <a href="javascript:void(0);" onclick="addNewRow();" class="btn btn-primary"><i class="fa fa-plus"></i></a>
                                </div>
                                @endif                                
                            </div>                            
                        </div>                            
                                                                                                   
                        <div class="col-md-12 form-group">
                            <center>
                                <input type="submit" name="submit" value="Save" class="btn btn-success" >
                            </center>
                        </div>
                    </div>
                    </form>

                </div>
            </div>
        </div>        
    </div>
</div>

@include('includes.footerJs')
<script src="/plugins/bower_components/clockpicker/dist/jquery-clockpicker.min.js"></script>

<script>
function addNewRow(){
    var html = '';    
    html += '<div class="clearfix"></div><div class="col-md-8 addButtonCheckbox"><div class="row align-items-center">';

    html += '<div class="col-md-5 form-group"><div class="input-group" data-placement="bottom" data-align="top" data-autoclose="true"><input type="text" id="from_time[]" required name="from_time[]" class="form-control batchname clockpicker" value=""><span class="input-group-addon"><span class="glyphicon glyphicon-time"></span></span></div></div>';    
    html += '<div class="col-md-5 form-group"><div class="input-group" data-placement="bottom" data-align="top" data-autoclose="true"><input type="text" id="to_time[]" required name="to_time[]" class="form-control batchname clockpicker" value=""><span class="input-group-addon"><span class="glyphicon glyphicon-time"></span></span></div></div>';    
    html += '<div class="col-md-1 form-group mt-4"><a href="javascript:void(0);" onclick="removeNewRow();" class="btn btn-danger"><i class="fa fa-minus"></i></a></div></div></div>';
    $('.addButtonCheckbox:last').after(html);
}
function removeNewRow() {     
    $(".addButtonCheckbox:last" ).remove();      
}

function removeNewRowAjax(id) {
    var standard_id = $("#standard_id").val(); 
    var division_id = $("#division_id").val();          
    var path = "{{ route('ajaxdestroybatch_master') }}";    
    $.ajax({
        url: path,        
        type:'post', 
        data: {"id": id},
        success: function(result){                    
            $("#title_"+id).remove();
        }
    });     
}

function getStandardwiseDivision(std_id){   
    var path = "{{ route('ajax_StandardwiseDivision') }}";
    $('#division_id').find('option').remove().end().append('<option value="">Select Division</option>').val('');
    $.ajax({url: path,data:'standard_id='+std_id, success: function(result){
        for(var i=0;i < result.length;i++){                   
            $("#division_id").append($("<option></option>").val(result[i]['division_id']).html(result[i]['name']));  
        } 
    }
    });
}
</script>
<script>
    $('#single-input').clockpicker({
    placement: 'bottom',
    align: 'left',
    autoclose: true,
    'default': 'now'
  });
  $('.clockpicker').clockpicker({
    donetext: 'Done',
  }).find('input').change(function() {
    console.log(this.value);
  });
  $('#check-minutes').click(function(e) {
    // Have to stop propagation here
    e.stopPropagation();
    input.clockpicker('show').clockpicker('toggleView', 'minutes');
  });
</script>
@include('includes.footer')
