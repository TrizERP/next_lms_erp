@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
    		<div class="row bg-title">
                <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                    <h4 class="page-title">Hostel Visitor</h4> </div>
            </div>        
        <div class="card">
            <div class="row">
                <div class="col-lg-12 col-sm-3 col-xs-3">
                    @if(!empty($data['message']))
                    <div class="alert alert-success alert-block">
                        <button type="button" class="close" data-dismiss="alert">×</button>
                        <strong>{{ $data['message'] }}</strong>
                    </div>
                    @endif
                </div>
                <div class="col-lg-3 col-sm-3 col-xs-3">
                    <a href="{{ route("add_hostel_visitor_master.create") }}" class="btn btn-info add-new"><i class="fa fa-plus"></i> Add Visitor</a>
                </div>
                <br><br><br>
                <div class="table-responsive">
                    <table id="example" class="table table-striped">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Contact</th>
                                <th>Email</th>
                                <th>Coming From</th>
                                <th>To Meet</th>
                                <th>Relation With</th>
                                <th>Date</th>
                                <th>Check In Time</th>
                                <th>Check Out Time</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($data['data'] as $key => $data)
                            <tr>    
                                <td>{{$data->name}}</td>
                                <td>{{$data->contact}}</td>
                                <td>{{$data->email}}</td>
                                <td>{{$data->coming_from}}</td>
                                <td>{{$data->to_meet}}</td>
                                <td>{{$data->relation}}</td>
                                <td>{{$data->meet_date}}</td>
                                <td>{{$data->in_time}}</td>
                                <td>{{$data->out_time}}</td>
                                <td>
                                    <div class="d-inline">
                                        <a href="{{ route('add_hostel_visitor_master.edit',$data->id)}}" class="btn btn-info btn-outline">
                                            <i class="ti-pencil-alt"></i>
                                        </a>
                                    </div>
                                    <form action="{{ route('add_hostel_visitor_master.destroy', $data->id)}}" method="post" class="d-inline">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-outline-danger" type="submit"><i class="ti-trash"></i></button>
                                    </form>
                            </tr>
                            @endforeach

                        </tbody>

                    </table>

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
