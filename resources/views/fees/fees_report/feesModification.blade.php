@extends('layout')
@section('container')
<div id="page-wrapper">
    <div class="container-fluid">
            <div class="row bg-title">
                <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                    <h4 class="page-title">Update Fees</h4> </div>
            </div>
        @php
            $grade_id = $standard_id = $division_id = $enrollment_no = $first_name = $last_name = $mobile_no = $uniqueid = $from_date = $to_date = $admission_year = $receipt_no= '';

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
           
            if(isset($data['from_date']))
            {
                $from_date = $data['from_date'];
            }
            if(isset($data['to_date']))
            {
                $to_date = $data['to_date'];
            }
            if(isset($data['admission_year']))
            {
                $admission_year = $data['admission_year'];
            }
            if(isset($data['receipt_no'])){
                $receipt_no = $data['receipt_no'];
            }
        @endphp
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
                <form action="{{ route('fees_modification.create') }}" enctype="multipart/form-data" class="row">
                    @csrf
                    <div class="col-md-4 form-group">
                        <label>First Name</label>
                        <input type="text" id="first_name" value="{{$first_name}}" name="first_name" class="form-control">
                    </div>
                    <div class="col-md-4 form-group">
                        <label>Last Name</label>
                        <input type="text" id="last_name" value="{{$last_name}}" name="last_name" class="form-control">
                    </div>
                    <div class="col-md-4 form-group">
                        <label>{{App\Helpers\get_string('grno','request')}}</label>
                        <input type="text" id="enrollment_no" name="enrollment_no" value="{{$enrollment_no}}" class="form-control">
                    </div>
                    <div class="col-md-4 form-group">
                        <label>Receipt No</label>
                        <input type="text" id="receipt_no" name="receipt_no" value="{{$receipt_no}}" class="form-control">
                    </div>
                    <div class="col-md-4 form-group">
                        <label>Mobile No.</label>
                        <input type="text" id="mobile_no" value="{{$mobile_no}}" name="mobile_no" class="form-control">
                    </div>
                  
                    {{ App\Helpers\SearchChain('4','single','grade,std,div',$grade_id,$standard_id,$division_id) }}
                    <div class="col-md-4 form-group">
                        <label>From Date</label>
                        <input type="text" id="from_date" name="from_date" value="{{$from_date}}" class="form-control mydatepicker" autocomplete="off">
                    </div>
                    <div class="col-md-4 form-group">
                        <label>To Date</label>
                        <input type="text" id="to_date" name="to_date" value="{{$to_date}}" class="form-control mydatepicker" autocomplete="off">
                    </div>
                    <div class="col-md-12 form-group">
                        <center>
                            <input type="submit" name="submit" value="Search" class="btn btn-success">
                        </center>
                    </div>

                </form>
            </div>
        @if(isset($data['fees_data']))
        @php
            if(isset($data['fees_data'])){
                $fees_data = $data['fees_data'];
            }

        @endphp
        <div class="card">
            <form action="{{ route('fees_modification.store') }}"  onsubmit="return validateForm()" method="POST">
            @csrf
            <div class="table-responsive">
                <table id="example" class="table table-striped">
                    <thead>
                        <tr>
                            <!-- <th><input type="checkbox" name="all" id="ckbCheckAll" class="ckbox">  </th> -->
                            <th>Sr No.</th>
                            <th>{{App\Helpers\get_string('grno','request')}}</th>
                            <th>{{App\Helpers\get_string('studentname','request')}}</th>
                            <th>{{App\Helpers\get_string('standard','request')}}</th>
                            <th>{{App\Helpers\get_string('division','request')}}</th>
                            <th>{{App\Helpers\get_string('studentquota','request')}}</th>
                            <th>Type</th>
                            <th>Receipt No.</th>
                            <th>Payment Mode</th>
                            <th>Bank Name</th>
                            <th>Bank Branch</th>
                            <th>Cheque No</th>
                            <th>Cheque Date</th>
                            <th>Receipt Date</th>
                            @if(isset($data['fees_heads']))
                                @foreach($data['fees_heads'] as $key => $val)
                                <th>{{$val['display_name']}}</th>
                                @endforeach
                            @endif
                            <th>Fine</th>
                            <th>{{App\Helpers\get_string('discount','request')}}</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                    @php
                    $j=1;
                    $final_grand_total = $total_fine = $total_disc = $total_amt = 0;
                    if(isset($data['fees_heads']))
                    {
                        foreach($data['fees_heads'] as $key => $val)
                        {
                            $grand_total[$val['fees_title']] = 0;
                        }
                    }
                    @endphp

                    @if(isset($data['fees_data']))
                        @foreach($fees_data as $key => $fees_value)
                        @php
                            $total_paid = 0;
                            if(session()->get('sub_institute_id')==76){
                                $paymentModes = ['Cash'=>'CASH','Cheque'=>'CHEQUE','POS'=>'POS','Online'=>'ONLINE','UPI'=>'UPI','RTGS/NEFT'=>'RTGS/NEFT'];
                            }
                            else{
                                $paymentModes = ['Cash'=>'Cash','Cheque'=>'Cheque','DD'=>'DD','Online'=>'Online','NACH'=>'NACH','UPI'=>'UPI','Swipe1'=>'Swipe1','Swipe2'=>'Swipe2','Swipe3'=>'Swipe3','POS'=>'POS'];
                            }
                        @endphp
                        <tr>
                            <td><input type="checkbox" name="checkedFees[{{$fees_value['id']}}]" id="checkedFees" class="checkedFees" value="{{$fees_value['student_id']}}">&nbsp;{{$j}}</td>
                            <td>{{$fees_value['enrollment_no']}}</td>
                            <td>{{App\Helpers\sortStudentName($fees_value['student_name'])}}</td>
                            <td>{{$fees_value['std_name']}}</td>
                            <td>{{$fees_value['div_name']}}</td>
                            <td>{{$fees_value['stu_qouta']}}</td>
                            <td>{{$fees_value['institute_type']}}</td>
                            <td>{{$fees_value['receipt_no']}}</td>
                            <td>
                                <select id="payment_mode_{{$fees_value['id']}}" name="payment_mode[{{$fees_value['id']}}]" class="form-control payment_mode" disabled="true">
                                    <option value="">select Mode</option>
                                    @foreach($paymentModes as $k=>$p)
                                    <option value="{{$k}}" @if(isset($fees_value['payment_mode']) && $fees_value['payment_mode']==$k) selected @endif>{{$p}}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td>
                                <input type="text" class="form-control cheque_bank_name" id="bank_name_{{$fees_value['id']}}" name="bank_name[{{$fees_value['id']}}]" value="{{$fees_value['cheque_bank_name']}}" disabled="true">
                            </td>
                            <td>
                                <input type="text" class="form-control bank_branch" id="bank_branch_{{$fees_value['id']}}" name="bank_branch[{{$fees_value['id']}}]" value="{{$fees_value['bank_branch']}}" disabled="true">
                            </td>
                            <td>
                                <input type="text" class="form-control cheque_no" id="cheque_no_{{$fees_value['id']}}" name="cheque_no[{{$fees_value['id']}}]" value="{{$fees_value['cheque_no']}}" disabled="true">
                            </td>
                            <td>
                                <input type="text" class="form-control mydatepicker cheque_date" id="cheque_date_{{$fees_value['id']}}" name="cheque_date[{{$fees_value['id']}}]" value="{{$fees_value['cheque_date']}}" disabled="true">
                            </td>
                            <td>{{$fees_value['receiptdate']}}</td>
                            @if(isset($data['fees_heads']))
                                @foreach($data['fees_heads'] as $k => $val)
                                    @php
                                        $total_paid += $fees_value["total_".$val['fees_title']];
                                        $grand_total[$val['fees_title']] += $fees_value["total_".$val['fees_title']];

                                        if($fees_value["total_".$val['fees_title']] != "")
                                        {
                                            echo "<td>".$fees_value["total_".$val['fees_title']]."</td>";

                                        }
                                        else
                                        {
                                            echo "<td>0</td>";
                                        }
                                    @endphp

                                @endforeach
                            @endif

                            @php
                                $total_fine += (int)$fees_value['total_fine'];
                                $total_disc += (int)$fees_value['tot_disc'];
                            @endphp
                            <td>{{$fees_value['total_fine']}}</td>
                            <td>{{$fees_value['tot_disc']}}</td>
                            <td>{{$fees_value['amount']}}</td>
                        </tr>
                    @php
                    $j++;
                    @endphp
                        @endforeach
                        <tr class="font-weight-bold">
                            <td>{{$j++}}</td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td>Total</td>
                            @if(isset($data['fees_heads']))
                                 @foreach($data['fees_heads'] as $key => $val)
                                    <td>{{$grand_total[$val['fees_title']]}}</td>
                                    @php $final_grand_total += $grand_total[$val['fees_title']]; @endphp
                                 @endforeach
                            @endif
                            <td>{{$total_fine}}</td>
                            <td>{{$total_disc}}</td>
                            <td>{{$final_grand_total + $total_fine - $total_disc}}</td>
                        </tr>
                    @endif
                    </tbody>
                </table>
            </div>
            <div class="col-md-12 mt-2">
                <center>
                    <input type="submit" value="Update" class="btn btn-success">
                </center>
            </div>
            </form>
        </div>
        @endif
    </div>
