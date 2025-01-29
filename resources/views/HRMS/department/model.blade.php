<style>
    .modal-dialog{
        max-width:1000px;
    }
</style>
<!-- Department Edit Modal -->
@foreach($data['userDepartmentList'] as $key=>$value)
<div class="modal fade" id="departmentEdit{{$value->id}}" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="exampleModalLabel">Edit Department</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
      <form action="{{route('add_department.update',$value->id)}}" method="post" class="row mt-4">
        {{method_field('PUT')}}
        @csrf
            <div class="col-md-4 form-group">
            <label for="deparment_title">Department Name</label>
            <input type="text" name="department_name" id="department_name" value="{{$value->department}}" required class="form-control" autocomplete="off">
            </div>

            <div class="col-md-4 form-group">
                <label class="control-label">Calculate PF/PT</label>
                <div class="radio-list">
                    <label class="radio-inline p-0">
                        <div class="radio radio-success">
                            <input type="radio" name="is_calculated" id="calculate" value="0" @if($value->is_calculated==0) checked @endif>
                            <label for="calculate">Calculate</label>
                        </div>
                    </label>
                    <label class="radio-inline">
                        <div class="radio radio-success">
                            <input type="radio" name="is_calculated" id="not_calculate" value="1" @if($value->is_calculated==1) checked @endif>
                            <label for="not_calculate">Not Calculate</label>
                        </div>
                    </label>
                </div>
            </div>

            <div class="col-md-12 form-group">
            <label for="tasks">Department Tasks</label>
            <input type="text" name="tasks" id="tasks" class="form-control" value="{{$value->tasks}}" autocomplete="off" >
            </div>

            <div class="col-md-12 form-group">
            <label for="roles_responsibility">Aims & Objectives</label>
            <textarea name="roles_responsibility" id="roles_responsibility" class="form-control"  style="height:10vh">{{$value->roles_responsibility}}</textarea>
            </div>

            <div class="col-md-12">
            <center>
                <input type="submit" name="update" Value="Update" class="btn btn-primary">
            </center>
            </div>
        </form>
      </div>
      
    </div>
  </div>
</div>
@endforeach

<!-- Sub Department Edit Modal -->
@foreach($data['SubDepartmentList'] as $key=>$value)
<div class="modal fade" id="subDepartmentEdit{{$value->id}}" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="exampleModalLabel">Edit Sub Department</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
      <form action="{{route('add_department.update',$value->id)}}" method="post" class="row mt-4">
        {{method_field('PUT')}}
        @csrf
            <div class="col-md-3 form-group">
            <label for="parent_id">Select Department</label>
            <select name="parentDiv" id="parentDiv" class="form-control" required>
                @foreach($data['departmentList'] as $keys=>$values)
                <option value="{{$values->id}}" @if($value->parent_id ==$values->id) selected @endif>{{$values->department}}</option>
                @endforeach
            </select>
            </div>

            <div class="col-md-3 form-group">
            <label for="deparment_title">Sub Department Name</label>
            <input type="text" name="department_name" id="department_name" value="{{$value->department}}" required class="form-control" autocomplete="off">
            </div>
            
            <div class="col-md-4 form-group">
                <label class="control-label">Calculate PF/PT</label>
                <div class="radio-list">
                    <label class="radio-inline p-0">
                        <div class="radio radio-success">
                            <input type="radio" checked="" name="is_calculated" id="calculate" value="0" @if($value->is_calculated==0) checked @endif>
                            <label for="calculate">Calculate</label>
                        </div>
                    </label>
                    <label class="radio-inline">
                        <div class="radio radio-success">
                            <input type="radio" name="is_calculated" id="not_calculate" value="1" @if($value->is_calculated==1) checked @endif>
                            <label for="not_calculate">Not Calculate</label>
                        </div>
                    </label>
                </div>
            </div>

            <div class="col-md-12 form-group">
            <label for="tasks">Department Tasks</label>
            <input type="text" name="tasks" id="tasks" class="form-control" value="{{$value->tasks}}">
            </div>

            <div class="col-md-12 form-group">
            <label for="roles_responsibility">Aims & Objectives</label>
            <textarea name="roles_responsibility" id="roles_responsibility_{{$value->id}}" class="form-control" style="height:10vh">{{$value->roles_responsibility}}</textarea>
            </div>

            <div class="col-md-12">
            <center>
                <input type="submit" name="Update" Value="Update" class="btn btn-primary">
            </center>
            </div>
        </form>
      </div>

    </div>
  </div>
</div>
@endforeach

<!-- Emp Data Modal -->
<div class="modal fade" id="empDataModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="exampleModalLabel">Department Mapped Employees</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <table class="table" style="filter:none !important">
            <thead>
                <tr>
                    <th>SR No.</th>
                    <th>Name</th>
                    <th>Mobile</th>
                    <th class="text-left">Department</th>
                </tr>
            </thead>
            <tbody id="empModelBody">
            </tbody>
        </table>
      </div>

    </div>
  </div>
</div>