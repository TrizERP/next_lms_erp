{{--
@include('includes.headcss')
--}}
@extends('layout')
@section('container')
<link href="/plugins/bower_components/clockpicker/dist/jquery-clockpicker.min.css" rel="stylesheet">
{{--@include('includes.header')
@include('includes.sideNavigation')--}}

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">
                Add Lo Indicator
                </h4>
            </div>
        </div>
        <div class="row" style=" margin-top: 25px;">
            <div class="white-box">
            <div class="panel-body">
                @if ($message = Session::get('success'))
                <div class="alert alert-success alert-block">
                    <button type="button" class="close" data-dismiss="alert">×</button>
                    <strong>{{ $message }}</strong>
                </div>
                @endif
                <div class="col-lg-12 col-sm-12 col-xs-12">
                    <form action="@if (isset($data['loindicator_data']))
                    {{ route('lo_indicator.update',['div_id'=>$data['loindicator_data']['id']])}}
                    @else
                    {{ route('lo_indicator.store') }}
                    @endif" method="post" enctype='multipart/form-data'>
                        @if(!isset($data['loindicator_data']))
                        {{ method_field("POST") }}
                        @else
                        {{ method_field("PUT") }}
                        @endif
                        @csrf

                        @if(isset($data['loindicator_data']))
						{{ App\Helpers\SearchChain('4','','grade,std',$data['loindicator_data']['grade_id'],$data['loindicator_data']['standard_id']) }}
						@else
						{{ App\Helpers\SearchChain('4','','grade,std') }}
                        @endif

                        <div class="col-md-3 form-group">
                            <label for="subject">Select Subject:</label>
                            <select name="subject" id="subject" class="form-control" required>
                                <option value="">Select Subject</option>
                                @if(isset($data['loindicator_data']))
                                @foreach($data['subjects'] as $key => $value)
                                <option value="{{$value['subject_id']}}" @if(isset($data['loindicator_data']['subject_id'])) @if($data['loindicator_data']['subject_id']==$value['subject_id']) selected='selected' @endif @endif>{{$value['display_name']}}</option>
                                @endforeach
                            @endif
                            </select>
                        </div>

                        <div class="col-md-4 form-group">
                            <label for="subject">Select Chapter:</label>
                            <select name="chapter" id="chapter" class="form-control" required>
                                <option value="">Select Chapter</option>
                                @if(isset($data['loindicator_data']))
                                    @foreach($data['chapters'] as $key1 => $value1)
                                    <option value="{{$value1['id']}}" @if(isset($data['loindicator_data']['chapter_id'])) @if($data['loindicator_data']['chapter_id']==$value1['id']) selected='selected' @endif @endif>{{$value1['chapter_name']}}</option>
                                    @endforeach
                                @endif
                            </select>
                        </div>

                        <div class="col-md-4 form-group">
                            <label for="subject">Select LO Master:</label>
                            <select name="lomaster" id="lomaster" class="form-control" required>
                                <option value="">Select LO Master</option>
                                @if(isset($data['loindicator_data']))
                                    @foreach($data['lomasters'] as $key2 => $value2)
                                    <option value="{{$value2['id']}}" @if(isset($data['loindicator_data']['lomaster_id'])) @if($data['loindicator_data']['lomaster_id']==$value2['id']) selected='selected' @endif @endif>{{$value2['title']}}</option>
                                    @endforeach
                                @endif
                            </select>
                        </div>

                        <div class="col-md-3 form-group">
                            <label>LO Indicator</label>
                            <input type="text" id='indicator' name="indicator" value="@if(isset($data['loindicator_data']['indicator'])){{$data['loindicator_data']['indicator']}}@endif" class="form-control" required>
                        </div>

                        <div class="col-md-3 form-group">
                            <label>Sort Order</label>
                            <input type="text" id='sort_order' name="sort_order" value="@if(isset($data['loindicator_data']['sort_order'])){{$data['loindicator_data']['sort_order']}}@endif" class="form-control">
                        </div>

                        <div class="col-md-2 form-group">
                            <label>Availability</label>
                            <br><input type="checkbox" id="availability" name="availability" value="1"
                            @if( isset($data['loindicator_data']['availability']) && $data['loindicator_data']['availability'] == 1)
                            checked
                            @elseif(!isset($data['loindicator_data']))
                            checked
                            @endif
                            >
                        </div>

                        <div class="col-md-2 form-group">
                            <label>Show</label>
                            <br><input type="checkbox" id="show_hide" name="show_hide" value="1"
                            @if( isset($data['loindicator_data']['show_hide']) && $data['loindicator_data']['show_hide'] == 1)
                            checked
                            @elseif(!isset($data['loindicator_data']))
                            checked
                            @endif
                            >
                        </div>

                        <div class="col-md-12 form-group">
                            <center>
                                <input type="submit" name="submit" value="Save" class="btn btn-success" >
                            </center>
                        </div>
                    </form>

                </div>
            </div>
            </div>
        </div>
    </div>
</div>

@include('includes.footerJs')
<script>
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

$( document ).ready(function() {
    $("#standard").change(function(){
        var std_id = $("#standard").val();
        var path = "{{ route('ajax_StandardwiseSubject') }}";
        $('#subject').find('option').remove().end().append('<option value="">Select Subject</option>').val('');
        $.ajax({url: path,data:'std_id='+std_id, success: function(result){
            for(var i=0;i < result.length;i++){
                $("#subject").append($("<option></option>").val(result[i]['subject_id']).html(result[i]['display_name']));
            }
        }
        });
    })
});

//START Bind chapters
$("#subject").change(function(){
    var subject = $("#subject").val();
    var standard = $("#standard").val();
    var path = "{{ route('ajax_SubjectwiseChapter') }}";
    $('#chapter').find('option').remove().end().append('<option value="">Select Chapter</option>').val('');
    $.ajax({
        url:path,
        data:'sub_id='+subject+'&std_id='+standard,
        success:function(result){
            for(var i=0;i < result.length ;i++)
            {
                $("#chapter").append($("<option></option>").val(result[i]['id']).html(result[i]['chapter_name']));
            }
        }
    });
})
//END Bind chapters

//START LO Master
$("#chapter").change(function(){
    var chapter = $("#chapter").val();
    var path = "{{ route('ajax_ChapterwiseLOmaster') }}";
    $('#lomaster').find('option').remove().end().append('<option value="">Select LO Master</option>').val('');
    $.ajax({
        url:path,
        data:'chapter_id='+chapter,
        success:function(result){
            for(var i=0;i < result.length ;i++)
            {
                $("#lomaster").append($("<option></option>").val(result[i]['id']).html(result[i]['title']));
            }
        }
    });
})
//END Bind LO Master

</script>
@include('includes.footer')
@endsection
