{{--@include('includes.lmsheadcss')
@include('includes.header')
@include('includes.sideNavigation')--}}
@extends('lmslayout')
@section('container')
<div class="content-main flex-fill">
    <div class="container-fluid mb-5">
		<div class="course-grid-tab tab-pane fade show active" id="grid" role="tabpanel" aria-labelledby="grid-tab">
			<ul class="nav nav-pills mb-3" id="pills-tab" role="tablist">
				<!-- <li class="nav-item">
				  <a class="nav-link" id="message-tab" data-toggle="pill" href="#message" role="tab" aria-controls="pills-profile" aria-selected="false">Latest File</a>
				</li>
				<li class="nav-item">
				  <a class="nav-link" id="chat-tab" data-toggle="pill" href="#chat" role="tab" aria-controls="chat-tab" aria-selected="false">Forum</a>
				</li> -->
				<li class="nav-item">
					<a class="nav-link active" id="notification-tab" data-toggle="pill" href="#notification" role="tab" aria-controls="pills-home" aria-selected="true">Discussion</a>
				</li>
				<!-- <li class="nav-item">
					<a class="nav-link" id="groupchat-tab" data-toggle="pill" href="#groupchat" role="tab" aria-controls="groupchat-tab" aria-selected="false">Group Chat</a>
				</li> -->
			</ul>
			<div class="tab-content" id="pills-tabContent">
				<div class="tab-pane fade show active" id="notification" role="tabpanel" aria-labelledby="notification-tab">

					@if(count($data['doubt_data']) > 0)
						@php $k=1; @endphp
						@foreach($data['doubt_data'] as $key => $val)
						<div class="accordion-card collapsed px-2 pt-2 border-0" data-toggle="collapse" href="#collapseExample{{$k}}" role="button" aria-expanded="false" aria-controls="collapseExample">
							<div class="notification-box card icon-card">
								<div class="icon-box">
									<i class="mdi mdi-file-pdf-outline"></i>
								</div>
								<div class="icon-info">
									<div class="noti-title">{!!$val['description']!!}</div>
									<div class="noti-des d-flex align-items-center">
										<div class="mr-3">By {{$val['student_name']}}</div>
										<div class="mr-3 border-left px-3 d-flex">
											<a class="mdi mdi-comment-outline mdi-18px mr-2" data-toggle="modal"
											onclick="javascript:add_data({{$val['id']}});" style="color:#000000;">Comments</a>
										</div>
									</div>
								</div>
								@if($val['totaldays'] == 0)
									<div class="time">Today</div>
								@elseif($val['totaldays'] == 1)
									<div class="time">{{$val['totaldays']}} Day ago</div>
								@else
									<div class="time">{{$val['totaldays']}} Days ago</div>
								@endif

							</div>
						</div>
						<div class="video-list mt-4 mb-4 ml-4 collapse"  id="collapseExample{{$k}}">
							@if(isset($data['doubt_conversation_data'][$val['id']]))
								@foreach($data['doubt_conversation_data'][$val['id']] as $key1 => $val1)
									<div class="video-box mb-2">
										<div class="noti-title mr-3">{{$val1['message']}}</div>
										<div class="border-left mr-3 px-3 d-flex">By {{$val1['student_name']}}</div>
									</div>
								@endforeach
							@endif
						</div>
						@php $k++; @endphp

						@endforeach
					@endif
				</div>
				<!-- <div class="tab-pane fade" id="message" role="tabpanel" aria-labelledby="message-tab">Latest File</div>
				<div class="tab-pane fade" id="chat" role="tabpanel" aria-labelledby="chat-tab">Forum</div>
				<div class="tab-pane fade" id="groupchat" role="tabpanel" aria-labelledby="groupchat-tab">Group Chat</div> -->
			</div>
		</div>
    </div>
</div>



<!--Modal: Add Comment Modal-->
<div class="modal fade right modal-scrolling" id="CommentModal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" style="display: none;" aria-hidden="true">
    <div class="modal-dialog modal-side modal-bottom-right modal-notify modal-info modal-xl" role="document">
        <!--Content-->
        <div class="modal-content">
            <!--Header-->
            <div class="modal-header">
                <h5 class="modal-title" id="heading">Add Comment</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">x</span>
                </button>
            </div>

            <!--Body-->
            <form action="{{ route('lmsDoubtConversation.store') }}" method="post" id="comment_form">
	            {{ method_field("POST") }}
	            @csrf
	            <div class="modal-body">
	                <div class="row">
	                    <div class="white-box">
	                        <div class="panel-body">
	                            @if ($message = Session::get('success'))
	                            <div class="alert alert-success alert-block">
	                                <button type="button" class="close" data-dismiss="alert">×</button>
	                                <strong>{{ $message }}</strong>
	                            </div>
	                            @endif
	                             <div class="col-md-12 form-group">
                                    <label>Comment</label>
                                    <textarea id="message" name="message" class="form-control"></textarea>
                                    <input type="hidden" id="doubt_id" name="doubt_id" />
                                </div>
	                        </div>
	                    </div>
	                </div>
	            </div>


	            <!--Footer-->
	            <div class="modal-footer flex-center">
	                <input type="submit" id="submit" name="submit" value="Save" class="btn btn-success" >
	            </div>
			</form>
        </div>
        <!--/.Content-->
    </div>
</div>
<!--Modal: Add Comment Modal-->

@include('includes.lmsfooterJs')
<script type="text/javascript">
function add_data(id)
{
    $('#doubt_id').val(id);
    $('#CommentModal').modal('show');
}
</script>
@include('includes.footer')
@endsection
