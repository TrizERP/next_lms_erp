@include('includes.headcss') @include('includes.header') @include('includes.sideNavigation')

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
				<div class="no_heads" id="no_heads" style="visibility: hidden">
				<h5>No heads</h5>
				</div>
				<div class="row">
					<div class="col-md-4 col-lg-4 col-sm-4 col-xs-4">
						<div class="box-title">
							<label>Fees Structure</label>
						</div>
						<div class="table-responsive">
							<table class="table table-stripped" style="color:#000 !important;">
								<tr>
									<th>Month</th>
									@if(session()->get('sub_institute_id')==61)
									<th>Bus Amount</th>
									@endif
									<th>Fees</th>
									<th>Paid</th>
									<th>Discount</th>
									<th>Remaining</th>
								</tr>
								@php
                  					$remainFees=$paidFees  = 0;
									$feesDetails= [];
									$bk=$paid=$remain=$discount=$Paid_and_discount=$busAmount=array();

									foreach ($data['total_fees'] as $id => $arr) {
									$feesDetails[$arr['month']] = $arr['remain'];
									if(isset($arr['bk'])){
                                @endphp
								<tr>
									<td>
										{{ $arr['month'] }}
									</td>
									@if(session()->get('sub_institute_id')==61)
									<td>@php $busAmount[] = $arr['bus_amount']; echo isset($arr['bus_amount']) ? $arr['bus_amount'] : 0  @endphp</td>
									@endif
									<td>
										@php $bk[] = $arr['bk']; echo $arr['bk'];  @endphp
									</td>
									@php $Paid_and_discount[] = $arr['paid'] + $arr['discount']; @endphp
									<td>
										@php echo $arr['paid'] + $arr['discount']; @endphp
									</td>
									<td>@php $discount[] = $arr['discount']; echo $arr['discount']; @endphp</td>
									<td>
										@php $remain[] = $arr['remain'];echo $arr['remain'];  @endphp
									</td>
								</tr>
								@php
                                    }
                                        $remainFees += $arr['remain'];
                                        $paidFees += $arr['paid'];
                                        } 
								@endphp
								<!-- hills previous pending fees Previous Year Fees Not Display in Current Year - Rajesh 01-07-2024  -->
								@if(!in_array(session()->get('sub_institute_id'),[48,61]) && $data['stu_data']['previous_fees'] > 0)
								<tr>
									<td>Previous Fees</td>
									<td>@php $bk[]= $data['stu_data']['previous_fees']; echo $data['stu_data']['previous_fees'] @endphp</td>
									<td>0</td>
									<td>0</td>
									<td>@php $remain[] = $data['stu_data']['previous_fees']; echo $data['stu_data']['previous_fees'] @endphp</td>
								</tr>
								@endif
								<!-- end previous fees  -->
								<tr>
									<td>Total</td>
									@if(session()->get('sub_institute_id')==61)
									<td>{{ array_sum($busAmount) }}</td>
									@endif
									<td>
										{{ array_sum($bk) }}
									</td>
									<td>
										{{ array_sum($Paid_and_discount) }}
									</td>
									<td>{{array_sum($discount) }}</td>
									<td>
										{{array_sum($remain) }}
									</td>
								</tr>

							</table>
						</div>
						
						<div class="row">
							<div class="col-md-12 text-center mt-4">
								<button type="button" class="btn btn-info" data-toggle="modal" id="add_data" onclick="javascript:add_data('{{ $data['stu_data']['enrollment']}}',{{$data['stu_data']['student_id']}});">
									Paid History
								</button>
							</div>
						</div>
					</div>

					<!-- 08-01-2025 -->
					@php 
					$class="";
					if(session()->get('sub_institute_id')==76){
						$class = "hide";
					}
					@endphp
					<!-- 08-01-2025 -->

					<div class="col-md-8 col-lg-8 col-sm-8 col-xs-8">
						<div class="box-title">
							<label>Fees Collection</label>
						</div>
						<div class="row">
							<div class="col-md-6">
								<div class="table-responsive">
									<table class="table table-stripped">
										<tr class="{{$class}}">
											<td>{{ App\Helpers\get_string('uniqueid')}}</td>
											<td>
												{{ $data['stu_data']['uniqueid']; }}
											</td>
										</tr>
										
										<tr>
											<td>{{ App\Helpers\get_string('studentname')}}</td>
											<td>
												{{App\Helpers\sortStudentName($data['stu_data']['name'])}}
												{{-- {{ $data['stu_data']['name']; }} --}}
											</td>
										</tr>
										<tr>
											<td>Admission Year</td>
											<td>
												{{ $data['stu_data']['admission']; }}
											</td>
										</tr>
										<tr>
											<td>Father Name</td>
											<td>
												{{ $data['stu_data']['father_name']; }}
											</td>
										</tr>
										<tr>
											<td>{{ App\Helpers\get_string('studentquota')}}</td>
											<td>
												{{ $data['stu_data']['student_quota']; }}
											</td>
										</tr>
										<!-- ssmission pan card  -->
										@if(session()->get('sub_institute_id')==76)
										<tr>
											<td>Pan Card No</td>
											<td>
											<input type="text" class="form-control" name="pan_card" id="inputPan" @if(isset($data['stu_data']['pan_card'])) value="{{ $data['stu_data']['pan_card']; }}" @endif onkeyup="addInputPan()";> 
											</td>
										</tr>
										@else
										<input type="hidden" id="inputPan" value="{{ $data['stu_data']['pan_card']; }}" >
										@endif
									</table>
								</div>
							</div>
							<div class="col-md-6">
								<div class="table-responsive">
									<table class="table table-stripped">
										<tr>
											<td>{{ App\Helpers\get_string('grno')}}</td>
											<td>
												{{ $data['stu_data']['enrollment']; }}
											</td>
										</tr>
										<tr>
											<td>{{ App\Helpers\get_string('std/div')}}</td>
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
										<tr>
											<td>Parent Email</td>
											<td>
												{{ $data['stu_data']['email']; }}
											</td>
										</tr>										
										<tr>
											<td style="color: red;">Pending Fees</td>
											<td style="color: red;">
											<!-- for hills  previous fees  -->
											@if(!in_array(session()->get('sub_institute_id'),[48,61]) && $data['stu_data']['previous_fees'] > 0)
											 	{{($data['stu_data']['previous_fees'] + $data['stu_data']['pending']) }}
											@else
												{{$data['stu_data']['pending']}}
											@endif
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
							<!-- // 2024-06-24 by uma -->
							<input type="hidden" name="student_batch" value="{{ $data['stu_data']['student_batch']; }}">
							<input type="hidden" name="pan_card" id="pan_card">
							
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
												// list($month, $year) = explode('/', $val); // commented on 08-01-2025

												// added on 08-01-2025 get year and month from $id
												$valueStr = (string)$id;
												$year = substr($valueStr, -4);
												$month = substr($valueStr, 0, strlen($valueStr) - 4);
												// added on 08-01-2025

                                                $monthDate = $year . '-' . date('m', strtotime($month)) . '-01';
                                                $date_now = time();
												// added on 18-01-2025 ssmission
                                                if(session()->get('sub_institute_id')==76){
													foreach($data['header_month'] as $mapId=>$mapMonth){
														$valueStr1 = (string)$mapMonth;
														$year1 = substr($valueStr1, -4);
														$month1 = substr($valueStr1, 0, strlen($valueStr1) - 4);
														if($month1 <= date('n') && date('Y')>=$year1 && $month == $month1){
															$slected = "checked";
														}
													}
													if($slected=='' && isset($data['header_month'][0]) && $data['header_month'][0]==$id){
														$slected = "checked ".date('n');
													}
												}
												// added on 18-01-2025 ssmission

												elseif (in_array($id, $data['search_ids']) && $date_now >= strtotime($monthDate)) {
                                                    $slected = "checked";
                                                }

                                                $disabled = '';
                                                if (isset($feesDetails[$val]) && $feesDetails[$val] <= 0) {
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
													<th style="width: 20%;padding-left: 15px;">{{ App\Helpers\get_string('Discount') }}</th>
													<th style="width: 20%;padding-left: 15px;">Fine</th>
												</tr>
												@php
                                                        $total = [];
                                                        foreach ($data['final_fee_new'] as $id => $val) {
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
												</tr>
												@php }
                                                       $total_amt= array_sum($total) 
												@endphp

											</table>
										</td>
									</tr>
									
									<tr>
										<td></td>
										<td>Remarks</td>
										<td></td>
										<td>
											<input type="text" class="form-control" name="remarks" id="remarks" autocomplete="off">
										</td>
									</tr>
									<tr>
										<td></td>
										<td>{{ App\Helpers\get_string('discount')}}</td>
										<td></td>
										<td>
											<input type='text' id='totalDis' name='totalDis' value='0' class='form-control directdiscount'>
										</td>
									</tr>
									@php 
										if(session()->get('sub_institute_id')==254 && !empty($data['hillsFine'])){
											$cheque_return_charges0 = $data['hillsFine']['total'];
											$cheque_return_charges=$data['hillsFine']['total'];
											$readable='';
											$inputId="cheque_return_charges1";
										}else{
											$cheque_return_charges0 = $data['cheque_return_charges'][0];
											$cheque_return_charges = $data['fees_config_data']->late_fees_amount;
											$readable='readonly="readonly"';
											$inputId="cheque_return_charges";
										}
										$sub_institute_id=[257]; 
									@endphp
									<tr  class="{{$class}}">
										<td></td>
										<td>Fine(Include Cheque return charges)</td>
										<td></td>
										<td>
											@if(in_array(session()->get('sub_institute_id'),$sub_institute_id) )
												<input type="text" name="fees_data[fine]" id="cheque_return_charges1" class="form-control cheque_return_charges1" value="@if(date('d') >= 3 && $total_amt!=0) {{$data['fees_config_data']['late_fees_amount']}} @else {{$cheque_return_charges0}} @endif" >
											
												<input type="hidden" name="hidden_cheque_return_charges" id="hidden_cheque_return_charges" class="form-control cheque_return_charges1" value="{{$data['fees_config_data']['late_fees_amount']}}"> 

												<input type="hidden" name="for_cn_only" id="hidden_cheque_return_charges2" value="{{$data['fees_config_data']['late_fees_amount']}}"> 
										 <!-- fees late master implement finr_type wise start 04-02-2025 -->
											@elseif(isset($data['config_late_fine']) && $data['config_late_fine']>0)
												<input type="text" name="fees_data[fine]" id="cheque_return_charges1" class="form-control cheque_return_charges0" value="{{$data['config_late_fine']}}" >
											
												<input type="hidden" name="hidden_cheque_return_charges" id="hidden_cheque_return_charges" class="form-control cheque_return_charges0" value="{{$data['config_late_fine']}}"> 

												<input type="hidden" name="for_cn_only" id="cheque_return_charges0" value="{{$data['config_late_fine']}}"> 
										 <!-- fees late master implement finr_type wise end 04-02-2025 -->

											@else
												<input type="text" name="fees_data[fine]" id="{{$inputId}}" class="form-control hillsFine" value="@php if(isset($cheque_return_charges0)) echo $cheque_return_charges0; @endphp">
												<input type="hidden" name="hidden_cheque_return_charges" id="hidden_cheque_return_charges" class="form-control" value="@if(isset($cheque_return_charges0)){{$cheque_return_charges0}}@endif">
											@endif
										</td>
									</tr>

									@php
                                            // START 30-12-2021 Added for include cheque return charges in grand total

                                            if (isset($cheque_return_charges) && $cheque_return_charges != '') {
                                                $grand_total_with_cheque_charges = $data['final_fee']['Total'] + $cheque_return_charges;
                                            } else {
                                                $grand_total_with_cheque_charges = $data['final_fee']['Total'];
                                            }
                                            // for send sms to parent
   											$fees_config =App\Helpers\fees_config();
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
												<option value="Swipe3">Swipe3</option>
												<option value="POS">POS</option>
											</select>
										</td>
										<td>Receipt Date</td>
										<td>
											<input type="text" name="receiptdate" id="receiptdate" class="form-control mydatepicker" autocomplete="off" value="{{date('Y-m-d'); }}">
										</td>
									</tr>
									<tr class="bnakDetail">
										<td class="{{$class}}">Cheque/DD Date</td>
										<td  class="{{$class}}">
											<input type="text" name="cheque_date" id="cheque_date" class="form-control mydatepicker" autocomplete="off" value="{{date('Y-m-d'); }}">
										</td>
										<td>Cheque/DD No/Transaction No</td>
										<td>
											<input type="text" name="cheque_no" id="cheque_no" class="form-control">
										</td>
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
										<td class="{{$class}}">Bank Branch</td>
										<td class="{{$class}}">
											<input type="text" name="bank_branch" id="bank_branch" class="form-control" value="N/A">
										</td>
									</tr>
									@if(isset($fees_config->send_sms) && $fees_config->send_sms == 1)
									<td>Send SMS</td>
									<td colspan="3"><input type="checkbox" name="send_sms" id="send_sms"></td>
									@endif
								</table>
							</div>
							<div class="table-responsive col-md-12">
								<div class="col-md-6 form-group">
								
								</div>
								<div class="col-md-6 form-group">
    								<center> <input type="submit" name="submit" onclick="return checkForm();" value="Save" class="btn btn-success"></center>
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
				<div class="modal-content">
					<div class="modal-header">
						<h5 class="modal-title" id="heading">Fees Payment History</h5>
						<button type="button" class="close" id="refresh_data" data-dismiss="modal" aria-label="Close">
							<span aria-hidden="true">x</span>
						</button>
					</div>
					<div class="modal-body">
						<div class="row">
							<div class="card">
								<div class="table-responsive">
								<h6><b>Paid History</b></h6>
									<table id="example" class="table table-striped">
										<thead>
											<tr>
												<th>Sr No.</th>
												<th>GR No.</th>
												<th>{{App\Helpers\get_string('StudentName')}}</th>
												<th>Std-Div</th>
												<th>Uniqueid</th>
												<th>Month</th>
												<th>Receipt No</th>
												<th>Payment Mode</th>
												<th>Bank Details</th>
												<th>Receipt Date</th>
												<th>Collected By</th>
												<th>Remark</th>
												<th>Discount</th>
												<th>Amount</th>
												<th class="text-left">Action</th>
											</tr>
										</thead>
										<tbody id="table_data">
										</tbody>
									</table>
								</div>
								<br>
								<br>
								<div class="table-responsive"  id="cancelTableDiv">
									<h6><b>Cancelled History</b></h6>
									<table id="cancelTable" class="table table-striped">
										<thead>
											<tr>
												<th>Sr No.</th>
												<th>GR No.</th>
												<th>{{App\Helpers\get_string('StudentName')}}</th>
												<th>Std-Div</th>
												<th>Uniqueid</th>
												<th>Month</th>
												<th>Receipt No</th>
												<th>Payment Mode</th>
												<th>Bank Details</th>
												<th>Received Date</th>
												<th>Cancelled Date</th>
												<th>Cancelled By</th>
												<th class="text-left">Amount</th>
											</tr>
										</thead>
										<tbody id="cancel_data">
										</tbody>
									</table>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
		
		@include('includes.footerJs')
		<script>
			document.body.className = document.body.className.replace("fix-header", "fix-header show-sidebar hide-sidebar");
			var elements = document.querySelectorAll('input,select,textarea');

			for (var i = elements.length; i--;) {
				elements[i].addEventListener('invalid', function() {
					this.scrollIntoView(false);
				});
			}

			// 01-02-2025
			function addInputPan(){
				var panNo = $('#inputPan').val();
				$('#pan_card').val(panNo);
			}
			// 01-02-2025 end
			$(document).ready(function() {
				// 01-02-2025
				addInputPan();
				// 01-02-2025 end
				monthCheck();
				$('#cancelTableDiv').hide();
					var sub = 0;
					var full_bk = @json($data['final_fee']);
					// console.log(full_bk);
					$.each(full_bk, function(index, value) {
						if (value !== 0 && index!=='Total' && index!=='Previous Fees') {
							$.ajax({
								url : '{{route("check_reciept_book")}}',
								data: {fees_title : index,standard_id:{{ $data['stu_data']['std_id'] }}},
								type: 'GET',
								success : function(result) {
									if(result[0] == 0){
										$('#no_heads').append(`<p>`+result[1]+`</p>`);
										$('#no_heads').css('visibility', 'visible');
										return false;
									}
								},
							})
						}
					});
				});

			// function checkForm() {
			// if ($('#payment_mode').val() == '') {
			// 	alert("Please select Payment Mode.");
			// 	return false;
			// }
			// if ($('#receiptdate').val() == '') {
			// 	alert("Please select Receipt Date.");
			// 	return false;
			// }
			// if ($('#payment_mode').val() != 'Cash') {
			// 	if ($('#cheque_date').val() == '') {
			// 	alert("Please select Cheque Date.");
			// 	return false;
			// 	}
			// 	if ($('#cheque_no').val() == '') {
			// 	alert("Please select Cheque Number.");
			// 	return false;
			// 	}
			// 	if ($('#bank_name').val() == '') {
			// 	alert("Please select Bank Name.");
			// 	return false;
			// 	}
			// 	if ($('#bank_branch').val() == '') {
			// 	alert("Please select Bank Branch.");
			// 	return false;
			// 	}
			// }
			// $('input[name="fees_data[]"]').each(function() {
			// 	var feesTitle = $(this).attr('id');
			// 	console.log(feesTitle);
			// 	// Do something with feesTitle
			// });
	
			// $('#formId').submit();

			// // Prevent the default form submission
			//return true;
			// // return false;
			
			// }

function checkForm() {

    if ($('#no_heads').length != 0) {
        var paragraphs = $('#no_heads').find('p');
		if (paragraphs.length != 0) {		
			paragraphs.each(function(index, element) {
			alert('Head not added in Receipt Book Master : ' + $(element).text());
			});
			return false;	
		}	
    }

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
	
    // Submit the form
	if(sub==0){
    $('#formId').submit();
    // Prevent the default form submission
    return true;
	}else{
		return false;
	}
}


			$('#fees_head').on('change', '.allField1', function() {
				var sum = 0;

				$('.allField1').each(function() {
					var amount;
                    console.log($(this).val())
                    if (!isNaN($(this).val()) && $(this).val() !== '') {
                        amount = !isNaN($(this).val()) ? parseFloat($(this).val()) : 0;
                        console.log(amount)
                        sum += amount;
                    }
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

			$(document).on('change', '.hillsFine', function() {
				var amount = parseFloat($(this).val());
				var grandTotal = parseFloat($('#totalVal').val());
				// subtract totalFin from grandTotal
				var TotVal = (grandTotal + amount);
				$("#grandTotal").val(TotVal);
			});

			// END 30-12-2021 Added for total fine in grandtotal

			function calculateTotal() {
				tot = parseFloat($("#totalVal").val());
				fin = parseFloat($("#totalFin").val());
				dis = parseFloat($("#totalDis").val());
				if({{session()->get('sub_institute_id')}} == 257){
					cheque_return_charges = $("#cheque_return_charges1").val();
				}else if({{session()->get('sub_institute_id')}} == 254){
					cheque_return_charges = $(".hillsFine").val();
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
				var currentCheckedIndex = $('.months').index(this); 

				$('.months').each(function(index) {
					if (index <= currentCheckedIndex) {
					$(this).prop('checked', true);
					} else {
					$(this).prop('checked', false);
					}
				});

  			monthCheck();
			});

			function monthCheck() {
				var checkedMonths = new Array();

					var j = 0;
					var TotalFin = 0;
					for (var i = 0; i < document.getElementsByClassName('months').length; i++) {
						if (document.getElementsByClassName('months')[i].checked) {
							checkedMonths[j] = document.getElementsByClassName('months')[i].value;
							@if(session()->get('sub_institute_id')==254 && !empty($data['hillsFine']) )
								@if($data['hillsFine']!=0)
									var hillsFine = @json($data['hillsFine']);
									if(!isNaN(hillsFine[checkedMonths[j]])){
										var fineVal = hillsFine[checkedMonths[j]];
										TotalFin += fineVal;
									}
								@endif
							@endif
							j = j + 1;
						}
					}
				

				$('#cheque_return_charges').val(TotalFin);
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

						// 26/08/2021 Start Added for The Millennium School for Advanced Imprest Collection payment
						$('.allField1').each(function() {
							var new_name = $(this).attr('name');
							amount = $('input[name="' + new_name + '"]').val();
							if (amount < 0) {
								$(this).attr('readonly', true);
							}
						});

						@if(session()->get('sub_institute_id') == 257)
							var k = 0;
							var checkedTitle = new Array();

								$('.allField1').each(function() {
									checkedTitle[k] = $(this).attr('id');
									k= k+1;
								});
								console.log(checkedTitle);
								var validValues = ['1', '2', '3', '4', '5', '6', '7', '8', '9', '10'];

								// when only other fees remain for payment make fees fine 0   
								if (checkedTitle.length > 0 && !checkedTitle.includes('tution_fee') && validValues.some(value => checkedTitle.includes(value)))
								{
									// console.log('if');
									fineZero(0);
								}
								// for advance months fees rather than current month
								else if(checkedMonths.length > 0 && checkedTitle.length > 0){
									lastMonth = checkedMonths[checkedMonths.length - 1];
									var currentMonth = "{{date('n')}}{{date('Y')}}";
									
									let greaterMonths = checkedMonths.filter(month => month > currentMonth);

									if (!greaterMonths.includes(currentMonth) && greaterMonths.length > 0) {
									// console.log(greaterMonths);
										fineZero(0);
									}
								}
								else{
									var charge = $('#hidden_cheque_return_charges2').val();
									var fine = parseFloat(charge);
									fineZero(fine);
								}
							@endif
							// START 30-12-2021 Added for total fine box value display wrong
								fin = parseFloat($("#totalFin").val());
								cheque_return_charges = $("#hidden_cheque_return_charges").val();
								// 16-08-2024
								$('.hillsFine').val(cheque_return_charges);
								sum = fin + parseFloat(cheque_return_charges);
								$("#cheque_return_charges").val(sum);
								calculateTotal();
							// $("#grandTotal").val(tot);
						
					}
				});
			}

			function fineZero(cheque_return_charges){
				$('#cheque_return_charges1').val(cheque_return_charges);
				$('#hidden_cheque_return_charges').val(cheque_return_charges);
			}

			$(document).on('blur', '#totalVal', function() {
				var new_total_amount = parseFloat(this.value);
				var new_copy_total_amount = parseFloat(this.value);
				var orginial_tot = parseFloat($("#hid_totalVal").val());
				var all_total = $('#all_total').text();				

				if (all_total < new_total_amount) {
					alert("Amount Cannot be greater than total amount - "+all_total);
					$('#totalVal').val(all_total);
					$('#grandTotal').val(all_total);
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
		<script>
			function add_data(grno, student_id) {
				$("#table_data").empty();
				$('#cancelTableDiv').hide();
				$('#cancel_data').empty();

				$(document).ready(function() {
					$.ajax({
						url: '/fees/feesDetails/getDetails/' + grno + "/" + student_id,
						type: 'GET',
						dataType: 'json',
						success: function(data) {
							$("#table_data").empty();
							$('#cancelTableDiv').hide();
							$('#cancel_data').empty();
							const months = ["Jan", "Feb", "Mar", "Apr", "May", "June", "July", "Aug", "Sep", "Oct", "Nov", "Dec"];
							var paperSize = data.config_data.fees_receipt_template ? data.config_data.fees_receipt_template : "A5";
							$.each(data.fees_data, function(index, value) {
								index++;
								const term_ids = value['term_ids'].split(','); // Split the term_ids string into an array
								let monthyear = [];

								term_ids.forEach(function(term_id) {
									year = term_id.slice(-4);
									month = term_id.substring(0, term_id.length - 4);
									month--;
									monthyear.push(months[month] + "/" + year);
								});

								monthyear = monthyear.join(', '); // Join the month names with a comma separator

								if (value['uniqueid'] == 'null') {
									valueuni = value['uniqueid'];
								} else {
									valueuni = '';
								}
								var sub_institute_id = "{{session()->get('sub_institute_id')}}";
								var hideEdit = "";
								if(sub_institute_id!=76){
									hideEdit = "display:none";
								}
								var hrefReciept = "#";
								if(value['receipt_no'] && value['receipt_no']!==null){
									hrefReciept = `/ajax_PDF_FeesReceipt?action=fees_re_receipt&student_id=${value['student_id']}&receipt_id_html=${value['receipt_no']}&paper_size=${paperSize}`;
								}
								// console.log(value['student_name']);
								$('#table_data').append(`
								<tr>
									<td>${index}</td>
									<td>${value['enrollment_no']}</td>
									<td>${value['student_name']}</td>
									<td>${value['standard_name']} - ${value['division_name']}</td>
									<td>${value['uniqueid']}</td>
									<td>${monthyear}</td>
									<td>${value['receipt_no']}</td>
									<td>${value['payment_mode']}</td>
									<td>${value['cheque_no']} ${value['cheque_bank_name']} ${value['bank_branch']}</td>
									<td>${value['receiptdate']}</td>
									<td>${value['user_name']}</td>
									<td>${value['remarks']}</td>
									<td>${value['discount']}</td>
									<td id='total_amt'>${value['actual_amountpaid']}</td>
									<td style="display:flex;">	
										<a class='btn btn btn-outline-warning mr-2' id='ajax_PDF' href='${hrefReciept}' target='_blank'><span class='mdi mdi-printer'></span></a>
										<a class='btn btn-outline-info' style='${hideEdit}' href="/fees/fees_modification/create?enrollment_no=${value['enrollment_no']}&receipt_no=${value['receipt_no']}" target="_blank"><span class='mdi mdi-pencil'></span></a>
									</td>
								</tr>`);
							});

							var total = 0;

							$('#table_data tr').each(function(index) {
								var found = $(this).find('#total_amt')
								if (found) {
									total += parseInt(found.text());
								}
								// console.log(total);
							});
							$('#table_data').append("<tr><td colspan=13>Total</td><td colspan='2'>" + total + "</td></tr>");

							// cancell data start
							var cancelData = data.cancelData;

							// Check if cancelData is an array and contains items
							if (Array.isArray(cancelData) && cancelData.length > 0) {
								$('#cancelTableDiv').show();
								// $('#cancel_data').empty();
								cancelData.forEach(function(item, index) {
									// Ensure month_ids exists and is a string
									if (item.month_ids && typeof item.month_ids === 'string') {
										var term_ids = item.month_ids.split(','); // Split the term_ids string into an array
										var monthyear = [];

										term_ids.forEach(function(term_id) {
											var year = term_id.slice(-4);
											var month = term_id.substring(0, term_id.length - 4);
											month--;
											monthyear.push(months[month] + "/" + year); // months[month] is assumed to be an array of month names
										});

										monthyear = monthyear.join(', ');
									} else {
										// Handle case where month_ids is not available or invalid
										var monthyear = "N/A"; // Set default value
									}

									// Construct the table row
									var row = '<tr><td>' + (index+1) + '</td><td>' + item.enrollment_no + '</td><td>' + item.student_name + '</td><td>' + item.std + ' - ' + item.divi + '</td><td>' + item.uniqueid + '</td><td>' + monthyear + '</td><td>' + item.reciept_id + '</td><td>' + item.payment_mode + '</td><td>' + (item.cheque_no || '') + ' ' + (item.cheque_bank_name || '') + ' ' + (item.bank_branch || '') + '</td><td>' + item.received_date + '</td><td>' + item.cancel_date + '</td><td>' + item.cancelled_by + '</td><td id="total_cancel_amt">' + item.actual_amountpaid + '</td></tr>';

									$('#cancel_data').append(row);
								});
							} else {
								console.log("cancelData is either not an array or is empty.");
							}

							var cancalledTotal = 0;

							$('#cancel_data tr').each(function(index) {
								var found = $(this).find('#total_cancel_amt')
								if (found) {
									cancalledTotal += parseInt(found.text());
								}
								// console.log(total);
							});
							$('#cancel_data').append("<tr><td colspan=12>Total</td><td>" + cancalledTotal + "</td></tr>");
							// cancel data end 
							$('#ChapterModal').modal('show');

						}
					});
				});
			}
		</script>