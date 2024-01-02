{{--
@include('includes.headcss')
--}}
<link href="/plugins/bower_components/clockpicker/dist/jquery-clockpicker.min.css" rel="stylesheet">
{{--
@include('includes.header')
@include('includes.sideNavigation')
--}}
@extends('layout')
@section('container')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">
                @if(!isset($data['batch_data']))
                Add Batch
                @else
                Edit Batch
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
                    <form action="@if (isset($data['batch_data']))
                    {{ route('batch_master.update',['batch_master'=>$data['batch_data']['standard_id'],'div_id'=>$data['batch_data']['division_id']])}}
                          @else
                          {{ route('batch_master.store') }}
                          @endif" enctype="multipart/form-data" method="post">
                          @if(!isset($data['batch_data']))
                            {{ method_field("POST") }}
                        @else
                            {{ method_field("PUT") }}
                        @endif
                        @csrf
                        <div class="row">
                            <div class="col-md-6 form-group">
                                <label>Standard</label>
                                <select class=" form-control" name="standard_id" id="standard_id"
                                        onchange="getStandardwiseDivision(this.value);">
                                    <option value="">Select Standard</option>
                                    @foreach($data['standard_data'] as $key =>$val)
                                        @php
                                            $selected = '';
                                            if( isset($data['batch_data']['standard_id']) && $data['batch_data']['standard_id'] == $val->id )
                                            {
                                                $selected = 'selected';
                                            }
                                        @endphp
                                        <option {{$selected}} value="{{$val->id}}">{{$val->name}}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6 form-group">
                                <label>Division</label>
                                <select class=" form-control" data-style="form-control" name="division_id"
                                        id="division_id">
                                    <option value="">Select Division</option>
                                    @if( isset($data['batch_data']['division_id']) )
                                        @foreach($data['division_data'] as $key =>$val)
                                            @php
                                                $selected = '';
                                                if( isset($data['batch_data']['division_id']) && $data['batch_data']['division_id'] == $val->id )
                                                {
                                                    $selected = 'selected';
                                                }
                                            @endphp
                                            <option {{$selected}} value="{{$val->id}}">{{$val->name}}</option>
                                        @endforeach
                                    @endif
                                </select>
                            </div>
                            <div class="col-md-12 form-group">
                                <div class="addButtonCheckbox">
                                    <div class="row">
                                        <div class="col-md-6 col-10">
                                            <label>Batch Name</label>
                                            <input type="text" id='title[NEW][]' name='title[NEW][]'
                                                   @if( ! isset($data['batch_data']['titles']) ) required
                                                   @endif name="title" class="form-control batchname">
                                        </div>
                                        <div class="col-md-1 col-2">
                                            <a href="javascript:void(0);" onclick="addNewRow();"
                                               class="btn btn-outline-success mt-md-4"><i class="fa fa-plus"></i></a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="clearfix"></div>
                            @if( isset($data['batch_data']['titles']) )
                                @php
                                    $titles_arr = explode(',',$data['batch_data']['titles']);
                                    $ids_arr = explode(',',$data['batch_data']['ids']);
                                    foreach($titles_arr as $key =>$val){
                                @endphp
                                <div class="addButtonCheckbox col-md-12 form-group" id="title_{{$ids_arr[$key]}}">
                                    <div class="row">
                                        <div class="col-md-6 form-group ml-0 mr-0">
                                        <!-- <div class="col-md-12 form-group" id="title_{{$ids_arr[$key]}}">  -->
                                            <input type="text" id='title[EDIT][{{$ids_arr[$key]}}]' value="{{$val}}"
                                                   name='title[EDIT][{{$ids_arr[$key]}}]' required name="title"
                                                   class="form-control batchname">
                                            <!-- </div>                                                               -->
                                        </div>
                                        <div class="col-md-1 form-group ml-0">
                                            <a href="javascript:void(0);"
                                               onclick="removeNewRowAjax({{$ids_arr[$key]}});"><span
                                                    class="btn btn-outline-danger mt-md-2"><i
                                                        class="fa fa-minus"></i></span></a>
                                        </div>
                                    </div>
                                </div>
                                <div class="clearfix"></div>
                                @php
                                    }
                                @endphp
                            @endif
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
    function addNewRow() {
        var html = '';
        html += '<div class="clearfix"></div><div class="addButtonCheckbox row"><div class="col-md-6 col-10">';
        html += '<input type="text" id="title[NEW][]" required name="title[NEW][]" class="form-control">';
        html += '';
        html += '</div><div class="col-md-1 col-2"><a href="javascript:void(0);" onclick="removeNewRow();" class="btn btn-outline-danger mt-md-2"><i class="fa fa-minus"></i></a></div></div>';
        $('.addButtonCheckbox:last').after(html);
    }

    function removeNewRow() {
        // $(value).find('.hi').remove();
        $(".addButtonCheckbox:last").remove();
        // $(".col-md-10:last" ).remove();
        // $(".col-md-2:last" ).remove();
    }

    function removeNewRowAjax(id) {
        var standard_id = $("#standard_id").val();
        var division_id = $("#division_id").val();
        var path = "{{ route('ajaxdestroybatch_master') }}";
        $.ajax({
            url: path,
            type: 'post',
            data: {"id": id},
            success: function (result) {
                $("#title_" + id).remove();
            }
        });
    }

    function update_removeNewRow(id) {
        $("#title_" + id).remove();
        $("#blank_" + id).remove();

    }

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
</script>
@include('includes.footer')
@endsection
