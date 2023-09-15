@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Fees Head Master</h4>
			</div>
		</div>
        <div class="card">
            @if ($sessionData = Session::get('data'))
	            @if($sessionData['status_code'] == 1)
	            <div class="alert alert-success alert-block">
	            @else
	            <div class="alert alert-danger alert-block">
                    @endif
                    <button type="button" class="close" data-dismiss="alert">×</button>
                    <strong>{{ $sessionData['message'] }}</strong>
                </div>
                    @endif
                    <form action="{{ route('fees_head_type_master.index') }}" enctype="multipart/form-data">
                        @csrf
                        <div class="row">
                            <div class="col-md-5 form-group">
                                <label>Code</label>
                                <input type="text" name="code" placeholder="Please Enter Head Code"
                                       @if(isset($data['code']))value="{{$data['code']}}" @endif class="form-control">
                            </div>
                            <div class="col-md-5 form-group">
                                <label>Head Title</label>
                                <input type="text" name="head_title" placeholder="Please Enter Head Title"
                                       @if(isset($data['head_title']))value="{{$data['head_title']}}"
                                       @endif class="form-control">
                            </div>
                            <div class="col-md-12 form-group">
                                <center>
                                    <input type="submit" name="submit" value="Search" class="btn btn-success">
                                </center>
                            </div>
                        </div>
                    </form>
                </div>
        </div>
        <div class="container-fluid">
            <div class="card">
                <div class="row">
                    <div class="col-lg-3 col-sm-3 col-xs-3 mb-30">
                        <a href="{{ route('fees_head_type_master.create') }}" class="btn btn-info add-new">
                            <i class="fa fa-plus"></i> Add New Fees Head
                        </a>
                    </div>
                    <div class="col-lg-12 col-sm-12 col-xs-12">
                        <div class="table-responsive">
                            <table id="example" class="table table-striped">
                                <thead>
                                <tr>
                                    <th>Sr No</th>
                                    <th>Code</th>
                                    <th>Head Title</th>
                                    <th>Description</th>
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
                                            <td>{{$data->code}}</td>
                                            <td>{{$data->head_title}}</td>
                                            <td>{{$data->description}}</td>
                                            <td>
                                                <div class="d-inline">
                                                    <a href="{{ route('fees_head_type_master.edit',$data->id)}}"
                                                       class="btn btn-info btn-outline">
                                                        <i class="ti-pencil-alt"></i>
                                                    </a>
                                                </div>
                                                <form action="{{ route('fees_head_type_master.destroy', $data->id)}}"
                                                      method="post" class="d-inline">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" onclick="return confirmDelete();"
                                                            class="btn btn-info btn-outline-danger"><i
                                                            class="ti-trash"></i>
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
