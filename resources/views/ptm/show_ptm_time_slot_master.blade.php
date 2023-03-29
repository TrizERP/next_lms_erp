@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">PTM Time Slot Master</h4>
            </div>            
        </div>        
        <div class="card">    
            <div class="panel-body">
                @if ($sessionData = Session::get('data'))
                <div class="alert alert-success alert-block">
                    <button type="button" class="close" data-dismiss="alert">×</button>
                    <strong>{{ $sessionData['message'] }}</strong>
                </div>
                @endif
                <div class="col-lg-3 col-sm-3 col-xs-3">
                    <a href="{{ route('add_ptm_time_slot_master.create') }}" class="btn btn-info add-new"><i class="fa fa-plus"></i> Add New</a>
                </div>
                
                <div class="col-lg-12 col-sm-12 col-xs-12">
                    <div class="table-responsive">                    
                        <table id="example" class="table table-striped table-bordered" style="width:100%">   
                        <thead>
                            <tr>
                                <th>Sr.No.</th>                               
                                <th>Standard</th>                               
                                <th>Division</th>
                                <th>PTM Date</th>
                                <th>PTM Title</th>
                                <th>PTM Time-Slot</th>
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
                                <td>{{$data->standard_name}}</td>
                                <td>{{$data->division_name}}</td>                 
                                <td>{{$data->ptm_date}}</td>                 
                                <td>{{$data->title}}</td>                 
                                <td>{{$data->from_time}} - {{$data->to_time}}</td>                 
                                <td>
                                    <div class="d-inline">
                                        <a href="{{ route('add_ptm_time_slot_master.edit',$data->id)}}" class="btn btn-info btn-outline"><i class="ti-pencil-alt"></i></a>                                                                                                            
                                    </div>
                                    <form action="{{ route('add_ptm_time_slot_master.destroy',$data->id)}}" method="post" class="d-inline">
                                    @csrf
                                    @method('DELETE')
                                    <button onclick="return confirmDelete();" type="submit" class="btn btn-outline-danger"><i class="ti-trash"></i></button>
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
    $('#batch_list').DataTable({});
});

</script>
@include('includes.footer')
