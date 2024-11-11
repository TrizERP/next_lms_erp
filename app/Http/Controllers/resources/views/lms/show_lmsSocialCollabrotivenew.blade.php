{{--@include('includes.lmsheadcss')
@include('includes.header')
@include('includes.sideNavigation')--}}
@extends('lmslayout')
@section('container')
<div class="content-main flex-fill">
    <div class="container-fluid mb-5">
    	<div class="row">
			<div class="col-lg-12 col-md-12 col-sm-12 col-xs-12 mb-4">
	            <h1 class="h4 mb-3">Social Collabrative</h1>
	             <nav aria-label="breadcrumb">
	                <ol class="breadcrumb bg-transparent p-0">
	                    <li class="breadcrumb-item"><a href="{{route('course_master.index')}}">LMS</a></li>
	                    <li class="breadcrumb-item">Engagement</li>
	                    <li class="breadcrumb-item">Show Social Collabrative</li>
	                </ol>
	            </nav>
	        </div>
        </div>
		<div class="course-grid-tab tab-pane fade show active" id="grid" role="tabpanel" aria-labelledby="grid-tab">
			<ul class="nav nav-pills mb-3" id="pills-tab" role="tablist">
				<li class="nav-item">
					<a class="nav-link active" id="notification-tab" data-toggle="pill" href="#notification" role="tab" aria-controls="pills-home" aria-selected="true">Discussion</a>
				</li>
			</ul>
			<div class="bootstrap snippets bootdey">
			    <div class="row">
					<div class="col-md-12">
					    <div class="blog-comment">
							<ul class="comments">
							@if(count($data['doubt_data']) > 0)
								@php $k=1; @endphp
								@foreach($data['doubt_data'] as $key => $val)
									<div class="accordion-card collapsed px-2 pt-2 border-0" data-toggle="collapse" href="#collapseExample{{$k}}" role="button" aria-expanded="false" aria-controls="collapseExample">
										<li class="clearfix">
										  <img src="/storage/{{$val['image']}}" class="avatar" alt="">
										  <div class="post-comments">
										      <p class="meta">{{$val['doubt_date']}} <a href="#">{{$val['student_name']}}({{$val['standard_division']}})</a> says : <i class="pull-right">

										      	<a href="javascript:add_data({{$val['id']}});"><small>Reply</small></a></i></p>

										          {!!$val['description']!!}
										          ({{$val['title']}})


										  </div>

										  	@if(isset($data['doubt_conversation_data'][$val['id']]))
						 					<ul class="comments" id="collapseExample{{$k}}">
						 						@foreach($data['doubt_conversation_data'][$val['id']] as $key1 => $val1)
											      <li class="clearfix">
											          <img src="/storage/{{$val1['image']}}" class="avatar" alt="">
											          <div class="post-comments">
											              <p class="meta">{{$val1['comment_date']}} <a href="#">{{$val1['student_name']}} {{$val1['standard_division']}}</a> says : </p>
											              <p>
											                  {{$val1['message']}}
											              </p>
											          </div>
											      </li>
											    @endforeach
											</ul>
											@endif
										</li>
									</div>

								@endforeach
							@else
								<li>
									<div class="post-comments" style="margin-left:0px !important;">
										No Records
									</div>
								</li>
							@endif
							</ul>
						</div>
					</div>
				</div>
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

<style type="text/css">
body{
    background:#eee;
}

hr {
    margin-top: 20px;
    margin-bottom: 20px;
    border: 0;
    border-top: 1px solid #FFFFFF;
}
a {
    color: #82b440;
    text-decoration: none;
}
.blog-comment::before,
.blog-comment::after,
.blog-comment-form::before,
.blog-comment-form::after{
    content: "";
	display: table;
	clear: both;
}

.blog-comment{
   /* padding-left: 15%;
	padding-right: 15%;*/
}

.blog-comment ul{
	list-style-type: none;
	padding: 0;
}

.blog-comment img{
	opacity: 1;
	filter: Alpha(opacity=100);
	-webkit-border-radius: 4px;
	   -moz-border-radius: 4px;
	  	 -o-border-radius: 4px;
			border-radius: 4px;
}

.blog-comment img.avatar {
	position: relative;
	float: left;
	margin-left: 0;
	margin-top: 0;
	width: 65px;
	height: 65px;
}

.blog-comment .post-comments{
	border: 1px solid #eee;
    margin-bottom: 20px;
    margin-left: 85px;
	margin-right: 0px;
    padding: 10px 20px;
    position: relative;
    -webkit-border-radius: 4px;
       -moz-border-radius: 4px;
       	 -o-border-radius: 4px;
    		border-radius: 4px;
	background: #fff;
	color: #6b6e80;
	position: relative;
}

.blog-comment .meta {
	font-size: 13px;
	color: #aaaaaa;
	padding-bottom: 8px;
	margin-bottom: 10px !important;
	border-bottom: 1px solid #eee;
}

.blog-comment ul.comments ul{
	list-style-type: none;
	padding: 0;
	margin-left: 85px;
}

.blog-comment-form{
	padding-left: 15%;
	padding-right: 15%;
	padding-top: 40px;
}

.blog-comment h3,
.blog-comment-form h3{
	margin-bottom: 40px;
	font-size: 26px;
	line-height: 30px;
	font-weight: 800;
}

.comments ul {
    border-bottom: 0px solid !important;
}
</style>

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
