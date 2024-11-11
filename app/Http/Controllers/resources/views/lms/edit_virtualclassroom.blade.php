{{--@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')--}}
@extends('layout')
@section('container')
<!-- Content main Section -->

<div class="content-main flex-fill">
    <div class="container-fluid mb-5">
        <div class="card border-0">
            <div class="card-body">
                <form action="{{ route('lmsVirtualClassroom.update',['id'=>$data['virtualclassroom_data']['id']])}}" method="post">
                    {{ method_field("PUT") }}
                    @csrf

                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="room_name">Room Name(Topic)</label>
                                <input type="text" class="form-control" id="room_name" name="room_name" placeholder="Room Name" value="@if(isset($data['virtualclassroom_data']['room_name'])){{$data['virtualclassroom_data']['room_name']}}@endif" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="description">Description</label>
                                <textarea type="text" rows="4" class="form-control" id="description" name="description" placeholder="Description">@if(isset($data['virtualclassroom_data']['description'])){{$data['virtualclassroom_data']['description']}}@endif</textarea>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Recurring</label>
                                <select id='recurring' name="recurring" class="form-control" onchange="showothers(value);">
                                    <option>--Select Recurring--</option>
                                    <option value="Yes" @if(isset($data['virtualclassroom_data']['recurring'])) @if( $data['virtualclassroom_data']['recurring'] == "Yes" ) selected='selected' @endif @endif>Yes</option>
                                    <option value="No" @if(isset($data['virtualclassroom_data']['recurring'])) @if( $data['virtualclassroom_data']['recurring'] == "No" ) selected='selected' @endif @endif>No</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group" id="event_date_div">
                                <label for="event_date">Event Date</label>
                                <input type="text" class="form-control mydatepicker" placeholder="dd/mm/yyyy" value="@if(isset($data['virtualclassroom_data']['event_date'])){{$data['virtualclassroom_data']['event_date']}}@endif" name="event_date" autocomplete="off">
                                <span class="input-group-addon"><i class="icon-calender"></i></span>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group" id="from_time_div">
                                <label>From Time</label>
                                <div class="input-group clockpicker " data-placement="bottom" data-align="top" data-autoclose="true">
                                    <input type="text" id='from_time' name="from_time" class="form-control" value="@if(isset($data['virtualclassroom_data']['from_time'])){{$data['virtualclassroom_data']['from_time']}}@endif" >
                                    <span class="input-group-addon"><span class="glyphicon glyphicon-time"></span></span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group" id="to_time_div">
                                <label>To Time</label>
                                <div class="input-group clockpicker " data-placement="bottom" data-align="top" data-autoclose="true">
                                    <input type="text" id='to_time' name="to_time" class="form-control" value="@if(isset($data['virtualclassroom_data']['to_time'])){{$data['virtualclassroom_data']['to_time']}}@endif" >
                                    <span class="input-group-addon"><span class="glyphicon glyphicon-time"></span></span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="url">URL</label>
                                <input type="text" class="form-control" id="url" name="url" placeholder="Url" value="@if(isset($data['virtualclassroom_data']['url'])){{$data['virtualclassroom_data']['url']}}@endif" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="password">Password</label>
                                <input type="password" class="form-control" id="password" name="password" value="@if(isset($data['virtualclassroom_data']['password'])){{$data['virtualclassroom_data']['password']}}@endif" placeholder="Password" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Display Status</label>
                                <select id='status' name="status" class="form-control">
                                    <option>--Select Status--</option>
                                    <option value="Yes" @if(isset($data['virtualclassroom_data']['status'])) @if( $data['virtualclassroom_data']['status'] == "Yes" ) selected='selected' @endif @endif>Yes</option>
                                    <option value="No" @if(isset($data['virtualclassroom_data']['status'])) @if( $data['virtualclassroom_data']['status'] == "No" ) selected='selected' @endif @endif>No</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Notification</label>
                                <select id='notification' name="notification" class="form-control">
                                    <option>--Select Notification--</option>
                                    <option value="Yes" @if(isset($data['virtualclassroom_data']['notification'])) @if( $data['virtualclassroom_data']['notification'] == "Yes" ) selected='selected' @endif @endif>Want to send notification to students?</option>
                                    <option value="No" @if(isset($data['virtualclassroom_data']['notification'])) @if( $data['virtualclassroom_data']['notification'] == "No" ) selected='selected' @endif @endif>Don't want to send notification to students.</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="sort_order">Sort Order</label>
                                <input type="number" class="form-control" id="sort_order" name="sort_order" value="@if(isset($data['virtualclassroom_data']['sort_order'])){{$data['virtualclassroom_data']['sort_order']}}@endif" required>
                            </div>
                        </div>
                    </div>
                    <button class="btn btn-primary" type="submit">Save</button>
                    <!-- <button class="btn btn-outline-primary" type="submit">Reset</button> -->
                </form>
            </div>
        </div>
    </div>
</div>
@include('includes.footerJs')
<script src="{{asset('/plugins/bower_components/bootstrap-tagsinput/dist/bootstrap-tagsinput.min.js')}}"></script>
<script>

$( document ).ready(function() {

    $('#datetimepicker').datetimepicker({
        //format: 'DD/MM/YYYY hh:SS A'
    });

});


//START Bind Mapping Value
// $('select[name="mapping_type[]"]').each(function(){
// $(this).change(function () {
//$(".load_map_value").change(function(){
$(document).on('change','.load_map_value', function(){
    var mapping_type = $(this).val();
    var data_new = $(this).attr('data-new');
       // alert(mapping_type);
       // alert(data_new);

    var path = "{{ route('ajax_LMS_MappingValue') }}";
    //$('#mapping_value').find('option').remove().end();
    $.ajax({
        url:path,
        data:'mapping_type='+mapping_type,
        success:function(result){
            //var e = $('#mapping_value[data-new='+data_new+']');
            var e = $('select[name="mapping_value[]"][data-new='+data_new+']');
            $(e).find('option').remove().end();
            for(var i=0;i < result.length ;i++)
            {
                $(e).append($("<option></option>").val(result[i]['id']).html(result[i]['name']));
                //$("#mapping_value[]").append($("<option></option>").val(result[i]['id']).html(result[i]['name']));
            }
        }
    });
});

//});


//END Bind Mapping Value

function addNewRow(){
    $('select[name="mapping_type[]"]').each(function(){
        data_new =  parseInt($(this).attr('data-new'));
        html = $(this).html();
    });
    data_new = parseInt(data_new) + 1;

    var mapping_type_data = html;//$('#mapping_type:first').html();
    var htmlcontent = '';
    htmlcontent += '<div class="clearfix"></div><div class="addButtonCheckbox" style="display: flex; margin-right: -15px; margin-left: -15px; flex-wrap: wrap;">';

    htmlcontent += '<div class="col-md-4"><div class="form-group"><label for="topicType">Mapping Type</label><select class="load_map_value form-control cust-select" name="mapping_type[]" data-new='+data_new+'>'+mapping_type_data+'</select></div></div>';
    htmlcontent += '<div class="col-md-4"><div class="form-group"><label for="topicType2">Mapping Value</label><select class="form-control cust-select" name="mapping_value[]" data-new='+data_new+'><option>Select Mapping Value</option></select></div></div>';
    htmlcontent += '<div class="col-md-4" style="margin-top: 32px;"><a href="javascript:void(0);" onclick="removeNewRow();" class="d-inline btn btn-danger btn-sm"><i class="mdi mdi-minus"></i></a></div></div>';

    $('.addButtonCheckbox:last').after(htmlcontent);
}
function removeNewRow() {
    $(".addButtonCheckbox:last" ).remove();
}

function removeOldRow(j) {
    $("#old_data_"+j ).remove();
}

function restrict_filetype(filetype)
{
    $("#filename" ).attr('accept',filetype);
}

function getFileNameWithExt(event) {

  if (!event || !event.target || !event.target.files || event.target.files.length === 0) {
    return;
  }

  var name = event.target.files[0].name;
  var lastDot = name.lastIndexOf('.');

  var fileName = name.substring(0, lastDot);
  var ext = name.substring(lastDot + 1);

//alert(fileName);
//alert(ext);

  var contentType = $("#contentType").val();
  //alert(contentType);

  if(contentType != ext)
  {
    $("#filename").val("");
    alert("Please Upload file of "+contentType+" extension");
    return false;
  }
  // outputfile.value = fileName;
  // extension.value = ext;
}

</script>
@include('includes.footer')
@endsection
