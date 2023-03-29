@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Inventory Master Setup</h4> 
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
                    <a href="{{ route("add_inventory_master_setup.create") }}" class="btn btn-info add-new">
                        <i class="fa fa-plus"></i> Add Inventory Master Setup
                    </a>
                </div>
                <div class="col-md-12">
                    <div class="table-responsive">                        
                        <table id="example" class="table table-striped">
                            <thead>
                                <tr>
                                    <th>GST Registration No.</th>
                                    <th>GST Registration Date</th>
                                    <th>CST Registration No.</th>
                                    <th>CST Registration Date</th>
                                    <th>LOGO</th>
                                    <th>PO No Prefix</th>
                                    <th>Item Setting for Requisition</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($data['data'] as $key => $data)
                                <tr>    
                                    <td>{{$data->GST_REGISTRATION_NO}}</td>
                                    <td>{{$data->GST_REGISTRATION_DATE}}</td>
                                    <td>{{$data->CST_REGISTRATION_NO}}</td>
                                    <td>{{$data->CST_REGISTRATION_DATE}}</td>
                                    <td>{{$data->LOGO}}</td>
                                    <td>{{$data->PO_NO_PREFIX}}</td>
                                    <td>{{ucwords(str_replace('_', ' ', $data->ITEM_SETTING_FOR_REQUISITION))}}</td>
                                    <td>
                                        <div class="d-inline">
                                            <a href="{{ route('add_inventory_master_setup.edit',$data->ID)}}" class="btn btn-outline-success"><i class="ti-pencil-alt"></i>
                                            </a>
                                        </div>
                                        <form action="{{ route('add_inventory_master_setup.destroy', $data->ID)}}" method="post" class="d-inline">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn btn-outline-danger" onclick="return confirmDelete();" type="submit">
                                                <i class="ti-trash"></i>
                                            </button>
                                        </form>
                                    </td>    
                                </tr>
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
