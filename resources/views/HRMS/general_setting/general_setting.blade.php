@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">General Setting</h4>
            </div>
        </div>
        <div class="card">
            <div class="row mb-2">     
            </div>  
                @php 
                    $field = Session::get('data');
                    $parent_communication = ['N'=>"Subject Wise","Y"=>"Class Teacher wise"];
                    $sandwich_leave = $multi_login =$timeTableTeacher = ["Yes","No"];
                    $casual_leave = [0,1,2,3,4,5];                    
                @endphp 
                @if ($sessionData = Session::get('data'))
                    @if (isset($sessionData['status_code']))
                        <div class="alert alert-{{ $sessionData['status_code'] == 1 ? 'success' : 'danger' }} alert-block">
                            <button type="button" class="close" data-dismiss="alert">×</button>
                            <strong>{!! $sessionData['message'] !!}</strong>
                        </div>
                    @endif
                @endif
                <form action="{{ route('hrms_general_setting.store') }}" method="post" enctype="multipart/form-data">
                @csrf
                <div class="table-responsive">
                    <table class="table table-box table-bordered">
                        <tbody>
                            <tr>
                                <th>Are you applying for sandwich leave in your institute?</th>
                                <td>
                                    <div class="row">
                                        <div class="col-md-4 form-group" style="margin-left: 0px !important">
                                            <select id='sandwich_leave' name="sandwich_leave" class="form-control" style="margin-left: 50px;">
                                                <option>-- Select --</option>
                                                @foreach($sandwich_leave as $key=>$value)
                                                    <option value="{{$value}}" @if(isset($data['get_sandwich_leave_data']->fieldvalue) && $data['get_sandwich_leave_data']->fieldvalue === $value) selected @endif>{{$value}}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <th>How Many days allowed for casual leave at one time?</th>
                                <td>
                                    <div class="row">
                                        <div class="col-md-4 form-group" style="margin-left: 0px !important">
                                            <select id='casual_leave_at_one_time' name="casual_leave_at_one_time" class="form-control" style="margin-left: 50px;">
                                            @foreach($casual_leave as $key=>$value)
                                                    <option value="{{$value}}" @if(isset($data['get_casual_leave_data']->fieldvalue) && $data['get_casual_leave_data']->fieldvalue === $value) selected @endif>{{$value}}</option>
                                            @endforeach
                                            </select>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <th>System to display parent communication class-teacher wise</th>
                                <td>
                                    <div class="row">
                                        <div class="col-md-4 form-group" style="margin-left: 0px !important">
                                            <select id='parent_communication' name="parent_communication" class="form-control" style="margin-left: 50px;">
                                            <option>-- Select --</option>
                                              @foreach($parent_communication as $key => $value)
                                              <option value="{{$key}}" @if(isset($data['get_parent_communication']->fieldvalue) && $data['get_parent_communication']->fieldvalue === $key)  selected @endif>{{$value}}</option>
                                              @endforeach
                                            </select>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <th>Do you want to enable multiple logins?</th>
                                <td>
                                    <div class="row">
                                        <div class="col-md-4 form-group" style="margin-left: 0px !important">
                                            <select id='multi_login' name="multi_login" class="form-control" style="margin-left: 50px;">
                                            @foreach($multi_login as $value)
                                                <option value="{{ $value }}" {{ isset($data['get_multi_login']->fieldvalue) && $data['get_multi_login']->fieldvalue === $value ? 'selected' : '' }}>
                                                    {{ $value }}
                                                </option>
                                            @endforeach

                                            </select>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <th>Display all teachers in creating timetable? </th>
                                <td>
                                    <div class="row">
                                        <div class="col-md-4 form-group" style="margin-left: 0px !important">
                                            <select id='timetable_teacher' name="timetable_teacher" class="form-control" style="margin-left: 50px;">
                                            <option>--Select--</option>
                                            @foreach($timeTableTeacher as $value)
                                                <option value="{{ $value }}" {{ isset($data['get_timetable_teacher']->fieldvalue) && $data['get_timetable_teacher']->fieldvalue === $value ? 'selected' : '' }}>
                                                    {{ $value }}
                                                </option>
                                            @endforeach

                                            </select>
                                        </div>
                                    </div>
                                </td>
                            </tr>

                        </tbody>
                    </table>
                </div>
                <div class="col-sm-12 form-group mt-3">
                    <center>
                        <input type="submit" name="submit" value="Submit" class="btn btn-success">
                    </center>
                </div>
            </form>
        </div>
    </div>
</div>

@include('includes.footerJs')
@include('includes.footer')
