@include('includes.headcss')
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.9.3/html2pdf.bundle.min.js"></script>

<link href="{{ asset('/plugins/bower_components/summernote/dist/summernote.css') }}" rel="stylesheet" /> @include('includes.header')
<script src="{{asset('/admin_dep/js/sqaa.js')}}"></script>
@include('includes.sideNavigation')
<style>
	.custom-tooltip,
	.tooltip-inner {
		max-width: 600px;
		background-color: #ddd;
		color: #303030;
		text-align: justify;
	}

	.note-editable {
		margin-top: 50px !important;
	}

	.note-children-containe,
	.note-image-popover,
	.note-table-popover,
	.note-popover.popover,
	.note-popover .popover-content {
		display: none !important;
	}
</style>
<div id="page-wrapper">
	<div class="container-fluid">
		<div class="row bg-title">
			<div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
				<h4 class="page-title">SQAA Master</h4>
			</div>
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

			<div class="row">
				<div class="col-lg-12 col-sm-12 col-xs-12">
					<div class="sttabs tabs-style-linemove triz-verTab bg-white style2">
						<ul class="nav nav-tabs tab-title mb-4">
							@php $i=0; @endphp @if(isset($data['level_1'])) @foreach($data['level_1'] as $key=>$value)
							<li class="nav-item">
								<a href="#section-linemove-{{$value['id']}}" class="nav-link active-link @if(isset($data['selected_1']) && $data['selected_1']==$value['id']) active @endif"
								 aria-selected="true" data-toggle="tab" data-html="true" data-original-title="{!!$value['description']!!}">
									<span>{{$value['title']}}</span>
									<input type="hidden" value="{{$value['id']}}" name="level_1_id">
								</a>
							</li>
							@endforeach @endif
						</ul>
					</div>

					<div class="row">
						<div class="col-md-9 form-group">
							<div class="tab-content">
								@if(isset($data['level_1'])) @foreach($data['level_1'] as $key=>$value)
								<div class="tab-pane fade called-tab" id="section-linemove-{{$value['id']}}">
									<form action="{{route('sqaa_master.create')}}" method="get" enctype="multiple/form-data">
										@csrf
										<!-- TABS FOR LEVEL 2 -->
										<div class="row">
											<!-- level 2 -->
											<div class="col-md-4">
												<input type="hidden" name="tabs_id" id="tabs_id" @if(isset($data[ 'selected_1'])) value="{{$data['selected_1']}}" @endif>
												<label for="">Select Levels</label>
												<select name="sel_level_1" id="sel_level_1_{{$value['id']}}" class="form-control" onchange="get_level_2(this.value,'#sel_level_2_{{$value['id']}}','level_3');">
													@if(isset($data['level_2_val']))
													<option> Select Value</option>
													 @foreach($data['level_2_val'] as $key => $value_2)
													<option value="{{$value_2['id']}}" @if(isset($data[ 'selected_2']) && $data[ 'selected_2']==$value_2[ 'id']) selected @endif>{{$value_2['title']}}</option>
													@endforeach @endif
												</select>
											</div>
											<!-- level 3 -->
											<div class="col-md-4">
												<label for="">Select Levels</label>
												<select name="sel_level_2" id="sel_level_2_{{$value['id']}}" class="form-control" onchange="get_level_2(this.value,'#sel_level_3_{{$value['id']}}','level_4');">
													@if(isset($data['level_3_val']))
													<option> Select Value</option>
													 @foreach($data['level_3_val'] as $key => $value_2)
													<option value="{{$value_2['id']}}" @if(isset($data[ 'selected_3']) && $data[ 'selected_3']==$value_2[ 'id']) selected @endif>{{$value_2['title']}}</option>
													@endforeach @endif
												</select>
											</div>
											<!-- level 4 -->
											<div class="col-md-4">
												<label for="">Select Levels</label>
												<select name="sel_level_3" id="sel_level_3_{{$value['id']}}" class="form-control" onchange="get_mark(this.value,'#sel_level_3_{{$value['id']}}');">
													@if(isset($data['level_4_val']))
													<option> Select Value</option>
													 @foreach($data['level_4_val'] as $key => $value_2)
													<option value="{{$value_2['id']}}" data-new="{{$i++}}" @if(isset($data[ 'selected_4']) && $data[ 'selected_4']==$value_2[
													 'id']) selected @endif>{{$value_2['title']}}</option>
													@endforeach @endif
												</select>
											</div>
										</div>
										<!-- END -->
								</div>
								@endforeach @endif
							</div>
						</div>
						@php $style = $style_m ='display:none';
						 $val=''; 
						 if(isset($data['selected_1'])){ $style="display:block"; } 
						if(isset($data['mark']) && $data['mark']!='' && $data['mark']!=null){
						$style_m="display:block";
						$val = 'value='.$data['mark'] ?? 0 .''; 
						} @endphp
						<div class="col-md-3 form-group" style="{{$style_m}}" id="enter_score">
							<label>Level Score</label>
							<input type="number" id="mark" name="mark" {{ $val }} class="form-control" min="1" max="4" readonly required>
						</div>

					</div>
					<div class="col-md-3 form-group" style="{{$style}}" id="save_button">
						<input type="submit" id="save_button" value="Search" class="btn btn-success">
					</div>
					
					<input type="hidden" id="level_1" name="level_1" @if(isset($data[ 'level_1_1'])) value="{{$data['level_1_1']}}" @endif>
					<input type="hidden" id="text_1" name="text_1" @if(isset($data[ 'text_1'])) value="{{$data['text_1']}}" @endif>

					<input type="hidden" id="level_2" name="level_2" @if(isset($data[ 'level_2'])) value="{{$data['level_2']}}" @endif>
					<input type="hidden" id="text_2" name="text_2" @if(isset($data[ 'text_2'])) value="{{$data['text_2']}}" @endif>

					<input type="hidden" id="level_3" name="level_3" @if(isset($data[ 'level_3'])) value="{{$data['level_3']}}" @endif>
					<input type="hidden" id="text_3" name="text_3" @if(isset($data[ 'text_3'])) value="{{$data['text_3']}}" @endif>

					<input type="hidden" id="level_4" name="level_4" @if(isset($data[ 'level_4'])) value="{{$data['level_4']}}" @endif>
					<input type="hidden" id="text_4" name="text_4" @if(isset($data[ 'text_4'])) value="{{$data['text_4']}}" @endif>

					</form>

					<form action="{{route('sqaa_master.store')}}" method="post" enctype="multiple/form-data">
					@csrf
					<!-- add new row -->
					<div class="addButtonCheckbox1" data-new="1">
						<!-- insert or update document  -->
						@if(isset($data['document']))

						
							<input type="hidden" id="mark" name="mark" {{ $val }} class="form-control" min="1" max="4">
						</div>

						<input type="hidden" id="lev_1" name="lev_1" @if(isset($data[ 'level_1_1'])) value="{{$data['level_1_1']}}" @endif>
						
					<input type="hidden" id="text_1" name="text_1" @if(isset($data[ 'text_1'])) value="{{$data['text_1']}}" @endif>

					<input type="hidden" id="lev_2" name="lev_2" @if(isset($data[ 'level_2'])) value="{{$data['level_2']}}" @endif>
					<input type="hidden" id="text_2" name="text_2" @if(isset($data[ 'text_2'])) value="{{$data['text_2']}}" @endif>

					<input type="hidden" id="lev_3" name="lev_3" @if(isset($data[ 'level_3'])) value="{{$data['level_3']}}" @endif>
					<input type="hidden" id="text_3" name="text_3" @if(isset($data[ 'text_3'])) value="{{$data['text_3']}}" @endif>

					<input type="hidden" id="lev_4" name="lev_4" @if(isset($data[ 'level_4'])) value="{{$data['level_4']}}" @endif>
					<input type="hidden" id="text_4" name="text_4" @if(isset($data[ 'text_4'])) value="{{$data['text_4']}}" @endif>
						@php $j=1; @endphp
						 @foreach($data['document'] as $key => $document)
						 <input type="hidden" id="doc_id" name="doc_id[]" value="{{$document->document_id}}">
						 
						<div class="row align-items-center">
							<input type="hidden" name="menu_id" id="menu_id" value="{{$document->menu_id}}">
							<input type="hidden" name="menu_id" id="menu_id" value="{{$data['mark'] ?? 0}}">							
							<div class="col-md-3">
								<div class="form-group">
									<label for="topicAvailability">Document</label>
									<textarea name="document[]" id="document" rows="3" class="form-control" data-new="{{$j}}" onchange="chat_gtp(this.value,1)">{{$document->title}}</textarea>
								</div>
							</div>

							<div class="col-md-2">
								<div class="form-group">
									<label for="topicAvailability2">Availability</label>
									<select class="cust-select form-control map-value mb-0" name="availability[]" data-new="{{$j}}" onchange="chat_gtp('{{$document->title}}','{{$j}}')" id="selectavail">
										<option value="">Select Availability</option>
										<option value="yes" @if($document->availability=="yes") selected @endif>Yes</option>
										<option value="no" @if($document->availability=="no") selected @endif>No</option>
										<option value="inprocess"  @if($document->availability=="inprocess") selected @endif>In-Process</option>
									</select>
								</div>
							</div>
							<div class="col-md-2">
								<div class="form-group">
									<label for="topicAvailability2">Files</label>
									<input type="file" class="form-control" name="files[]" data-new="{{$j}}" accept=".pdf,.xlsx,.doc,.docx" >
									@if (!empty($document->file) && $document->file !=" ")
									<input type="hidden" name="update_file[]" id="" value="{{$document->file}}">
										<a href="https://s3-triz.fra1.digitaloceanspaces.com/public/sqaa/{{$document->file}}" target="_blank">View</a>
										@else N/A @endif
								</div>
							</div>

							<div class="col-md-3">
								<div class="form-group">
									<label for="topicAvailability2">Files To be uploaded</label>
									<textarea name="reasons[]" id="reasons" rows="3" class="form-control" data-new="{{$j}}" readonly></textarea>
									<a class="form-control btn btn-outline-secondary mt-2 w-50" style="font-size:0.8em" id="edit_gen_pdf" data-new="{{$j}}" onclick="genPdf({{$j}});">Edit & Generate PDF</a>
								</div>
							</div>
						
						</div>
						<!-- end row  -->
						@php $j++ @endphp
						@endforeach

						<div class="col-md-12 form-group">
							<center>
								<input type="submit" name="submit" value="Save" class="btn btn-success" onclick="check_menu(event)">
							</center>
						</div>
						@endif

					</div>
				</div>
			</div>
			</form>


		</div>


		<!-- for description  -->
		<div class="modal fade" id="descriptionModal" tabindex="-1" role="dialog" aria-labelledby="descriptionModalLabel" aria-hidden="true">
			<div class="modal-dialog" role="document">
				<div class="modal-content">
					<div class="modal-header">
						<h5 class="modal-title" id="descriptionModalLabel">Description</h5>
						<button type="button" class="close" data-dismiss="modal" aria-label="Close">
							<span aria-hidden="true">&times;</span>
						</button>
					</div>
					<div class="modal-body">
						<div id="descriptionPlaceholder">Description content will appear here.</div>
					</div>
					<div class="modal-footer">
						<button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
					</div>
				</div>
			</div>
		</div>

		<!-- for pdf  -->
		<!-- Add your modal HTML -->
		<div class="modal fade" id="generatePdf" tabindex="-1" role="dialog" aria-labelledby="generatePdfLabel" aria-hidden="true">
			<div class="modal-dialog" role="document" style="max-width: 90% !important">
				<div class="modal-content">
					<div class="modal-header">
						<h5 class="modal-title" id="generatePdfLabel">Generate PDF</h5>
						<button type="button" class="close" data-dismiss="modal" aria-label="Close">
							<span aria-hidden="true">&times;</span>
						</button>
					</div>
					<div class="modal-body">
						<div class="col-md-12 form-group">
							<label>PDF Content</label>
							<textarea class="summernote" id="html_content" name="html_content" required></textarea>
							<input type="hidden" id="menu_id_pdf" name="menu_id_pdf">
							<input type="hidden" id="doc_id_pdf" name="doc_id_pdf">
						</div>
						<div class="modal-footer">
							<button type="button" class="btn btn-primary" onclick="generate_pdf1();">Generate PDF</button>

							<button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
						</div>
					</div>
				</div>
			</div>
		</div>
		<script>
			$(document).ready(function() {
				@if(isset($data['selected_1']))
				var selectedTabId = "section-linemove-{{ $data['selected_1'] }}";
				$('#' + selectedTabId).addClass('show active');
				$('a[href="#' + selectedTabId + '"]').tab('show');
				@endif
			});
		</script>

		@include('includes.footerJs')
		<script src="{{asset('/plugins/bower_components/summernote/dist/summernote.min.js')}}"></script>

		<script>
			function check_menu() {
				var selected_menu = $('#level_1').val();
				if (selected_menu === '') {
					alert('Please Select Options');
					event.preventDefault(); // Prevent the default form submission
				}
			}

			$(document).ready(function() {

				$('.active-link').on('click', function(e) {
					//active tabs
					$('.nav-link').tooltip('dispose');
					// Add tooltip to the active tab
					$(this).tooltip({
						template: '<div class="tooltip custom-tooltip" role="tooltip"><div class="arrow"></div><div class="tooltip-inner"></div></div>'
					});
					// Show the tooltip
					$(this).tooltip('show');
					e.preventDefault();
					$('.active-link').removeClass('active');
					$(this).addClass('active');
					$('.called-tab').removeClass('show active');

					// Remove all <div> elements with class "my-div" where data-new is not equal to 1
					$('div.addButtonCheckbox1').not('[data-new="1"]').empty();
					var targetTab = $(this).attr('href');
					$(targetTab).addClass('show active');
					// get level 2 data 
					var level_2 = $(this).find('input').val();
					var inputElement = document.getElementById("level_1");
					inputElement.value = level_2;
					$('input[name="tabs_id"]').val(level_2);
					$('#lev_1').val('');
					$('#lev_2').val('');
					$('#lev_3').val('');
					$('#lev_4').val('');
					$('#text_1').val('');
					$('#text_2').val('');
					$('#text_3').val('');
					$('#text_4').val('');

					$('#sel_level_1_' + level_2).empty();
					$('#sel_level_2_' + level_2).empty();
					$('#sel_level_3_' + level_2).empty();
					$('#enter_score').hide();
					var text_1 = $('.active-link.active span').text();

					var text_1 = $('.active-link.active span').text();
					var inputElement_txet = document.getElementById("text_1");
					inputElement_txet.value = text_1;
					$.ajax({
						type: 'GET',
						url: '/get-level',
						data: {
							level_2: level_2
						},
						success: function(data) {
							// new code start
							$('#sel_level_1_' + level_2).empty();
							// Append the "Select Level" option
							$('#sel_level_1_' + level_2).append('<option value="">Select Level</option>');

							if (Array.isArray(data)) {
								data.forEach(function(value) {
									var option = '<option value="' + value.id + '"  data-description="' + value.description +
										'"  data-description="' + value.description + '">' + value.title + '</option>';
									$('#sel_level_1_' + level_2).append(option);
								});
								$("#save_button").show();
							}
							// new code end
						},
						error: function(error) {
							console.error(error);
						}
					});
				});

				// summer notes 
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

			function get_level_2(val, sel_element, level) {
				if(level=="level_3"){
					$('#level_2').val(val);
					var selectedText = $('#sel_level_1_1').find('option:selected').text();
					$('#text_2').val(selectedText);
				}
				if(level=="level_4"){
					$('#level_3').val(val);		
					var selectedText = $('#sel_level_2_1').find('option:selected').text();
					$('#text_3').val(selectedText);			
				}
				var dataObject = {};
				dataObject[level] = val;
				$(sel_element).empty();
		
				$.ajax({
					type: 'GET',
					url: '/get-level',
					data: dataObject,
					success: function(data) {

						// Append the "Select Level" option
						$(sel_element).append('<option value="">Select Level</option>');
						var i = 1;
						if (Array.isArray(data)) {
							data.forEach(function(value) {
								var option = '<option value="' + value.id + '" data-new="' + (i++) + '" data-description="' + value.description +
									'" >' + value.title + '</option>';
								$(sel_element).append(option); // Use sel_element as the selector
							});
						}
					},
					error: function(error) {
						console.error(error);
					}
				});
			}

			function get_mark(val,sel_element) {
				$('#level_4').val(val);
				var selectedText = $(sel_element).find('option:selected').text();
					$('#text_4').val(selectedText);

				$('#enter_score').show();
				var enter_score = $(sel_element).find('option:selected').data('new');
				if (enter_score <= 4) {
					$('#mark').val(enter_score);
				}
				$(sel_element).val(val);
			}
		</script>
		@include('includes.footer')