@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
            <div class="row bg-title">
                <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                    <h4 class="page-title">PTM Attened Status</h4> </div>
            </div>

            <div class="card">
                <div class="panel-body">
                    @if ($sessionData = Session::get('data'))
                    <div class="alert alert-success alert-block">
                        <button type="button" class="close" data-dismiss="alert">×</button>
                        <strong>{{ $sessionData['message'] }}</strong>
                    </div>
                    @endif
                    <form action="{{ route('add_ptm_attened_status.create') }}">
                        <div class="row">
                            <div class="col-md-4 form-group">
                                <label>Date</label>
                                <input type="text" name="date" class="form-control mydatepicker" placeholder="Please select PTM date." required="required" value="@if(isset($data['date'])) {{$data['date']}} @endif" autocomplete="off">
                            </div>

                            <div class="col-md-4 form-group">
                                <label for="standard">Standard</label>
                                <select name="standard_id" id="standard_id" class="form-control">
                                    <option value="">Select Standard</option>
                                    @foreach($data['standards'] as $key => $value)
                                        <option value="{{$value['id']}}"
                                        @if(isset($data['standard_id']))
                                            @if($data['standard_id'] == $value['id'])
                                            selected='selected'
                                            @endif
                                        @endif
                                        >{{$value['name']}}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-4 form-group">
                                <label>Teacher</label>                                                                         
                                <select class="form-control" name="teacher_id" id="teacher_id">
                                    <option value="">Select Teacher</option>                        
                                    @if(isset($data['users']))
                                    @foreach($data['users'] as $key =>$val)
                                        @php 
                                        $selected = '';
                                        if( isset($data['teacher_id']) && $data['teacher_id'] == $val->id )
                                        {
                                            $selected = 'selected';
                                        }
                                        @endphp                                                                                                                                                                            
                                        <option {{$selected}} value="{{$val->id}}">{{$val->teacher_name}}</option>                            
                                    @endforeach                       
                                    @endif                                                                                                 
                                </select>                        
                            </div>                       

                            <div class="col-md-4 form-group">                               
                                <center>
                                    <input type="submit" name="submit" value="Search" class="btn btn-success" >
                                </center>    
                            </div>
                        </div>
                    </form>
                </div>
            </div>        
        @if(isset($data['student_data']))
        @php
            if(isset($data['student_data'])){
                $student_data = $data['student_data'];
                $finalData = $data;
            }
        @endphp       
            <div class="card">
                <div class="panel-body">
                <form method="POST" action="{{ route('add_ptm_attened_status.store') }}">
                    <div class="col-lg-12 col-sm-12 col-xs-12">
                    <div class="table-responsive">
                        <table id="example" class="table table-striped">
                            <thead>
                                <tr>
                                    <th><input id="checkall" onchange="checkAll(this);" type="checkbox"></th>
                                    <th>Student</th>
                                    <th>Standard</th>
                                    <th>Mobile</th>
                                    <th>Teacher</th>
                                    <th>PTM Title</th>
                                    <th>PTM Date</th>
                                    <th>PTM Time</th>
                                    <th>Attened Status</th>
                                    <th>Attened Remarks</th>
                                </tr>
                            </thead>
                            <tbody>
                                    @php
                                    $j=1;
                                    @endphp
                                @foreach($student_data as $key => $data)
                                <tr>
                                    <td><input id="{{$data->CHECKBOX}}" value="{{$data->CHECKBOX}}" name="students[]" type="checkbox"></td>
                                    <td>{{$data->STUDENT}}</td>
                                    <td>{{$data->std_div}}</td>
                                    <td>{{$data->mobile}}</td>
                                    <td>{{$data->TEACHER}}</td>
                                    <td>{{$data->title}}</td>
                                    <td>{{$data->PTM_DATE}}</td>
                                    <td>{{$data->TIME_SLOT}}</td>
                                    <td>
                                        <select name="attened_status[{{$data->CHECKBOX}}]" class="form-control">
                                            <option>--Select Status--</option>
                                            <option value="Yes" @if(isset($data->PTM_ATTENDED_STATUS)) @if($data->PTM_ATTENDED_STATUS == 'Yes') selected @endif @endif>Yes</option>
                                            <option value="No" @if(isset($data->PTM_ATTENDED_STATUS)) @if($data->PTM_ATTENDED_STATUS == 'No') selected @endif @endif>No</option>
                                        </select>
                                    </td>
                                    <td><textarea class="form-control" rows="2" name="attened_remarks[{{$data->CHECKBOX}}]">{{$data->PTM_ATTENDED_REMARKS}}</textarea></td>
                                </tr>
                                    @php
                                    $j++;
                                    @endphp
                                @endforeach
                            </tbody>
                        </table>                        
                    </div>
                    <div class="col-md-12 form-group mt-4">
                            <center>
                                <input type="submit" name="submit" value="Submit" class="btn btn-success" >
                            </center>
                        </div>
                    </div>
                </form>
                </div>
            </div>        
        @endif
    </div>
</div>

@include('includes.footerJs')
<script>
	function checkAll(ele) {
	     var checkboxes = document.getElementsByTagName('input');
	     if (ele.checked) {
	         for (var i = 0; i < checkboxes.length; i++) {
	             if (checkboxes[i].type == 'checkbox') {
	                 checkboxes[i].checked = true;
	             }
	         }
	     } else {
	         for (var i = 0; i < checkboxes.length; i++) {
	             console.log(i)
	             if (checkboxes[i].type == 'checkbox') {
	                 checkboxes[i].checked = false;
	             }
	         }
	     }
	}
</script>
<script>
$(document).ready(function () {
    $('#example').DataTable();
});
</script>
@include('includes.footer')
