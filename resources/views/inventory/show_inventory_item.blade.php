@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
            <div class="row">
                <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                    <h4 class="page-title">Inventory Item</h4> </div>
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
                    <a href="{{ route('add_inventory_item.create') }}" class="btn btn-info add-new">
                        <i class="fa fa-plus"></i> Add Inventory Item
                    </a>
                </div>
                <div class="col-lg-12 col-sm-12 col-xs-12">
                    <div class="table-responsive">                        
                        <table id="example" class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Sr.No.</th>
                                    <th>Item Category</th>
                                    <th>Item Sub Category</th>
                                    <th>Item Type</th>
                                    <th>Title</th>
                                    <th>Opening Stock</th>
                                    <th>Description</th>
                                    <th>Minimum Stock</th>
                                    <th>Item Status</th>
                                    <th>Image</th>
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
                                    <td>{{$data->category_id}}</td>
                                    <td>{{$data->sub_category_id}}</td>
                                    <td>{{$data->item_type_id}}</td>  
                                    <td>{{$data->title}}</td> 
                                    <td>{{$data->opening_stock}}</td> 
                                    <td>{{$data->description}}</td> 
                                    <td>{{$data->minimum_stock}}</td> 
                                    <td>{{$data->item_status}}</td>                                     
                                    <td>
                                        <a target="blank" href="/storage/inventory_item/{{$data->item_attachment}}">{{$data->item_attachment}}</a>
                                    </td>
                                    <td>
                                        <div class="d-inline">
                                            <a href="{{ route('add_inventory_item.edit',$data->id)}}" class="btn btn-outline-success">
                                                <i class="ti-pencil-alt"></i>
                                            </a>
                                        </div>    
                                        <form action="{{ route('add_inventory_item.destroy', $data->id)}}" method="post" class="d-inline">
                                        @csrf
                                        @method('DELETE')
                                            <button type="submit" class="btn btn-outline-danger" onclick="return confirmDelete();">
                                                <i class="ti-trash"></i>
                                            </button>
                                        </form>
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

<script src="{{ asset("/plugins/bower_components/datatables/datatables.min.js") }}"></script>
<script>
$(document).ready(function () {
    $('#example').DataTable({

    });
});

</script>
@include('includes.footer')
