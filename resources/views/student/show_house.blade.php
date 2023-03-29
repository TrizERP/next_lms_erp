@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
            <div class="row bg-title">
                <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                    <h4 class="page-title">House</h4> 
                </div>
            </div>

            <div class="card">
                <div class="panel-body">
                    @if($sessionData = Session::get('data'))
                    @if($sessionData['status_code'] == 1)
                        <div class="alert alert-success alert-block">
                        @else
                        <div class="alert alert-danger alert-block">
                    @endif
                        <button type="button" class="close" data-dismiss="alert">×</button>
                        <strong>{{ $sessionData['message'] }}</strong>
                    </div>
                    @endif

                    <div class="col-lg-3 col-sm-3 col-xs-3">
                        <a href="{{ route('add_house.create') }}" class="btn btn-info add-new"><i class="fa fa-plus"></i> Add House</a>
                    </div>
                    
                    <div class="col-lg-12 col-sm-12 col-xs-12">
                        <div class="table-responsive">
                            <table id="example" class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Sr.No.</th>
                                        <th>House Name</th>                                    
                                        <th>Sort Order</th>                                    
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
                                        <td>{{$data->house_name}}</td>                                     
                                        <td>{{$data->sort_order}}</td>                                     
                                        <td>                                        
                                            <div class="d-flex align-items-center justify-content-end">
                                                <a href="{{ route('add_house.edit',$data->id)}}" class="btn btn-outline-success mr-1">
                                                    <i class="ti-pencil-alt"></i></a>
                                                <form action="{{ route('add_house.destroy', $data->id)}}" class="d-inline" method="post">
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
