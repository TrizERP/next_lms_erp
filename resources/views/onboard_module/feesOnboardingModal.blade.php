<!-- fees Account Details Modal -->
<div class="modal fade" id="exampleModal_fees" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
<div class="modal-dialog">
   <div class="modal-content">
      <div class="modal-header" style="align-items:center;padding:8px !important">
         <h3 class="modal-title fs-5 fcolor" id="exampleModalLabel">Fees Setup</h3>
         <button type="button" class="btn-close border-0" data-bs-dismiss="modal" aria-label="Close">X</button>
      </div>
      <div class="modal-body">
         <!-- step 1 start  -->
         <div id="fees_step_1">
         <div class="row flex-nowrap">
            <div class="col-md-3 bg-white" style="border-radius: 25px;">
               <div class="mt-4 d-block list-group h-100 justify-content-around">
                  <div class="mt-3 mb-3 d-flex align-items-center justify-content-between step-selected" id="step-1">
                     <div class="d-flex">
                        <div class="d-flex flex-column align-items-center">
                           <div class="disc"></div>
                           <div class="line"></div>
                        </div>
                        <div class="ml-2 mt-n1">
                           <p class="mb-1 main-p">Step 1</p>
                           <p class="sub-p">Check Account Details</p>
                        </div>
                     </div>
                     <div class="arrow-img" style="display: none">
                        <img src="{{asset('/fees_onboarding/icons8-arrow-26.png')}}" width="22px" alt="" />
                     </div>
                     <div class="done-img" style="display: none">
                        <img src="{{asset('/fees_onboarding/done.png')}}" width="22px" alt="" />
                     </div>
                  </div>
                  <div class="mt-3 mb-3 d-flex align-items-center justify-content-between" id="step-2">
                     <div class="d-flex">
                        <div class="d-flex flex-column align-items-center">
                           <div class="disc"></div>
                           <div class="line fee-setup-line"></div>
                        </div>
                        <div class="ml-2 mt-n1">
                           <p class="mb-1 main-p">Step 2</p>
                           <div class="d-flex justify-content-between" style="width: 200px">
                              <div class="dropdown">
                                 <div type="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" >
                                    <p class="d-inline mr-2 sub-p">Fees Setup</p>
                                    <img class="arrow-down" src="{{asset('/fees_onboarding/arrow-down-sign-to-navigate.png')}}" height="12" />
                                 </div>
                                 <div class="dropdown-menu show" aria-labelledby="dropdownMenuButton" style="width: 190px;margin-top:10px" >
                                    <div class="line" style=" position: absolute; height: 190px; background-color: #5c4ac7;" ></div>
                                    <div id="sub-step-1" class="d-flex align-items-center justify-content-around" >
                                       <a class="px-3 no-active dropdown-item dropdown-sub-p" href="#" >1) Fees Map Year</a>
                                       <img class="dropdown-arrow-img" style="display: none" src="{{asset('/fees_onboarding/icons8-arrow-26.png')}}" width="22px" height="22px" class="mt-2" alt="" />
                                       <img class="dropdown-done-img" style="display: none" src="{{asset('/fees_onboarding/done.png')}}" width="22px" height="22px" class="mt-2" alt="" />
                                    </div>
                                    <div id="sub-step-2" class="d-flex align-items-center justify-content-around" >
                                       <a class="px-3 no-active dropdown-item dropdown-sub-p" href="#" >2) Fees Title</a >
                                     <img class="dropdown-arrow-img" style="display: none" src="{{asset('/fees_onboarding/icons8-arrow-26.png')}}" width="22px" height="22px" class="mt-2" alt="" />
                                       <img class="dropdown-done-img" style="display: none" src="{{asset('/fees_onboarding/done.png')}}" width="22px" height="22px" class="mt-2" alt="" />
                                    </div>
                                    <div id="sub-step-3" class="d-flex align-items-center justify-content-around" >
                                       <a class="px-3 no-active dropdown-item dropdown-sub-p" href="#" >3) Fees Config Master</a >
                                       <img class="dropdown-arrow-img" style="display: none" src="{{asset('/fees_onboarding/icons8-arrow-26.png')}}" width="22px" height="22px" class="mt-2" alt="" />
                                       <img class="dropdown-done-img" style="display: none" src="{{asset('/fees_onboarding/done.png')}}" width="22px" height="22px" class="mt-2" alt="" />
                                    </div>
                                    <div id="sub-step-4" class="d-flex align-items-center justify-content-around" >
                                       <a class="px-3 no-active dropdown-item dropdown-sub-p" href="#" >4) Fees Month Header</a >
                                       <img class="dropdown-arrow-img" style="display: none" src="{{asset('/fees_onboarding/icons8-arrow-26.png')}}" width="22px" height="22px" class="mt-2" alt="" />
                                       <img class="dropdown-done-img" style="display: none" src="{{asset('/fees_onboarding/done.png')}}" width="22px" height="22px" class="mt-2" alt="" />
                                    </div>
                                    <div id="sub-step-5" class="d-flex align-items-center justify-content-around" >
                                       <a class="px-3 no-active dropdown-item dropdown-sub-p" href="#" >5) Fees Receipt Book <br> Master</a >
                                       <img class="dropdown-arrow-img" style="display: none" src="{{asset('/fees_onboarding/icons8-arrow-26.png')}}" width="22px" height="22px" class="mt-2" alt="" />
                                       <img class="dropdown-done-img" style="display: none" src="{{asset('/fees_onboarding/done.png')}}" width="22px" height="22px" class="mt-2" alt="" />
                                    </div>
                                    <div id="sub-step-6" class="d-flex align-items-center justify-content-around" >
                                       <a class="px-3 no-active dropdown-item dropdown-sub-p" href="#" >6) Fees Breakoff</a >
                                       <img class="dropdown-arrow-img" style="display: none" src="{{asset('/fees_onboarding/icons8-arrow-26.png')}}" width="22px" height="22px" class="mt-2" alt="" />
                                       <img class="dropdown-done-img" style="display: none" src="{{asset('/fees_onboarding/done.png')}}" width="22px" height="22px" class="mt-2" alt="" />
                                    </div>
                                 </div>
                              </div>
                              <div class="done-img" id="step-2-check" style="display: none" >
                                 <img src="{{asset('/fees_onboarding/done.png')}}" width="22px" alt="" />
                              </div>
                           </div>
                        </div>
                     </div>
                  </div>
                  <div class="mt-3 mb-3 d-flex align-items-center justify-content-between" id="step-3" >
                     <div class="d-flex">
                        <div class="d-flex flex-column align-items-center">
                           <div class="disc"></div>
                           <div class="line"></div>
                        </div>
                        <div class="ml-2 mt-n1">
                           <p class="mb-1 main-p">Step 3</p>
                           <p class="sub-p">Import Data</p>
                        </div>
                     </div>
                     <div class="arrow-img" style="display: none">
                        <img src="{{asset('/fees_onboarding/icons8-arrow-26.png')}}" width="22px" alt="" />
                     </div>
                     <div class="done-img" style="display: none">
                        <img src="{{asset('/fees_onboarding/done.png')}}" width="22px" alt="" />
                     </div>
                  </div>
                  <div class="mt-3 mb-3 d-flex align-items-center justify-content-between" id="step-4" >
                     <div class="d-flex">
                        <div class="d-flex flex-column align-items-center">
                           <div class="disc"></div>
                        </div>
                        <div class="ml-2 mt-n1">
                           <p class="mb-1 main-p">Step 4</p>
                           <p class="sub-p">Roles & Responsibilities</p>
                        </div>
                     </div>
                     <div class="arrow-img" style="display: none">
                        <img src="{{asset('/fees_onboarding/icons8-arrow-26.png')}}" width="22px" alt="" />
                     </div>
                     <div class="done-img" style="display: none">
                        <img src="{{asset('/fees_onboarding/done.png')}}" width="22px" alt="" />
                     </div>
                  </div>
               </div>
            </div>
            <div class="col-md-9">
               <div class="card shadow" style="border-radius: 25px">
                  <!-- Screen 1 -->
                  <div id="screen-1" class="card-body">
                  <h5 class="card-title">Check your Account Details</h5>
                            <div class="row">
                               <div class="col-md-4 form-group">
                                  <label>Account Number </label>
                                  <input type="text" id='account_number' required @if(isset($data[ 'userdata'][ 'user_name'])) value="{{str_pad($data['userdata']['id'], 5, '0', STR_PAD_LEFT)}}"
                                  @endif readonly="readonly" class="form-control">
                               </div>
                               <div class="col-md-4 form-group">
                                  <label>Creation Date </label>
                                  <input type="text" id='created_at' required @if(isset($data[ 'schooldata'][ 'created_at'])) value="{{date('d-m-Y',strtotime($data['schooldata']['created_at']))}}"
                                  @endif readonly="readonly" class="form-control">
                               </div>
                               <div class="col-md-4 form-group">
                                  <label>School Name </label>
                                  <input type="text" id='school_name' @if(isset($data[ 'schooldata'][ 'SchoolName'])) value="{{$data['schooldata']['SchoolName']}}"
                                  @endif required name='school_name' readonly="readonly" class="form-control">
                               </div>
                               <div class="col-md-4 form-group">
                                  <label>User Name </label>
                                  <input type="text" id='contact_person' @if(isset($data[ 'userdata'][ 'user_name'])) value="{{$data['userdata']['user_name']}}"
                                  @endif required name='contact_person' readonly="readonly" class="form-control">
                               </div>
                               <div class="col-md-4 form-group">
                                  <label>Mobile </label>
                                  <input type="text" @if(isset($data[ 'userdata'][ 'mobile'])) value="{{$data['userdata']['mobile']}}" @endif readonly="readonly"
                                  id='mobile' required name='mobile' class="form-control">
                               </div>
                               <div class="col-md-4 form-group">
                                  <label>Email </label>
                                  <input type="text" id='email' @if(isset($data[ 'userdata'][ 'email'])) value="{{$data['userdata']['email']}}" @endif required
                                  name='email' class="form-control" readonly="readonly">
                               </div>
                               <div class="col-md-4 form-group">
                                  <label>First Name </label>
                                  <input type="text" id='first_name' @if(isset($data[ 'userdata'][ 'first_name'])) value="{{$data['userdata']['first_name']}}"
                                  @endif required name='first_name' class="form-control" readonly="readonly">
                               </div>
                               <div class="col-md-4 form-group">
                                  <label>Last Name </label>
                                  <input type="text" id='last_name' @if(isset($data[ 'userdata'][ 'last_name'])) value="{{$data['userdata']['last_name']}}"
                                  @endif required name='last_name' class="form-control" readonly="readonly">
                               </div>
                            </div>
                         </div>
                         <!-- Screen 2 -->
                  <div id="screen-2" class="card-body d-none">
                  <h5 class="card-title">Fees Map Year</h5>
                                <div class="row">
                                    <div class="col-md-4 form-group">
                                        <label for="fee_interval">Select Fee Type:</label>
                                        <select name="fee_type" id="fee_type" class="form-control" required>
                                            <option value="">Select Type</option>
                                            <option value="yearly_fees">Yearly Fees</option>
                                            <option value="half_year_fees">Half Year Fees</option>
                                            <option value="quarterly_fees">Quarterly Fees</option>
                                            <option value="monthly_fees">Monthly Fees</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4 form-group ml-0 mr-0">
                                        <label>Starting Month</label>
                                        <select name="start_month" id="start_month" class="form-control" required>
                                            <option value="">--Select--</option>
                                        @foreach ($data['map_year']->data->ddMonth as $id => $arr) 
                                            <option value='{{$id}}'>{{$arr}}</option>";
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-4 form-group ml-0">
                                        <label>Ending Month</label>
                                        <select name="end_month" id="end_month" class="form-control" required>
                                            <option value="">--Select--</option>
                                        @foreach ($data['map_year']->data->ddMonth as $id => $arr) 
                                                <option value='{{$id}}'>{{$arr}}</option>
                                        @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-12 form-group ml-0">
                                        <input type="submit" name="submit" value="Save" class="btn btn-success" onclick="addFeesMapYear()">
                                    </div>
                                </div>
                         </div>
                  <!-- Screen 3 -->
                  <div id="screen-3" class="card-body d-none">
                  <h5 class="card-title">Fees Title</h5>
                            <div class="row">
                              <div class="col-md-3 form-group">
                                  <label>Fees Title</label>
                                  <select name="fees_title_id" id="fees_title_id" class="form-control van">
                                      <option value="">--Select--</option>
                                     @foreach ($data['feesTitle']->data->ddTtitle as $id => $arr) 
                                          <option value='{{$id}}'>{{$arr}}</option>
                                    @endforeach
                                  </select>
                              </div>
                              <div class="col-md-3 form-group">
                                  <label>Display Name</label>
                                  <input type="text" id='display_name' required name="display_name" class="form-control">
                              </div>
                              <div class="col-md-3 form-group">
                                  <label>Cumulative Name</label>
                                  <input type="text" id='cumulative_name' name="cumulative_name" class="form-control">
                              </div>
                              <div class="col-md-3 form-group">
                                  <label>Append Name</label>
                                  <input type="text" id='append_name' name="append_name" class="form-control">
                              </div>
                              <div class="col-md-3 form-group">
                                  <label>Sort Order</label>
                                  <input type="number" id='sort_order' name="sort_order" class="form-control">
                              </div>
                              <div class="col-md-3 form-group ml-0 mr-0">
                                  <label>Mandetory </label>
                                  <div class="checkbox checkbox-info">
                                      <input id="mandatory" name="mandatory" value="1" type="checkbox">
                                      <label for="mandatory"> Mandatory </label>
                                  </div>
                              </div>
                              <div class="col-md-12 form-group">
                                  <center>
                                    <input type="submit" name="submit" value="Save" class="btn btn-success" onclick="addFeesTitle();">
                                  </center>
                              </div>
                            </div>
                         </div>
                  <!-- Screen 4 -->
                  <div id="screen-4" class="card-body d-none">
                  <h5 class="card-title">Fees Config Master</h5>
                            <div class="row">
                                <div class="col-md-4 form-group">
                                    <label>Late Fees Amount </label>
                                    <input type="number" id='late_fees_amount' required name="late_fees_amount" class="form-control">
                                </div>
    
                                <div class="col-md-4 form-group">
                                    <label>Fees Paid Send SMS</label>
                                    <select name="send_sms" id="send_sms" class="form-control" required>
                                        <option value=""> Select Send Sms </option>
                                        <option value="1"> Yes </option>
                                        <option value="0"> No. </option>
                                    </select>
                                </div>
                                <div class="col-md-4 form-group">
                                    <label>Fees Paid Send Email</label>
                                    <select name="send_email" id="send_email" class="form-control" required>
                                        <option value=""> Select Send Email </option>
                                        <option value="1"> Yes </option>
                                        <option value="0"> No. </option>
                                    </select>
                                </div>
    
                                <div class="col-md-4 form-group">
                                    <label>Fees Receipt Template</label>
                                    <select name="fees_receipt_template" id="fees_receipt_template" class="form-control" required>
                                        <option value=""> Select Receipt Template </option>
                                        <option value="A5"> A5 </option>
                                        <option value="A5DB"> A5 Double </option>
                                        <option value="A4"> A4 </option>
                                        <option value="A4DB"> A4 Double </option>
                                    </select>
                                </div>
                                <div class="col-md-4 form-group">
                                    <label>Fees Bank Challan Template</label>
                                    <select name="fees_bank_challan_template" id="fees_bank_challan_template" class="form-control" required>
                                        <option value=""> Select Bank Challan Template </option>
                                        <option value="template_1"> Template 1 </option>
                                        <option value="template_2"> Template 2 </option>
                                        <option value="template_3"> Template 3 </option>
                                        <option value="template_4"> Template 4 </option>
                                    </select>
                                </div>
                                <div class="col-md-4 form-group">
                                    <label>Fees Receipt Note</label>
                                    <textarea placeholder="Please write fees notes" id='fees_receipt_note' required name="fees_receipt_note" class="form-control"></textarea>
                                </div>
                                <div class="col-md-4 form-group">
                                    <label>Institute Name </label>
                                    <input type="text" id='institute_name' required name="institute_name" class="form-control">
                                </div>
                                <div class="col-md-4 form-group">
                                    <label>Pan No. </label>
                                    <input type="text" id='pan_no' required name="pan_no" class="form-control">
                                </div>
                                <div class="col-md-4 form-group">
                                    <label>Account To Be Credited </label>
                                    <input type="text" id='account_to_be_credited' required name="account_to_be_credited" class="form-control">
                                </div>
                                <div class="col-md-4 form-group">
                                    <label>CMS Client Code </label>
                                    <input type="text" id='cms_client_code' required name="cms_client_code" class="form-control">
                                </div>
                                <div class="col-md-4 form-group">
                                    <label>Auto Head Counting </label>
                                    <input type="checkbox" id='auto_head_counting' value="1" name="auto_head_counting">
                                </div>
                               <div class="col-md-4 form-group">
                                        <label>Month Beside Fees Heading </label>
                                        <input type="checkbox" id='show_month' value="1" name="show_month">
                                     </div>
                                     <div class="col-md-4 form-group">
                                        <label>NACH Account Type</label>
                                        <select name="nach_account_type" id="nach_account_type" class="form-control" required>
                                           <option value=""> Select Account Type </option>
                                           <option value="saving"> Saving Account </option>
                                           <option value="current"> Current Account </option>
                                           <option value="cash"> Cash / Credit </option>
                                        </select>
                                     </div>
                                     <div class="col-md-4 form-group">
                                        <label>NACH Registration Charge </label>
                                        <input type="number" id='nach_registration_charge' required name="nach_registration_charge" class="form-control">
                                     </div>
                                     <div class="col-md-4 form-group">
                                        <label>NACH Transaction Charge </label>
                                        <input type="number" id='nach_transaction_charge' required name="nach_transaction_charge" class="form-control">
                                     </div>
                                     <div class="col-md-4 form-group ml-0 mr-0">
                                        <label>NACH Failed Charge </label>
                                        <input type="number" id='nach_failed_charge' required name="nach_failed_charge" class="form-control">
                                     </div>
                                     <div class="col-md-4 form-group ml-0">
                                        <label for="input-file-now">Bank Logo</label>
                                        <input type="file" accept="image/*" name="fees_bank_logo" id="input-file-now" class="dropify" />
                                     </div>
                                     <div class="col-md-12 form-group">
                                        <center>
                                           <input type="submit" name="submit" value="Save" class="btn btn-success" onclick="addConfig()">
                                        </center>
                                     </div>
                                  </div>
                         </div>
                     <!-- Screen 5 -->
                        <div id="screen-5" class="card-body d-none">
                           <h5 class="card-title">Fees Month Header</h5>
                              
                           <div class="row">
                              <div class="col-md-12 form-group">
                                    <div class="table-responsive">
                                       <table id="example" class="table table-striped">
                                          <thead>
                                                <tr>
                                                   <th>Month</th>
                                                   <th style="text-align: left;">Header</th>
                                                </tr>
                                          </thead>
                                          @foreach($data['feesMonthHeader']->data->ddMonth as $key => $value)
                                                @php
                                                   $existingHeader = null;
                                                   foreach($data['feesMonthHeader']->month_header as $row) {
                                                      if ($row->month_id == $key) {
                                                            $existingHeader = $row->header;
                                                            break;
                                                      }
                                                   }
                                                @endphp
                                                <tr>
                                                   <td>{{ $value }}</td>
                                                   <td>
                                                      <input type="text" name="month_value[{{$key}}]" id="monthHeader" value="{{ $existingHeader ?? null }}" placeholder="Enter data">
                                                   </td>
                                                </tr>
                                          @endforeach
                                       </table>
                                    </div>
                              </div>
                              <div class="col-md-12 form-group">
                                    <center>
                                       <button type="submit" class="btn btn-info">Submit</button>
                                    </center>
                              </div>
                           </div>
            
                        </div>
                     <!-- Screen 6 -->
                          <div id="screen-6" class="card-body d-none">
                           <h5 class="card-title">Fees Receipt Book Master</h5>
                          
                        <div class="row">
                            <div class="col-md-4 form-group">
                                <label>Receipt Line 1 </label>
                                <input type="text" id='receipt_line_1' required name="receipt_line_1" class="form-control">
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Receipt Line 2 </label>
                                <input type="text" id='receipt_line_2' required name="receipt_line_2" class="form-control">
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Receipt Line 3 </label>
                                <input type="text" id='receipt_line_3'  name="receipt_line_3" class="form-control">
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Receipt Line 4 </label>
                                <input type="text" id='receipt_line_4'  name="receipt_line_4" class="form-control">
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Receipt Prefix </label>
                                <input type="text" id='receipt_prefix'  name="receipt_prefix" class="form-control">
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Receipt Postfix </label>
                                <input type="text" id='receipt_postfix'  name="receipt_postfix" class="form-control">
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Account Number </label>
                                <input type="text" id='account_number' name="account_number" class="form-control">
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Sort Order </label>
                                <input type="text" id='sort_order' name="sort_order" class="form-control" required>
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Last Receipt Number </label>
                                <input type="number" id='last_receipt_number'  name="last_receipt_number" class="form-control" value="0">
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Pan </label>
                                <input type="text" id='pan'  name="pan" class="form-control">
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Bank Branch </label>
                                <input type="text" id='branch'  name="branch" class="form-control">
                            </div>

                            <div class="col-md-4 form-group">
                                <label>Bank Logo </label>
                                <input type="file" accept="image/*" name="fees_bank_logo" id="bank-logo" class="dropify" />
                            </div>
                            <div class="col-md-4 form-group" hidden="hidden">
                                <label>Receipt Id </label>
                                <input type="hidden" id='receipt_id' value="@if(isset($data['data']->receipt_id)){{ $data['data']->receipt_id }}@endif" name="receipt_id" class="form-control">
                            </div>
                            {{ App\Helpers\SearchChain('4','multiple','grade,std') }}
                            <div class="col-md-4 form-group">
                                <label>Fees Head</label>
                                <select name="fees_head_id[]" id="fees_head_id" class="form-control" required multiple>
                                    @foreach($data['feesReceiptBook']->feeHeadList as $key => $value)
                                        <option value="{{$value->id}}">{{$value->display_name}}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4 form-group" id="imagelogo">
                                <label for="input-file-now">Receipt Logo</label>
                                <input type="file" accept="image/*" name="fees_receipt_logo" id="fees_receipt_logo" class="dropify" />
                            </div>
                            <div class="col-md-12 form-group">
                                <center>
                                    <input type="submit" name="submit" value="Save" class="btn btn-success" onclick="addReceiptBook()">
                                </center>
                            </div>
                        </div>

                        </div>
                        <!-- Screen 7 -->
                        <div id="screen-7" class="card-body d-none">
                           <h5 class="card-title">Fees Breakoff</h5>
                           <form action="{{ route('fees_breackoff.store') }}" method="post" target="_blank">
                           <div class="row">
                        <div class="col-md-12 form-group">
                            <div class="row" id="breakoffData">
                              {{ App\Helpers\SearchChain('4','multiple','grade') }}
                              <div class="col-md-4">
                                 <label for="">Select Standard</label>
                                 <select name="standard[]" id="standard_id" class="form-control" multiple>

                                 </select>
                              </div>
                             
                            </div>

                        </div>
                        @foreach ($data['feesBreakoff']->data->ddMonth as $id => $val)
                           <div class="col-md-3 form-group month-option">
                              <input class="monthclass breakoffmonths" name="month[{{$id}}]" id="breakoffmonths{{$id}}" value="{{$id}}" type="checkbox">
                              <label>{{$val}}</label>
                           </div>
                        @endforeach

                        <div class="col-md-12 form-group">
                          <center>
                            <input type="submit" name="submit" value="Add Fees Structure" class="btn btn-success" >
                          </center>
                        </div>
                      </div>
                     </form>
                        </div>
                  <!-- Screen 8 -->
                  <div id="screen-8" class="card-body d-none">
                     <h5 class="card-title">CSV File to Import</h5>
                     <form class="form-horizontal" method="POST" action="{{ route('import_parse') }}"
                              enctype="multipart/form-data" target="_blank">
                            @csrf
                            <div class="form-group{{ $errors->has('csv_file') ? ' has-error' : '' }}">
                                <label for="csv_file" class="col-md-4 control-label">CSV file to import</label>
                                <div class="col-md-6">
                                    <label for="email">Module :</label>
                                    <select name="tablename" id="table" class="form-control" required>
                                        <option value=""> Select Module Name</option>
                                        @foreach($data['importData'] as $value)
                                            @if($value->is_customized_table == 1)<option
                                                value="{{$value->table_name}}">{{ $value->display_table_name }}</option>@endif
                                        @endforeach
                                    </select>
                                </div>
                                @if ($errors->has('tablename'))
                                    <span class="help-block">
                                        <strong style="color: red">{{ $errors->first('tablename') }}</strong>
                                    </span>
                                @endif

                                <div class="col-md-6">
                                    <input id="csv_file" type="file" class="form-control" name="csv_file" required>

                                    @if ($errors->has('csv_file'))
                                        <span class="help-block">
                                        <strong style="color: red">{{ $errors->first('csv_file') }}</strong>
                                    </span>
                                    @endif
                                </div>
                            </div>

                            <div class="form-group">
                                <div class="col-md-6 col-md-offset-4">
                                    <div class="checkbox">
                                        <label>
                                            <input type="checkbox" name="header" checked> File contains header row?
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <div class="col-md-8 col-md-offset-4">
                                    <button type="submit" class="btn btn-primary">
                                        Next
                                    </button>
                                </div>
                            </div>
                        </form>
                  </div>
                  <!-- Screen 9 -->
                  <div id="screen-9" class="card-body d-none">
                     <h5 class="card-title">Roles & Responsibilities</h5>
                     <form>
                        <div class="form-row">
                           <div class="form-group col-md-12">
                              <label for="responsabilities">Select Roles & Responsibilities</label>
                              <select class="form-control" id="responsabilities">
                                 <option>Select Profile</option>
                              @if(isset($data['roles']['Fees']) && !empty($data['roles']['Fees']))
                                 @foreach($data['roles']['Fees'] as $profilenames => $values)
                                    <option value="{{$profilenames}}" data-val="{{$values->id}}">{{$profilenames}}</option>
                                 @endforeach
                              @endif
                              </select>
                           </div>
                           @if(isset($data['roles']['Fees']) && !empty($data['roles']['Fees']))
                              @foreach($data['roles']['Fees'] as $profilenames => $values)
                                 <div class="profileDiv form-group col-md-12" id="profilesDiv_{{$values->id}}">
                                       {!! $values->text !!}
                                 </div>
                              @endforeach
                           @endif
                        </div>
                     </form>
                  </div>
               </div>
            </div>
         </div>
            <!-- end of steps  -->
         </div>
         <!-- BODY END -->
         </div>
         <div class="modal-footer">
            <!-- buttons save  -->
            <div class="button-container" style="right: 6px">
               <button id="backButton" type="button" class="btn mr-2" style="color: #5c4ac7; border: 2px solid #5c4ac7; width: 144px">
               Back
               </button>
               <button id="saveBtn" type="submit" class="btn" style="background-color: #5c4ac7; color: white; width: 144px">
               Continue
               </button>
            </div>
            <!-- buttons ends -->	
         </div>
      </div>
   </div>
