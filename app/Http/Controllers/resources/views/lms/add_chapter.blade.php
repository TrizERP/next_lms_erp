{{--@include('includes.headcss')--}}
<link href="/plugins/bower_components/clockpicker/dist/jquery-clockpicker.min.css" rel="stylesheet">
{{--@include('includes.header')
@include('includes.sideNavigation')--}}
@extends('layout')
@section('container')
<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">
                Add Chapter
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
                    <form action="{{ route('chapter_master.store') }}" method="post">
                        {{ method_field("POST") }}
                        @csrf

                        {{ App\Helpers\SearchChain('4','','grade,std','','') }}
                        <div class="col-md-4 form-group">
                            <label for="subject">Select Subject:</label>
                            <select name="subject" id="subject" class="form-control" required>
                                <option value="">Select Subject</option>
                            </select>
                        </div>

                        <div class="addButtonCheckbox">
                            <div class="col-md-3 form-group">
                                <label>Chapter Name</label>
                                <input type="text" id='chapter_name[]' required name="chapter_name[]" class="form-control">
                            </div>
                            <div class="col-md-3 form-group">
                                <label>Chapter Description</label>
                                <textarea id="chapter_desc[]" name="chapter_desc[]" class="form-control"></textarea>
                            </div>

                            <div class="col-md-2 form-group">
                                <label>Availability</label>
                                <br><input type="checkbox" id="availability[]" name="availability[]" value="1">
                            </div>

                            <div class="col-md-2 form-group">
                                <label>Show</label>
                                <br><input type="checkbox" id="show_hide[]" name="show_hide[]" value="1" checked>
                            </div>

                            <div class="col-md-1 form-group">
                                <br>
                                <a href="javascript:void(0);" onclick="addNewRow();"><span class="circle circle-sm bg-success di form-control"><i class="ti-plus"></i></span></a>
                            </div>

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
function addNewRow(){
    var html = '';
    html += '<div class="clearfix"></div><div class="addButtonCheckbox">';

    html += '<div class="col-md-3 form-group"><label>Chapter Name</label><input type="text" id="chapter_name[]" required name="chapter_name[]" class="form-control"></div>';
    html += '<div class="col-md-3 form-group"><label>Chapter Description</label><textarea id="chapter_desc[]" name="chapter_desc[]" class="form-control"></textarea></div>';
    html += '<div class="col-md-2 form-group"><label>Availability</label><br><input type="checkbox"  value="1" id="availability[]" name="availability[]"></div>';
    html += '<div class="col-md-2 form-group"><label>Show</label><br><input type="checkbox" checked value="1" id="show_hide[]" name="show_hide[]"></div>';

    html += '<div class="col-md-1  form-group"><a href="javascript:void(0);" onclick="removeNewRow();"><span class="circle circle-sm di form-control" style="background-color:#41b3f9;"><i class="ti-minus"></i></span></a></div></div>';
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
</script>
@include('includes.footer')
@endsection
