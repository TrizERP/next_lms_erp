@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Add Transport Kilometer Rate</h4>
            </div>            
        </div>        
        <div class="card">
               @if(!empty($data['message']))
            <div class="alert alert-success alert-block">
                <button type="button" class="close" data-dismiss="alert">×</button>
                <strong>{{ $data['message'] }}</strong>
            </div>
            @endif
           
            <div class="col-lg-3 col-sm-3 col-xs-3">
                <a href="{{ route('transport_rate.create') }}" class="btn btn-info add-new"><i class="fa fa-plus"></i> Add New</a>
                
            </div><br>
            <div class="col-lg-12 col-sm-12 col-xs-12" style="overflow:auto;">
                <table id="example" class="table table-striped table-bordered" style="width:100%">
                    <thead>
                        <tr>
                            <th>Id</th>
                            <th>Distance From School</th>
                            <th>From Distance</th>
                            <th>To Distance</th>
                            <th>Rick Old</th>
                            <th>Rick New</th>
                            <th>Van Old</th>
                            <th>Van New</th>
                            <th>Created On</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($datas as $element => $val)
                        <tr>    
                            <td>{{$element+1 }}</td>
                            <td>{{$val->distance_from_school}}</td>
                            <td>{{$val->from_distance}}</td>
                            <td>{{$val->to_distance}}</td>
                            <td>{{$val->rick_old}}</td>
                            <td>{{$val->rick_new}}</td>
                            <td>{{$val->van_old}}</td>
                            <td>{{$val->van_new}}</td>
                            <td>{{$val->created_on}}</td>
                                <td class="d-flex">
                                <div class="d-inline">
                                    <a href="{{ route('transport_rate.edit',$val->id)}}" class="btn btn-info btn-outline">
                                        <i class="ti-pencil-alt"></i>
                                    </a>
                                </div>
                             <form method="post" action="{{route('transport_rate.destroy',$val->id)}}">
                            @method('delete')
                            @csrf
                            <button type="submit" class="btn btn-danger btn-outline mx-2"><i class="ti ti-trash"></i></button>
                        </form>

                        </tr>
                        @endforeach
                    </tbody>
                </table>
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