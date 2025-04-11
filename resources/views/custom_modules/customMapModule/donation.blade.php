@extends('layout')
@section('container')
<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Donation Collection</h4>
            </div>
        </div>
       
        <div class="card">
            @if ($sessionData = Session::get('data'))
                @if($sessionData['status'] == 1)
                    <div class="alert alert-success alert-block">
                @else
                    <div class="alert alert-danger alert-block">
                @endif
                    <button type="button" class="close" data-dismiss="alert">×</button>
                        <strong>{{ $sessionData['message'] }}</strong>
                    </div>
                @endif
                @if (isset($data['status']) && $data['status']==0)
                        <div class="alert alert-danger alert-block">
                        <button type="button" class="close" data-dismiss="alert">×</button>
                            <strong>{{ $data['message'] }}</strong>
                        </div>
                @endif
                    <form action="{{ route('donation_collection.create') }}">
                        @csrf
                       <div class="row">
                            <div class="col-md-6">
                                <label for="name">Name</label>
                                <input type="text" class="form-control" name="full_name" list="donarLists" placeholder="Enter Full Name" autocomplete="off" @if(isset($data['full_name']) && $data['full_name']!='') value="{{$data['full_name']}}" @endif>
                                <datalist id="donarLists">
                                    @foreach($data['donarLists'] as $k=>$v)
                                    <option>{{$v->full_name}}</option>
                                    @endforeach
                                </datalist>
                            </div>
                            <div class="col-md-6">
                                <label for="mobile">Mobile Number</label>
                                <input type="text" class="form-control" pattern="[1-9]{1}[0-9]{9}" name="mobile_number" placeholder="Enter Mobile Number" autocomplete="off" @if(isset($data['mobile_number']) && $data['mobile_number']!='') value="{{$data['mobile_number']}}" @endif>
                            </div>
                       </div>

                        <div class="col-md-12 form-group mt-4">
                            <center>
                                <input type="submit" name="submit" value="Search" class="btn btn-success">
                            </center>
                        </div>
                    </form>
                </div>

            <!-- searched data starts  -->
             @if(isset($data['donarData']->id) && !empty($data['donarData']))
                <div class="card">
                    <!-- detail card starts  -->
                    <div class="card" style="padding:10px 20px !important;box-shadow:none;">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="card" style="border:1px solid #ddd;padding:8px !important;">
                                    <label for=""><b>Name :</b></label>
                                    <p style="margin:0px !important">{{$data['donarData']->full_name}}</p>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card" style="border:1px solid #ddd;padding:8px !important;">
                                    <label for=""><b>PAN Number :</b></label>
                                    <p style="margin:0px !important">{{$data['donarData']->pan_number}}</p>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card" style="border:1px solid #ddd;padding:8px !important;">
                                    <label for=""><b>Address :</b></label>
                                    <p style="margin:0px !important">{{$data['donarData']->address}}</p>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card" style="border:1px solid #ddd;padding:8px !important;">
                                    <label for=""><b>Mobile :</b></label>
                                    <p style="margin:0px !important">{{$data['donarData']->mobile_number}}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- detail card end  -->

                    <!-- payment details starts  -->
                     <div class="card" style="padding:10px 50px !important;box-shadow:none;">
                        <form action="{{route('donation_collection.store')}}" method="post">
                        @csrf
                        <input type="hidden" name="donar_id" value="{{$data['donarData']->id}}">
                        <input type="hidden" name="full_name" value="{{$data['donarData']->full_name}}">
                        <input type="hidden" name="pan_number" value="{{$data['donarData']->pan_number}}">
                        <input type="hidden" name="address" value="{{$data['donarData']->address}}">
                        <input type="hidden" name="mobile_number" value="{{$data['donarData']->mobile_number}}">

                        <table class="table table-stripped">
                            <tr>
                                <td style="width:25% !important;">Date of Receipt</td>
                                <td style="width:25% !important;"><input type="text" name="paid_date" id="paid_date" class="form-control mydatepicker" required></td>

                                <td style="width:25% !important;">Amount</td>
                                <td style="width:25% !important;"><input type="number" name="amount" id="amount" class="form-control" required placeholder="Enter amount"></td>
                            </tr>
                            <tr>
                                <td style="width:25% !important;">Payment Mode</td>
                                <td style="width:25% !important;">
                                    <select name="payment_mode" id="payment_mode" class="form-control">
                                        @foreach($data['paymentModes'] as $pk=>$pv)
                                        <option value="{{$pk}}">{{$pv}}</option>
                                        @endforeach
                                    </select>
                                </td>

                                <td style="width:25% !important;">Remarks (if any)</td>
                                <td style="width:25% !important;"><textarea name="remarks" id="remarks" class="form-control resizableVertical" placeholder="Add Remarks"></textarea></td>
                            </tr>
                            <tr>
                                <td style="width:25% !important;">Bank Name</td>
                                <td style="width:25% !important;">
                                    <select name="bank_name" id="bank_name" class="form-control">
                                        <option value="">Select Bank Name</option>
										@if(!empty($data['bank_data'])) 
                                            @foreach($data['bank_data'] as $key => $value)
												<option value="{{$value['bank_name']}}">{{$value['bank_name']}}</option>
										    @endforeach
                                        @endif
                                    </select>
                                </td>

                                <td style="width:25% !important;">Bank Brach</td>
                                <td style="width:25% !important;"><input type="text" name="bank_branch" id="bank_branch" class="form-control" placeholder="Enter Bank Branch" value="N/A"></td>
                            </tr>
                            <tr>
                                <td style="width:25% !important;">Cheque/DD No.</td>
                                <td style="width:25% !important;"><input type="text" name="cheque_no" id="cheque_no" class="form-control" placeholder="Enter Cheque/DD No."></td>

                                <td style="width:25% !important;">Cheque/DD Date</td>
                                <td style="width:25% !important;"><input type="text" name="cheque_date" id="cheque_date" class="form-control mydatepicker"></td>
                            </tr>
                        </table>
                        <div class="row mt-4">
                            <div class="col-md-12">
                                <center>
                                    <input type="submit" value="Submit" class="btn btn-success">
                                </center>
                            </div>
                        </div>
                        </form>
                     </div>
                    <!-- payment details end  -->

                </div>
             @endif
            <!-- searched data end  -->

    </div>
</div>

@include('includes.footerJs')

@include('includes.footer')
@endsection