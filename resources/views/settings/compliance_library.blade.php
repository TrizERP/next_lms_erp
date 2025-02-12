<form action="{{route('institute_detail.store')}}" class="row" method="POST" enctype="multipart/form-data">
    @csrf
    <input type="hidden" name="formName" value="complaince_library">
    <div class="col-md-4 form-group">
        <label for="name">Name</label>
        <input type="text" class="form-control" name="name" id="compliance_name" required>
    </div>
    <div class="col-md-4 form-group">
        <label for="description">Description</label>
        <textarea name="description" id="description" class="form-control resizable"></textarea>
    </div>
    <div class="col-md-4 form-group">
        <label for="standard_name">Standard Name</label>
        <input type="text" class="form-control" name="standard_name" id="standard_name">
    </div>
    <div class="col-md-4 form-group">
        <label for="assigned_to">Assigned To</label>
        <select name="assigned_to" id="assigned_to" class="form-control" required>
            <option value="">Select Users</option>
            @foreach($data['userDetails'] as $key=>$value)
            <option value="{{$value['id']}}">{{$value['full_name']}}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-4 form-group">
        <label for="duedate">Due Date</label>
        <input type="text" class="form-control mydatepicker" name="duedate" id="duedate" required>
    </div>
    <div class="col-md-4 form-group">
        <label for="attachment">Attachment</label>
        <input type="file" class="form-control" name="attachment" id="attachment">
    </div>
    <div class="col-md-12">
        <center>
            <input type="submit" class="btn btn-primary" name="submit" value="Submit">
        </center>
    </div>
</form>

<a class="btn btn-outline-success" data-toggle="collapse" data-target="#collapseOne" aria-expanded="true"
    aria-controls="collapseOne">View Added Data</a>

<div id="collapseOne" class="collapse" aria-labelledby="headingOne" data-parent="#accordion">
    <div class="card-body">
        <div class="table-responsive">
            <table id="complainceTable" class="table table-box table-bordered">
                <thead>
                    <tr>
                        <th>Sr No.</th>
                        <th>Name</th>
                        <th>Description</th>
                        <th>Standard Name</th>
                        <th>Assigned To</th>
                        <th>Due Date</th>
                        <th>Attachment</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($data['complainceData'] as $key=>$value)
                    <tr>
                        <td>{{$key+1}}</td>
                        <td>{{$value->name}}</td>
                        <td>{!! substr(strip_tags($value->description), 0, 100) !!}....</td>
                        <td>{{$value->standard_name}}</td>
                        <td>{{$value->assigned_user}}</td>
                        <td>{{ date('d-m-Y',strtotime($value->duedate))}}</td>
                        <td>@if($value->attachment) <a href="https://s3-triz.fra1.cdn.digitaloceanspaces.com/public/compliance_library/{{$value->attachment}}" target="_blank">View</a> @else - @endif</td>
                        <td>
                            <div class="d-inline">
                                <a class="btn btn-info btn-outline" data-toggle="modal" data-target="#complainceModal_{{$value->id}}">
                                    <i class="ti-pencil-alt"></i>
                                </a>
                                <form action="{{ route('institute_detail.destroy', $value->id)}}"
                                        method="post" class="d-inline">
                                    @csrf
                                    @method('DELETE')
                                    <input type="hidden" name="formName" value="complaince_library">
                                    <button onclick="return confirmDelete();" type="submit"
                                            class="btn btn-outline-danger"><i class="mdi mdi-close"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @endforeach    
                </tbody>
            </table>
        </div>
    </div>
</div>

@foreach($data['complainceData'] as $key=>$value)
<!-- Modal -->
<div class="modal fade" id="complainceModal_{{$value->id}}" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="exampleModalLabel">Edit : {{$value->name}}</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <form action="{{route('institute_detail.update',[$value->id])}}" class="row" method="POST" enctype="multipart/form-data">
            @csrf
            {{ @method_field('PUT') }}
            <input type="hidden" name="formName" value="complaince_library">
            <div class="col-md-4 form-group">
                <label for="name">Name</label>
                <input type="text" class="form-control" name="name" id="compliance_name" value="{{$value->name}}" required>
            </div>
            <div class="col-md-4 form-group">
                <label for="description">Description</label>
                <textarea name="description" id="description" class="form-control resizable">{{$value->description}}</textarea>
            </div>
            <div class="col-md-4 form-group">
                <label for="standard_name">Standard Name</label>
                <input type="text" class="form-control" name="standard_name" id="standard_name" value="{{$value->standard_name}}">
            </div>
            <div class="col-md-4 form-group">
                <label for="assigned_to">Assigned To</label>
                <select name="assigned_to" id="assigned_to" class="form-control" required>
                    <option value="">Select Users</option>
                    @foreach($data['userDetails'] as $key=>$val)
                    <option value="{{$val['id']}}" @if($value->assigned_to==$val['id']) selected @endif>{{$val['full_name']}}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4 form-group">
                <label for="duedate">Due Date</label>
                <input type="text" class="form-control mydatepicker" name="duedate" id="duedate" value="{{$value->duedate}}" required>
            </div>
            <div class="col-md-4 form-group">
                <label for="attachment">Attachment</label>
                <input type="file" class="form-control" name="attachment" id="attachment">
                <input type="hidden" name="oldAttachment" value="{{$value->attachment}}">
                @if($value->attachment)<br><a href="https://s3-triz.fra1.cdn.digitaloceanspaces.com/public/compliance_library/{{$value->attachment}}" target="_blank">View</a>@endif
            </div>
            <div class="col-md-12">
                <center>
                    <input type="submit" class="btn btn-primary" name="submit" value="Update">
                </center>
            </div>
        </form>
      </div>
    </div>
  </div>
</div>
@endforeach
