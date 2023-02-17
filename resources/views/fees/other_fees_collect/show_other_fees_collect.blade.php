@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Other Fees Collect</h4>
            </div>
        </div>
        @php
        $grade_id = $standard_id = $division_id = $enrollment_no = $first_name = $last_name = $mobile_no = $uniqueid = $from_date = '';
            if(isset($data['grade_id'])){
                $grade_id = $data['grade_id'];
                $standard_id = $data['standard_id'];
                $division_id = $data['division_id'];
            }

            if(isset($data['first_name']))
            {
                $first_name = $data['first_name'];
            }
            if(isset($data['last_name']))
            {
                $last_name = $data['last_name'];
            }
            if(isset($data['enrollment_no']))
            {
                $enrollment_no = $data['enrollment_no'];
            }
            if(isset($data['mobile_no']))
            {
                $mobile_no = $data['mobile_no'];
            }
            if(isset($data['uniqueid']))
            {
                $uniqueid = $data['uniqueid'];
            }

        @endphp
        <div class="card">            
            @if ($sessionData = Session::get('data'))
            <div class="alert alert-success alert-block">
                <button type="button" class="close" data-dismiss="alert">×</button>
                <strong>{{ $sessionData['message'] }}</strong>
            </div>
            @endif
            <form action="{{ route('other_fees_collect.create') }}">  
                <div class="row">                    
                    {{ App\Helpers\SearchChain('4','single','grade,std,div',$grade_id,$standard_id,$division_id) }}
                    <div class="col-md-4 form-group">
                        <label for="other_fees_title">Other Fees Title(Head)</label>
                        <select name="other_fees_title" id="other_fees_title" class="form-control" required="required">
                            <option value="">Select Other Fees Title</option>
                            @foreach($data['other_fees_title'] as $key => $value)
                                <option value="{{$value['id']}}"
                                @if(isset($data['other_fees_title_selected']))
                                    @if($data['other_fees_title_selected'] == $value['id'])
                                    selected='selected'
                                    @endif
                                @endif
                                >{{$value['display_name']}}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4 form-group">
                        <label>First Name</label>
                        <input type="text" id="first_name" value="{{$first_name}}" name="first_name" class="form-control">
                    </div>
                    <div class="col-md-4 form-group">
                        <label>Last Name</label>
                        <input type="text" id="last_name" value="{{$last_name}}" name="last_name" class="form-control">
                    </div>
                    <div class="col-md-4 form-group">
                        <label>Enrollment No</label>
                        <input type="text" id="enrollment_no" name="enrollment_no" value="{{$enrollment_no}}" class="form-control">
                    </div>
                    <div class="col-md-4 form-group">
                        <label>Mobile No.</label>
                        <input type="text" id="mobile_no" value="{{$mobile_no}}" name="mobile_no" class="form-control">
                    </div>                    
                    <div class="col-md-4 form-group">
                        <label>Unique ID</label>
                        <input type="text" id="uniqueid" value="{{$uniqueid}}" name="uniqueid" class="form-control">
                    </div>
                    <div class="col-sm-12 form-group">
                        <center>                            
                            <input type="submit" name="submit" value="Search" class="btn btn-success" >
                        </center>
                    </div>
                </div>              
            </form>            
        </div>
        @if(isset($data['student_data']))
        @php
            if(isset($data['student_data'])){
                $student_data = $data['student_data'];
                $finalData = $data;
            }
        @endphp

        <div class="card">                
            <form method="POST" action="{{ route('other_fees_collect.store') }}" id="submit_form">
                <center>                            
                    <div class="table-responsive mb-5 w-50">
                        <table class="table table-box table-bordered">
                            <tbody>
                                <tr>
                                    <td class="w-25">Date of Deduction:</td>
                                    <td class="w-25">
                                        <input type="text" id="deduction_date" name="deduction_date" value="@php echo date('Y-m-d'); @endphp" class="form-control mydatepicker">
                                    </td>
                                    <td class="w-25">Other Fees Head:</td>
                                    <td class="w-25 text-center">{{$data['get_name_of_head']}}</td>
                                </tr>
                                <tr>
                                    <td>Payment Mode:</td>
                                    <td>
                                        <select class="form-control" required="required" name="payment_mode" id="payment_mode">
                                        <option value="">Select Payment Mode</option>        
                                        <option value="Cash">Cash</option>        
                                        <option value="Cheque">Cheque</option>        
                                        <option value="DD">DD</option>        
                                        <option value="Online">Online</option>              
                                        <option value="From Imprest">From Imprest</option>        
                                    </select>
                                    </td>
                                    <td>Remarks (if any):</td>
                                    <td>
                                        <input type="text" id="remarks" name="remarks" class="form-control">
                                    </td>
                                </tr>
                                <tr>
                                    <td>Bank Name:</td>
                                    <td>
                                        <select class="form-control" name="bank_name" id="bank_name">
                                            <option value="">Select Bank Name</option>
                                            @if(!empty($data['bank_data']))  
                                            @foreach($data['bank_data'] as $key => $value)
                                                <option value="{{$value['bank_name']}}">{{$value['bank_name']}}</option>
                                            @endforeach
                                            @endif
                                        </select>
                                    </td>
                                    <td>Bank Branch:</td>
                                    <td>
                                        <input type="text" id="bank_branch" name="bank_branch" value="N/A" class="form-control">
                                    </td>
                                </tr>
                                <tr>
                                    <td>Cheque/DD No:</td>
                                    <td>
                                        <input type="text" id="cheque_no" name="cheque_no" value="N/A" class="form-control">
                                    </td>
                                    <td>Cheque/DD Date:</td>
                                    <td>
                                        <input type="text" id="cheque_date" name="cheque_date" value="@php echo date('Y-m-d'); @endphp" class="form-control mydatepicker">
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </center>
                                  

                <div class="row mt-5">                    
                    <div class="col-lg-12 col-sm-12 col-xs-12">
                        <div class="table-responsive">
                            <table class="table table-box table-bordered">
                                <thead>
                                    <tr>
                                        <th><input id="checkall" name="checkall" onchange="checkAll(this);" type="checkbox"></th>
                                        <th>Sr.No.</th>
                                        <th>Student Name</th>
                                        <th>Enrollment No.</th>
                                        <th>Standard</th>
                                        <th>Division</th>
                                        <th>Mobile</th>
                                        <th>Student Quota</th>
                                        <!-- <th>Paid Amount</th> -->
                                        <!-- <th>Remaining Amount</th> -->
                                        <th>Amount of Deduction <input type="checkbox" value="Y" name="aod_chkbx_cmn" id="aod_chkbx_cmn"
                            onclick="fill_all_amount_of_deduction(this);"/>
                          <br/><INPUT type="text" name="aod_txtbx_cmn" id="aod_txtbx_cmn" @if(isset($data['get_amount_of_head'])) value="{{$data['get_amount_of_head']}}" @endif/>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                        @php
                                        $j=1;
                                        @endphp
                                    @foreach($student_data as $key => $data)
                                        @php
                                        if(isset($data['remaining_amt']) && $data['remaining_amt'] != 0 && $data['remaining_amt'] >= 0)
                                        {
                                            $disabled = '';
                                        }else{
                                            $disabled = 'disabled';
                                        }
                                       
                                        @endphp
                                    <tr>
                                        <td>

                                            <input id="students" value="{{$data['student_id']}}" name="students[]" type="checkbox" onclick="required_amount(this.value)" {{$disabled}}></td>
                                        <td>{{$j}}</td>
                                        <td>{{$data['student_name']}}</td>
                                        <td>{{$data['enrollment_no']}}</td>
                                        <td>{{$data['standard_name']}}</td>
                                        <td>{{$data['division_name']}}</td>
                                        <td>{{$data['mobile']}}</td>
                                        <td>{{$data['stu_quota']}}</td>
                                        <!-- <td>{{$data['paid_amt']}}</td> -->
                                        <input type="hidden" name="paid_amt[{{$data['student_id']}}]" id="paid_amt[{{$data['student_id']}}]"value={{$data['paid_amt']}}>
                                        <!-- <td>{{$data['remaining_amt']}}</td> -->
                                        <input type="hidden" name="remaining_amt[{{$data['student_id']}}]" id="remaining_amt[{{$data['student_id']}}]" value={{$data['remaining_amt']}}>
                                        <td>
                                        @if(isset($data['remaining_amt']) && $data['remaining_amt'] != 0 && $data['remaining_amt'] >= 0)
                                            <INPUT type="number" name="amount_of_deduction[{{$data['student_id']}}]" id="amount_of_deduction[{{$data['student_id']}}]" class="form-control cls_txtbx_amount_of_deduction"autocomplete="off" data-value={{$data['student_id']}} />
                                        @else
                                            <span><font color=red>Fees has been already paid.</font></span>
                                        @endif
                                        </td>                                        
                                    </tr>
                                        @php
                                        $j++;
                                        @endphp
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>    
                    <div class="col-md-12 form-group">
                        <center>
                            <input type="hidden" name="division_id" @if(isset($finalData['division_id'])) value="{{$finalData['division_id']}}" @endif>
                        	<input type="hidden" name="standard_id" @if(isset($finalData['standard_id'])) value="{{$finalData['standard_id']}}" @endif>
                            <input type="hidden" name="other_fees_title" @if(isset($finalData['other_fees_title_selected'])) value="{{$finalData['other_fees_title_selected']}}" @endif>                            
                            <input type="hidden" name="other_fees_title_name" @if(isset($finalData['get_name_of_head'])) value="{{$finalData['get_name_of_head']}}" @endif>                            
                            <input type="submit" name="submit" value="Submit" class="btn btn-success" >
                        </center>
                    </div>            
                </div>
            </form>
        </div>
        @endif
    </div>
