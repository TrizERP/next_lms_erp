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
                    <a href="{{ route('result_master.create') }}" class="btn btn-info add-new"><i class="fa fa-plus"></i> Add New</a>
                </div>
                <div class="col-lg-12 col-sm-12 col-xs-12">
                    <div class="table-responsive">
                        <table id="example" class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Term</th>
                                    <th>Grade</th>
                                    <th>Standard</th>
                                    <th>Result Date</th>
                                    <th>Reopen Date</th>
                                    <th>Vaction Start Date</th>
                                    <th>Vaction End Date</th>
                                    <th>Result Remark</th>
                                    <th>Optional Subject Display</th>
                                    <th>Remove Fail Percentage</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($data['data'] as $key => $data)
                                <tr>    
                                    <td>{{$data->term_name}}</td>
                                    <td>{{$data->grade_name}}</td>
                                    <td>{{$data->standard_name}}</td>
                                    <td>{{$data->result_date}}</td>                 
                                    <td>{{$data->reopen_date}}</td>                 
                                    <td>{{$data->vaction_start_date}}</td>                 
                                    <td>{{$data->vaction_end_date}}</td>                 
                                    <td>{{$data->result_remark}}</td>                 
                                    <td>{{$data->optional_subject_display}}</td>                 
                                    <td>{{$data->remove_fail_per}}</td>     
                                    <td>
                                        <div class="d-inline">                                            
                                            <a href="{{ route('result_master.edit',$data->id)}}" class="btn btn-outline-success">
                                                <i class="ti-pencil-alt"></i>
                                            </a>
                                        </div>
                                        <form action="{{ route('result_master.destroy', $data->id)}}" method="post" class="d-inline">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn btn-outline-danger" onclick="return confirmDelete();" type="submit">
                                                <i class="ti-trash"></i></button>
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
