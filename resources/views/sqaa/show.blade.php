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
	.note-editable	{
		margin-top:50px !important;
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
								<a href="#section-linemove-{{$value['id']}}" class="nav-link active-link" aria-selected="true" data-toggle="tab" data-html="true"
								 data-original-title="{!!$value['description']!!}">
									<span>{{$value['title']}}</span>
									<input type="hidden" value="{{$value['id']}}" name="level_1_id">
								</a>
							</li>
							@endforeach @endif
						</ul>
					</div>


					<form action="{{ route('sqaa_master.store') }}" enctype="multipart/form-data" method="post" id="check_form">
						{{ method_field("POST") }} @csrf
						<div class="row">

							<div class="col-md-9 form-group">

								<div class="tab-content">
									@if(isset($data['level_1'])) @foreach($data['level_1'] as $key=>$value)
									<div class="tab-pane fade called-tab" id="section-linemove-{{$value['id']}}">
										<!-- TABS FOR LEVEL 2 -->
										<div class="row">
											<!-- level 2 -->
											<div class="col-md-4 sel_div_2_container_{{$value['id']}}" id="sel_div_2_container_{{$value['id']}}"></div>
											<!-- level 3 -->
											<div class="col-md-4 sel_div_3_container_{{$value['id']}}" id="sel_div_3_container_{{$value['id']}}"></div>
											<!-- level 4 -->
											<div class="col-md-4 sel_div_4_container_{{$value['id']}}" id="sel_div_4_container_{{$value['id']}}"></div>
										</div>
										<!-- END -->
									</div>
									@endforeach @endif
								</div>
							</div>
							<div class="col-md-3 form-group" style="display:none" id="enter_score">
								<label>Enter Score</label>
								<input type="number" id="mark" name="mark" class="form-control" min="1" max="4" readonly required>
							</div>

						</div>
						<input type="hidden" id="lev_1" name="lev_1">
						<input type="hidden" id="text_1" name="text_1">

						<input type="hidden" id="lev_2" name="lev_2">
						<input type="hidden" id="text_2" name="text_2">

						<input type="hidden" id="lev_3" name="lev_3">
						<input type="hidden" id="text_3" name="text_3">

						<input type="hidden" id="lev_4" name="lev_4">
						<input type="hidden" id="text_4" name="text_4">

						<!-- add new row -->
						<div class="addButtonCheckbox1" data-new="1">
							<div class="row align-items-center">
								<div class="col-md-3">
									<div class="form-group">
										<label for="topicAvailability">Document</label>
										<textarea name="document[]" id="document" rows="3" class="form-control" data-new="1" onchange="chat_gtp(this.value,1)" required></textarea>
									</div>
								</div>

								<div class="col-md-2">
									<div class="form-group">
										<label for="topicAvailability2">Availability</label>
										<select class="cust-select form-control map-value mb-0" name="availability[]" data-new="1" onchange="toggleInput(1)" id="selectavail"
										 required>
											<option value="">Select Availability</option>
											<option value="yes">Yes</option>
											<option value="no">No</option>
											<option value="inprocess">In-Process</option>
										</select>
									</div>
								</div>
								<div class="col-md-2">
									<div class="form-group">
										<label for="topicAvailability2">Files</label>
										<input type="file" class="form-control" name="files[]" data-new="1" accept=".pdf,.xlsx,.doc,.docx" id="fileInput">
									</div>
								</div>

								<div class="col-md-3">
									<div class="form-group">
										<label for="topicAvailability2">Files To be uploaded</label>
										<textarea name="reasons[]" id="reasons" rows="3" class="form-control" data-new="1" readonly></textarea>
										<button class="form-control btn btn-outline-secondary mt-2 w-50" style="font-size:0.8em" id="edit_gen_pdf" data-new="1" onclick="genPdf(1);">Edit & Generate PDF</button>
									</div>
								</div>
								<div class="col-md-2">
									<a href="javascript:void(0);" onclick="addNewRow1();" class="btn btn-success btn-sm mr-2">
										<i class="mdi mdi-plus"></i>
									</a>
								</div>
							</div>
						</div>
						<!-- end row  -->

						<div class="col-md-12 form-group">
							<center>
								<input type="submit" name="submit" value="Save" class="btn btn-success" onclick="check_menu(event)">
							</center>
						</div>
					</form>
				</div>
			</div>
		</div>

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
		@include('includes.footerJs')
		<script src="{{asset('/plugins/bower_components/summernote/dist/summernote.min.js')}}"></script>

		<script>
			function check_menu(){
			    var selected_menu = $('#lev_1').val();
			    if (selected_menu==='') {
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
					// make empty all input
					$('#lev_1').val('');
					$('#lev_2').val('');
					$('#lev_3').val('');
					$('#lev_4').val('');
					$('#text_1').val('');
					$('#text_2').val('');
					$('#text_3').val('');
					$('#text_4').val('');
					$('#enter_score').hide('');
					$('#document').val('');
					$('#selectavail').val('');
					$('#fileInput').val('');
					$('#reasons').val('');
					// Remove all <div> elements with class "my-div" where data-new is not equal to 1
					$('div.addButtonCheckbox1').not('[data-new="1"]').empty();
					var targetTab = $(this).attr('href');
					$(targetTab).addClass('show active');
					// get level 2 data 
					var level_2 = $(this).find('input').val();
					var inputElement = document.getElementById("lev_1");
					inputElement.value = level_2;
					$('#sel_div_2_container_' + level_2).hide();
					$('#sel_div_3_container_' + level_2).hide();
					$('#sel_div_4_container_' + level_2).hide();
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
							var sel_level_3_container = $('#sel_div_2_container_' + level_2);
							var sel_level_3 = $('#sel_level_3');
							if (sel_level_3_container.length === 0) {
								sel_level_3_container = $('<div class="col-md-4 sel_div_2_container_' + level_2 +
									'" id="sel_div_2_container_' + level_2 + '"></div>');
								$('#sel_div_2').after(sel_level_3_container);
							}
							sel_level_3_container.empty();
							// Create a label for sel_level_3
							var sel_level_3_label = $('<label for="sel_level_2">Select option </label>');
							sel_level_3 = $('<select id="sel_level_2" class="form-control" name="sel_level_2"></select>');
							var defaultOption = '<option value="">--Select--</option>';
							sel_level_3.append(defaultOption);
							if (Array.isArray(data)) {
								$('#sel_div_2_container_' + level_2).show();
								data.forEach(function(value) {
									var option = '<option value="' + value.id + '"  data-description="' + value.description +
										'"  data-description="' + value.description + '">' + value.title + '</option>';
									sel_level_3.append(option);
								});
							} else {
								console.error('data is not an array');
							}
							sel_level_3_container.append(sel_level_3_label);
							sel_level_3_container.append(sel_level_3);
						},
						error: function(error) {
							console.error(error);
						}
					});
				});

				// sel 2
				$(document).on('change', '#sel_level_2', function() {
					var selectedValue = $(this).val();
					var lev_1_value = document.getElementById("lev_1").value;
					fetchDataForLevel3(selectedValue, lev_1_value);
					var inputElement = document.getElementById("lev_2");
					inputElement.value = selectedValue;
					var selectedText = $(this).find('option:selected').text();
					$('#text_2').val(selectedText);
					$('.sel_level_4').hide();
					$('#enter_score').hide();
					//model
					$('#descriptionPlaceholder').empty();
					var description = $(this).find('option:selected').data('description');
					// console.log(description);
					$('#descriptionPlaceholder').append(description);
					if (description !== null && description != '') {
						$('#descriptionModal').modal('show');
					}
				});

				function fetchDataForLevel3(selectedValue, lev_1_value) {
					$.ajax({
						type: 'GET',
						url: '/get-level',
						data: {
							level_3: selectedValue
						},
						success: function(data) {
							$('#sel_div_3_container_' + lev_1_value).show();
							var sel_level_3_container = $('#sel_div_3_container_' + lev_1_value);
							var sel_level_3 = $('#sel_level_3');

							if (sel_level_3_container.length === 0) {
								sel_level_3_container = $('<div class="col-md-4 sel_div_3_container_' + lev_1_value +
									'" id="sel_div_3_container_' + lev_1_value + '"></div>');
								$('#sel_div_2').after(sel_level_3_container);
							}
							sel_level_3_container.empty();
							var sel_level_3_label = $('<label for="sel_level_3">Select option </label>');
							sel_level_3 = $('<select id="sel_level_3" class="form-control" name="sel_level_3"></select>'); // Create sel_level_3
							var defaultOption = '<option value="">--Select--</option>';
							sel_level_3.append(defaultOption);
							if (Array.isArray(data)) {
								data.forEach(function(value) {
									var option = '<option value="' + value.id + '"  data-description="' + value.description + '">' + value.title +
										'</option>';
									sel_level_3.append(option);
								});
							} else {
								console.error('data is not an array');
							}
							sel_level_3_container.append(sel_level_3_label);
							sel_level_3_container.append(sel_level_3);
						},
						error: function(error) {
							console.error(error);
						}
					});
				}

				// sel 3 
				$(document).on('change', '#sel_level_3', function() {
					var selectedValue = $(this).val();
					var lev_1_value = document.getElementById("lev_1").value;
					fetchDataForLevel4(selectedValue, lev_1_value);
					var inputElement = document.getElementById("lev_3");
					inputElement.value = selectedValue;
					var selectedText = $(this).find('option:selected').text();
					$('#text_3').val(selectedText);
					$('#descriptionPlaceholder').empty();
					var description = $(this).find('option:selected').data('description');
					$('#descriptionPlaceholder').append(description);
					if (description !== null && description != '') {
						$('#descriptionModal').modal('show');
					}
				});

				function fetchDataForLevel4(selectedValue, lev_1_value) {
					$.ajax({
						type: 'GET',
						url: '/get-level',
						data: {
							level_4: selectedValue
						},
						success: function(data) {
							$('#sel_div_4_container_' + lev_1_value).show();

							var sel_level_3_container = $('#sel_div_4_container_' + lev_1_value);
							var sel_level_3 = $('#sel_level_4');

							if (sel_level_3_container.length === 0) {
								sel_level_3_container = $('<div class="col-md-4 sel_div_4_container_' + lev_1_value +
									'" id="sel_div_4_container_' + lev_1_value + '"></div>');
								$('#sel_div_3').after(sel_level_3_container);
							}
							sel_level_3_container.empty();
							var sel_level_3_label = $('<label for="sel_level_4" class="sel_level_4">Select option </label>');
							sel_level_3 = $('<select id="sel_level_4" class="form-control sel_level_4" name="sel_level_4"></select>');
							var defaultOption = '<option value="">--Select--</option>';
							sel_level_3.append(defaultOption);
							var i = 1;
							if (Array.isArray(data)) {
								data.forEach(function(value) {
									var option = '<option value="' + value.id + '"  data-description="' + value.description + '" data-new=' +
										(i++) + '>' + value.title + '</option>';
									sel_level_3.append(option);
								});
							} else {
								console.error('data is not an array');
							}
							sel_level_3_container.append(sel_level_3_label);
							sel_level_3_container.append(sel_level_3);
						},
						error: function(error) {
							console.error(error);
						}
					});
				}
				// sel 4 
				$(document).on('change', '#sel_level_4', function() {
					$('#enter_score').show();
					var selectedValue = $(this).val();
					var inputElement = document.getElementById("lev_4");
					inputElement.value = selectedValue;
					var selectedText = $(this).find('option:selected').text();
					$('#text_4').val(selectedText);
					$('#descriptionPlaceholder').empty();

					var description = $(this).find('option:selected').data('description');
					$('#descriptionPlaceholder').append(description);
					if (description !== null && description != '') {
						$('#descriptionModal').modal('show');
					}
					var enter_score = $(this).find('option:selected').data('new');
					if (enter_score <= 5) {
						$('#mark').val(enter_score);
					}
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

		</script>
		@include('includes.footer')