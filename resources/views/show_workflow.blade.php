@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Create Workflow</h4>
            </div>            
        </div>
        <div class="row" style=" margin-top: 25px;">
        <div class="white-box">    
            <div class="panel-body">
                @if ($sessionData = Session::get('data'))
                <div class="alert alert-success alert-block">
                    <button type="button" class="close" data-dismiss="alert">×</button>
                    <strong>{{ $sessionData['message'] }}</strong>
                </div>
                @endif
				<?php                
                $servername = ($_SERVER['HTTP_HOST']);                
                ?>
                <div class="col-lg-3 col-sm-3 col-xs-3">
                    <a href="http://{{$servername}}/getworkflow.php" class="btn btn-info add-new">Get Workflow</a>
                </div>
				<div class="col-lg-3 col-sm-3 col-xs-3">
                    <a href="{{ route('workflow.create') }}" class="btn btn-info add-new"><i class="fa fa-plus"></i> Add New</a>
                </div>
                <br><br><br>
                <div class="col-lg-12 col-sm-12 col-xs-12">
                    <table id="batch_list" class="table table-striped table-bordered table-responsive" style="width:100%">
                        <thead>
                            <tr>
                                <th>ModuleName</th>                               
                                <th>Workflow Type</th>                               
                                <th>Description</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($data['wk_data'] as $key => $val)
                            <tr>    
                                <td>{{$val->modulename}}</td>
                                <td>{{$data['execute_arr'][$val->execute_id]}}</td>                 
                                <td>{{$val->description}}</td>                 
                                <td>
								@php								
								if($val->status == 1){
									echo "Active";
								}else{
									echo "Inactive";
								}
								@endphp
								</td>                 
                                <td style="display: inline-flex;">                                    
                                    <a href="{{ route('workflow.edit',$val->id)}}"><button type="button" class="btn btn-info btn-outline btn-circle btn m-r-5"><i class="ti-pencil-alt"></i></button></a>                                                                      
                                    
                                    <form action="{{ route('workflow.destroy', ['id'=>$val->id])}}" method="post">
                                    @csrf
                                    @method('DELETE')
                                    <button onclick="return confirmDelete();" type="submit" class="btn btn-info btn-outline btn-circle btn m-r-5"><i class="ti-trash"></i></button>
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
<script src="{{ asset("/plugins/bower_components/datatables/datatables.min.js") }}"></script>
<script>
$(document).ready(function () {
    $('#batch_list').DataTable({});
});

</script>
@include('includes.footer')
