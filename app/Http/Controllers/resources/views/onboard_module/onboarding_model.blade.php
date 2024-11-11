<!-- Modal -->
<div class="modal fade" id="exampleModal_all" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header" style="align-items:center;padding:8px !important">
				<h3 class="modal-title fs-5 fcolor fees_step_title fcolor" id="allTitle"></h3>
				<button type="button" class="btn-close border-0" data-bs-dismiss="modal" aria-label="Close">X</button>
			</div>

			<div class="modal-body">
				<div id="step_1">

					<div class="row">
						<div class="col-md-4">
							<h4>
								<b>Modules</b>
							</h4>
						</div>
						<div class="col-md-4">
							<h4>
								<b>Deatils</b>
							</h4>
						</div>
						<div class="col-md-4">
							<h4>
								<b>Status</b>
							</h4>
						</div>
					</div>
					<div class="row" id="all_model">

					</div>
					<div class="col-md-12 form-group mb-0">
						<center>
							<button class="btn btn-primary move_step_2">Continue</button>
						</center>
					</div>
				</div>
			</div>
			<div id="step_2">
				<div class="col-md-12">
					<div class="row" id="roles">


					</div>
				</div>

				<div class="col-md-12 form-group mb-0">
					<center>
						<button class="btn btn-primary finish">Finish</button>
						<button class="btn btn-warning back_1">Back</button>
					</center>
				</div>

			</div>
		</div>
	</div>

</div>
</div>

<!-- endofmodal -->
<style>
	.modal-dialog {
		max-width: 1050px !important;
	}
</style>
<script>
	$(document).ready(function() {
		$('#fees_step_2').hide();
		$('#fees_step_3').hide();
		$('#fees_step_4').hide();
		$('#step_2').hide();

		$('.fees_step_title').append('<b>Step 1 : Check Account Details</b>');
		// Collapse toggle
		$('.fees-modulde-2').click(function() {
			$('.fees_step_title').empty();
			$('#fees_step_2').show();
			$('#fees_step_1').hide();
			$('.fees_step_title').append('<b>Step 2 : Fees Setup </b>');
		});

		$('.fees-modulde-3').click(function() {
			$('.fees_step_title').empty();
			$('#fees_step_3').show();
			$('#fees_step_2').hide();
			$('#fees_step_1').hide();
			$('.fees_step_title').append('<b>Step 3 : Import Data </b>');
		});

		$('.fees-modulde-4').click(function() {
			$('.fees_step_title').empty();
			$('#fees_step_4').show();
			$('#fees_step_3').hide();
			$('#fees_step_2').hide();
			$('#fees_step_1').hide();
			$('.fees_step_title').append('<b>Step 4 : Roles & Responsibilites </b>');
		});

		$('.fees_back').click(function() {
			$('#fees_step_2').hide();
			$('#fees_step_1').show();
			$('.fees_step_title').empty();
			$('.fees_step_title').append('<b>Step 1 : Check Account Details</b>');
		});

		$('.fees_back2').click(function() {
			$('#fees_step_3').hide();
			$('#fees_step_2').show();
			$('.fees_step_title').empty();
			$('.fees_step_title').append('<b>Step 2 : Fees Setup</b>');
		});
		$('.fees_back3').click(function() {
			$('#fees_step_4').hide();
			$('#fees_step_3').show();
			$('.fees_step_title').empty();
			$('.fees_step_title').append('<b>Step 3 : Import Data </b>');
		});

		$('.finish').click(function() {
			window.location.reload();
		});
	});

	function get_roles_respo_fees(profile_name, modules) {
		$('#roles_respo').empty();
		var getRoles = @json($data['roles'] ?? []);

		var html = '';
		if(getRoles){
		html +=
			`<h5>${profile_name} Roles and Responsibility</h5>
					${getRoles[modules][profile_name]['text']}
					`;

		$('#roles_respo').html(html);
		}
	}

	function get_roles_respo(profile_name, modules) {
		$('#roles_respo2').empty();
		var html = '';
		var getRoles = @json($data['roles'] ?? []);
		if(getRoles){
		html +=
			`<h5>${profile_name} Roles and Responsibility</h5>
					${getRoles[modules][profile_name]['text']}
					`;
		$('#roles_respo2').html(html);
		}
	}
	// 22-05-24
	
</script>