</div>

<script>
   $(document).ready(function(){
      $('.profileDiv').toggleClass('hide');
   })
    function addFeesMapYear(){
        var fee_type = $('#fee_type').val();
        var start_month = $('#start_month').val();
        var end_month = $('#end_month').val();
        
        // alert(fee_type+'-'+start_month+'-'+end_month);
        $.ajax({
            url: "{{route('map_year.store')}}",
            data : {'type':"API","fee_type":fee_type,"start_month":start_month,"end_month":end_month},
            type:"POST",
            success : function(result){
                alert('Fees Map added successfully!');
            }, 
            error: function(xhr, status, error) {
               console.error(xhr.responseText);
               alert('Error adding fees title. Please check the console for details.');
            }
        })
    }

    function addFeesTitle(){
        var fees_title_id = $('#fees_title_id').val();
        var display_name = $('#display_name').val();
        var cumulative_name = $('#cumulative_name').val(); 
        var append_name = $('#append_name').val();
        var sort_order = $('#sort_order').val();
        var mandatory = $('#mandatory').val();

        $.ajax({
            url: "{{route('fees_title.store')}}",
            data : {'type':"API","fees_title_id":fees_title_id,"display_name":display_name,"cumulative_name":cumulative_name,"append_name":append_name,"sort_order":sort_order,"mandatory":mandatory},
            type:"POST",
            success : function(result){
                alert('Fees Title added successfully!');
            }, 
            error: function(xhr, status, error) {
            console.error(xhr.responseText);
            alert('Error adding fees title. Please check the console for details.');
         }
        })

    }

    function addConfig(){
      var late_fees_amount = $('#late_fees_amount').val();
      var send_sms = $('#send_sms').val();
      var send_email = $('#send_email').val(); 
      var fees_receipt_template = $('#fees_receipt_template').val();
      var fees_bank_challan_template = $('#fees_bank_challan_template').val();
      var institute_name = $('#institute_name').val();
      var pan_no = $('#pan_no').val();
      var account_to_be_credited = $('#account_to_be_credited').val();
      var cms_client_code = $('#cms_client_code').val();
      var auto_head_counting = $('#auto_head_counting').val();
      var show_month = $('#show_month').val();
      var nach_account_type = $('#nach_account_type').val();
      var nach_registration_charge = $('#nach_registration_charge').val();
      var nach_failed_charge = $('#nach_failed_charge').val();
      var input_file_now = $('#input-file-now').val();

      $.ajax({
         url: "{{route('fees_config_master.store')}}",
         data: {
            'late_fees_amount': late_fees_amount,
            'send_sms': send_sms,
            'send_email': send_email,
            'fees_receipt_template': fees_receipt_template,
            'fees_bank_challan_template': fees_bank_challan_template,
            'institute_name': institute_name,
            'pan_no': pan_no,
            'account_to_be_credited': account_to_be_credited,
            'cms_client_code': cms_client_code,
            'auto_head_counting': auto_head_counting,
            'show_month': show_month,
            'nach_account_type': nach_account_type,
            'nach_registration_charge': nach_registration_charge,
            'nach_failed_charge': nach_failed_charge,
            'fees_bank_logo': input_file_now
         },
         type: "POST",
         success: function(result) {
            alert('Fees Config added successfully!');
         },
         error: function(xhr, status, error) {
            console.error(xhr.responseText);
            alert('Error adding fees title. Please check the console for details.');
         }
      });

    }

    function addMonthHeader(){
      var formData = {
         'month_value': {}
      };

      $('#monthHeader input[type="text"]').each(function() {
         var monthId = $(this).attr('name').match(/\[(.*?)\]/)[1];
         var headerValue = $(this).val();
         formData['month_value'][monthId] = headerValue;
      });

         // AJAX request to send formData
         $.ajax({
            url: "{{ route('fees_month_header.store') }}",
            type: "POST",
            data: formData,
            success: function(response) {
                  alert('Fees Month Header added successfully!');
            },
            error: function(xhr, status, error) {
                  console.error(xhr.responseText);
                  alert('Error submitting data. Please check the console for details.');
            }
         });
    }

    function addReceiptBook(){
      var receipt_line_1 = $('#receipt_line_1').val();
      var receipt_line_2 = $('#receipt_line_2').val();
      var receipt_line_3 = $('#receipt_line_3').val();
      var receipt_line_4 = $('#receipt_line_4').val();
      var receipt_prefix = $('#receipt_prefix').val();
      var receipt_postfix = $('#receipt_postfix').val();
      var account_number = $('#account_number').val();
      var sort_order = $('#sort_order').val();
      var last_receipt_number = $('#last_receipt_number').val();
      var pan = $('#pan').val();
      var branch = $('#branch').val();
      var fees_bank_logo = $('#bank-logo').val();
      var grade = $('#grade').val();
      var standard = $('#standard').val();
      var fees_head_id = $('#fees_head_id').val();
      var fees_receipt_logo = $('#fees_receipt_logo').val();

       // AJAX request to send formData
       $.ajax({
            url: "{{ route('fees_receipt_book_master.store') }}",
            type: "POST",
            data: {type:"API",receipt_line_1:receipt_line_1,receipt_line_2:receipt_line_2,receipt_line_3:receipt_line_3,receipt_line_4:receipt_line_4,receipt_prefix:receipt_prefix,receipt_postfix:receipt_postfix,account_number:account_number,sort_order:sort_order,last_receipt_number:last_receipt_number,pan:pan,branch:branch,fees_bank_logo:fees_bank_logo,grade:grade,standard:standard,fees_head_id:fees_head_id,fees_receipt_logo:fees_receipt_logo},
            success: function(response) {
               if (typeof response === 'string') {
                     response = JSON.parse(response);
               }
               alert(response.message);
            },
            error: function(xhr, status, error) {
                  console.error(xhr.responseText);
                  alert('Error submitting data. Please check the console for details.');
            }
         });

    }
   //  get standard for fees breakoff
    $('#breakoffData #grade').on('click',function(){
      var selectedGrades = $(this).val();
      $('#standard_id').empty();

       if (selectedGrades) {
        var gradesParam = selectedGrades.join(','); 
         $.ajax({
               url: "/api/get-standard-list?grade_id="+selectedGrades,
               type: "GET",
               success: function(response) {

                  $.each(response, function(index, item) {
                    $('#standard_id').append('<option value="'+index+'">' + item + '</option>');
                });
               },
               error: function(xhr, status, error) {
                     console.error(xhr.responseText);
                     alert('Error submitting data. Please check the console for details.');
               }
            });
         }
    })

   function addBk() {
      var grade = $('#breakoffData #grade').val();
    var standard = $('#standard_id').val();
    var division = $('#division_id').val();
    var formData = {};

    // Ensure the selector targets the correct checkboxes
    $('.monthclass:checked').each(function() {
        var monthId = $(this).attr('name').match(/\[(.*?)\]/)[1];
        var headerValue = $(this).val();
        formData[monthId] = headerValue;
    });

    $.ajax({
        url: "{{ route('fees_breackoff.store') }}",
        type: "POST",
        data: {
            type: "API",
            grade: grade,
            standard: standard,
            division: division,
            month: formData,
            getPath : 1,
        },
        success: function(response) {
         console.log(response);
         if (typeof response === 'string') {
            response = JSON.parse(response);
         }

         // Check if response.data and response.data.path exist
         if (response.data && response.data.path) {
               var path = response.data.path;
               window.open(path, '_blank');
         } else {
               alert('Failed to add: path not found in the response.');
         }
        },
        error: function(xhr, status, error) {
            console.error(xhr.responseText);
            alert('Error submitting data. Please check the console for details.');
        }
    });
}
$('#responsabilities').on('change',function(){
   var selectedOption = $(this).find('option:selected');
   var dataVal = selectedOption.attr('data-val');

   $('[id^="profilesDiv_"]').hide();

   $('#profilesDiv_' + dataVal).toggleClass('show', true).show();
})
</script>

<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
<script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
<script src="{{asset('/fees_onboarding/script.js')}}"></script>