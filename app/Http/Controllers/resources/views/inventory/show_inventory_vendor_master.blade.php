@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Vendor Master</h4>
            </div>
        </div>
        <div class="card">
            @if(!empty($data['message']))
            <div class="alert alert-success alert-block">
                <button type="button" class="close" data-dismiss="alert">×</button>
                <strong>{{ $data['message'] }}</strong>
            </div>
            @endif
            <div class="row">
                <div class="col-lg-3 col-sm-3 col-xs-3">
                    <a href="{{ route("add_inventory_vendor_master.create") }}" class="btn btn-info add-new"><i class="fa fa-plus"></i> Add Vendor</a>
                </div>
                <div class="col-lg-12 col-sm-12 col-xs-12">
                    <div class="table-responsive">                        
                        <table id="example" class="table table-striped table-bordered">
                            <thead>
                                <tr>
                                    <th>Sr No.</th>
                                    <th>Vendor Name</th>
                                    <th>Contact Number</th>
                                    <th>Vendor Email</th>
                                    <th>Firm Name</th>
                                    <th>Business Type</th>
                                    <th>Office Contact Person</th>
                                    <th>Office Phone Number</th>
                                    <th>Office Email</th>
                                    <th>Tin No</th>
                                    <th>Registration No</th>
                                    <th>Service Tax No</th>
                                    <th>PAN No.</th>
                                    <th>Bank AC No.</th>
                                    <th>Bank Name</th>
                                    <th>Bank Branch</th>
                                    <th>Bank IFSC Code</th>
                                    <th>File No.</th>
                                    <th>File Location</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                            @php
                            $j=1;
                            @endphp    
                                @foreach($data['data'] as $key => $data)
                                <tr>
                                    <td>{{$j}}</td>
                                    <td>{{$data->vendor_name}}</td>
                                    <td>{{$data->contact_number}}</td>
                                    <td>{{$data->email}}</td>
                                    <td>{{$data->company_name}}</td>
                                    <td>{{$data->business_type}}</td>
                                    <td>{{$data->office_contact_person}}</td>
                                    <td>{{$data->office_number}}</td>
                                    <td>{{$data->office_email}}</td>
                                    <td>{{$data->tin_no}}</td>
                                    <td>{{$data->registration_no}}</td>
                                    <td>{{$data->serivce_tax_no}}</td>
                                    <td>{{$data->pan_no}}</td>
                                    <td>{{$data->bank_account_no}}</td>
                                    <td>{{$data->bank_name}}</td>
                                    <td>{{$data->bank_branch}}</td>
                                    <td>{{$data->bank_ifsc_code}}</td>
                                    <td>{{$data->file_number}}</td>
                                    <td>{{$data->file_location}}</td>
                                    <td>
                                        <div class="d-flex align-items-center justify-content-end">                                        
                                            <a href="{{ route('add_inventory_vendor_master.edit',$data->id)}}" class="btn btn-outline-success mr-1">
                                                <i class="ti-pencil-alt"></i>
                                            </a>
                                            <form action="{{ route('add_inventory_vendor_master.destroy', $data->id)}}" method="post" class="d-inline">
                                                @csrf
                                                @method('DELETE')
                                                <button class="btn btn-outline-danger" onclick="return confirmDelete();" type="submit">
                                                    <i class="ti-trash"></i>
                                                </button>
                                            </form>
                                        </div>
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
            </div>
        </div>
    </div>
</div>

@include('includes.footerJs')
<script>
$(document).ready(function () {
    $('#example').DataTable({
    });
});
</script>
@include('includes.footer')
