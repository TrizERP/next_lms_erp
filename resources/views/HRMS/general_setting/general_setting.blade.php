@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-6 col-sm-4 col-xs-12">
                <h4 class="page-title">General Setting</h4>
            </div>
        </div>
        <div class="card">
            <div class="row mb-2">     
            </div>  
                @php 
                    $field = Session::get('data');
                    $parent_communication = ['N'=>"Subject Wise","Y"=>"Class Teacher wise"];
                    $timetable_ai = ["0"=>"Standard Wise","1"=>"Teacher wise"];
                    $sandwich_leave = $multi_login = $timeTableTeacher = $previousAdmission = ['Y'=>"Yes",'N'=>"No"];
                    $bulkDiscount = ["No","Yes"];
                    $casual_leave = [0,1,2,3,4,5];
                    $sat_late_day = [0,0.5,1];
                    $studentNameFormat = [0=>"Student Name First",1=>"Last Name First"];                  
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
                            <!-- sandwich leave  -->
                            <tr>
                                <th>Are you applying for sandwich leave in your institute?</th>
                                <td>
                                    <div class="row">
                                        <div class="col-md-6 form-group" style="margin-left: 0px !important">
                                            <select id='sandwich_leave' name="sandwich_leave" class="form-control" >
                                                <option>-- Select --</option>
                                                @foreach($sandwich_leave as $key=>$value)
                                                    <option value="{{$value}}" @if(isset($data['get_sandwich_leave_data']->fieldvalue) && $data['get_sandwich_leave_data']->fieldvalue === $value) selected @endif>{{$value}}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <!-- causual leave  -->
                            <tr>
                                <th>How Many days allowed for casual leave at one time?</th>
                                <td>
                                    <div class="row">
                                        <div class="col-md-6 form-group" style="margin-left: 0px !important">
                                            <select id='casual_leave_at_one_time' name="casual_leave_at_one_time" class="form-control" >
                                            @foreach($casual_leave as $key=>$value)
                                                    <option value="{{$value}}" @if(isset($data['get_casual_leave_data']->fieldvalue) && $data['get_casual_leave_data']->fieldvalue == $value) selected @endif>{{$value}}</option>
                                            @endforeach
                                            </select>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <!-- Earned leave  -->
                            <tr>
                                <th>How Many days allowed for Earned Leave at one time?</th>
                                <td>
                                    <div class="row">
                                        <div class="col-md-6 form-group" style="margin-left: 0px !important">
                                            <select id='earned_leave_days' name="earned_leave_days" class="form-control" >
                                            @foreach($casual_leave as $key=>$value)
                                                    <option value="{{$value}}" @if(isset($data['get_earned_leave_data']->fieldvalue) && $data['get_earned_leave_data']->fieldvalue == $value) selected @endif>{{$value}}</option>
                                            @endforeach
                                            </select>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <!-- Half Day and Other leave  -->
                            <tr>
                                <th>How Many days allowed for Half day Leave at one time?</th>
                                <td>
                                    <div class="row">
                                        <div class="col-md-6 form-group" style="margin-left: 0px !important">
                                            <input type="number" name="half_days_allowed" id="half_days_allowed" class="form-control" @if(!empty($data['getallow_leave_days']) && isset($data['getallow_leave_days']->fieldvalue)) value="{{$data['getallow_leave_days']->fieldvalue}}" @endif>
                                        </div>
                                        @php 
                                        $leaveTypeArr = [];
                                        if(!empty($data['getallow_leave_days']) && $data['getallow_leave_days']->extra_field1!=''){
                                            $leaveTypeArr = json_decode($data['getallow_leave_days']->extra_field1,true);
                                            // echo $data['getallow_leave_days']->extra_field1;
                                        }
                                        @endphp
                                        <div class="col-md-6">
                                            <select name="leave_types[]" id="leave_types" class="form-control" multiple>
                                                <option value="">select Type</option>
                                                @foreach($data['leaveTypeArr'] as $k=>$val)
                                                <option value="{{$val->id}}" @if(in_array($val->id,$leaveTypeArr)) selected @endif>{{$val->leave_type}}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <!-- saturday late  -->
                            <tr>
                                <th>HRMS: 2<sup>nd</sup> Saturday Late Arrival - Day Cutoff Policy</th>
                                <td>
                                    <div class="row">
                                        <div class="col-md-6 form-group" style="margin-left: 0px !important">
                                            <select id='sat_late_day' name="sat_late_day" class="form-control" >
                                            @foreach($sat_late_day as $key=>$value)
                                                    <option value="{{$value}}" @if(isset($data['get_sat_late_day']->fieldvalue) && $data['get_sat_late_day']->fieldvalue == $value) selected @endif>{{$value}}</option>
                                            @endforeach
                                            </select>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <!-- parent communication  -->
                            <tr>
                                <th>System to display parent communication class-teacher wise</th>
                                <td>
                                    <div class="row">
                                        <div class="col-md-6 form-group" style="margin-left: 0px !important">
                                            <select id='parent_communication' name="parent_communication" class="form-control" >
                                            <option>-- Select --</option>
                                              @foreach($parent_communication as $key => $value)
                                              <option value="{{$key}}" @if(isset($data['get_parent_communication']->fieldvalue) && $data['get_parent_communication']->fieldvalue === $key)  selected @endif>{{$value}}</option>
                                              @endforeach
                                            </select>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <!-- multiple login or not  -->
                            <tr>
                                <th>Do you want to enable multiple logins?</th>
                                <td>
                                    <div class="row">
                                        <div class="col-md-6 form-group" style="margin-left: 0px !important">
                                            <select id='multi_login' name="multi_login" class="form-control" >
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
                            <!-- timetable  -->
                            <tr>
                                <th>Display all teachers in creating timetable? </th>
                                <td>
                                    <div class="row">
                                        <div class="col-md-6 form-group" style="margin-left: 0px !important">
                                            <select id='timetable_teacher' name="timetable_teacher" class="form-control" >
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
                            <!-- timetable AI-->
                            <tr>
                                <th>Display all teachers in AI timetable? </th>
                                <td>
                                    <div class="row">
                                        <div class="col-md-6 form-group" style="margin-left: 0px !important">
                                            <select id='timetable_ai' name="timetable_ai" class="form-control" >
                                            <option>--Select--</option>
                                            @foreach($timetable_ai as $key=>$value)
                                                <option value="{{ $key }}" @if(isset($data['get_timetable_ai']->fieldvalue) && $data['get_timetable_ai']->fieldvalue == $key) selected @endif >
                                                    {{ $value }}
                                                </option>
                                            @endforeach
                                            </select>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <!-- 3 month fees bulk discount-->
                            <tr>
                                <th>If students pay 3 months fees at once, should we give a discount?<br>And if yes, how much should we give?</th>
                                <td>
                                    <div class="row">
                                        <div class="col-md-6 form-group" style="margin-left: 0px !important">
                                            <label for="" >Select Discount</label>
                                            <select id='bulkDiscount' name="bulkDiscount" class="form-control"  onchange="makeAmountReq()">
                                            @foreach($bulkDiscount as $key=>$value)
                                                <option value="{{ $value }}" @if(isset($data['get_bulkDiscount']->fieldvalue) && $data['get_bulkDiscount']->fieldvalue == $value) selected @endif >
                                                    {{ $value }}
                                                </option>
                                            @endforeach
                                            </select>
                                        </div>
                                        <div class="col-md-6 form-group" style="margin-left: 0px !important">
                                           <label for="">Discount Percentage</label>
                                           <input type="number" name="bulkDiscountAmt" id="bulkDiscountAmt" class="form-control" @if(isset($data['get_bulkDiscount']->extra_field1)) value="{{$data['get_bulkDiscount']->extra_field1}}" @endif>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                             <!-- Student Name Sort Order-->
                             <tr>
                                <th>Student Name display format</th>
                                <td>
                                    <div class="row">
                                        <div class="col-md-6 form-group" style="margin-left: 0px !important">
                                            <label for="" >Select Discount</label>
                                            <select id='studentName' name="studentName" class="form-control"  >
                                            @foreach($studentNameFormat as $key=>$value)
                                                <option value="{{ $key }}" @if(isset($data['get_studentName']->fieldvalue) && $data['get_studentName']->fieldvalue == $key) selected @endif >
                                                    {{ $value }}
                                                </option>
                                            @endforeach
                                            </select>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                             <!-- Previous year admission -->
                             <tr>
                                <th>Allow Previous Year Admission</th>
                                <td>
                                    <div class="row">
                                        <div class="col-md-6 form-group" style="margin-left: 0px !important">
                                            <label for="" >Select Discount</label>
                                            <select id='previousAdmission' name="previousAdmission" class="form-control"  >
                                            @foreach($previousAdmission as $key=>$value)
                                                <option value="{{ $key }}" @if(isset($data['get_previousAdmission']->fieldvalue) && $data['get_previousAdmission']->fieldvalue == $key) selected @endif >
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
<script>
    function makeAmountReq(){
        var bulkDiscount =$('#bulkDiscount').val();
        if(bulkDiscount==='Yes'){
            $('#bulkDiscountAmt').prop('required',true);
        }else{
            $('#bulkDiscountAmt').prop('required',true);
        }
    }
</script>
@include('includes.footerJs')
@include('includes.footer')
