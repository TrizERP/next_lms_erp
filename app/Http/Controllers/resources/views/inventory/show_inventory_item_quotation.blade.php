@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Item Quotation</h4> 
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
                    <a href="{{ route("add_inventory_item_quotation.create") }}" class="btn btn-info add-new"><i class="fa fa-plus"></i> Add Item Quotation</a>
                </div>
                <div class="col-lg-12 col-sm-12 col-xs-12">
                    <div class="table-responsive">                        
                        <table id="example" class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Sr No.</th>
                                    <th>Item</th>
                                    <th>Vendor Name</th>                                    
                                    <th>Quantity</th>
                                    <th>Rate</th>
                                    <th>Unit</th>
                                    <th>Tax</th>
                                    <th>Total Price</th>
                                    <th>Remarks (if any)</th>
                                    <th>Transportation Charge</th>
                                    <th>Installation Charge</th>
                                    <th>Approval Status</th>
                                    <th>Approved Date</th>
                                    <th>Approved By</th>
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
                                    <td>{{$data->item_id}}</td>
                                    <td>{{$data->vendor_id}}</td>                                   
                                    <td>{{$data->qty}}</td>
                                    <td>{{$data->price}}</td>
                                    <td>{{$data->unit}}</td> 
                                    <td>{{$data->tax}}</td> 
                                    <td>{{$data->total}}</td> 
                                    <td>{{$data->remarks}}</td> 
                                    <td>{{$data->transportation_charge}}</td> 
                                    <td>{{$data->installation_charge}}</td> 
                                    <td>{{$data->approved_status}}</td> 
                                    <td>{{date('d-m-Y',strtotime($data->approved_date))}}</td> 
                                    <td>{{$data->first_name}} {{$data->last_name}}</td> 
                                    <td>
                                        <div class="d-flex align-items-center justify-content-end">                                        
                                            <a href="{{ route('add_inventory_item_quotation.edit',$data->id)}}" class="btn btn-outline-success">
                                                <i class="ti-pencil-alt"></i>
                                            </a>
                                        <form action="{{ route('add_inventory_item_quotation.destroy', $data->id)}}" method="post" class="d-inline">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn btn-outline-danger" type="submit" onclick="return confirmDelete();"><i class="ti-trash"></i></button>
                                        </form>
                                        </div>
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
<script src="https://cdn.datatables.net/1.10.19/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.10.19/js/dataTables.bootstrap.min.js"></script>
<script>
$(document).ready(function () {
    $('#example').DataTable({
        "scrollX": true
    });
});

</script>
@include('includes.footer')
