@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
            <div class="row bg-title">
                <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                    <h4 class="page-title">Petty Cash</h4> </div>
            </div>        
            <div class="card">
                <div class="panel-body">
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
                    <div class="col-lg-3 col-sm-3 col-xs-3">
                        <a href="{{ route('pettycash.create') }}" class="btn btn-info add-new"><i class="fa fa-plus"></i> Add New</a>
                    </div>
                    <br><br><br>
                    <div class="col-lg-12 col-sm-12 col-xs-12">
                    <div class="table-responsive">
                        <table id="example" class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Sr No</th>
                                    <th>Date</th>                                                                        
                                    <th>Title</th>                                                                        
                                    <th>Amount</th>                                                                        
                                    <th>Description</th> 
                                    <th>Image</th> 
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                            @php
                            $j=1;
                            @endphp
                            @if(isset($data['data']))
                                @foreach($data['data'] as $key => $value)
                                <tr>
                                    <td>{{$j}}</td>
                                    <td>{{date('d-m-Y',strtotime($value->bill_date))}}</td>
                                    <td>{{$value->title_name}}</td>                                                                        
                                    <td>{{$value->amount}}</td>                                                                        
                                    <td>{{$value->description}}</td>                                                                        
                                    <td><img height="50px" width="50px" src="../pettycash/{{$value->bill_image}}" /></td>
                                    <td>
                                        <form action="{{ route('pettycash.destroy', $value->id)}}" method="post">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" onclick="return confirmDelete();" class="btn btn-outline-danger"><i class="ti-trash"></i></button>
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
