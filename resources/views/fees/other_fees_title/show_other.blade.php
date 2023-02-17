@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Other Fees Title</h4>
            </div>
        </div>
        <div class="card">
            @if ($sessionData = Session::get('data'))
                @if($sessionData['status'] == 1)
                <div class="alert alert-success alert-block">
                @else
                <div class="alert alert-danger alert-block">
                @endif
                    <button type="button" class="close" data-dismiss="alert">×</button>
                    <strong>{{ $sessionData['message'] }}</strong>
                </div>
            @endif
            <div class="row">
                <div class="col-lg-3 col-sm-3 col-xs-3 mb-3">
                    <a href="{{ route('other_fees_title.create') }}" class="btn btn-info add-new"><i class="fa fa-plus"></i> Add New</a>
                </div>
                <div class="col-lg-12 col-sm-12 col-xs-12">
                    <div class="table-responsive">                            
                        <table id="example" class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Sr No</th>
                                    <th>Display Title</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th>Include Imprest</th>
                                    <th>Sort order</th>
                                    <th>Syear</th>
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
                                    <td>{{$data->display_name}}</td>
                                    <td>{{$data->amount}}</td>
                                    <td>{{$data->status}}</td>
                                    <td>{{$data->include_imprest}}</td>
                                    <td>{{$data->sort_order}}</td>
                                    <td>{{$data->syear}}</td>
                                    <td>
                                        <div class="d-inline">
                                           <a href="{{ route('other_fees_title.edit',$data->id)}}" class="btn btn-info btn-outline">
                                               <i class="ti-pencil-alt"></i>
                                           </a>                                            
                                        </div>
                                        <form action="{{ route('other_fees_title.destroy', $data->id)}}" method="post" class="d-inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" onclick="return confirmDelete();" class="btn btn-outline-danger">
                                                <i class="ti-trash"></i>
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
<script>
    $("#division").parent('.form-group').hide();
</script>
<script>
$(document).ready(function () {
    $('#example').DataTable();
});
</script>
@include('includes.footer')