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
            <div class="row felx-nowrap">
               <!-- left side bar start  -->
               <div class="col-md-3 bg-white" style="border-radius: 25px;">
                  <div class="mt-4 d-block list-group h-100 justify-content-around">
                     <div class="mt-3 mb-3 d-flex align-items-center justify-content-between" id="step-1">
                        <div class="d-flex">
                           <div class="d-flex flex-column align-items-center">
                              <div class="disc disc-active"></div>
                              <div class="line"></div>
                           </div>
                           <div class="ml-2 mt-n1">
                              <p class="mb-1" style="color: #5c4ac7">Step 1</p>
                              <p>Check Account Details</p>
                           </div>
                        </div>
                        <div>
                           <img src="{{asset('/fees_onboarding/icons8-arrow-26.png')}}" width="22px" alt="">
                        </div>
                     </div>
                     <div class="mt-3 mb-3 d-flex align-items-center justify-content-between" id="step-2">
                        <div class="d-flex">
                           <div class="d-flex flex-column align-items-center">
                              <div class="disc"></div>
                              <div class="line fee-setup-line"></div>
                           </div>
                           <div class="ml-2 mt-n1">
                              <p class="mb-1">Step 2</p>
                              <div class="d-flex justify-content-between" style="width: 212px">
                                 <div class="dropdown">
                                    <div type="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                       <p class="d-inline mr-2">Fees Setup</p>
                                       <img class="arrow-down" src="{{asset('/fees_onboarding/arrow-down-sign-to-navigate.png')}}" height="12">
                                    </div>
                                    <div class="dropdown-menu show" aria-labelledby="dropdownMenuButton" style="width: 212px">
                                       <div class="line" style="
                                          position: absolute;
                                          height: 190px;
                                          background-color: #5c4ac7;
                                          "></div>
                                       <div class="sub-step-1 d-flex align-items-center justify-content-around">
                                          <a class="px-3 no-active dropdown-item" href="#">1) Fees Map Year</a>
                                          <img class="d-none" src="{{asset('/fees_onboarding/icons8-arrow-26.png')}}" width="22px" height="22px" alt="">
                                       </div>
                                       <div class="sub-step-2 d-flex align-items-center justify-content-around">
                                          <a class="px-3 no-active dropdown-item" href="#">2) Fees Title</a>
                                          <img class="d-none" src="{{asset('/fees_onboarding/icons8-arrow-26.png')}}" width="22px" height="22px" alt="">
                                       </div>
                                       <div class="sub-step-3 d-flex align-items-center justify-content-around">
                                          <a class="px-3 no-active dropdown-item" href="#">3) Fees Config Master</a>
                                          <img class="d-none" src="{{asset('/fees_onboarding/icons8-arrow-26.png')}}" width="22px" height="22px" alt="">
                                       </div>
                                       <div class="sub-step-4 d-flex align-items-center justify-content-around">
                                          <a class="px-3 no-active dropdown-item" href="#">4) Fees Month Header</a>
                                          <img class="d-none" src="{{asset('/fees_onboarding/icons8-arrow-26.png')}}" width="22px" height="22px" alt="">
                                       </div>
                                       <div class="sub-step-5 d-flex align-items-center justify-content-around">
                                          <a class="px-3 no-active dropdown-item" href="#">5) Fees Receipt Book Master</a>
                                          <img class="d-none" src="{{asset('/fees_onboarding/icons8-arrow-26.png')}}" width="22px" height="22px" alt="">
                                       </div>
                                       <div class="sub-step-6 d-flex align-items-center justify-content-around">
                                          <a class="px-3 no-active dropdown-item" href="#">6) Fees Breakoff</a>
                                          <img class="d-none" src="{{asset('/fees_onboarding/icons8-arrow-26.png')}}" width="22px" height="22px" alt="">
                                       </div>
                                    </div>
                                 </div>
                                 <div class="d-none" id="step-2-check">
                                    <img src="{{asset('/fees_onboarding/done.png')}}" width="22px" alt="done">
                                 </div>
                              </div>
                           </div>
                        </div>
                     </div>
                     <div class="mt-3 mb-3 d-flex align-items-center justify-content-between" id="step-3">
                        <div class="d-flex">
                           <div class="d-flex flex-column align-items-center">
                              <div class="disc"></div>
                              <div class="line"></div>
                           </div>
                           <div class="ml-2 mt-n1">
                              <p class="mb-1">Step 3</p>
                              <p>Import Date</p>
                           </div>
                        </div>
                        <div class="d-none arrow-img">
                           <img src="{{asset('/fees_onboarding/icons8-arrow-26.png')}}" width="22px" alt="">
                        </div>
                     </div>
                     <div class="mt-3 mb-3 d-flex align-items-center justify-content-between" id="step-4">
                        <div class="d-flex">
                           <div class="d-flex flex-column align-items-center">
                              <div class="disc"></div>
                           </div>
                           <div class="ml-2 mt-n1">
                              <p class="mb-1">Step 4</p>
                              <p>Roles &amp; Responsibilities</p>
                           </div>
                        </div>
                        <div class="d-none arrow-img">
                           <img src="{{asset('/fees_onboarding/icons8-arrow-26.png')}}" width="22px" alt="">
                        </div>
                     </div>
                  </div>
               </div>
               <!-- left side bar end  -->
               <!-- right side bar start  -->
               <div class="col-md-9  bg-white">
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
                       <!-- Screen 6 -->
                       <div id="screen-6" class="card-body d-none">
                        <h5 class="card-title">Fees Receipt Book Master</h5>
                        <div class="row">
                      
                        </div>
                     </div>
                    
                     <!-- SCREEN ENDS -->
                  </div>
               </div>
               <!-- right side bar end  -->
            </div>
            <!-- </div> -->
            <!-- end of steps  -->
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
</script>

<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
<script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
<script src="{{asset('/fees_onboarding/script.js')}}"></script>