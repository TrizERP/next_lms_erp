@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Individual Rights</h4>
            </div>
        </div>
        <div class="card">
            @if ($sessionData = Session::get('data'))
            <div class="alert alert-success alert-block">
                <button type="button" class="close" data-dismiss="alert">×</button>
                <strong>{{ $sessionData['message'] }}</strong>
            </div>
            @endif
            <div class="row">
                <div class="col-lg-3 col-sm-3 col-xs-3">
                    <a href="{{ route('add_individual_rights.create') }}" class="btn btn-info add-new"><i class="fa fa-plus"></i> Manage Individual Rights </a>
                </div>
                <div class="col-lg-12 col-sm-12 col-xs-12">
                    <div class="table-responsive">
                        <table id="example" class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Id</th>
                                    <th>User Profile Name</th>
                                    <th>User Name</th>
                                    <th>Menu Name</th>
                                    <th><center>Can View</center></th>
                                    <th><center>Can Add</center></th>
                                    <th><center>Can Edit</center></th>
                                    <th><center>Can Delete</center></th>
                                </tr>
                            </thead>
                            <tbody>
                            @php
                            $j=1;
                            @endphp
                                @foreach($data['data'] as $key => $data)
                                <tr>    
                                    <td>{{$j}}</td>
                                    <td>{{$data->profile_name}}</td>
                                    <td>{{$data->user_name}}</td>
                                    <td>{{$data->menu_name}}</td>
                                    <td>
                                    <center>
                                    @if ($data->can_view == 1) 
                                    <i class="fa fa-check text-success"></i>
                                    @else 
                                    <i class="fa fa-times text-danger"></i>
                                    @endif
                                    </center>
                                    </td>
                                    <td>
                                    <center>
                                    @if ($data->can_add == 1) 
                                    <i class="fa fa-check text-success"></i>
                                    @else 
                                    <i class="fa fa-times text-danger"></i>
                                    @endif
                                    </center>
                                    </td>  
                                    <td>
                                    <center>
                                    @if ($data->can_edit == 1) 
                                    <i class="fa fa-check text-success"></i>
                                    @else 
                                    <i class="fa fa-times text-danger"></i>
                                    @endif
                                    </center>
                                    </td> 
                                    <td>
                                    <center>
                                    @if ($data->can_delete == 1) 
                                    <i class="fa fa-check text-success"></i>
                                    @else 
                                    <i class="fa fa-times text-danger"></i>
                                    @endif
                                    </center>                                    
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
    $('#example').DataTable();
});

</script>
@include('includes.footer')
