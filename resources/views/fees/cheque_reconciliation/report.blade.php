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
            <form action="{{ url('/fees/search_details') }}" method="GET">
                    {{ method_field("POST") }}
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
                    <div class="col-md-4 form-group ml-0">
                        <label>Payment Option</label>

                        <div class="input-daterange input-group" id="date-range">
                        <select class="form-select form-control" name="mode" id="mode" style="width:110px">
                            @if(isset($data['mode']))
                            <option value="{{$data['mode']}}">{{$data['mode']}}</option>
                            @endif
                          <option value="">Select Payment Option</option>
                          <option value="clear">Clear</option>
                          <option value="return">Return</option>
                      </select>
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
              <div class="table-responsive">
                    <table class="table table-box table-bordered" style="width:50%">
                        <thead>
                        <tr>
                            <th>Sr No.</th>
                            <th>{{ App\Helpers\get_string('studentname','request')}}</th>
                            <th>Medium</th>
                            <th>{{ App\Helpers\get_string('grno','request')}}</th>
                            <th>{{ App\Helpers\get_string('standard','request')}}</th>
                            <th>{{ App\Helpers\get_string('division','request')}}</th>                            
                            <th>Mobile</th>
                            <th>Term</th>
                            <th>Amount</th>
                            <th>Bank Name</th>
                            <th>Bank Branch</th>
                            <th>Cheque No</th>
                            <th>Cheque Date</th>
                            <th>Payment Type</th>
                            <th>Reconciliation Date </th>
                            <th>Remarks</th>
                       </tr>
                        </thead>
                        <tbody>
                            @foreach($data['details'] as $key => $val)
                            <tr>
                              <td>{{$j++}}</td>
                                <td>{{$val->student_name ?? ' '}}</td>
                                <td>{{$val->medium ?? ' '}}</td>
                                <td>{{$val->enrollment_no ?? ' '}}</td>
                                <td>{{$val->standard_name ?? ' '}}</td>
                                <td>{{$val->divison_name ?? ' '}}</td>
                                <td>{{$val->mobile ?? ' '}}</td>
                                <td>{{$val->term_name ?? ' '}}</td>
                                <td>{{$val->amountpaid ?? ' '}}</td>
                                <td>{{$val->cheque_bank_name ?? ' '}}</td>
                                <td>{{$val->bank_branch ?? ' '}}</td>
                                <td>{{$val->cheque_no ?? ' '}}</td>
                                <td>{{$val->cheque_date ?? ' '}}</td>
                                <td>{{$val->cancel_type ?? ' '}}</td>
                                <td>{{$val->cancel_date ?? ' '}}</td>
                                <td>{{$val->cancel_remark ?? ' '}}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                     
                @endif
            </div>
    </div>
</div>
@include('includes.footerJs')

@include('includes.footer')