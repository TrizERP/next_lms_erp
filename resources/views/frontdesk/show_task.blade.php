@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
            <div class="row bg-title">
                <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                    <h4 class="page-title">Tasks</h4> </div>
            </div>       
            <div class="card">
                <div class="row">                    
                    <div class="col-lg-12 col-sm-12 col-xs-12">
                        <form action="{{ route('task.index') }}" enctype="multipart/form-data">

                            @csrf
                            <div class="row">
                                <div class="col-md-4 form-group">
                                    <label>From Date </label>
                                    <input type="text" id='from_date' value="@if(isset($data['from_date'])) {{$data['from_date']}} @endif" required name='from_date' class="form-control mydatepicker">
                                </div>

                                <div class="col-md-4 form-group">
                                    <label>To Date </label>
                                    <input type="text" id='to_date' value="@if(isset($data['to_date'])) {{$data['to_date']}} @endif" required name='to_date' class="form-control mydatepicker">
                                </div>

                                <div class="col-md-4 form-group mt-4">
                                    <br>
                                    <input type="submit" name="submit" value="Search" class="btn btn-success" >
                                </div>
                            </div>

                        </form>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="row">
                    <div class="col-lg-12">
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
                        <a href="{{ route('task.create') }}" class="btn btn-info add-new"><i class="fa fa-plus"></i> Add New Tasks</a>
                    </div>
                    <br><br><br>
                    <div class="col-lg-12 col-sm-12 col-xs-12">
                    <div class="table-responsive">
                        <table id="example" class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Sr No</th>
                                    <th>Title</th>
                                    <th>Description</th>
                                    <th>Date</th>
                                    <th>Allocator</th>
                                    <th>Allocated To</th>
                                    <th>Reply</th>
                                    <th>Status</th>
                                    <th>Approved By</th>
                                    <th>Attachment</th>
                                    <th>Approved Date</th>
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
                                    <td>{{$value->TASK_TITLE}}</td>
                                    <td>{{$value->TASK_DESCRIPTION}}</td>
                                    <td>{{ $value->TASK_DATE ? date('d-m-Y', strtotime($value->TASK_DATE)) : '-' }}</td>
                                    <td>{{$value->ALLOCATOR}}</td>
                                    <td>{{$value->ALLOCATED_TO}}</td>
                                    <td>{{$value->reply}}</td>
                                    <td>{{$value->STATUS}}</td>
                                    <td>@if($value->approved_by == "") - @else {{$value->approved_by}} @endif</td>
                                    <td>
                                        <a target="blank" href="/storage/frontdesk/{{$value->TASK_ATTACHMENT}}">{{$value->TASK_ATTACHMENT}}</a> </td>
                                    <td>{{ $value->approved_on ? date('d-m-Y H:i:s', strtotime($value->approved_on)) : '-' }}</td>
                                    <td>
                                        <div class="d-inline">
                                            <a href="{{ route('task.edit',$value->ID)}}" class="btn btn-info btn-outline"><i class="ti-pencil-alt"></i></a>
                                        </div>
                                        <form action="{{ route('task.destroy', $value->ID)}}" method="post" class="d-inline">
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
