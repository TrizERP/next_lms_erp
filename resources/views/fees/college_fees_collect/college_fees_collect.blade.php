@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<?php
// echo ('<pre>');print_r($data);exit;
?>
<style>
    tr.spaceUnder>th {
        padding-bottom: 1em !important;
    }
</style>
<div id="page-wrapper" style="color:#000;">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Fees Collect</h4>
            </div>
        </div>
        <div class="row">
            <div class="white-box">
                <div class="panel-body">
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

                        <div class="col-md-12 col-lg-12 col-sm-12 col-xs-12">
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

                                </table>
                            </div>
                            <form action="{{ route('college_fees_collect.store') }}" enctype="multipart/form-data"
                                method="post">
                                @csrf
                                <input type="hidden" name="grade_id"
                                    value="<?php echo $data['stu_data']['grade_id']; ?>">
                                <input type="hidden" name="standard_id"
                                    value="<?php echo $data['stu_data']['std_id']; ?>">
                                <input type="hidden" name="div_id" value="<?php echo $data['stu_data']['div_id']; ?>">
                                <input type="hidden" name="student_id"
                                    value="<?php echo $data['stu_data']['student_id']; ?>">
                                <input type="hidden" name="std_div" value="<?php echo $data['stu_data']['stddiv']; ?>">
                                <input type="hidden" name="full_name" value="<?php echo $data['stu_data']['name']; ?>">


                                <div class="table-responsive col-md-12">
                                    <table class="table table-stripped" border="0" width="100%">

                                        <tr>
                                            <td colspan="5">
                                                <table width="100%" border="0" id="fees_head">
                                                    <tr class="spaceUnder">
                                                        <th align="center" style="width: 30%;padding-left: 15px;">
                                                            Particular</th>
                                                        <th style="width: 10%;padding-left: 15px;">Amount</th>
                                                    </tr>
                                                    <?php foreach ($data['all_head'] as $id => $val) { ?>
                                                    <tr>
                                                        <td style="width: 20%"><?php echo $val; ?></td>
                                                        <?php
                                                            echo "<td style='width: 20%'><input type='number' min=0 value=0 name='fees_data[$id]' class='form-control allField1'></td>";
                                                        ?>
                                                    </tr>
                                                    <?php } ?>
                                                </table>
                                            </td>
                                        </tr>
                                   
                                        <tr style="border-bottom: 2px solid black;">
                                            <td></td>
                                            <td>Grand Total</td>
                                            <td></td>
                                            <td><input type="text" id="grandTotal" readonly="" value="<?php echo 0; ?>"
                                                    class="form-control"></td>
                                        </tr>
                                        <tr>
                                            <td>Payment Mode</td>
                                            <td>
                                                <select class="form-control" required="required" name="PAYMENT_MODE"
                                                    id="payment_mode" onchange="sh_bankDetail(this.value);">
                                                    <option value="">Select Payment Mode</option>
                                                    <option value="Cash">Cash</option>
                                                    <option value="Cheque">Cheque</option>
                                                    <option value="DD">DD</option>
                                                </select>
                                            </td>
                                            <td>Receipt Date</td>
                                            <td><input type="text" name="receiptdate" id="receiptdate"
                                                    class="form-control mydatepicker"></td>
                                        </tr>
                                        <tr class="bnakDetail">
                                            <td>Cheque/DD Date</td>
                                            <td><input type="text" name="cheque_date" id="cheque_date"
                                                    class="form-control mydatepicker"></td>
                                            <td>Cheque/DD No</td>
                                            <td><input type="text" name="cheque_no" id="cheque_no" maxlength="6"
                                                    class="form-control"></td><!-- pattern="\d{6}" maxlength="6" -->
                                        </tr>
                                        <tr class="bnakDetail" style="border-bottom: 2px solid black;">
                                            <td>Bank Name</td>
                                            <td><input type="text" name="bank_name" id="bank_name" class="form-control">
                                            </td>
                                            <td>Bank Branch</td>
                                            <td><input type="text" name="bank_branch" id="bank_branch"
                                                    class="form-control"></td>
                                        </tr>
                                        <tr style="border-bottom: 2px solid black;">
                                            <td>Notes</td>
                                            <td><input type="text" name="remarks" id="notes" class="form-control">
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                                <div class="table-responsive col-md-12">
                                    <div class="col-md-6 form-group">
                                   
                                    </div>
                                    <div class="col-md-6 form-group">
                                        <input type="submit" name="submit" onclick="return checkForm();" value="Save"
                                            class="btn btn-success">
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    
    @include('includes.footerJs')
    
    <script>
     
        var elements = document.querySelectorAll('input,select,textarea');

        for (var i = elements.length; i--;) {
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
                var n = $("#cheque_no").val().length;
                if (n != 6) {
                    alert("Cheque/DD Number Must Be 6 Digit.");
                    return false;
                }
                //                alert(n);

            }
            return true;
        }
        // in another js file, far, far away
        //        $('.allField1').on('change', function () {
        $('#fees_head').on('change', '.allField1', function () {
            // alert("asdasd");
            var sum = 0;
            $('.allField1').each(function () {
                var amount;
                amount = parseFloat($(this).val());
                sum += amount; // Or this.innerHTML, this.innerText
            });
            // alert(sum);
            $("#grandTotal").val(sum);
            // calculateTotal();
        });

        //        $('.allDisField').on('change', function () {
        $('#fees_head').on('change', '.allDisField', function () {
            //            alert("asds");
            var sum = 0;
            $('.allDisField').each(function () {
                var amount;
                amount = parseFloat($(this).val());
                sum += amount; // Or this.innerHTML, this.innerText
            });

            $("#totalDis").val(sum);
            calculateTotal();
        });
        $('#fees_head').on('change', '.allFinField', function () {
            //            alert("asds");
            var sum = 0;
            $('.allFinField').each(function () {
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

            if (dis > tot && dis != 0) {
                alert("Discount Can Not Be More Then Total Amount.");
                $("#discount").val(0);
            } else {
                if (isNaN(dis)) {} else {
                    tot = (tot - dis) + fin;
                }
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
      
    </script>
  
    @include('includes.footer')