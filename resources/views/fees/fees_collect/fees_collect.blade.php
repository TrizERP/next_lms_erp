@include('includes.headcss') @include('includes.header') @include('includes.sideNavigation')

<style>
	tr.spaceUnder>th {
		padding-bottom: 1em !important;
	}
	#overlay-new {
		position: fixed;
		top: 0;
		left: 0;
		width: 100%;
		height: 100%;
		background-color: rgba(0, 0, 0, 0.5); /* Adjust the opacity as needed */
		z-index: 9999;
	}

	#overlay-new center {
	position: absolute;
	top: 30%;
	left: 50%;
	transform: translate(-50%, -50%);
	}

</style>
<div id="page-wrapper" style="color:#000;">
	<div class="container-fluid">
	
		<div class="row bg-title">
			<div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
				<h4 class="page-title">Fees Collect</h4>
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
					<div class="col-md-4 col-lg-4 col-sm-4 col-xs-4">
						<div class="box-title">
							<label>Fees Structure</label>
						</div>
						<div class="table-responsive">
							<table class="table table-stripped" style="color:#000 !important;">
								<tr>
									<th>Month</th>
									<th>Fees</th>
									<th>Paid</th>
									<th>Remaining</th>
								</tr>
								@php
                                        $remainFees = 0;
                                        $feesDetails= [];
                                        $bk=$paid=$remain =array();
                                        foreach ($data['total_fees'] as $id => $arr) {
										$feesDetails[$arr['month']] = $arr['remain'];
										if(isset($arr['bk'])){
                                @endphp
								<tr>
									<td>
										{{ $arr['month'] }}
									</td>
									<td>
										@php $bk[] = $arr['bk']; echo $arr['bk'];  @endphp
									</td>
									<td>
										@php $paid[] = $arr['paid']; echo $arr['paid']; @endphp
									</td>
									<td>
										@php $remain[] = $arr['remain'];echo $arr['remain'];  @endphp
									</td>
								</tr>
								@php
                                    }
                                        $remainFees += $arr['remain'];
                                        } 
								@endphp
								<tr>
									<td>Total</td>
									<td>
										{{ array_sum($bk) }}
									</td>
									<td>
										{{array_sum($paid) }}
									</td>
									<td>
										{{array_sum($remain) }}
									</td>
								</tr>

							</table>
						</div>
						<div class="row">
							<div class="col-md-12 text-center mt-4">
								<button type="button" class="btn btn-info" data-toggle="modal" id="add_data" onclick="javascript:add_data({{ $data['stu_data']['enrollment']}},{{$data['stu_data']['student_id']}});">
									Paid History
								</button>
							</div>
						</div>
					</div>

					<div class="col-md-8 col-lg-8 col-sm-8 col-xs-8">
						<div class="box-title">
							<label>Fees Collection</label>
						</div>
						<div class="row">
							<div class="col-md-6">
								<div class="table-responsive">
									<table class="table table-stripped">
										<tr>
											<td>Unique Id/Adm.No.</td>
											<td>
												{{ $data['stu_data']['uniqueid']; }}
											</td>
										</tr>
										<tr>
										<tr>
											<td>{{ App\Helpers\get_string('studentname','request')}}<span id="menuId" style="display:none"></span><a href="{{route('norm-clature.create')}}"><i class="mdi mdi-lead-pencil"></i></a></td>
											<td>
												{{ $data['stu_data']['name']; }}
											</td>
										</tr>
										<tr>
											<td>Admission Year</td>
											<td>
												{{ $data['stu_data']['admission']; }}
											</td>
										</tr>
										<tr>
											<td>Parent Email</td>
											<td>
												{{ $data['stu_data']['email']; }}
											</td>
										</tr>
										<tr>
											<td>Student Quota<span id="menuId" style="display:none"></span><a href="{{route('norm-clature.create')}}"><i class="mdi mdi-lead-pencil"></i></a></td>
											<td>
												{{ $data['stu_data']['student_quota']; }}
											</td>
										</tr>
									</table>
								</div>
							</div>
							<div class="col-md-6">
								<div class="table-responsive">
									<table class="table table-stripped">
										<tr>
											<td>GR. No</td>
											<td>
												{{ $data['stu_data']['enrollment']; }}
											</td>
										</tr>
										<tr>
											<td>Std/Div</td>
											<td>
												{{ $data['stu_data']['stddiv']; }}
											</td>
										</tr>
										<tr>
											<td>Contact No</td>
											<td>
												{{ $data['stu_data']['mobile']; }}
											</td>
										</tr>
										<tr style="color: red;">
											<td>Pending Fees</td>
											<td>
												{{ $data['stu_data']['pending']; }}
											</td>
										</tr>
										@if (Session::get('sub_institute_id') == '181')
										<tr>
											<td>Previous Year Imprest Balance</td>
											<td>
												{{ $data['stu_data']['previous_year_imprest_balance']; }}
											</td>
										</tr>
										@endif
									</table>
								</div>
							</div>
						</div>

						<form action="{{ route('fees_collect.store') }}" enctype="multipart/form-data" method="post" id="myform">
							@csrf
							<input type="hidden" name="grade_id" value="{{ $data['stu_data']['grade_id']; }}">
							<input type="hidden" name="standard_id" value="{{ $data['stu_data']['std_id']; }}">
							<input type="hidden" name="div_id" value="{{ $data['stu_data']['div_id']; }}">
							<input type="hidden" name="student_id" value="{{ $data['stu_data']['student_id']; }}">
							<input type="hidden" name="std_div" value="{{ $data['stu_data']['stddiv']; }}">
							<input type="hidden" name="full_name" value="{{ $data['stu_data']['name']; }}">
							<input type="hidden" name="mobile" value="{{ $data['stu_data']['mobile']; }}">
							<input type="hidden" name="uniqueid" value="{{ $data['stu_data']['uniqueid']; }}">
							<input type="hidden" name="enrollment" value="{{ $data['stu_data']['enrollment']; }}">
							<input type="hidden" name="roll_no" value="{{ $data['stu_data']['roll_no']; }}">
							<input type="hidden" name="medium" value="{{ $data['stu_data']['medium']; }}">
							<input type="hidden" name="father_name" value="{{ $data['stu_data']['father_name']; }}">
							<input type="hidden" name="mother_name" value="{{ $data['stu_data']['mother_name']; }}">

							<div class="table-responsive col-md-12" style="border-top: 2px solid black;">
								<table class="table table-stripped">
									<tr>
										@php
                                                $i = 1;
                                                foreach ($data['month_arr'] as $id => $val) {
                                                if ($i == 1) {
                                                    echo "<tr>";
                                                }
                                                $slected = "";
                                                list($month, $year) = explode('/', $val);
                                                $monthDate = $year . '-' . date('m', strtotime($month)) . '-01';
                                                $date_now = time();
                                                if (in_array($id, $data['search_ids']) && $date_now >= strtotime($monthDate)) {
                                                    $slected = "checked";
                                                }

                                                $disabled = '';
                                                if (isset($feesDetails[$val]) && $feesDetails[$val] == 0) {
                                                    $disabled = 'disabled="disabled"';
                                                }
                                        @endphp
										<td>
											<div class="checkbox checkbox-info">
												<input id= "{{$id}}" name="months[{{$id}}]"
												 value="{{$id}}" {{$slected}} class="months" type="checkbox" {{ $disabled}}>
												<!-- removed name attribute -->
												@if( isset($feesDetails[$val]) && $feesDetails[$val] == 0 )
												<input type="hidden" value="{{$id}}"> @endif
												<label for="{{ $id}}">
													{{ $val}}
												</label>
											</div>
										</td>
										@php
                                                if ($i == 6) {
                                                    echo "</tr>";
                                                }
                                                $i++;
                                                }
										@endphp
									</tr>
								</table>
							</div>
							<div class="table-responsive col-md-12">
								<table class="table table-stripped" border="0" width="100%">
									<!-- <tr>
                                                <th colspan="2" style="width: 40%">Particular</th>
                                                <th style="width: 20%">Amount</th>
                                                <th style="width: 20%">Collection Amount</th>
                                                <th style="width: 20%">Discount</th>
                                            </tr>-->
									<!--<span id="fees_head">-->
									<tr>
										<td colspan="5">
											<table width="100%" border="0" id="fees_head">
												<tr class="spaceUnder">
													<th align="center" style="width: 10%;align-content: center;"></th>
													<th align="center" style="width: 30%;padding-left: 15px;">
														Particular
													</th>
													<th style="width: 10%;padding-left: 15px;">Amount</th>
													<th style="width: 20%;padding-left: 15px;">Collection Amount
													</th>
													<th style="width: 20%;padding-left: 15px;">{{ App\Helpers\get_string('Discount','request') }}<span id="menuId" style="display:none"></span><a href="{{route('norm-clature.create')}}"><i class="mdi mdi-lead-pencil"></i></a></th>
													<th style="width: 20%;padding-left: 15px;">Fine</th>
												</tr>
												@php
                                                        $total = [];
                                                        foreach ($data['final_fee_new'] as $id => $val) {
                                                      // if ($id != 'Total') {     print_r($data['final_fee_name'][$id]); }  
												@endphp
												<tr>
													<td style="width: 10%">
														<input type="checkbox" name="" id="" <?=( isset($val[ 'mandatory']) && $val[
														 'mandatory']) ? 'checked' : '' ?>>
													</td>
													<td style="width: 20%">
														<?= $id ?>
													</td>
													<td style="width: 20%">
														<?= ($val['amount'] ?? 0) ?>
													</td>
													@php
                                                        $auto_head_counting = $data['fees_config_data']['auto_head_counting'];

                                                        if ($auto_head_counting == 1) {
                                                            $individual_enable = "readonly";
                                                            $total_disable = "";
                                                        } else {
                                                            $individual_enable = "";
                                                            $total_disable = "readonly";
                                                        }

                                                        // 26/08/2021 Start Added for The Millennium School for Advanced Imprest Collection payment
                                                        if ($val < 0) {
                                                            $negative_disable = 'readonly';
                                                        } else {
                                                            $negative_disable = '';
                                                        }
                                                        // 26/08/2021 End Added for The Millennium School for Advanced Imprest Collection payment
                                                        if (isset($val['mandatory']) && $val['mandatory']) {
                                                            $total_disable = 'disabled';
                                                            $negative_disable = 'disabled';
                                                            $individual_enable = "disabled";
                                                        }
                                                        if($data['previous_fees']=0 || $data['previous_fees']=null){

                                                        if ($id != 'Total') {   
                                                         
                                                            echo "<td style='width: 20%'><input  $individual_enable $negative_disable type='number'  min=0 max=".($val['amount'] ?? 0)." value='" . ($val['amount'] ?? 0) ."' name='fees_data[" . $data['final_fee_name'][$id] . "]' class='form-control allField1 fees_data[" . $data['final_fee_name'][$id] . "]'>
                                                            <input type='hidden' value=" .($val['amount'] ?? 0) . " name='hid_fees_data[" . $data['final_fee_name'][$id] . "]' class='hid_allField1' $individual_enable id=". $data['final_fee_name'][$id] . ">
                                                            </td>";
                                                            echo "<td style='width: 20%'><input type='number' value=0 name='discount_data[" . $data['final_fee_name'][$id] . "]' $individual_enable class='form-control allDisField' style='min-width:150px;'></td>"; // min=0 max=$val
                                                            echo "<td style='width: 20%'><input type='number' $individual_enable min=0 value=0 name='fine_data[" . $data['final_fee_name'][$id] . "]' class='form-control allFinField' style='min-width:150px;'></td>";

                                                        } else {
                                                            echo "<td style='width: 25%'><input $total_disable id='totalVal' type='text' name='total' value='" . ($val['amount'] ?? 0) . "' class='form-control'></td>
                                                            <input type='hidden' value=" . ($val['amount'] ?? 0) . " name='hid_totalVal' id='hid_totalVal'>";
                                                            echo "<td style='width: 25%'><input id='totalDis' type='text' name='totalDis' value=0 class='form-control directdiscount' $total_disable></td>";
                                                            echo "<td style='width: 25%'><input id='totalFin' type='text' name='totalFin' value=0 class='form-control directfine' $total_disable></td>";

                                                        }
                                                    }

                                                        $total[] =$val['amount'] ?? 0;
                                                      @endphp
													<!--<td style="width: 25%"><input type="text" class="form-control"></td>-->
												</tr>
												@php }
                                                       $total_amt= array_sum($total) 
												@endphp

											</table>
										</td>
									</tr>
									<!--<tr>
                                                <td></td>
                                                <td>Discount</td>
                                                <td></td>
                                                <td><input type="number" onchange="calculateTotal();" id="discount" name="discount" class="form-control"></td>
                                            </tr>-->
									<tr>
										<td></td>
										<td>Remarks</td>
										<td></td>
										<td>
											<input type="text" class="form-control" name="remarks" id="remarks" autocomplete="off">
										</td>
									</tr>
									@php $cheque_return_charges0 = $data['cheque_return_charges'][0]; $cheque_return_charges = $data['fees_config_data']['late_fees_amount'];
									$sub_institute_id=[257]; @endphp
									<tr>
										<td></td>
										<td>Fine(Include Cheque return charges)</td>
										<td></td>
										<td>
											@if(in_array(session()->get('sub_institute_id'),$sub_institute_id) && date('d') >= 5 && $total_amt!=0)

											<input type="text" name="fees_data[fine]" id="cheque_return_charges1" class="form-control cheque_return_charges1" value="100" >
											<!-- <input type="hidden" name="hidden_cheque_return_charges"
                                                               id="hidden_cheque_return_charges" class="form-control"
                                                               value="@if(isset($cheque_return_charges) && $cheque_return_charges>0){{ $cheque_return_charges}};@else {{$data['cheque_return_charges'][0]}} @endif"> -->
											<input type="hidden" name="hidden_cheque_return_charges" id="hidden_cheque_return_charges" class="form-control cheque_return_charges1" value="100"> @else
											<input type="text" name="fees_data[fine]" id="cheque_return_charges" class="form-control" value="@php if(isset($cheque_return_charges0)) echo $cheque_return_charges0; @endphp"
											 readonly="readonly">
											<input type="hidden" name="hidden_cheque_return_charges" id="hidden_cheque_return_charges" class="form-control" value="@if(isset($cheque_return_charges0)){{$cheque_return_charges0}}@endif"> @endif
										</td>
									</tr>

									@php
                                            // START 30-12-2021 Added for include cheque return charges in grand total

                                            if (isset($cheque_return_charges) && $cheque_return_charges != '') {
                                                $grand_total_with_cheque_charges = $data['final_fee']['Total'] + $cheque_return_charges;
                                            } else {
                                                $grand_total_with_cheque_charges = $data['final_fee']['Total'];
                                            }
                                            // END 30-12-2021 Added for include cheque return charges in grand total

                                    @endphp
									<tr style="border-bottom: 2px solid black;">
										<td></td>
										<td>Grand Total</td>
										<td></td>
										<td>
											<input type="text" id="grandTotal" readonly="" value="{{ $grand_total_with_cheque_charges;}}"
											 class="form-control">
										</td>
									</tr>
									<tr>
										<td>Payment Mode</td>
										<td>
											<select class="form-control" required="required" name="PAYMENT_MODE" id="payment_mode" onchange="sh_bankDetail(this.value);">
												<option value="">Select Payment Mode</option>
												<option value="Cash">Cash</option>
												<option value="Cheque">Cheque</option>
												<option value="DD">DD</option>
												<option value="Online">Online</option>
												<option value="NACH">NACH</option>
												<option value="UPI">UPI</option>
												<option value="Swipe1">Swipe1</option>
												<option value="Swipe2">Swipe2</option>
											</select>
										</td>
										<td>Receipt Date</td>
										<td>
											<input type="text" name="receiptdate" id="receiptdate" class="form-control mydatepicker" autocomplete="off" value="{{date('Y-m-d'); }}">
										</td>
									</tr>
									<tr class="bnakDetail">
										<td>Cheque/DD Date</td>
										<td>
											<input type="text" name="cheque_date" id="cheque_date" class="form-control mydatepicker" autocomplete="off" value="{{date('Y-m-d'); }}">
										</td>
										<td>Cheque/DD No/Transaction No</td>
										<td>
											<input type="text" name="cheque_no" id="cheque_no" class="form-control">
										</td>
										<!-- pattern="\d{6}" maxlength="6" maxlength="6" -->
									</tr>

									<tr class="bnakDetail" style="border-bottom: 2px solid black;">
										<td>Bank Name</td>
										<td>
											<select class="form-control" name="bank_name" id="bank_name">
												<option value="">Select Bank Name</option>
												@if(!empty($data['bank_data'])) @foreach($data['bank_data'] as $key => $value)
												<option value="{{$value['bank_name']}}">{{$value['bank_name']}}</option>
												@endforeach @endif
											</select>
										</td>
										<td>Bank Branch</td>
										<td>
											<input type="text" name="bank_branch" id="bank_branch" class="form-control" value="N/A">
										</td>
									</tr>
								</table>
							</div>
							<div class="table-responsive col-md-12">
								<div class="col-md-6 form-group">
									<!-- <div class="checkbox checkbox-info">
                                                <input id="sendsms" name="send_sms" type="checkbox">
                                                <label for="sendsms"> SEND SMS </label>
                                            </div>-->
								</div>
								<div class="col-md-6 form-group">
								<div id="overlay-new" style="display:none;"><center><p style="margin-top: 273px;color:red;font-weight: 700;">Please do not refresh the page, while the process is going on.</p><img src="https://erp.triz.co.in/admin_dep/images/loader.gif"></center></div>
    								<center> <input type="submit" name="submit" onclick="return checkForm();" value="Save" class="btn btn-success"  id="Load_gif"></center>
								</div>
							</div>
						</form>
					</div>
				</div>
			</div>
		</div>
	</div>

	<!--Modal: Add ChapterModal-->
	<div id="printThis">
		<div class="modal fade right modal-scrolling" id="ChapterModal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel"
		 style="display: none;" aria-hidden="true">
			<div class="modal-dialog modal-side modal-bottom-right modal-notify modal-info" role="document" style="min-width: 85%;">
				<!--Content-->
				<div class="modal-content">
					<!--Header-->
					<div class="modal-header">
						<h5 class="modal-title" id="heading">Fees Payment History</h5>
						<button type="button" class="close" id="refresh_data" data-dismiss="modal" aria-label="Close">
							<span aria-hidden="true">x</span>
						</button>
					</div>
					<!--Body-->
					<div class="modal-body">
						<div class="row">
							<!-- TABLE START-->
							<div class="card">
								<div class="table-responsive">
									<table id="example" class="table table-striped">
										<thead>
											<tr>
												<th>Sr No.</th>
												<th>GR No.</th>
												<th>{{App\Helpers\get_string('StudentName','request')}}<span id="menuId" style="display:none"></span><a href="{{route('norm-clature.create')}}"><i class="mdi mdi-lead-pencil"></i></a></th>
												<th>Std-Div</th>
												<th>Uniqueid</th>
												<th>Month</th>
												<th>Receipt No</th>
												<th>Payment Mode</th>
												<th>Bank Details</th>
												<!--<th>Cheque Date</th>-->
												<th>Receipt Date</th>
												<th>Collected By</th>
												<!--<th>Created On</th>-->
												<th>Amount</th>
											</tr>
										</thead>
										<tbody id="table_data">
											<!-- //data -->
										</tbody>
									</table>
								</div>
							</div>
							<!-- table end -->
						</div>
					</div>
					<!--Footer-->
					<!--  <div class="modal-footer" style="display: block !important;">
                         <div id="overlay" style="display:none;"><center><p style="margin-top: 273px;color:red;font-weight: 700;">Please do not refresh the page, while the process is going on.</p><img src="http://dev.triz.co.in/admin_dep/images/loader.gif"></center></div>
                         <center>
                             <button id="ajax_PDF" type="button" class="btn btn-primary">Print Receipt</button>
                         </center>
                     </div>
                 </div> -->
					<!--/.Content-->
				</div>
			</div>
		</div>
		<!--Modal: Add ChapterModal-->

		@include('includes.footerJs')
		<script>
			document.body.className = document.body.className.replace("fix-header", "fix-header show-sidebar hide-sidebar");
			var elements = document.querySelectorAll('input,select,textarea');

			for (var i = elements.length; i--;) {
				elements[i].addEventListener('invalid', function() {
					this.scrollIntoView(false);
				});
			}


			$(document).ready(function() {

				console.log("hello");
				monthCheck();
			});

			function checkForm() {
			if ($('#payment_mode').val() == '') {
				alert("Please select Payment Mode.");
				return false;
			}
			if ($('#receiptdate').val() == '') {
				alert("Please select Receipt Date.");
				return false;
			}
			if ($('#payment_mode').val() != 'Cash') {
				if ($('#cheque_date').val() == '') {
				alert("Please select Cheque Date.");
				return false;
				}
				if ($('#cheque_no').val() == '') {
				alert("Please select Cheque Number.");
				return false;
				}
				if ($('#bank_name').val() == '') {
				alert("Please select Bank Name.");
				return false;
				}
				if ($('#bank_branch').val() == '') {
				alert("Please select Bank Branch.");
				return false;
				}
			}

			// Show the loading overlay
			$('#overlay-new').show();

			// Submit the form
			// Replace 'formId' with the actual ID of your form
			$('#formId').submit();

			// Prevent the default form submission
			return true;
			}

			$('#fees_head').on('change', '.allField1', function() {
				var sum = 0;

				$('.allField1').each(function() {
					var amount;
					amount = parseFloat($(this).val());
					sum += amount; // Or this.innerHTML, this.innerText
				});
				$("#totalVal").val(sum);
				calculateTotal();
			});

			$('#fees_head').on('change', '.allDisField', function() {
				var sum = 0;
				$('.allDisField').each(function() {
					var amount;
					amount = parseFloat($(this).val());
					sum += amount; // Or this.innerHTML, this.innerText
				});
				$("#totalDis").val(sum);
				calculateTotal();
			});

			$(document).on('change', '.directdiscount', function() {
				amount = parseFloat($(this).val());
				$('.allDisField').each(function() {
					$(this).val(0);
				});
				calculateTotal();
			});

			$('#fees_head').on('change', '.allFinField', function() {
				//alert("asds");
				var sum = 0;
				cheque_return_charges = $("#hidden_cheque_return_charges").val();
				$('.allFinField').each(function() {
					var amount;
					amount = parseFloat($(this).val());
					sum += amount; // Or this.innerHTML, this.innerText
				});
				$("#totalFin").val(sum);
				sum = sum + parseFloat(cheque_return_charges);
				$("#cheque_return_charges").val(sum);
				calculateTotal();
			});


			// START 30-12-2021 Added for total fine in grandtotal
			$(document).on('change', '.directfine', function() {
				var sum = 0;
				cheque_return_charges = $("#hidden_cheque_return_charges").val();
				amount = parseFloat($(this).val());
				$('.allFinField').each(function() {
					$(this).val(0);
					// sum += amount;
				});
				sum = amount + parseFloat(cheque_return_charges);
				$("#cheque_return_charges").val(sum);
				calculateTotal();
			});

			// END 30-12-2021 Added for total fine in grandtotal

			function calculateTotal() {
				tot = parseFloat($("#totalVal").val());
				fin = parseFloat($("#totalFin").val());
				dis = parseFloat($("#totalDis").val());
				if({{session()->get('sub_institute_id')}} == 257){
					cheque_return_charges = $("#cheque_return_charges1").val();
				}else{
					cheque_return_charges = $("#hidden_cheque_return_charges").val();
				}

				if (dis > tot && dis != 0) {
					alert("Discount Can Not Be More Then Total Amount.");
					$("#discount").val(0);
					$("#totalDis").val(0)
				} else {
					if (isNaN(dis)) {} else {
						tot = (tot - dis) + fin;
					}
					tot = tot + parseFloat(cheque_return_charges);
					$("#grandTotal").val(tot);
				}
			}
			$(document).on('change', '.cheque_return_charges1', function() {

				calculateTotal();
			});

			function sh_bankDetail(selectedVal) {
				if (selectedVal == 'Cash') {
					$('.bnakDetail').hide();
				} else {
					$('.bnakDetail').show();
				}
			}

			$('.months').click(function() {
				monthCheck();
			});

			function monthCheck() {
				var checkedMonths = new Array();
				var j = 0;
				for (var i = 0; i < document.getElementsByClassName('months').length; i++) {
					if (document.getElementsByClassName('months')[i].checked) {
						checkedMonths[j] = document.getElementsByClassName('months')[i].value;
						j = j + 1;
					}
				}

				$.ajax({
					type: "POST",
					url: "{{route('get-fees-list')}}",
					data: {
						checkedMonths: checkedMonths,
						student_id: {{$data['stu_data']['student_id']; }}
					},
					//--> send id of checked checkbox on other page
					success: function(data) {
						$("#fees_head").empty();
						$("#fees_head").html(data);

						var auto_head_counting = {{ (($auto_head_counting == '1') ? ($auto_head_counting) : ('0')); }} ;
						if (auto_head_counting == 1) {
							$('.allField1').attr('readonly', true);
							$('#totalVal').attr('readonly', false);
						} else {
							$('.allField1').attr('readonly', false);
							$('#totalVal').attr('readonly', true);
							$('#previous_fees').attr('readonly', true);

						}

						tot = $("#totalVal").val();

						// START 30-12-2021 Added for total fine box value display wrong
						fin = parseFloat($("#totalFin").val());
						cheque_return_charges = $("#hidden_cheque_return_charges").val();
						sum = fin + parseFloat(cheque_return_charges);
						$("#cheque_return_charges").val(sum);
						calculateTotal();
						// $("#grandTotal").val(tot);
						// END 30-12-2021 Added for total fine box value display wrong

						// 26/08/2021 Start Added for The Millennium School for Advanced Imprest Collection payment
						$('.allField1').each(function() {
							var new_name = $(this).attr('name');
							amount = $('input[name="' + new_name + '"]').val();
							if (amount < 0) {
								$(this).attr('readonly', true);
							}
						});
						// 26/08/2021 END Added for The Millennium School for Advanced Imprest Collection payment
					}
				});
			}

			$(document).on('blur', '#totalVal', function() {
				var new_total_amount = parseFloat(this.value);
				var new_copy_total_amount = parseFloat(this.value);
				var orginial_tot = parseFloat($("#hid_totalVal").val());

				if (new_total_amount > orginial_tot) {
					alert("Amount Cannot be greater than total amount");
					$('#totalVal').val(orginial_tot);
					$('#grandTotal').val(orginial_tot);
				} else {
					$('.allField1').each(function() {
						var new_name = "hid_" + $(this).attr('name');
						amount = $('input[name="' + new_name + '"]').val();
						if (amount != 0) {
							if (amount >= new_total_amount) {
								$(this).val(new_total_amount);
								new_total_amount = 0;
							} else {
								new_total_amount = parseInt(new_total_amount - amount);
								$(this).val(amount);
							}
						}
					});
					calculateTotal();
					// $('#grandTotal').val(new_copy_total_amount);
				}
			});
		</script>
		@if(app('request')->input('implementation') == 1)
		<script type="text/javascript">
			document.body.className = document.body.className.replace("fix-header", "fix-header show-sidebar hide-sidebar");
			document.getElementById('main-header').style.display = 'none';
		</script>
		@endif @include('includes.footer')

		<!--fees payment history code  -->
		<script src="https://cdn.jsdelivr.net/gh/gitbrent/bootstrap4-toggle@3.6.1/js/bootstrap4-toggle.min.js"></script>
		<script>
			function add_data(grno, student_id) {
				$(document).ready(function() {
					$.ajax({
						url: '/fees/feesDetails/getDetails/' + grno + "/" + student_id,
						type: 'GET',
						dataType: 'json',
						success: function(data) {
							const months = ["Jan", "Feb", "Mar", "Apr", "May", "June", "July", "Aug", "Sep", "Oct", "Nov", "Dec"];

							$.each(data, function(index, value) {
								index++;
								const term_id = value['term_id'];
								year = String(term_id).slice(-4);
								month = String(term_id).substring(0, String(term_id).length - 4);
								month--;
								// const d = new Date(value['term_id']);
								let monthyear = months[month] + "/" + year;
								// console.log(monthyear);

								if (value['uniqueid'] == 'null') {
									valueuni = value['uniqueid'];
								} else {
									valueuni = '';
								}
								// console.log(value['student_name']);
								$('#table_data').append("<tr><td>" + index + "</td><td>" + value['enrollment_no'] + "</td><td>" + value[
										'student_name'] + "</td><td>" + value['division_name'] + "</td><td>" + valueuni + "</td><td>" +
									monthyear + "</td><td>" + value['receipt_no'] + "</td><td>" + value['payment_mode'] + "</td><td>" +
									value['cheque_bank_name'] + "</td><td>" + value['receiptdate'] + "</td><td>" + value['user_name'] +
									"</td><td id='total_amt'>" + value['actual_amountpaid'] + "</td></tr>");
							});

							var total = 0;

							$('#table_data tr').each(function(index) {
								var found = $(this).find('#total_amt')
								if (found) {
									total += parseInt(found.text());
								}
								// console.log(total);
							});

							$('#table_data').append("<tr><td colspan=11>Total</td><td>" + total + "</td></tr>");
							$('#ChapterModal').modal('show');

						}
					});
				});
			}

			$('body').on('hidden.bs.modal', '.modal', function() {
				$("#table_data").empty();
			});
		</script>

<script>
    var menuId = localStorage.getItem('current_id');
    var spans = document.querySelectorAll('span.menuId');
    for (var i = 0; i < spans.length; i++) {
        spans[i].textContent = menuId;
    }
    var url = '{{ route("norm-clature.create") }}?menu_id=' + menuId;
    var links = document.querySelectorAll('a[href="{{ route("norm-clature.create") }}"]');
    for (var j = 0; j < links.length; j++) {
        links[j].href = url;
    }
</script>