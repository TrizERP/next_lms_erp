@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="card">
            @if(!empty($data['message']))
            <div class="alert alert-success alert-block">
                <button type="button" class="close" data-dismiss="alert">×</button>
                <strong>{{ $data['message'] }}</strong>
            </div>
            @endif
            <div class="row">                
                <div class="col-lg-3 col-sm-3 col-xs-3">
                    <a href="{{ route("add_inventory_item_lost.create") }}" class="btn btn-info add-new"><i class="fa fa-plus"></i> Add Item Lost</a>
                </div>
                <div class="col-lg-12 col-sm-12 col-xs-12">
                    <div class="table-responsive">                        
                        <table id="example" class="table table-striped table-bordered">
                            <thead>
                                <tr>
                                    <th>Sr No.</th>
                                    <th>Item Name</th>
                                    <th>Lost Date</th>
                                    <th>Remarks</th>
                                    <th>Requisition By</th>
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
                                    <td>{{$data->ITEM_NAME}}</td>
                                    <td>{{$data->LOST_DATE}}</td>
                                    <td>{{$data->REMARKS}}</td>
                                    <td>{{$data->requisition_name}}</td>
                                    <td>
                                        <div class="d-inline">                                            
                                            <a href="{{ route('add_inventory_item_lost.edit',$data->ID)}}" class="btn btn-outline-success">
                                                <i class="ti-pencil-alt"></i>
                                            </a>
                                        </div>
                                        <form action="{{ route('add_inventory_item_lost.destroy', $data->ID)}}" method="post" class="d-inline">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn btn-outline-danger" type="submit" onclick="return confirmDelete();"><i class="ti-trash"></i></button>
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
<script>
$(document).ready(function () {
    $('#example').DataTable({
      
    });
});

</script>
@include('includes.footer')