</div>

@include('includes.footerJs')
<script>
   $(function () {
        // var $tblChkBox = $("input:checkbox");
        // $("#ckbCheckAll").on("click", function () {
        //     $($tblChkBox).prop('checked', $(this).prop('checked'));
        // });
        $(".checkedFees").on("click", function () {
            var row = $(this).closest('tr');
            var payment_mode = row.find('.payment_mode'); 
            var cheque_bank_name = row.find('.cheque_bank_name'); 
            var bank_branch = row.find('.bank_branch'); 
            var cheque_no = row.find('.cheque_no'); 
            var cheque_date = row.find('.cheque_date'); 

            payment_mode.prop('disabled', function (i, v) {
                return !v;
            });
            
            cheque_bank_name.prop('disabled', function (i, v) {
                return !v;
            });
            
            bank_branch.prop('disabled', function (i, v) {
                return !v;
            });
            
            cheque_no.prop('disabled', function (i, v) {
                return !v;
            });
            
            cheque_date.prop('disabled', function (i, v) {
                return !v;
            });

        });

    });
    function validateForm() {
        var checkboxes = document.querySelectorAll('input[name^="checkedFees["]');
        var anyChecked = false;
        
        checkboxes.forEach(function(checkbox) {
            if (checkbox.checked) {
                anyChecked = true;
            }
        });
        
        if (!anyChecked) {
            alert("Please select at least one checkbox");
            return false; 
        }
        
        return true;
    }
</script>
@include('includes.footer')
@endsection
