@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<style type="text/css">
    .Present {
        accent-color: green;
    }

    .Absent {
        accent-color: red;
    }
</style>

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Student Attendance</h4>
            </div>
        </div>
        @php
        $standard_division = '';

            if(isset($data['standard_division'])){
                $standard_division = $data['standard_division'];
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
                        <form action="{{ route('show_student_attendance') }}" enctype="multipart/form-data"
                              method="post">
                            @csrf
                            <div class="row">
                                <div class="col-md-4 form-group" id="std_div">
                                    <label>Select Standard Division</label>
                                    {{ App\Helpers\ClassTeacherSearch($standard_division) }}
                                </div>
                                @if(isset($data['batch_id']) && !empty($data['batchs']))
                                    <div class="col-md-4 form-group" id="batch_div">
                                    <label>Select Batch</label>
                                    <select name="batch_sel" class="form-control" id="batch_sel" required="">
                                    @foreach($data['batchs'] as $batch)
                                    <option value="{{$batch->id}}" @if($data['batch_id']==$batch->id) selected @endif>{{$batch->title}}</option>
                                    @endforeach                                    
                                    </select>
                                </div>
                                @endif
                                <div class="col-md-4 form-group">
                                    <label>Select Date</label>
                                    <input type="text" name="date" autocomplete="off"
                                           @if(isset($data['date'])) value="{{$data['date']}}"
                                           @endif class="form-control mydatepicker" placeholder="Select Date">
                                </div>
                                <div class="col-md-4 form-group mt-4">
                                    <center>
                                        <input type="submit" name="submit" value="Search" class="btn btn-success">
                                    </center>
                                </div>
                            </div>
                        </form>
                    </div>
                    @if(isset($data['student_data']))
                    @php
                    $j = 1;
                        if(isset($data['student_data'])){
                            $student_data = $data['student_data'];
                        }
                    @endphp
                        <div class="card">
                            <form method="POST" action="{{route('save_student_attendance')}}">
                            @csrf
                            <div class="table-responsive">
                                <table id="example" class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Sr No</th>
                                        <th>{{App\Helpers\get_string('grno','request')}}</th>
                                        <th>Roll No</th>
                                        <th>Last Name</th>
                                        <th>{{App\Helpers\get_string('studentname','request')}}</th>
                                        <th>Middle Name</th>
                                        @if(isset($data['batch_id']) && !empty($data['batchs']))
                                        <th>Batch</th>
                                        @endif
                                        <th>Present <input id="checkall" name="attendance" onchange="checkAll(this,'Present');" type="radio"></th>
                                        <th>Absent <input id="checkall" name="attendance" onchange="checkAll(this,'Absent');" type="radio"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($student_data as $key => $value)
                                        <tr>
                                            <td> {{$j++}} </td>
                                            <td> {{$value['enrollment_no']}} </td>
                                            <td> {{$value['roll_no']}} </td>
                                            <td> {{$value['last_name']}} </td>
                                            <td> {{$value['first_name']}} </td>
                                            <td> {{$value['middle_name']}} </td>
                                            @if(isset($data['batch_id']) && !empty($data['batchs']))
                                            <td>{{$value['batch_title']}}</td>
                                            @endif
                                            <!-- <td> <input type="radio" value="P" @if(isset($data['attendance_data'][$value['id']])) @if($data['attendance_data'][$value['id']] == 'P') checked @endif  @endif class="Present" name="student[{{$value['id']}}]"> </td>
                                            <td> <input type="radio" value="A" @if(isset($data['attendance_data'][$value['id']])) @if($data['attendance_data'][$value['id']] == 'A') checked @endif  @endif class="Absent" name="student[{{$value['id']}}]"> </td> -->

                                            <td> <input type="radio" value="P" @if(!isset($data['attendance_data'][$value['id']]) || $data['attendance_data'][$value['id']] == 'P') checked @endif class="Present" name="student[{{$value['id']}}]"> </td>
                                            <td> <input type="radio" value="A" @if(isset($data['attendance_data'][$value['id']]) && $data['attendance_data'][$value['id']] == 'A') checked @endif class="Absent" name="student[{{$value['id']}}]"> </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                        </table>
                        <div class="row">
                            <div class="col-md-12 form-group">
                                <center>
                                    <input type="hidden" name="date" @if(isset($data['date'])) value="{{$data['date']}}" @endif">
                                    <input type="hidden" name="standard_division" @if(isset($data['standard_division'])) value="{{$data['standard_division']}}" @endif">
                                    <input type="submit" name="submit" value="Submit" class="btn btn-success" >
                                </center>
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
    $(".mydatepicker").datepicker({  maxDate: '0'});

    function checkAll(ele,name) {
         var checkboxes = document.getElementsByClassName(name);
         if (ele.checked) {
             for (var i = 0; i < checkboxes.length; i++) {
                 if (checkboxes[i].type == 'radio') {
                     checkboxes[i].checked = true;
                 }
             }
         } else {
             for (var i = 0; i < checkboxes.length; i++) {
                 console.log(i)
                 if (checkboxes[i].type == 'radio') {
                     checkboxes[i].checked = false;
                 }
             }
         }
    }
</script>
<script>

    $(document).on('change', '#standard_division', function () {
        var std_div_id = $(this).val();
        var parts = std_div_id.split("||");
        var standard_id = parts[0];
        var division_id = parts[1];
       var path = "{{ route('get_batch') }}";
    // Clear existing batch options
    $('#batch_div').remove();    

    $.ajax({
        url: path,
        data: 'standard_id=' + standard_id + '&division_id=' + division_id,
        success: function (data) {
            var batch_select_container = $('#batch_div');
            var batch_select = $('#batch_sel');

            if (Array.isArray(data) && data.length > 0) {
                if (batch_select_container.length === 0) {
                    batch_select_container = $('<div class="col-md-4 form-group" id="batch_div"></div>');
                    $('#std_div').after(batch_select_container);
                    var batch_select_label = $('<label for="batch_sel">Select Batch</label>');
                    batch_select = $('<select id="batch_sel" class="form-control" name="batch_sel"></select>');
                    var defaultOption = '<option value="">--Select--</option>';
                    batch_select.append(defaultOption);

                    batch_select_container.append(batch_select_label);
                    batch_select_container.append(batch_select);
                }

                // Populate the batch options
                data.forEach(function (value) {
                    var option = '<option value="' + value.id + '">' + value.title + '</option>';
                    batch_select.append(option);
                });
            }
        }
    });
});

</script>
@include('includes.footer')
