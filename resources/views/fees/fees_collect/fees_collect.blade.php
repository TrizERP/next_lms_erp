@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<style>
    tr.spaceUnder>th {
        padding-bottom: 1em !important;
    }
</style>
<div id="page-wrapper" style="color:#000;">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Fees Collect</h4> </div>
        </div>
            <div class="card" >
            @if ($sessionData = Session::get('data'))
            @if($sessionData['status_code'] == 1)
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
                                        <th>Paid </th>
                                        <th>Remaining </th>
                                    </tr>
                                    <?php 
                                        $remainFees = 0; 
                                        $feesDetails = [];
                                        foreach ($data['total_fees'] as $id => $arr) { 
                                            $feesDetails[$arr['month']] = $arr['remain'];
                                        ?>
                                        <tr>
                                            <td><?php echo $arr['month']; ?></td>
                                            <td><?php echo $arr['bk']; ?></td>
                                            <td><?php echo $arr['paid']; ?></td>
                                            <td><?php echo $arr['remain']; ?></td>
                                        </tr>
                                    <?php 
                                        $remainFees += $arr['remain'];
                                    } ?>

                                </table>
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
                                            <td><?php echo $data['stu_data']['uniqueid']; ?></td>
                                        </tr>
                                        <tr>
                                            <td>Student Name</td>
                                            <td><?php echo $data['stu_data']['name']; ?></td>
                                        </tr>
                                        <tr>
                                            <td>Admission Year</td>
                                            <td><?php echo $data['stu_data']['admission']; ?></td>
                                        </tr>
                                        <tr>
                                            <td>Parent Email</td>
                                            <td><?php echo $data['stu_data']['email']; ?></td>
                                        </tr>
                                        <tr>
                                            <td>Student Quota</td>
                                            <td><?php echo $data['stu_data']['student_quota']; ?></td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="table-responsive">
                                    <table class="table table-stripped">
                                        <tr>
                                            <td>Gr. No</td>
                                            <td><?php echo $data['stu_data']['enrollment']; ?></td>
                                        </tr>
                                        <tr>
                                            <td>Std-Div</td>
                                            <td><?php echo $data['stu_data']['stddiv']; ?></td>
                                        </tr>
                                        <tr>
                                            <td>Contact No</td>
                                            <td><?php echo $data['stu_data']['mobile']; ?></td>
                                        </tr>
                                        <tr style="color: red;">
                                            <td>Pending Fees</td>
                                            <td><?php echo $data['stu_data']['pending']; ?></td>
                                        </tr>
                                        @if (Session::get('sub_institute_id') == '181')
                                        <tr>
                                            <td>Previous Year Imprest Balance</td>
                                            <td><?php echo $data['stu_data']['previous_year_imprest_balance']; ?></td>
                                        </tr>
                                        @endif
                                    </table>
                                </div>
                            </div>
                        </div>
                       
                        <form action="{{ route('fees_collect.store') }}" enctype="multipart/form-data" method="post">
                            @csrf
                            <input type="hidden" name="grade_id" value="<?php echo $data['stu_data']['grade_id']; ?>">
                            <input type="hidden" name="standard_id" value="<?php echo $data['stu_data']['std_id']; ?>">
                            <input type="hidden" name="div_id" value="<?php echo $data['stu_data']['div_id']; ?>">
                            <input type="hidden" name="student_id" value="<?php echo $data['stu_data']['student_id']; ?>">
                            <input type="hidden" name="std_div" value="<?php echo $data['stu_data']['stddiv']; ?>">
                            <input type="hidden" name="full_name" value="<?php echo $data['stu_data']['name']; ?>">
                            <input type="hidden" name="mobile" value="<?php echo $data['stu_data']['mobile']; ?>">
                            <input type="hidden" name="uniqueid" value="<?php echo $data['stu_data']['uniqueid']; ?>">
                            <input type="hidden" name="enrollment" value="<?php echo $data['stu_data']['enrollment']; ?>">
                           

                            <div class="table-responsive col-md-12" style="border-top: 2px solid black;">
                                <table class="table table-stripped">
                                    <tr>
    <!--                                            <td>
                                            <div class="checkbox checkbox-info">
                                                <input id="months" onclick="checkedAll();" type="checkbox">
                                                <label for="months"> Months </label>
                                            </div>
                                        </td>-->

                                        <?php
                                        foreach ($data['month_arr'] as $id => $val) {
                                            $slected = "";
                                            if (in_array($id, $data['search_ids'])) {
                                                $slected = "checked";
    //                                                    checked
                                            }

                                            $disabled = '';
                                            if ( isset($feesDetails[$val]) && $feesDetails[$val] == 0 ) {
                                                $disabled = 'disabled="disabled"';
                                            }
                                            ?>
                                            <td>
                                                <div class="checkbox checkbox-info">
                                                    <input id="<?php echo $id; ?>" name="months[<?php echo $id; ?>]" value="<?php echo $id; ?>" <?php echo $slected; ?> class="months" type="checkbox" @php
                                                       echo $disabled;
                                                    @endphp>
                                                    <label for="<?php echo $id; ?>"><?php echo $val; ?></label>
                                                </div>
                                            </td>
                                        <?php } ?>

                                    </tr>
                                </table>                            
                            </div>
                            <div class="table-responsive col-md-12">
                                <table class="table table-stripped" border="0" width="100%">
    <!--                                        <tr>
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
                                                    <!--<th colspan="2" align="center" style="width: 40%;align-content: center;">Particular</th>-->
                                                    <th  align="center" style="width: 30%;padding-left: 15px;">Particular</th>
                                                    <th style="width: 10%;padding-left: 15px;">Amount</th>
                                                    <th style="width: 20%;padding-left: 15px;">Collection Amount</th>
                                                    <th style="width: 20%;padding-left: 15px;">Discount</th>
                                                    <th style="width: 20%;padding-left: 15px;">Fine</th>
                                                </tr>
                                                <?php 
                                                foreach ($data['final_fee'] as $id => $val) { ?>
                                                    <tr>
                                                        <!--<td style="width: 20%"></td>-->
                                                        <td style="width: 20%"><?php echo $id; ?></td>
                                                        <td style="width: 20%"><?php echo $val; ?></td>
                                                        <?php                                                        
                                                        
                                                        $auto_head_counting  = $data['fees_config_data']['auto_head_counting'];
                                                        
                                                        if($auto_head_counting == 1)
                                                        {
                                                            $individual_enable = "readonly";
                                                            $total_disable = "";
                                                        }
                                                        else
                                                        {
                                                            $individual_enable = "";
                                                            $total_disable = "readonly";
                                                        }

                                                        // 26/08/2021 Start Added for The Millennium School for Advanced Imprest Collection payment
                                                        if($val < 0){
                                                            $negative_disable = 'readonly';
                                                        }else{
                                                            $negative_disable = '';
                                                        }
                                                        // 26/08/2021 End Added for The Millennium School for Advanced Imprest Collection payment
                                                        if ($id != 'Total') {                                                           
                                                            echo "<td style='width: 20%'><input $individual_enable $negative_disable type='number'  min=0 max=$val value=" . $val . " name='fees_data[" . $data['final_fee_name'][$id] . "]' class='form-control allField1'>
                                                            <input type='hidden' value=" . $val . " name='hid_fees_data[" . $data['final_fee_name'][$id] . "]' class='hid_allField1'>
                                                            </td>";
                                                            echo "<td style='width: 20%'><input type='number' value=0 name='discount_data[" . $data['final_fee_name'][$id] . "]' class='form-control allDisField' style='min-width:150px;'></td>"; // min=0 max=$val
                                                            echo "<td style='width: 20%'><input type='number'  min=0 value=0 name='fine_data[" . $data['final_fee_name'][$id] . "]' class='form-control allFinField' style='min-width:150px;'></td>";
                                                        } else {
                                                            echo "<td style='width: 25%'><input $total_disable id='totalVal' type='text' name='total' value=" . $val . " class='form-control'></td>
                                                            <input type='hidden' value=" . $val . " name='hid_totalVal' id='hid_totalVal'>";
                                                            echo "<td style='width: 25%'><input id='totalDis' type='text' name='totalDis' value=0 class='form-control directdiscount'></td>";
                                                            echo "<td style='width: 25%'><input id='totalFin' type='text' name='totalFin' value=0 class='form-control directfine'></td>";
                                                        }
                                                        ?>
                                                        <!--<td style="width: 25%"><input type="text" class="form-control"></td>-->
                                                    </tr>
                                                <?php } ?>
                                                
                                            </table>
                                        </td>
                                    </tr>
    <!--                                        <tr>
                                        <td></td>
                                        <td>Discount</td>
                                        <td></td>
                                        <td><input type="number" onchange="calculateTotal();" id="discount" name="discount" class="form-control"></td>
                                    </tr>-->
                                    <tr>
                                        <td></td>
                                        <td>Discount Description</td>
                                        <td></td>
                                        <td><input type="text" class="form-control" name="remarks" id="remarks" autocomplete="off"></td>
                                    </tr>
                                    @php                                    
                                       $cheque_return_charges = $data['cheque_return_charges'][0]; 

                                    @endphp
                                    <tr>
                                        <td></td>
                                        <td>Fine(Include Cheque return charges)</td>
                                        <td></td>
                                        <td><input type="text" name="fees_data[fine]" id="cheque_return_charges" class="form-control" value="@php if(isset($cheque_return_charges)) echo $cheque_return_charges; @endphp" readonly="readonly">
                                            <input type="hidden" name="hidden_cheque_return_charges" id="hidden_cheque_return_charges" class="form-control" value="@if(isset($cheque_return_charges)){{$cheque_return_charges}}@endif" >
                                        </td>
                                    </tr>
                                   
                                    <?php
                                        // START 30-12-2021 Added for include cheque return charges in grand total

                                        if(isset($cheque_return_charges) && $cheque_return_charges != '')
                                        {
                                            $grand_total_with_cheque_charges = $data['final_fee']['Total'] + $cheque_return_charges;
                                        }else
                                        {
                                            $grand_total_with_cheque_charges = $data['final_fee']['Total'];
                                        }
                                        // END 30-12-2021 Added for include cheque return charges in grand total

                                    ?>
                                    <tr style="border-bottom: 2px solid black;">
                                        <td></td>
                                        <td>Grand Total</td>
                                        <td></td>
                                        <td><input type="text" id="grandTotal" readonly="" value="<?php echo $grand_total_with_cheque_charges; ?>" class="form-control"></td>
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
                                            </select>
                                        </td>
                                        <td>Receipt Date</td>
                                        <td><input type="text" name="receiptdate" id="receiptdate" class="form-control mydatepicker" autocomplete="off" value="<?php echo date('Y-m-d'); ?>"></td>
                                    </tr>
                                    <tr class="bnakDetail">
                                        <td>Cheque/DD Date</td>
                                        <td><input type="text" name="cheque_date" id="cheque_date" class="form-control mydatepicker" autocomplete="off" value="<?php echo date('Y-m-d'); ?>"></td>
                                        <td>Cheque/DD No/Transaction No</td>
                                        <td><input type="text" name="cheque_no" id="cheque_no" class="form-control"></td>
                                        <!-- pattern="\d{6}" maxlength="6" maxlength="6" -->
                                    </tr>
                                    
                                    <tr class="bnakDetail" style="border-bottom: 2px solid black;">
                                        <td>Bank Name</td>
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
                                        <td>Bank Branch</td>
                                        <td><input type="text" name="bank_branch" id="bank_branch" class="form-control" value="N/A"></td>
                                    </tr>
                                </table>
                            </div>
                            <div class="table-responsive col-md-12" >
                                <div class="col-md-6 form-group">
                                    <!-- <div class="checkbox checkbox-info">
                                        <input id="sendsms" name="send_sms" type="checkbox">
                                        <label for="sendsms"> SEND SMS </label>
                                    </div>-->
                                </div>
                                <div class="col-md-6 form-group">
                                    <input type="submit" name="submit" onclick="return checkForm();" value="Save" class="btn btn-success" >    
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
    </div>
    </div>

    @include('includes.footerJs')
    <script>
        document.body.className = document.body.className.replace("fix-header", "fix-header show-sidebar hide-sidebar");
        var elements = document.querySelectorAll('input,select,textarea');

        for (var i = elements.length; i--; ) {
            elements[i].addEventListener('invalid', function () {
                this.scrollIntoView(false);
            });
        }
        function checkForm() {
//            alert("checkForm");
            if ($('#payment_mode').val() == '') {
                alert("Please Select Payment Mode.");
                return false;
            }
            if ($('#receiptdate').val() == '') {
                alert("Please Select Receipt Date.");
                return false;
            }
            if ($('#payment_mode').val() != 'Cash') {
                if ($('#cheque_date').val() == '') {
                    alert("Please Select Cheque Date.");
                    return false;
                }
                if ($('#cheque_no').val() == '') {
                    alert("Please Select Cheque Number.");
                    return false;
                }
                if ($('#bank_name').val() == '') {
                    alert("Please Select Bank Name.");
                    return false;
                }
                if ($('#bank_branch').val() == '') {
                    alert("Please Select Bank Branch.");
                    return false;
                }
//                var n = $("#cheque_no").length;
               // var n = $("#cheque_no").val().length;
               // if (n != 6) {
                 //   alert("Cheque/DD Number Must Be 6 Digit.");
                   // return false;
                //}
//                alert(n);

            }
            return true;
        }

        $('#fees_head').on('change', '.allField1', function () {
            var sum = 0;
           
            $('.allField1').each(function () {
                var amount;
                amount = parseFloat($(this).val());
                sum += amount;  // Or this.innerHTML, this.innerText
            });
            $("#totalVal").val(sum);
            calculateTotal();
        });

        $('#fees_head').on('change', '.allDisField', function () {
            var sum = 0;
            $('.allDisField').each(function () {
                var amount;
                amount = parseFloat($(this).val());
                sum += amount;  // Or this.innerHTML, this.innerText
            });
            $("#totalDis").val(sum);
            calculateTotal();
        });
        
        $(document).on('change','.directdiscount', function () {
            amount = parseFloat($(this).val());  
            $('.allDisField').each(function () {
                $(this).val(0);                                               
            });                      
            calculateTotal();
        });

        $('#fees_head').on('change', '.allFinField', function () {
            //alert("asds");
            var sum = 0;
            cheque_return_charges = $("#hidden_cheque_return_charges").val();
            $('.allFinField').each(function () {
                var amount;
                amount = parseFloat($(this).val());
                sum += amount;  // Or this.innerHTML, this.innerText
            });
            $("#totalFin").val(sum);
            sum = sum + parseFloat(cheque_return_charges);
            $("#cheque_return_charges").val(sum);
            calculateTotal();
        });


        // START 30-12-2021 Added for total fine in grandtotal
        $(document).on('change','.directfine', function () {
            var sum = 0;
            cheque_return_charges = $("#hidden_cheque_return_charges").val();
            amount = parseFloat($(this).val());  
            $('.allFinField').each(function () {
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
            cheque_return_charges = $("#hidden_cheque_return_charges").val();

            if (dis > tot && dis != 0) {
                alert("Discount Can Not Be More Then Total Amount.");
                $("#discount").val(0);
                $("#totalDis").val(0)
            } else {
                if (isNaN(dis)) {
                } else {
                    tot = (tot - dis) + fin ;
                }
                tot = tot + parseFloat(cheque_return_charges);
                $("#grandTotal").val(tot);
            }
        }
        function sh_bankDetail(selectedVal) {
            if (selectedVal == 'Cash') {
                $('.bnakDetail').hide();
            } else {
                $('.bnakDetail').show();
            }
        }
        $('.months').click(function () {
            var checkedMonths = new Array();
            var j = 0;
            for (var i = 0; i < document.getElementsByClassName('months').length; i++)
            {
                if (document.getElementsByClassName('months')[i].checked) {
                    checkedMonths[j] = document.getElementsByClassName('months')[i].value;
                    j = j + 1;
                }
            }

            $.ajax({
                type: "POST",
                url: "{{route('get-fees-list')}}",
                data: {checkedMonths: checkedMonths, student_id: <?php echo $data['stu_data']['student_id']; ?>}, 
                //--> send id of checked checkbox on other page
                success: function (data) {
                    $("#fees_head").empty();
                    $("#fees_head").html(data);

                    var auto_head_counting = <?php echo (($auto_head_counting == '1') ? ($auto_head_counting) : ('0')); ?>;
                    if(auto_head_counting == 1)
                    {
                        $('.allField1').attr('readonly', true);
                        $('#totalVal').attr('readonly', false);
                    }else{
                        $('.allField1').attr('readonly', false);
                        $('#totalVal').attr('readonly', true);
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
                    $('.allField1').each(function () {             
                        var new_name  = $(this).attr('name');           
                        amount = $('input[name="'+new_name+'"]').val();                           
                        if(amount < 0)
                        {
                            $(this).attr('readonly',true);
                        }
                    });
                    // 26/08/2021 END Added for The Millennium School for Advanced Imprest Collection payment
                }
            });
        });


        

       $(document).on('blur','#totalVal', function () {                        
            var new_total_amount = parseFloat(this.value);            
            var new_copy_total_amount = parseFloat(this.value);            
            var orginial_tot = parseFloat($("#hid_totalVal").val());
            
            if(new_total_amount >  orginial_tot)
            {
                alert("Amount Cannot be greater than total amount");
                $('#totalVal').val(orginial_tot);
                $('#grandTotal').val(orginial_tot);
            }
            else
            {
                $('.allField1').each(function () {               
                    var new_name  = "hid_"+$(this).attr('name');                
                    amount = $('input[name="'+new_name+'"]').val();                            
                    if(amount != 0)
                    {
                        if(amount >= new_total_amount)
                        {                            
                            $(this).val(new_total_amount);
                            new_total_amount = 0;
                        }
                        else
                        {
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
@endif
    @include('includes.footer')
