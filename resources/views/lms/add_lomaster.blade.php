{{--@include('includes.headcss')--}}
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
                    @if(!isset($data['lomaster_data']))
                    Add LO Master
                    @else
                    Edit LO Master
                    @endif
                </h4>
            </div>
        </div>
        <div class="card">
            @if ($message = Session::get('success'))
            <div class="alert alert-success alert-block">
                <button type="button" class="close" data-dismiss="alert">×</button>
                <strong>{{ $message }}</strong>
            </div>
            @endif
            <div class="row">
                <div class="col-lg-12 col-sm-12 col-xs-12">
                    <form action="@if (isset($data['lomaster_data']))
                    {{ route('lo_master.update', $data['lomaster_data']['id'])}}
                    @else
                    {{ route('lo_master.store') }}
                    @endif" method="post" enctype='multipart/form-data'>
                        @if(!isset($data['lomaster_data']))
                            {{ method_field("POST") }}
                        @else
                            {{ method_field("PUT") }}
                        @endif
                        @csrf
                        <div class="row">
                            @if(isset($data['lomaster_data']))

                                {{ App\Helpers\SearchChain('4','','grade,std',$data['lomaster_data']['grade_id'],$data['lomaster_data']['standard_id']) }}

                            @else

                                {{ App\Helpers\SearchChain('4','','grade,std') }}

                            @endif


                            <div class="col-md-4 form-group">
                                <label for="subject">Select Subject:</label>
                                <select name="subject" id="subject" class="form-control" required>
                                    <option value="">Select Subject</option>
                                    @if(isset($data['lomaster_data']))
                                        @foreach($data['subjects'] as $key => $value)
                                            <option value="{{$value['subject_id']}}"
                                                    @if(isset($data['lomaster_data']['subject_id'])) @if($data['lomaster_data']['subject_id']==$value['subject_id']) selected='selected' @endif @endif>{{$value['display_name']}}</option>
                                        @endforeach
                                    @endif
                                </select>
                            </div>
                            <div class="col-md-4 form-group">
                                <label for="subject">Select Chapter:</label>
                                <select name="chapter" id="chapter" class="form-control" required>
                                    <option value="">Select Chapter</option>
                                    @if(isset($data['lomaster_data']))
                                        @foreach($data['chapters'] as $key1 => $value1)
                                            <option value="{{$value1['id']}}"
                                                    @if(isset($data['lomaster_data']['chapter_id'])) @if($data['lomaster_data']['chapter_id']==$value1['id']) selected='selected' @endif @endif>{{$value1['chapter_name']}}</option>
                                        @endforeach
                                    @endif
                                </select>
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Title</label>
                                <input type="text" id='title' name="title"
                                       value="@if(isset($data['lomaster_data']['title'])){{$data['lomaster_data']['title']}}@endif"
                                       class="form-control" required>
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Short Code</label>
                                <input type="text" id='short_code' name="short_code"
                                       value="@if(isset($data['lomaster_data']['short_code'])){{$data['lomaster_data']['short_code']}}@endif"
                                       class="form-control" required>
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Sort Order</label>
                                <input type="text" id='sort_order' name="sort_order"
                                       value="@if(isset($data['lomaster_data']['sort_order'])){{$data['lomaster_data']['sort_order']}}@endif"
                                       class="form-control">
                            </div>
                            <div class="col-md-4 form-group mt-4">
                                <label>Availability</label>
                                <br><input type="checkbox" id="availability" name="availability" value="1"
                                           @if( isset($data['lomaster_data']['availability']) && $data['lomaster_data']['availability'] == 1)
                                           checked
                                           @elseif(!isset($data['lomaster_data']))
                                           checked
                                    @endif
                                >
                            </div>
                            <div class="col-md-4 form-group mt-4">
                                <label>Show</label>
                                <br><input type="checkbox" id="show_hide" name="show_hide" value="1"
                                           @if( isset($data['lomaster_data']['show_hide']) && $data['lomaster_data']['show_hide'] == 1)
                                           checked
                                           @elseif(!isset($data['lomaster_data']))
                                           checked
                                    @endif
                                >
                            </div>
                            <div class="col-md-12 form-group">
                                <center>
                                    <input type="submit" name="submit" value="Save" class="btn btn-success">
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
<script>
    function getStandardwiseDivision(std_id) {
        var path = "{{ route('ajax_StandardwiseDivision') }}";
        $('#division_id').find('option').remove().end().append('<option value="">Select Division</option>').val('');
        $.ajax({
            url: path, data: 'standard_id=' + std_id, success: function (result) {
                for (var i = 0; i < result.length; i++) {
                    $("#division_id").append($("<option></option>").val(result[i]['division_id']).html(result[i]['name']));
                }
            }
        });
    }

    $(document).ready(function () {
        $("#standard").change(function () {
            var std_id = $("#standard").val();
            var path = "{{ route('ajax_StandardwiseSubject') }}";
        $('#subject').find('option').remove().end().append('<option value="">Select Subject</option>').val('');
        $.ajax({url: path,data:'std_id='+std_id, success: function(result){
                for (var i = 0; i < result.length; i++) {
                    $("#subject").append($("<option></option>").val(result[i]['subject_id']).html(result[i]['display_name']));
                }
            }
        });
        })
    });

    //START Bind chapters
    $("#subject").change(function () {
        var subject = $("#subject").val();
        var standard = $("#standard").val();
        var path = "{{ route('ajax_SubjectwiseChapter') }}";
        $('#chapter').find('option').remove().end().append('<option value="">Select Chapter</option>').val('');
        $.ajax({
            url: path,
            data: 'sub_id=' + subject + '&std_id=' + standard,
            success: function (result) {
                for (var i = 0; i < result.length; i++) {
                    $("#chapter").append($("<option></option>").val(result[i]['id']).html(result[i]['chapter_name']));
                }
            }
        });
    })
    //END Bind chapters
</script>
@include('includes.footer')
@endsection
