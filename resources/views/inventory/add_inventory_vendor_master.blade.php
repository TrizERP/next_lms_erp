@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Vendor Master</h4>
            </div>
        </div>
        <div class="card">
            @if ($message = Session::get('success'))
            <div class="alert alert-success alert-block">
                <button type="button" class="close" data-dismiss="alert">×</button>
                <strong>{{ $message }}</strong>
            </div>
            @endif
            <div class="row">                
                <div class="col-lg-12 col-sm-12 col-xs-12">  
                    <form action="
                      @if (isset($data))
                      {{ route('add_inventory_vendor_master.update', $data->id) }}
                      @else
                      {{ route('add_inventory_vendor_master.store') }}
                      @endif" method="post">

                        @if(!isset($data))
                        {{ method_field("POST") }}
                        @else
                        {{ method_field("PUT") }}
                        @endif
                        {{csrf_field()}}
                        <div class="row">
                            <div class="col-md-4 form-group">
                                @csrf
                                <label>Vendor Name</label>
                                <input type="text" id='vendor_name' required name="vendor_name" value="@if(isset($data->vendor_name)){{$data->vendor_name}}@endif" class="form-control">
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Contact Number</label>
                                <input type="number" id='title' required name="contact_number" value="@if(isset($data->contact_number)){{$data->contact_number}}@endif" class="form-control" maxlength="10">
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Short Name</label>
                                <input type="short_name" id='vendor_name' required name="short_name" value="@if(isset($data->short_name)){{$data->short_name}}@endif" class="form-control">
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Sort Order</label>
                                <input type="number" id='sort_order' required name="sort_order" value="@if(isset($data->sort_order)){{$data->sort_order}}@endif" class="form-control">
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Address</label>
                                <input type="text" id='address' required name="address" value="@if(isset($data->address)){{$data->address}}@endif" class="form-control">
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Email</label>
                                <input type="email" id='email' required name="email" value="@if(isset($data->email)){{$data->email}}@endif" class="form-control">
                            </div>
                            <div class="col-md-4 form-group">
                                <label>File Number</label>
                                <input type="text" id='file_number'  name="file_number" value="@if(isset($data->file_number)){{$data->file_number}}@endif" class="form-control">
                            </div>
                            <div class="col-md-4 form-group">
                                <label>File Location</label>
                                <input type="text" id='file_location'  name="file_location" value="@if(isset($data->file_location)){{$data->file_location}}@endif" class="form-control">
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Company/Firm Name</label>
                                <input type="text" id='company_name' required name="company_name" value="@if(isset($data->company_name)){{$data->company_name}}@endif" class="form-control">
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Business Type</label>
                                <input type="text" id='business_type' required name="business_type" value="@if(isset($data->business_type)){{$data->business_type}}@endif" class="form-control">
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Office Address</label>
                                <input type="text" id='office_address' required name="office_address" value="@if(isset($data->office_address)){{$data->office_address}}@endif" class="form-control">
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Office Contact Person</label>
                                <input type="text" id='office_contact_person' required name="office_contact_person" value="@if(isset($data->office_contact_person)){{$data->office_contact_person}}@endif" class="form-control">
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Office Number</label>
                                <input type="text" id='office_number'  name="office_number" value="@if(isset($data->office_number)){{$data->office_number}}@endif" class="form-control">
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Office Email</label>
                                <input type="email" id='office_email' required name="office_email" value="@if(isset($data->office_email)){{$data->office_email}}@endif" class="form-control">
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Tin No.</label>
                                <input type="text" id='tin_no' required name="tin_no" value="@if(isset($data->tin_no)){{$data->tin_no}}@endif" class="form-control">
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Tin Date</label>
                                <div class="input-daterange input-group" id="date-range">
                                    <input type="text" class="form-control mydatepicker" placeholder="dd/mm/yyyy" value="@if(isset($data->tin_date)){{date('d-m-Y', strtotime($data->tin_date))}}@endif" name="tin_date" autocomplete="off">
                                    <span class="input-group-addon"><i class="icon-calender"></i></span> 
                                </div>
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Registration No.</label>
                                <input type="text" id='registration_no'  name="registration_no" value="@if(isset($data->registration_no)){{$data->registration_no}}@endif" class="form-control">
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Registration Date</label>
                                <div class="input-daterange input-group" id="date-range">
                                    <input type="text" class="form-control mydatepicker" placeholder="dd/mm/yyyy" value="@if(isset($data->registration_date)){{date('d-m-Y', strtotime($data->registration_date))}}@endif" name="registration_date" autocomplete="off">
                                    <span class="input-group-addon"><i class="icon-calender"></i></span> 
                                </div>
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Service Tax No.</label>
                                <input type="text" id='serivce_tax_no'  name="serivce_tax_no" value="@if(isset($data->serivce_tax_no)){{$data->serivce_tax_no}}@endif" class="form-control">
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Service Tax Date </label>
                                <div class="input-daterange input-group" id="date-range">
                                    <input type="text" class="form-control mydatepicker" placeholder="dd/mm/yyyy" value="@if(isset($data->serivce_tax_date)){{date('d-m-Y', strtotime($data->serivce_tax_date))}}@endif" name="serivce_tax_date" autocomplete="off">
                                    <span class="input-group-addon"><i class="icon-calender"></i></span> 
                                </div>
                            </div>
                            <div class="col-md-4 form-group">
                                <label>PAN No.</label>
                                <input type="text" id='pan_no' required name="pan_no" value="@if(isset($data->pan_no)){{$data->pan_no}}@endif" class="form-control">
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Bank Account No.</label>
                                <input type="text" id='bank_account_no' required name="bank_account_no" value="@if(isset($data->bank_account_no)){{$data->bank_account_no}}@endif" class="form-control">
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Bank Name</label>
                                <input type="text" id='bank_name' required name="bank_name" value="@if(isset($data->bank_name)){{$data->bank_name}}@endif" class="form-control">
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Bank Branch</label>
                                <input type="text" id='bank_branch' required name="bank_branch" value="@if(isset($data->bank_branch)){{$data->bank_branch}}@endif" class="form-control">
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Bank IFSC Code</label>
                                <input type="text" id='bank_ifsc_code' required name="bank_ifsc_code" value="@if(isset($data->bank_ifsc_code)){{$data->bank_ifsc_code}}@endif" class="form-control">
                            </div>
                            <div class="col-md-12 form-group">
                                <center>
                                    <input type="submit" name="submit" value="Save" class="btn btn-success" >
                                </center>
                            </div>
                        </div>
                    </form>
                </div>
            </div>           
            @if (count($errors) > 0)
            <div class="alert alert-danger">
                <strong>Whoops!</strong> There were some problems with your input.<br><br>
                <ul>
                    @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
            @endif
        </div>
    </div>
</div>

@include('includes.footerJs')
@include('includes.footer')
