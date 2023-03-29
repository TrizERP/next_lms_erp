@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
            <div class="row bg-title">
                <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                    <h4 class="page-title">Items Direct Purchase</h4> 
                </div>
            </div>

            <div class="card">
                <div class="panel-body">
                    @if(!empty($data['message']))
                    <div class="alert alert-success alert-block">
                        <button type="button" class="close" data-dismiss="alert">×</button>
                        <strong>{{ $data['message'] }}</strong>
                    </div>
                    @endif
                    <div class="col-lg-3 col-sm-3 col-xs-3">
                        <a href="{{ route('add_item_direct_purchase.create') }}" class="btn btn-info add-new"><i class="fa fa-plus"></i> Add Items Direct Purchase</a>
                    </div>
                    
                    <div class="col-lg-12 col-sm-12 col-xs-12">
                        <div class="table-responsive">
                            <table id="example" class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Sr.No.</th>
                                        <th>Vendor Name</th>
                                        <th>Category Name</th>
                                        <th>Sub Category Name</th>
                                        <th>Item Name</th>
                                        <th>Item Qty</th>
                                        <th>Item Price</th>
                                        <th>Total Amount</th>
                                        <th>Challan No.</th>
                                        <th>Challan Date</th>                                    
                                        <th>Bill No.</th>                                    
                                        <th>Bill Date</th>                                    
                                        <th>Remarks</th>                                    
                                        <th>Created By</th>                                    
                                        <th>Created Date</th>                                  
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                @php
                                $j=1;                                
                                @endphp
                                @if(isset($data['data']))
                                    @foreach($data['data'] as $key => $data)
                                    <tr> 
                                        <td>{{$j}}</td>
                                        <td>{{$data->vendor_name}}</td>
                                        <td>{{$data->catergory_name}}</td>
                                        <td>{{$data->sub_catergory_name}}</td>  
                                        <td>{{$data->item_name}}</td> 
                                        <td>{{$data->item_qty}}</td> 
                                        <td>{{$data->price}}</td> 
                                        <td>{{$data->amount}}</td> 
                                        <td>{{$data->challan_no}}</td> 
                                        <td>{{date('d-m-Y',strtotime($data->challan_date))}}</td> 
                                        <td>{{$data->bill_no}}</td> 
                                        <td>{{date('d-m-Y',strtotime($data->bill_date))}}</td> 
                                        <td>{{$data->remarks}}</td> 
                                        <td>{{$data->created_by}}</td> 
                                        <td>{{date('d-m-Y H:i:s',strtotime($data->created_on))}}</td> 
                                        <td>                                        
                                            <div class="d-flex align-items-center justify-content-end">
                                                <a href="{{ route('add_item_direct_purchase.edit',$data->id)}}" class="btn btn-outline-success mr-1">
                                                    <i class="ti-pencil-alt"></i></a>
                                                <form action="{{ route('add_item_direct_purchase.destroy', $data->id)}}" class="d-inline" method="post">
                                                @csrf
                                                @method('DELETE')
                                                    <button onclick="return confirmDelete();" type="submit" class="btn btn-outline-danger"><i class="ti-trash"></i></button>
                                                </form>
                                            </div>
                                        </td>     
                                    </tr>
                                    @php
                                    $j++;
                                    @endphp
                                    @endforeach
                                @endif    
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
    $('#example').DataTable();
});
</script>
@include('includes.footer')
