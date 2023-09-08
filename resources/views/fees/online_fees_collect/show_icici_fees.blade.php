@include('includes.headcss')
<style>
    tr.spaceUnder>th {
        padding-bottom: 1em !important;
    }

    #page-wrapper {
        margin: 0px;
        padding: 20px;        
    }

    .footer {
        left: 0;
    }
</style>
<div id="page-wrapper" style="color:#000;">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-md-12">
                <h4 class="page-title text-center">Fees Collect</h4>
            </div>
        </div>
        <div class="card">
            <form action="<?php echo $data['redirect_url']; ?>" id="myForm" method="post">
                @csrf
                <input type="hidden" name="student_id" value=<?php echo $data['student_id']; ?>>
                <div class="row">
                    <div class="col-md-4">
                        <label for="year">Choose Year:</label>
                        <select name="syear" id="year" onchange="this.form.submit();" class="form-control">
                            <?php foreach ($data["dd_arr"] as $id => $val) {
                                $selected = "";
                                if ($val == $data["cur_year"]) {
                                    $selected = "selected";
                                } else {
                                    $selected = "";
                                }
                                ?>
                                <option {{$selected}} value={{$id}}>{{$val}}</option>
                            <?php } ?>
                        </select>
                    </div>
                </div>
            </form>
        </div>
        <div class="card">
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
                                    <th>Paid</th>
                                    <th>Remaining</th>
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
                        <div class="table-responsive col-md-6">
                            <table class="table table-stripped">
                                <tr>
                                    <td>{{ App\Helpers\get_string('uniqueid','request')}}</td>
                                    <td><?php echo $data['stu_data']['student_id']; ?></td>
                                </tr>
                                <tr>
                                    <td>{{ App\Helpers\get_string('studentname','request')}}</td>
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
                            </table>
                        </div>
                        <div class="table-responsive col-md-6">
                            <table class="table table-stripped">
                                <tr>
                                    <td>{{ App\Helpers\get_string('grno','request')}}</td>
                                    <td><?php echo $data['stu_data']['enrollment']; ?></td>
                                </tr>
                                <tr>
                                    <td>{{ App\Helpers\get_string('std/div','request')}}</td>
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
                            </table>
                        </div>
                        <form action="{{ route('icici_request_handler') }}" enctype="multipart/form-data" method="post">
                            @csrf
                            <input type="hidden" name="grade_id" value="<?php echo $data['stu_data']['grade_id']; ?>">
                            <input type="hidden" name="standard_id" value="<?php echo $data['stu_data']['std_id']; ?>">
                            <input type="hidden" name="div_id" value="<?php echo $data['stu_data']['div_id']; ?>">
                            <input type="hidden" name="student_id" value="<?php echo $data['stu_data']['student_id']; ?>">
                            <input type="hidden" name="std_div" value="<?php echo $data['stu_data']['stddiv']; ?>">
                            <input type="hidden" name="full_name" value="<?php echo $data['stu_data']['name']; ?>">

                            <div class="col-md-12" style="border-top: 2px solid black;">
                                <div style="display:flex; flex-wrap:wrap; justify-content: space-between;">
                                    <tr>
                                        <?php
                                        $no = $pay_amount = 0;
                                        $i = 1;
                                        foreach ($data['month_arr'] as $id => $val) {
                                            $no++;
                                            if ($i == 1) {
                                                echo "<tr>";
                                            }
                                            $slected = "";
                                            if (in_array($id, $data['search_ids'])) {
                                                $slected = '';
                                            }
                                            $disabled = '';
                                            if ( isset($feesDetails[$val]) && $feesDetails[$val] == 0 ) {
                                                $disabled = 'disabled="disabled"';
                                                $slected = 'checked';
                                               
                                            }

                                            ?>
                                            <td>
                                                <div class="checkbox checkbox-info">
                                                    <input id="<?php echo $id; ?>" name="months[<?php echo $id; ?>]" value="<?php echo $id; ?>" <?php echo $slected; ?> class="months" type="checkbox" 
                                                    @php echo $disabled; echo 'data-no='.$no; @endphp>
                                                    <label for="<?php echo $id; ?>"><?php echo $val; ?></label>
                                                </div>
                                            </td>
                                        <?php 
                                        if ($i == 6) {
                                            echo "</tr>";
                                        }
                                        $i++;
                                        } 
                                        ?>

                                    </tr>
                                </div>
                            </div>
                            <div class="table-responsive col-md-12">
                                <table class="table table-stripped" border="0" width="100%">
                                    <tr>
                                        <td colspan="5">
                                            <table width="100%" border="0" id="fees_head">
                                                <tr class="spaceUnder">
                                                    <th align="center" style="width: 30%;padding-left: 15px;">Particular</th>
                                                    <th style="width: 10%;padding-left: 15px;">Amount</th>
                                                </tr>
												<?php
                                                $total = [];
                                                $cheque_return_charges0 = $data['cheque_return_charges'][0]; 
                                                $cheque_return_charges = $data['fees_config_data'][0]['late_fees_amount'];
									            $sub_institute_id=[257];
                                                 foreach ($data['final_fee'] as $id => $val) { ?>
                                                    
                                                    <tr>
                                                        <td style="width: 20%"><?php echo $id; ?></td>
                                                        
                                                        <td style="width: 20%"><?php echo $val; ?></td>

                                                        <?php
                                                        //echo "<pre>";print_r($id);
                                                        if ($id != 'Total') {

                                                        } else {
                                                            echo "<input type='hidden' id='totalVal' name='total' value=" . $val . " class='form-control'>";
                                                            $pay_amount = $val;
                                                            
                                                            $total[] =$val ?? 0;
                                                            
                                                            //echo "<pre>";print_r($total);
                                                            
                                                        }
                                                        ?>
                                                        
                                                    </tr>
                                                    
                                                <?php
                                                                                                                                                        
                                                }
                                                  $total_amt= array_sum($total);
                                            ?>

                                            </table>
                                        </td>
                                    </tr>
                                    
                                    <?php if ($data["fees_type"] != "fix") { ?>
                                        <tr>
                                            <td></td>
                                            <td>Fine</td>
                                            <td></td>
                                            <td>@if(in_array(session()->get('sub_institute_id'), $sub_institute_id))
                                                <input type="text" name="fees_data[fine]" id="cheque_return_charges1" class="form-control cheque_return_charges1" value="@if(date('d') > 5 && isset($total_amt) && $total_amt!=0 && $data['admission_under'] == 'Old') {{  $data['fees_config_data'][0]['late_fees_amount'] }} @else {{ $cheque_return_charges0 ?? 0 }} @endif" readonly="readonly">
                                                <input type="hidden" name="hidden_cheque_return_charges" id="hidden_cheque_return_charges" class="form-control cheque_return_charges1" value="{{ $data['fees_config_data'][0]['late_fees_amount'] }}">
                                            @else
                                                <input type="text" name="fees_data[fine]" id="cheque_return_charges" class="form-control" value="@php if(isset($cheque_return_charges0)) echo $cheque_return_charges0; @endphp" readonly="readonly">
                                                <input type="hidden" name="hidden_cheque_return_charges" id="hidden_cheque_return_charges" class="form-control" value="@if(isset($cheque_return_charges0)){{ $cheque_return_charges0 }}@endif">
                                            @endif</td>
                                            
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
                                        <tr>
                                            <td></td>
                                            <td>Collection Amount</td>
                                            <td></td>
                                            <td><input type="number" id="pay_amount" name="pay_amount" max="<?php echo $grand_total_with_cheque_charges; ?>" class="form-control" value="<?php echo $grand_total_with_cheque_charges; ?>" readonly="readonly"></td>
                                        </tr>
                                    <?php } ?>
                                </table>
                            </div>
                            <div class="table-responsive col-md-12">
                                <div class="col-md-4 text-center form-group"></div>
                                <div class="col-md-4 text-center form-group">
                                    <?php
                                    if ($data['error'] == "") {
                                        ?>
                                        <input type="submit" name="submit" value="Pay Now" class="btn btn-success">
                                    <?php
                                    } else {
                                        ?>
                                        <label style="color:red;">Please pay previous year fees first.</label>
                                    <?php
                                    }
                                    ?>
                                </div>
                                <div class="col-md-4 text-center form-group"></div>
                            </div><br/><br/><br/>
                        </form>
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
                var n = $("#cheque_no").val().length;
                if (n != 6) {
                    alert("Cheque/DD Number Must Be 6 Digit.");
                    return false;
                }
                //                alert(n);

            }
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

        //        $('.allDisField').on('change', function () {
        $('#fees_head').on('change', '.allDisField', function() {
            //            alert("asds");
            var sum = 0;
            $('.allDisField').each(function() {
                var amount;
                amount = parseFloat($(this).val());
                sum += amount; // Or this.innerHTML, this.innerText
            });

            $("#totalDis").val(sum);
            calculateTotal();
        });
        $('#fees_head').on('change', '.allFinField', function() {
            //            alert("asds");
            var sum = 0;
            $('.allFinField').each(function() {
                var amount;
                amount = parseFloat($(this).val());
                sum += amount; // Or this.innerHTML, this.innerText
            });

            $("#totalFin").val(sum);
            calculateTotal();
        });

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
                   
					$("#pay_amount").val(tot);
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
        // $('.months').click(function() {
        //     var checkedMonths = new Array();
        //     var j = 0;
        //     for (var i = 0; i < document.getElementsByClassName('months').length; i++) {
        //         if (document.getElementsByClassName('months')[i].checked) {
        //             checkedMonths[j] = document.getElementsByClassName('months')[i].value;
        //             j = j + 1;
        //         }
        //     }
        //     console.log(checkedMonths);
        //     $.ajax({
        //         type: "POST",
        //         url: "{{route('get-online-fees-list')}}",
        //         data: {
        //             checkedMonths: checkedMonths,
        //             student_id: <?php echo $data['stu_data']['student_id']; ?>,
        //             fees_type: "<?php echo $data['fees_type']; ?>"
        //         }, //--> send id of checked checkbox on other page
        //         success: function(data) {
        //             $("#fees_head").empty();
        //             $("#fees_head").html(data);
        //             tot = $("#totalVal").val();
        //             $("#discount").val(0);
        //             $("#pay_amount").val(tot);//grandTotal

        //             fin = parseFloat($("#totalFin").val());
        //             cheque_return_charges = $("#hidden_cheque_return_charges").val();
        //             sum = fin + parseFloat(cheque_return_charges);
        //             $("#cheque_return_charges").val(sum);
        //             calculateTotal();
        //         }
        //     });
        // });

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
				for (var i = 0; i < document.getElementsByClassName('months').length; i++) {
					if (document.getElementsByClassName('months')[i].checked) {
						checkedMonths[j] = document.getElementsByClassName('months')[i].value;
						j = j + 1;
					}
				}

				$.ajax({
					type: "POST",
					url: "{{route('get-online-fees-list')}}",
					data: {
                    checkedMonths: checkedMonths, 
                    student_id: <?php echo $data['stu_data']['student_id']; ?>,
                    fees_type: "<?php echo $data['fees_type']; ?>"
                    },
					//--> send id of checked checkbox on other page
					success: function(data) {
                        $("#fees_head").empty();
                    $("#fees_head").html(data);
                    tot = $("#totalVal").val();
                    $("#discount").val(0);
                    $("#pay_amount").val(tot);//grandTotal

                    fin = parseFloat($("#totalFin").val());
                    cheque_return_charges = $("#hidden_cheque_return_charges").val();
                    sum = fin + parseFloat(cheque_return_charges);
                    if(isNaN(sum)){
                    $("#cheque_return_charges").val(0);                        
                    }else{
                    $("#cheque_return_charges").val(sum);
                    }
                    calculateTotal();
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
    </script>
    @if(app('request')->input('implementation') == 1)
    <script type="text/javascript">
        document.body.className = document.body.className.replace("fix-header", "fix-header show-sidebar hide-sidebar");
        document.getElementById('main-header').style.display = 'none';
    </script>
    @endif
    @include('includes.footer')
