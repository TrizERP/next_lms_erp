@include('includes.headcss') @include('includes.header') @include('includes.sideNavigation')
<link href="{{ asset('/plugins/bower_components/summernote/dist/summernote.css') }}" rel="stylesheet" />
<style>
	.popover {
		max-width: 700px;
	}
</style>
<div id="page-wrapper">
	<div class="container-fluid">
		<div class="row bg-title">
			<div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
				<h4 class="page-title">Generate PDF to Upload Document</h4>
			</div>
		</div>

		<div class="card">
			@if ($sessionData = Session::get('data')) @if($sessionData['status_code'] == 1)
			<div class="alert alert-success alert-block">
				@else
				<div class="alert alert-danger alert-block">
					@endif
					<button type="button" class="close" data-dismiss="alert">×</button>
					<strong>{{ $sessionData['message'] }}</strong>
				</div>
				@endif
				<form class="row" action="{{route('download-pdf')}}" enctype="multpart/form-data" method="post">
					@csrf
					<textarea name="hidden_input" cols="2" rows="2" id="hidden_input" style="display:none">{{$_REQUEST['text']}}</textarea>
					<div class="col-md-12 form-group">
						<label>HTML Content</label>
						<textarea class="summernote" id="html_content" name="html_content" required>
                            @if(isset($data['text']))
                            {!! nl2br(e(html_entity_decode($data['text']))) !!}
                            @endif
                        </textarea>
					</div>
					<div class="col-md-12 form-group">
						<div class="col-md-12 form-group">
							<center>
								<input type="submit" class="btn btn-success" id="generate-pdf" value="Generate PDF"> @if(isset($_REQUEST['path']))
								<a href="{{ $_REQUEST['path'] }}" class="btn btn-danger" download>Download</a>
								@endif
							</center>
						</div>
					</div>
				</form>

			</div>
		</div>
	</div>
</div>

@include('includes.footerJs')
<script src="{{asset('/plugins/bower_components/summernote/dist/summernote.min.js')}}"></script>
<script>
	@if(isset($_REQUEST['path']))
	var val = "{{$_REQUEST['path']}}";
	setTimeout(function() {
		$.ajax({
			type: 'POST',
			url: '/unlink-file',
			data: {
				file: val
			},
			success: function(data) {
				console.log('pass ho gaya');
			}
		});
		window.location.reload();
		if (window.location.href.indexOf('&path') !== -1) {
			const newUrl = window.location.href.split('&path')[0];
			window.location.href = newUrl;
		}
	}, 30000);
	@endif
</script>
<script>
	$(document).ready(function() {
		window.
		$('.summernote').summernote({
			height: 'auto', // Set the height to 'auto'
			minHeight: null,
			maxHeight: null,
			focus: false
		});

		$('[data-toggle="popover"]').popover({
			title: "",
			html: true
		});

		$('[data-toggle="popover"]').on('click', function(e) {
			$('[data-toggle="popover"]').not(this).popover('hide');

		});
	});
</script>
@include('includes.footer')