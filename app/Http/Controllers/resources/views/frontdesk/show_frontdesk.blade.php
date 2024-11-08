@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
            <div class="row bg-title">
                <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                    <h4 class="page-title">Front Desk / Reception</h4> </div>
            </div>        
            <div class="card">
                <div class="row">
                    <div class="col-lg-12 col-sm-12 col-xs-12">
                        
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
                    </div>
                    <div class="col-lg-3 col-sm-3 col-xs-3">
                        <a href="{{ route('frontdesk.create') }}" class="btn btn-info add-new"><i class="fa fa-plus"></i> Add New </a>
                    </div>
                    
                    <div class="col-lg-12 col-sm-12 col-xs-12">
                    <div class="table-responsive">
                        <table id="example" class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Sr No</th>
                                    <th>Title</th>
                                    <th>Description</th>
                                    <th>Student Name</th>
                                    <th>Visitor Type</th>
                                    <th>To Whom Meet</th>
                                    <th>Date-Time</th>
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
                                    <td>{{$value->TITLE}}</td>
                                    <td>{{$value->DESCRIPTION}}</td>
                                    <td>{{$value->student_name}}</td>
                                    <td>{{$value->VISITOR_TYPE}}</td>
                                    <td>{{$value->user_name}}</td>
                                    <td>{{date('d-m-Y',strtotime($value->DATE))." ".$value->IN_TIME}}</td>                                     
                                    <td>
                                        <div class="d-inline">
                                            <a href="{{ route('frontdesk.edit',$value->ID)}}" class="btn btn-info btn-outline"><i class="ti-pencil-alt"></i></a>
                                        </div>
                                        <form action="{{ route('frontdesk.destroy', $value->ID)}}" method="post" class="d-inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" onclick="return confirmDelete();" class="btn btn-info btn-outline-danger"><i class="ti-trash"></i></button>
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
