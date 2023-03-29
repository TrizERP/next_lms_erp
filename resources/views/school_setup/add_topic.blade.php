@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">
                @if(!isset($data))
                Add Topic
                @else
                Edit Topic
                @endif
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
                          {{ route('topic_master.update', $data['chapter_data']['id']) }}
                          @else
                          {{ route('topic_master.store') }}
                          @endif" enctype="multipart/form-data" method="post">
							@if(!isset($data['chapter_data']))
							{{ method_field("POST") }}
							@else
							{{ method_field("PUT") }}
							@endif
                            @csrf
							
						
                        <div class="col-md-4 form-group">
                            <label>Topic Name</label>
                            <input type="hidden" id='hidchapter_id' name='hidchapter_id' value="@if(isset($data['chapter_id'])){{$data['chapter_id']}}@endif" class="form-control">
                            <input type="text" id='topic_name' value="@if(isset($data['chapter_data']['chapter_name'])){{$data['chapter_data']['chapter_name']}}@endif" required name="topic_name" class="form-control">
                        </div>
                        <div class="col-md-4 form-group">
                            <label>Description</label>
                            <textarea id="topic_desc" name="topic_desc" class="form-control">@if(isset($data['chapter_data']['chapter_desc'])){{$data['chapter_data']['chapter_desc']}}@endif</textarea>
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