</div>

@include('includes.footerJs')
<script>
    // $('#grade').attr('required',true);
    // $('#standard').attr('required',true);

    $('#submit_form').submit(function(){ 
        var selected_stud = $("input[name='students[]']:checked").length;
        if(selected_stud == 0)
        {
            alert("Please Select Atleast One Student");
            return false;
        }
        else{
            return true;
        }   
    });

    // $('.cls_txtbx_amount_of_deduction').keyup(function(){
    //   student_id = $(this).attr('data-value');
      
    //   paid_amount = document.getElementById('paid_amt['+student_id+']').value;
    //   if(paid_amount > 0)
    //   {
    //     amount = document.getElementById('remaining_amt['+student_id+']').value;
    //   }else{
    //     amount = document.getElementById('aod_txtbx_cmn').value;
    //   }
    //   if(parseInt($(this).val()) > parseInt(amount)){
    //     alert("No amount allow above "+amount);
    //     $(this).val(amount);
    //   }
    // });

    function required_amount(val){


       document.getElementById('amount_of_deduction['+val+']').required = true;
    }

	function checkAll(ele) {
         // var checkboxes = $("input[name='checkall']");
         // alert(checkboxes);
	     var checkboxes_new = document.getElementsByTagName('input');
         // alert(checkboxes);
         
	     if (ele.checked) {
	         for (var i = 0; i < checkboxes.length; i++) {
	             if (checkboxes[i].type == 'checkbox') {
	                 checkboxes[i].checked = true;
	             }
	         }
	     } else {
	         for (var i = 0; i < checkboxes.length; i++) {
	             console.log(i)
	             if (checkboxes[i].type == 'checkbox') {
	                 checkboxes[i].checked = false;
	             }
	         }
	     }
	}
    function fill_all_amount_of_deduction(element)
    {           
        element = document.getElementById('aod_chkbx_cmn');                                 
        aod_txtbx_cmn_element = document.getElementById('aod_txtbx_cmn');

        if (element.checked == true)
        {   
            all_aod_txtbxs = document.getElementsByClassName('cls_txtbx_amount_of_deduction');   
            for(var i = 0; i < all_aod_txtbxs.length; i++)
            {
                all_aod_txtbxs.item(i).value = aod_txtbx_cmn_element.value;
            }
        }
        else
        {
            all_aod_txtbxs = document.getElementsByClassName('cls_txtbx_amount_of_deduction');   
            for(var i = 0; i < all_aod_txtbxs.length; i++)
            {
                all_aod_txtbxs.item(i).value = 0;  
            }
        }
    }
</script>
@include('includes.footer')
