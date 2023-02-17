@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Item Defective</h4> 
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
                    <a href="{{ route("add_inventory_item_defective.create") }}" class="btn btn-info add-new"><i class="fa fa-plus"></i> Add Item Defective</a>
                </div>
                <div class="col-lg-12 col-sm-12 col-xs-12">
                    <div class="table-responsive">                        
                        <table id="example" class="table table-striped table-bordered">
                            <thead>
                                <tr>
                                    <th>Sr No.</th>
                                    <th>Item Name</th>
                                    <th>Defect Remarks</th>
                                    <th>Item Given To</th>
                                    <th>Estimated Received Date</th>
                                    <th>Warranty Start Date</th>
                                    <th>Warranty End Date</th>
                                    <th>Added By</th>
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
                                    <td>{{$data->item_name}}</td>
                                    <td>{{$data->DEFECT_REMARKS}}</td>
                                    <td>{{$data->ITEM_GIVEN_TO}}</td>
                                    <td>{{date('d-m-Y',strtotime($data->ESTIMATED_RECEIVED_DATE))}}</td>
                                    <td>{{date('d-m-Y',strtotime($data->WARRANTY_START_DATE))}}</td>
                                    <td>{{date('d-m-Y',strtotime($data->WARRANTY_END_DATE))}}</td>
                                    <td>{{$data->created_by}}</td>
                                    <td>
                                        <div class="d-inline">                                        
                                            <a href="{{ route('add_inventory_item_defective.edit',$data->ID)}}">
                                                <button type="button" class="btn btn-outline-success">
                                                    <i class="ti-pencil-alt"></i>
                                                </button>
                                            </a>
                                        </div>
                                        <form action="{{ route('add_inventory_item_defective.destroy', $data->ID)}}" method="post" class="d-inline">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn btn-outline-danger" type="submit" onclick="return confirmDelete();">
                                                <i class="ti-trash"></i>
                                            </button>
                                        </form>
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
