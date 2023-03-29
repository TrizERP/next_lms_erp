@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Negotiat PO</h4>
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
                <div class="col-lg-12 col-sm-12 col-xs-12">
                    <div class="table-responsive">                        
                        <table id="example" class="table table-striped table-bordered">
                            <thead>
                                <tr>
                                    <th>Sr No.</th>
                                    <th>PO No.</th>
                                    <th>Item</th>
                                    <th>Vendor Name</th>
                                    <th>Firm Name</th>
                                    <th>Price</th>
                                    <th>Qty</th>
                                    <th>Amount</th>
                                    <!-- <th>Dis. Percentage</th>
                                    <th>Dis. Percentage Amount</th>
                                    <th>After Dis. Percentage Amount</th>
                                    <th>Tax Percentage</th>
                                    <th>Tax Amount</th>
                                    <th>After Tax Amount</th>
                                    <th>Grand Total</th> -->
                                    <th>Transportation Charge</th> 
                                    <th>Installation Charge</th>
                                    <th>Payment Terms</th>
                                    <th>Remarks</th>
                                    <th>Delivery Time</th>
                                    <th>PO Approval Status</th>
                                    <th>PO Approval Remarks</th>
                                    <th>PO Approved Date</th>
                                    <th>PO Approved By</th>
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
                                    <td>{{$data->po_number}}</td>
                                    <td>{{$data->item_name}}</td>
                                    <td>{{$data->vendor_id}}</td>
                                    <td>{{$data->company_name}}</td>
                                    <td>{{$data->price}}</td> 
                                    <td>{{$data->qty}}</td> 
                                    <td>{{$data->amount}}</td> 
                                    <!-- <td>{{$data->dis_per}}</td> 
                                    <td>{{$data->dis_amount_value}}</td> 
                                    <td>{{$data->after_dis_amount}}</td> 
                                    <td>{{$data->tax_per}}</td> 
                                    <td>{{$data->tax_amount_value}}</td> 
                                    <td>{{$data->after_tax_amount}}</td> 
                                    <td>{{$data->after_tax_amount}}</td>  -->
                                    <td>{{$data->transportation_charge}}</td> 
                                    <td>{{$data->installation_charge}}</td> 
                                    <td>{{$data->payment_terms}}</td> 
                                    <td>{{$data->remarks}}</td> 
                                    <td>{{date('d-m-Y H:i:s',strtotime($data->delivery_time))}}</td> 
                                    <td>{{$data->po_approval_status}}</td> 
                                    <td>{{$data->po_approval_remark}}</td> 
                                    <td>{{date('d-m-Y H:i:s',strtotime($data->po_approved_date))}}</td> 
                                    <td>{{$data->po_approved_by}}</td> 
                                    <td>
                                        <div class="d-flex align-items-center justify-content-end">
                                            <a href="{{ route('add_inventory_negotiate_po.edit',$data->id)}}" class="btn btn-outline-success">
                                                <i class="ti-pencil-alt"></i>
                                            </a>
                                            <form action="{{ route('add_inventory_negotiate_po.destroy', $data->id)}}" method="post" class="d-inline">
                                                @csrf
                                                @method('DELETE')
                                                <button class="btn btn-outline-danger" type="submit">
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
