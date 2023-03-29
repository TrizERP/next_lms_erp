@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Requisition Form</h4> 
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
                    <a href="{{ route("add_requisition.create") }}" class="btn btn-info add-new"><i class="fa fa-plus"></i> Add Requisition Form</a>
                </div>
                <div class="col-lg-12 col-sm-12 col-xs-12">
                    <div class="table-responsive">                        
                        <table id="example" class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Sr.No.</th>
                                    <th>Requisition By</th>
                                    <th>Requisition Date</th>
                                    <th>Requisition No.</th>
                                    <th>Item</th>
                                    <th>Item Qty</th>
                                    <th>Unit</th>
                                    <th>Expected Delivery Time</th>
                                    <th>Remarks(if any)</th>
                                    <th>Requisition Status</th>
                                    <th>Requisition Approved By</th>
                                    <th>Requisition Approved Remarks</th>
                                    <th>Requisition Approved Date</th>
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
                                    <td>{{$data->requisition_name}}</td>
                                    <td>{{$data->requisition_date}}</td>
                                    <td>{{$data->requisition_no}}</td>
                                    <td>{{$data->item_name}}</td>
                                    <td>{{$data->item_qty}}</td>
                                    <td>{{$data->item_unit}}</td>
                                    <td>{{$data->expected_delivery_time}}</td>
                                    <td>{{$data->remarks}}</td>
                                    <td>{{$data->requisition_status}}</td>
                                    <td>{{$data->requisition_approved_by}}</td>
                                    <td>{{$data->requisition_approved_remarks}}</td>
                                    <td>{{$data->requisition_approved_date}}</td>
                                    <td>
                                        <div class="d-flex align-items-center justify-content-end">
                                            <a href="{{ route('add_requisition.edit',$data->id)}}" class="btn btn-outline-success">
                                                <i class="ti-pencil-alt"></i>
                                            </a>
                                            <form action="{{ route('add_requisition.destroy', $data->id)}}" method="post" class="d-inline">
                                                @csrf
                                                @method('DELETE')
                                                <button class="btn btn-outline-danger" type="submit" onclick="return confirmDelete();">
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
