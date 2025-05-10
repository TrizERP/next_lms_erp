{{--@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')--}}
@extends('layout')
@section('container')
<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Inactive Student Bulk Update</h4>
            </div>
        </div>
        <div class="card">
            <div class="row mb-2">     
            </div>  
                @php $field = Session::get('data'); @endphp
                @if ($sessionData = Session::get('data'))
                    @if (isset($sessionData['status']))
                        <div class="alert alert-{{ $sessionData['status'] == 1 ? 'success' : 'danger' }} alert-block">
                            <button type="button" class="close" data-dismiss="alert">×</button>
                            <strong>{!! $sessionData['message'] !!}</strong>
                        </div>
                    @endif
                @endif

                <form action="{{ route('student_bulk_update.store') }}" id="submit_student_bulk_update_form" method="post" enctype="multipart/form-data">
                @csrf
                <div class="table-responsive">
                    <table class="table table-box table-bordered">
                        <thead>
                            <tr>
                                <th>Module Name</th>
                                <th style="text-align:left;">Check for Student Bluk Update Data
                                    <p style="color:red;">(No. of ACTIVE Students {{count($data['get_student_enrollments'])}})</p>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Can all students inactive</td>
                                <td>
                                    <input type="checkbox" id="student_bluk_update" name="tables" >
                                </td>
                            </tr>
                            <tr>
                                <td>Delete Fees Breakoff</td>
                                <td>	
                                    <div class="col-md-4 form-group" style="margin-left: 0px !important">
                                        <select id='bk_month' name="bk_month[]" class="form-control" multiple>
                                            <option disabled selected>--Select BK Month--</option>
                                            @if(isset($data['bk_month'])) 
                                            @foreach($data['bk_month'] as $key => $value)
                                            <option value="{{$key}}" @if(isset($field['sel_bk_month']) && in_array($key,$field  ['sel_bk_month'])) selected @endif>{{$value}}</option>
                                            @endforeach 
                                            @endif
                                        </select>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <th>Students active/inactive excel</th>
                                <td>
                                    <div class="row">
                                        <div class="col-md-4" style="margin-left: 0px !important">
                                            <select id='student_active_inactive_excel' name="student_active_inactive_excel" class="form-control">
                                                <option disabled selected>-- Select --</option>
                                                <option value="Active" name="active">Active</option>
                                                <option value="Inactive" name="inactive">Inactive</option>
                                            </select>
                                        </div>
                                        <div class="col-md-4" style="margin-left: 0px !important">
                                            <div class="custom-file">
                                                <input type="file" class="form-control" name="attachment" id="attachment">
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <!-- for roll no update  -->
                            <tr>
                                <td>Update Student Roll No : </td>
                                <td>
                                <div class="row">
                                    <div class="col-md-4">
                                    <label for="">Select Standard</label>
                                    <select name="rollno_standard" id="standard" class="form-control">
                                        <option disabled selected>Select Standard</option>
                                        @foreach($data['standard_arr'] as $key => $value)
                                        <option value="{{$value->standard_id}}">{{$value->standard_name}}</option>
                                        @endforeach
                                    </select>
                                    </div>
                                    <div class="col-md-4">
                                    <label for="">Select Division</label>
                                    <select name="division" id="division" class="form-control">
                                            <option disabled>Select Division</option>
                                    </select>
                                    </div>
                                </div>
                                </td>
                            </tr>
                             <!-- for Earned Levae no update  -->
                             <tr>
                                <td>Rollover Earned Opening Leaves: </td>
                                <td>
                                    <input type="checkbox" name="leave_rollover" id="leave_rollover">
                                    <br> 
                                    <span style="color:red;">Only current Year closed leave balance will be rollover to next year</span>
                                </td>
                            </tr>
                            <!-- for roll no update  -->
                            <tr>
                                <td>Rollover Casual Opening Leaves: </td>
                                <td>
                                    <input type="checkbox" name="cleave_rollover" id="cleave_rollover">
                                    <br> 
                                    <span style="color:red;">Only current Year closed leave balance will be rollover to next year</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="col-sm-12 form-group mt-3">
                    <center>
                        <input type="submit" name="submit" value="Submit" class="btn btn-success" id="formBtn">
                    </center>
                </div>
            </form>
        </div>
    </div>
</div>

@include('includes.footerJs')
<script>
$(document).ready(function() {
    $('#attachment').prop('required', true);
   
    $('#standard').change(function() {
        var standard = $('#standard').val();
       if(standard !==''){
        $('#division').prop('required', true);
        $('#attachment').prop('required',false);        
       }
    })
    $('#leave_rollover').change(function() {
        var leave_rollover = $('#leave_rollover').val();
       if(leave_rollover !==''){
        $('#division').prop('required', false);
        $('#attachment').prop('required',false);        
       }
    })
    $('#cleave_rollover').change(function() {
        var cleave_rollover = $('#cleave_rollover').val();
       if(cleave_rollover !==''){
        $('#division').prop('required', false);
        $('#attachment').prop('required',false);        
       }
    })
    $('#bk_month').change(function() {
        var standard = $('#bk_month').val();
       if(standard !==''){
        $('#attachment').prop('required',false);        
       }
    })
    
})

function checked_input(){
    var student_bluk_update = $('#student_bluk_update').val(); 
    if(student_bluk_update===''){
        $('#attachment').prop('required',false); 
    } 
    else if(student_bluk_update==1){
        $('#student_bluk_update').val(0);  
        $('#attachment').prop('required',true);                         
    }else{
        $('#student_bluk_update').val(1); 
        $('#attachment').prop('required',false);                 
    }
}
</script>
@include('includes.footer')
@endsection