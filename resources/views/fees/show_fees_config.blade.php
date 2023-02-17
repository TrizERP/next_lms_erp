@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Fees Config Master</h4>
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
                <div class="col-lg-6 col-sm-6 col-xs-6">
                    <a href="{{ route('fees_config_master.create') }}" class="btn btn-info add-new"><i class="fa fa-plus"></i> Add New Fees Config </a>
                    <a href="/storage/fees/final.pdf" class="btn btn-info add-new" download="/storage/fees/final.pdf">Download PDF</a>
                </div>
                <div class="col-lg-12 col-sm-12 col-xs-12">
                    <div class="table-responsive">
                        <table id="example" class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Id</th>
                                    <th>Institute Name</th>
                                    <th>Pan No</th>
                                    <th>Account No</th>
                                    <th>Late Fees Amount</th>
                                    <th>CMS Client Code</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                            @php
                            $j=1;
                            @endphp
                            @if(isset($data['data']))
                                @foreach($data['data'] as $key => $data)
                                <tr>    
                                    <td>{{$j}}</td>
                                    <td>{{$data->institute_name}}</td>
                                    <td>{{$data->pan_no}}</td>
                                    <td>{{$data->account_to_be_credited}}</td>  
                                    <td>{{$data->late_fees_amount}}</td> 
                                    <td>{{$data->cms_client_code}}</td> 
                                    <td>
                                        <div class="d-inline">                                            
                                            <a href="{{ route('fees_config_master.edit',$data->id)}}" class="btn btn-info btn-outline">
                                                <i class="ti-pencil-alt"></i>
                                            </a>
                                        </div>
                                        <form action="{{ route('fees_config_master.destroy', $data->id)}}" method="post" class="d-inline">
                                        @csrf
                                        @method('DELETE')
                                            <button type="submit" onclick="return confirmDelete();" class="btn btn-info btn-outline-danger">    <i class="ti-trash"></i>
                                            </button>
                                        </form>
                                    </td>  
                                </tr>
                            @php
                            $j++;
                            @endphp
                                @endforeach
    						@endif
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
