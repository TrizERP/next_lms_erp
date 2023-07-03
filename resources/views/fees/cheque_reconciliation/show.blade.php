@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')
<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Fees Collect</h4>
            </div>
        </div>

        <div class="card">
            @if ($sessionData = Session::get('data'))
            <div class="@if($sessionData['status_code']==1) alert alert-success alert-block @else alert alert-danger alert-block @endif ">
                <button type="button" class="close" data-dismiss="alert">×</button>
                <strong>{{ $sessionData['message'] }}</strong>
            </div>
            @endif
            
               @if ($message = Session::get('success'))
                    <div class="alert alert-success alert-block">
                        <button type="button" class="close" data-dismiss="alert">×</button>
                        <strong>{{ $message }}</strong>
                    </div>
                @endif

           @if ($message = session('failed'))
                <div class="alert alert-danger alert-block">
                    <button type="button" class="close" data-dismiss="alert">×</button>
                    <strong>{{ $message }}</strong>
                </div>
            @endif
            <form action="{{ route('cheque_reconciliation.create') }}" method="GET">
                @csrf
                <div class="row">       
                    <div class="col-md-4 form-group ml-0 mr-0">
                        <label>From Date</label>
                        <div class="input-daterange input-group" id="date-range">
                            <input value="@if(isset($data['from_date'])){{ $data['from_date'] }}@endif"
                                   type="text"
                                   required class="form-control mydatepicker" placeholder="YYYY/MM/DD"
                                   name="from_date" id="from_date" autocomplete="off">
                            <span class="input-group-addon"><i class="icon-calender"></i></span>
                        </div>
                    </div>
                    <div class="col-md-4 form-group ml-0">
                        <label>To Date</label>
                        <div class="input-daterange input-group" id="date-range">
                            <input value="@if(isset($data['to_date'])){{ $data['to_date'] }}@endif" type="text"
                                   required class="form-control mydatepicker" placeholder="YYYY/MM/DD"
                                   name="to_date" id="to_date" autocomplete="off">
                            <span class="input-group-addon"><i class="icon-calender"></i></span>
                        </div>
                    </div>
                    <div class="col-md-12 form-group mt-4">
                        <center>
                            <input type="submit" name="submit" value="Search" class="btn btn-success">
                        </center>
                    </div>                
                </div>
            </form>
            
        </div>

            <div class="card">

                @php
                $j = 1;
                @endphp
                @if(isset($data['details']) && !empty($data['details']))
                <form action="{{route('cheque_reconciliation.store')}}" enctype="multipart/form-data" method="post" id="chequeForm"  onsubmit="check_submit();">
                    {{ method_field("POST") }}
                    @csrf
                    <div class="table-responsive">
                    <table class="table table-box table-bordered" style="width:50%">
                        <thead>
                        <tr>
                            <th><input id="checkall" onchange="checkAll(this);" type="checkbox"></th>
                            <th>Sr No.</th>
                            <th>{{ App\Helpers\get_string('studentname','request')}}</th>
                            <th>Medium</th>
                            <th>{{ App\Helpers\get_string('grno','request')}}</th>
                            <th>{{ App\Helpers\get_string('standard','request')}}</th>
                            <th>{{ App\Helpers\get_string('division','request')}}</th>                            
                            <th>Mobile</th>
                            <th>Term</th>
                            <th>Roll No</th>
                            <th>Amount</th>
                            <th>Bank Name</th>
                            <th>Bank Branch</th>
                            <th>Cheque No</th>
                            <th>Cheque Date</th>
                            <th>Payment Option</th>
                            <th>Date</th>
                            <th>Remarks</th>
                        </tr>
                        </thead>
                        <tbody>
                            @foreach($data['details'] as $key => $val)
                            <tr>
                                <td>
                                    <input type="checkbox" name="cheque[]"  title="{{$val->collect_id}}" value="{{$val->collect_id}}">
                                    <input type="hidden" name="student_id[]"  title="{{$val->student_id}}" value="{{$val->student_id}}">
                                    <input type="hidden" name="standard_id[]"  title="{{$val->standard_id}}" value="{{$val->standard_id}}">
                                    <input type="hidden" name="term_id[]"  title="{{$val->term_id}}" value="{{$val->term_id}}">
                                    <input type="hidden" name="receipt_id[]"  title="{{$val->receipt_no}}" value="{{$val->receipt_no}}">
                                    <input type="hidden" name="received_date[]"  title="{{$val->cheque_date}}" value="{{$val->cheque_date}}">
                                    <input type="hidden" name="cancel_type[]"  title="{{$val->is_waved}}" value="{{$val->is_waved}}">
                                     <input type="hidden" name="amountpaid[]"  title="{{$val->amount}}" value="{{$val->amount}}">                                    
                                     <input type="hidden" name="cancel_by[]"  title="{{$val->created_by}}" value="{{$val->created_by}}">
                                </td> 
                                
                                <td>{{$j++}}</td>
                                <td>{{$val->student_name}}</td>
                                <td>{{$val->medium}}</td>
                                <td>{{$val->enrollment_no}}</td>
                                <td>{{$val->standard_name}}</td>
                                <td>{{$val->divison_name}}</td>
                                <td>{{$val->mobile}}</td>
                                <td>{{$val->term_name}}</td>
                                <td>{{$val->roll_no}}</td>
                                <td>{{$val->amount}}</td>
                                <td>{{$val->cheque_bank_name}}</td>
                                <td>{{$val->bank_branch}}</td>
                                <td>{{$val->cheque_no}}</td>
                                <td>{{$val->cheque_date}}</td>
                                <td>
                                    <select class="form-select form-control" name="mode[]" id="mode" style="width:110px">
                                      <option value="">Select Payment Option</option>
                                      <option value="clear">Clear</option>
                                      <option value="return">Return</option>
                                    </select>
                                </td>
                                <td>
                                    <div class="input-daterange input-group" id="date-range" style="width:90px">
                                        <input type="date" class="form-control" placeholder="YYYY/MM/DD" name="confirm_date[]"  id="confirm_date" autocomplete="off">
                                        <span class="input-group-addon"><i class="icon-calender"></i></span>
                                    </div>
                                </td>
                                <td>
                                    <div class="" style="width:90px">
                                    <input type="text" id='remark' name="remark[]" class="form-control mb-0" >
                                    </div>

                                 </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                     <div class="col-md-12 form-group mt-4">
                        <center>
                            <input type="submit" name="submit" value="Submit" class="btn btn-success">
                        </center>
                    </div>
                </form>
                @endif
            </div>
    </div>
</div>
@include('includes.footerJs')

<script>
 function check_submit(){

    var checked_questions = err = 0;


    $("input[name='cheque[]']:checked").each(function ()
    {             
        checked_questions = checked_questions + 1;
    });
    if(checked_questions == 0)
    {
        alert("Please Select Atleast one checkbox");
        err = 1;
    }
    }
    
    if($('#mode').val() == ''){
      alert('Payment Mode can not be empty');
        err = 1;

   }
    if(err == 1)
    {
        return false;
    }else{
        return true;
    }
}
function checkAll(ele) {
         var checkboxes = document.getElementsByTagName('input');
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
</script>
@include('includes.footer')
<!-- php_flag display_errors 1 -->
