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
                        <a href="{{ route('dynamic_report.create') }}" class="btn btn-info add-new"><i class="fa fa-plus"></i> Add New</a>
                    </div>
                    <div class="col-lg-12 col-sm-12 col-xs-12">
                        <div class="table-responsive">
                            <table id="example" class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Sr.No.</th>
                                        <th>Report Name</th>
                                        <th>Description</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                @php
                                 if(isset($data['data'])){
                                @endphp
                                    @foreach($data['data'] as $key => $data)
                                    <tr>    
                                        <td>{{$data->SrNo}}</td>
                                        <td>{{$data->report_name}}</td>
                                        <td>{{$data->description}}</td>
                                        <td>
                                            <div class="d-inline">
                                                <a href="{{ route('dynamic_report.show',$data->id)}}" class="btn btn-info btn-outline"><i class="fa fa-eye"></i></button></a>
                                            </div>
                                            <form class="d-inline" action="{{ route('dynamic_report.destroy', $data->id)}}" method="post">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" onclick="return confirmDelete();" class="btn btn-info btn-outline-danger"><i class="ti-trash"></i></button>
                                            </form>
                                        </td>  
                                    </tr>
                                    @endforeach
                                @php
                                    }
                                @endphp
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
        
    });
});

</script>
@include('includes.footer')
