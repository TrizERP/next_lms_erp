{{--@include('includes.lmsheadcss')
@include('includes.header')
@include('includes.sideNavigation')--}}
@extends('lmslayout')
@section('container')
<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">
                Edit Chapter
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
                    <form action="@if (isset($data['chapter_data']))
                          {{ route('chapter_master.update', $data['chapter_data']['id']) }}
                          @endif" enctype="multipart/form-data" method="post">
							{{ method_field("PUT") }}
                            @csrf

						@if(isset($data['chapter_data']))
						{{ App\Helpers\SearchChain('4','','grade,std',$data['chapter_data']['grade_id'],$data['chapter_data']['standard_id']) }}
						@endif
						<div class="col-md-3 form-group">
                            <label for="subject">Select Subject:</label>
                            <select name="subject" id="subject" class="form-control">
                                <option value="">Select Subject</option>
                                @foreach($data['subjects'] as $key => $value)
                                <option value="{{$value['subject_id']}}" @if(isset($data['chapter_data']['subject_id'])) @if($data['chapter_data']['subject_id']==$value['subject_id']) selected='selected' @endif @endif>{{$value['display_name']}}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-4 form-group">
                            <label>Chapter Name</label>
                            <input type="text" id='chapter_name' value="@if(isset($data['chapter_data']['chapter_name'])){{$data['chapter_data']['chapter_name']}}@endif" required name="chapter_name" class="form-control">
                        </div>
                        <div class="col-md-4 form-group">
                            <label>Chapter Description</label>
                            <textarea id="chapter_desc" name="chapter_desc" class="form-control">@if(isset($data['chapter_data']['chapter_desc'])){{$data['chapter_data']['chapter_desc']}}@endif</textarea>
                        </div>
                        <div class="col-md-2 form-group">
                            <label>Availability</label>
                            <br><input type="checkbox" id="availability" name="availability" value="1" @if($data['chapter_data']['availability'] == 1) checked @endif>
                        </div>

                        <div class="col-md-2 form-group">
                            <label>Show</label>
                            <br><input type="checkbox" id="show_hide" name="show_hide" value="1" @if($data['chapter_data']['show_hide'] == 1) checked @endif>
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

@include('includes.lmsfooterJs')
<script>
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
