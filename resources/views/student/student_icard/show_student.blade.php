@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Student I-Card</h4>
            </div>
        </div>
        @php
        $grade_id = $standard_id = $division_id = $driver_id = '';

            if(isset($data['grade_id']))
            {
                $grade_id = $data['grade_id'];
                $standard_id = $data['standard_id'];
                $division_id = $data['division_id'];
            }
            if(isset($data['driver_id']))
            {
                $driver_id = $data['driver_id'];
            }
        @endphp
        <div class="card">
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
            <form action="{{ route('student_icard.show_student') }}" enctype="multipart/form-data" method="post">
            {{ method_field("POST") }}
            @csrf
                <div class="row">
                    {{ App\Helpers\SearchChain('3','single','grade,std,div',$grade_id,$standard_id,$division_id) }}

                    <div class="col-md-3 form-group">
                        <label>Dirver </label>
                        <select id='driver_id' name="driver_id" class="form-control">
                        <option value=""> Select Driver </option>
                            @foreach($data['driver'] as $key => $value)
                                <option value="{{$value['id']}}" @if(isset($driver_id)) {{$driver_id == $value['id'] ? 'selected' : '' }} @endif> {{$value['first_name']}} </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-12 form-group">
                        <center>
                            <input type="submit" name="submit" value="Search" class="btn btn-success">
                        </center>
                    </div>
                </div>
            </form>
                </div>

                    @if(isset($data['data']))
                        @php
                            if(isset($data['data'])){
                                $student_data = $data['data'];
                            }
                        @endphp
                        <div class="card">
                            <form method="POST" action="show_student_icard">
                                @csrf
                                <div class="row">
                                    <div class="col-md-4 form-group">
                                        <label>Template</label>
                                        <select class="form-control" name="template" required="required">
                                            <option value="">Select Template</option>
                                            <option value="template_1">Template 1</option>
                                            <option value="template_2">Template 2</option>
                                            <option value="template_3">Template 3</option>
                                            <option value="template_4">Template 4</option>
                                        </select>
                                    </div>
                    <div class="col-md-3 form-group">
                        <label>Card Per Row</label>
                        <input type="text" id="row" name="row" required="required" class="form-control">
                    </div>
                    <div class="col-md-3 form-group">
                        <label>Card Per Column</label>
                        <input type="text" required="required" id="column" name="column" class="form-control">
                    </div>
                    <div class="col-md-2 form-group">
                        <label>View Templates</label>
                        <a href="{{ route('view_samples') }}" target="blank" class="form-control">View Template</a>
                    </div>
                    <div class="col-lg-12 col-sm-12 col-xs-12">
                        <div class="table-responsive">
                            <table id="example" class="table table-striped">
                                <thead>
                                    <tr>
                                        <th><input id="checkall" onchange="checkAll(this);" type="checkbox"></th>
                                        <th>{{App\Helpers\get_string('grno','request')}}</th>
                                        <th>{{App\Helpers\get_string('studentname','request')}}</th>
                                        <th>{{App\Helpers\get_string('standard','request')}}</th>
                                        <th>{{App\Helpers\get_string('division','request')}}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                @php
                                $j=1;
                                @endphp
                                @if(isset($data['data']))
                                    @foreach($data['data'] as $key => $value)
                                    <tr>
                                        <td><input id="{{$value['id']}}" value="{{$value['id']}}" name="students[]" type="checkbox"></td>
                                        <td>{{$value['enrollment_no']}}</td>
                                        <td>{{$value['first_name']}} {{$value['last_name']}}</td>
                                        <td>{{$value['standard_name']}}</td>
                                        <td>{{$value['division_name']}}</td>
                                    </tr>
                                @php
                                $j++;
                                @endphp
                                    @endforeach
                                @endif
                                </tbody>
                            </table>
                        </div>
                        <div class="row">
                            <div class="col-md-12 form-group">
                                <center>
                                    <input type="hidden" name="grade_id" @if(isset($data['grade_id'])) value="{{$data['grade_id']}}" @endif">
                                     <input type="hidden" name="standard_id" @if(isset($data['standard_id'])) value="{{$data['standard_id']}}" @endif">
                                    <input type="submit" name="submit" value="Submit" class="btn btn-success" >
                                </center>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
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
                 if (checkboxes[i].type == 'checkbox') {
                     checkboxes[i].checked = false;
                 }
             }
         }
    }
</script>
@include('includes.footer')
