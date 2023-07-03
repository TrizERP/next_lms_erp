@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<style>
    tr.spaceUnder > th {
        padding-bottom: 1em !important;
    }
</style>
<div id="page-wrapper" style="color:#000;">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Fees Refund</h4></div>
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
                            <div class="col-md-12 col-lg-12 col-sm-12 col-xs-12">
                                <div class="box-title">
                                    <label>Fees Refund</label>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="table-responsive">
                                            <table class="table table-stripped">
                                                <tr>
                                                    <td>{{ App\Helpers\get_string('uniqueid','request')}}</td>
                                                    <td><?php echo $data['stu_data']['uniqueid']; ?></td>
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
                                                <tr>
                                                    <td>{{ App\Helpers\get_string('studentquota','request')}}</td>
                                                    <td><?php echo $data['stu_data']['student_quota']; ?></td>
                                                </tr>
                                            </table>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="table-responsive">
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
                                    </div>
                                </div>

                                <form action="{{ route('save_fees_refund') }}" method="post">
                                    @csrf
                                    <input type="hidden" name="grade_id"
                                           value="<?php echo $data['stu_data']['grade_id']; ?>">
                                    <input type="hidden" name="standard_id"
                                           value="<?php echo $data['stu_data']['std_id']; ?>">
                                    <input type="hidden" name="div_id"
                                           value="<?php echo $data['stu_data']['div_id']; ?>">
                                    <input type="hidden" name="student_id"
                                           value="<?php echo $data['stu_data']['student_id']; ?>">
                                    <input type="hidden" name="std_div"
                                           value="<?php echo $data['stu_data']['stddiv']; ?>">
                                    <input type="hidden" name="full_name"
                                           value="<?php echo $data['stu_data']['name']; ?>">
                                    <input type="hidden" name="mobile"
                                           value="<?php echo $data['stu_data']['mobile']; ?>">
                                    <input type="hidden" name="uniqueid"
                                           value="<?php echo $data['stu_data']['uniqueid']; ?>">
                                    <input type="hidden" name="enrollment"
                                           value="<?php echo $data['stu_data']['enrollment']; ?>">

                                    <div class="table-responsive col-md-12">
                                        <table class="table table-stripped" border="0" width="100%">
                                            <tr>
                                                <td colspan="5">
                                                    <table width="100%" border="0" id="fees_head">
                                                        <tr class="spaceUnder">
                                                            <th>Particular</th>
                                                            <th>Amount</th>
                                                            <th>Collection Amount</th>
                                                        </tr>
                                                        <?php
                                                        $total_amount = 0;
                                                        if(isset($data['paid_data_title_wise']))
                                                        {
                                                        foreach ($data['paid_data_title_wise'] as $fees_title_name => $paid_amount)
                                                        {

                                                        $explod_amt = explode('/', $paid_amount);
                                                        $paid_amt = $explod_amt[0];
                                                        $fees_title = $explod_amt[1];

                                                        $total_amount = $total_amount + $paid_amt;
                                                        ?>
                                                        <tr>
                                                            <td>{{$fees_title}}</td>
                                                            <td>{{$paid_amt}}</td>
                                                            <td>
                                                                <input type="number" min=0
                                                                       max={{$paid_amt}} value={{$paid_amt}} name='refund_amount[{{$fees_title_name}}]'
                                                                       id='refund_amount' class='form-control'
                                                                       onchange="calculate_total(this.value);"
                                                                       data-value='refund_amount[{{$fees_title_name}}]'>
                                                            </td>
                                                        </tr>
                                                        <?php
                                                        }
                                                        }
                                                        ?>
                                                        <tr>
                                                            <td>Total</td>
                                                            <td>{{$total_amount}}</td>
                                                            <td>
                                                            <!-- <input type="number" min=0 max={{$total_amount}} value={{$total_amount}} name='total_amt' id="total_amt" class='form-control' readonly>   -->
                                                            </td>
                                                        </tr>
                                                    </table>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>Refund Remark</td>
                                                <td>
                                                    <input type="text" name="refund_remark" id="refund_remark"
                                                           class="form-control">
                                                </td>
                                                <td>Payment Mode</td>
                                                <td>
                                                    <select class="form-control" required="required" name="PAYMENT_MODE"
                                                            id="payment_mode" onchange="sh_bankDetail(this.value);">
                                                        <option value="">Select Payment Mode</option>
                                                        <option value="Cash">Cash</option>
                                                        <option value="Cheque">Cheque</option>
                                                        <option value="DD">DD</option>
                                                        <option value="Online">Online</option>
                                                        <option value="NACH">NACH</option>
                                                    </select>
                                                </td>
                                                <td>Receipt Date</td>
                                                <td><input type="text" name="receiptdate" id="receiptdate"
                                                           class="form-control mydatepicker" autocomplete="off"></td>
                                            </tr>
                                            <tr class="bnakDetail">
                                                <td>Cheque/DD Date</td>
                                                <td><input type="text" name="cheque_date" id="cheque_date"
                                                           class="form-control mydatepicker" autocomplete="off"
                                                           value="<?php echo date('Y-m-d'); ?>"></td>
                                                <td>Cheque/DD No/Transaction No</td>
                                                <td><input type="text" name="cheque_no" id="cheque_no"
                                                           class="form-control"></td>
                                            </tr>

                                            <tr class="bnakDetail" style="border-bottom: 2px solid black;">
                                                <td>Bank Name</td>
                                                <td>
                                                    <select class="form-control" name="bank_name" id="bank_name">
                                                        <option value="">Select Bank Name</option>
                                                        @if(!empty($data['bank_data']))
                                                            @foreach($data['bank_data'] as $key => $value)
                                                                <option
                                                                    value="{{$value['bank_name']}}">{{$value['bank_name']}}</option>
                                                            @endforeach
                                                        @endif
                                                    </select>
                                                </td>
                                                <td>Bank Branch</td>
                                                <td>
                                                    <input type="text" name="bank_branch" id="bank_branch"
                                                           class="form-control" value="N/A">
                                                </td>
                                            </tr>
                                        </table>
                                    </div>
                                    <div class="table-responsive col-md-12">
                                        <div class="col-md-6 form-group">
                                            <input type="submit" name="submit" onclick="return checkForm();"
                                                   value="Save" class="btn btn-success">
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
        function checkForm() {
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
            }
            return true;
        }

        function sh_bankDetail(selectedVal) {
            if (selectedVal == 'Cash') {
                $('.bnakDetail').hide();
            } else {
                $('.bnakDetail').show();
            }
        }

        function calculate_total(amount) {

            // var total_amount = amount + total_amount;
            // $("#total_amt").val(total_amount);
        }

    </script>
@include('includes.footer')
