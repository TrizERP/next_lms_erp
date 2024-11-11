@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">            
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">                
                <h4 class="page-title">Add Shift</h4>            
            </div>                    
        </div>        
            <div class="card">
                @if(!empty($data['message']))
                @if($data['status_code']==1)                
                <div class="alert alert-success alert-block">
                @else
                <div class="alert alert-danger alert-block">
                @endif
                    <button type="button" class="close" data-dismiss="alert">×</button>
                    <strong>{{ $data['message'] }}</strong>
                </div>
                @endif
                <div class="col-lg-3 col-sm-3 col-xs-3">
                    <a href="{{ route('transport_shift.create') }}" class="btn btn-info add-new"><i class="fa fa-plus"></i> Add New</a>
                </div>
                <br><br><br>
                <div class="col-lg-12 col-sm-12 col-xs-12" style="overflow:auto;">
                    <table id="example" class="table table-striped table-bordered" style="width:100%">
                        <thead>
                            <tr>
                                <th>Shift Name</th>
                                <th>Shift Rate</th>
                                <th>KM Amount</th>                                
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                        @if(isset($data['data']))
                        @foreach($data['data'] as $key => $val)
                        <tr>
                        <td>{{$val->shift_title}}</td>
                        <td>{{$val->shift_rate}}</td>
                        <td>{{$val->km_amount}}</td>  
                        <td>
                            <div class="d-inline">
                            <a href="{{ route('transport_shift.edit',$val->id)}}" class="btn btn-info btn-outline">
                                <i class="ti-pencil-alt"></i>
                            </a>
                            </div>
                            <form class="d-inline" action="{{ route('transport_shift.destroy', $val->id)}}" method="post">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-info btn-outline-danger" onclick="return confirmDelete();" type="submit"><i class="ti-trash"></i></button>
                            </form>
                        </td>                       
                        </tr>
                        @endforeach
                       @endif
                        </tbody>
                    </table>
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
        
    });
});

</script>
@include('includes.footer')
