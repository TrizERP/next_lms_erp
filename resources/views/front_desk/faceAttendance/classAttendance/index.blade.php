@extends('layout')
@section('container')

<style>
    .Present {
        accent-color: green;
    }

    .Absent {
        accent-color: red;
    }

    .remove-btn {
        cursor: pointer;
        padding: 8px;
    }
</style>

<div id="page-wrapper">
    <div class="container-fluid">

        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Capture Class Attendance</h4>
            </div>
        </div>

        @php
        $standard_division = $data['standard_division'] ?? '';
        @endphp

        <div class="card">

            @if ($sessionData = Session::get('data'))
            <div class="alert {{isset($sessionData['status_code']) && $sessionData['status_code']==1?'alert-success':'alert-danger'}}">
                <button type="button" class="close" data-dismiss="alert">×</button>
                <strong>{{$sessionData['message']}}</strong>
            </div>
            @endif

            @if ($errors->any())
            <div class="alert alert-danger">
                <ul>
                    @foreach ($errors->all() as $error)
                    <li>{{$error}}</li>
                    @endforeach
                </ul>
            </div>
            @endif

            <form action="{{route('class_face_attendance.store')}}" method="post" enctype="multipart/form-data" id="attendanceForm">
                @csrf

                <div class="row">

                    <div class="col-md-4 form-group" id="std_div">
                        <label>Select Standard Division</label>
                        {{App\Helpers\ClassTeacherSearch($standard_division)}}
                    </div>

                    <div class="col-md-4 form-group">
                        <label>Select Date</label>
                        <input type="text" name="date"
                            value="{{$data['date']??''}}"
                            class="form-control mydatepicker"
                            onchange="checkIfSunday(this)"
                            required>
                    </div>

                </div>

                <div class="row">
                    <div class="col-md-12">
                        <label>Upload Images (Minimum 3 required)</label>
                    </div>

                    <div id="image_wrapper" class="row" style="width:100%;margin:0;">

                        @for($i=1;$i<=3;$i++)
                            <div class="col-md-3 form-group image-block">
                                <label>Image {{$i}} <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="file" name="images[]" class="form-control image-input" required>
                                    <div class="input-group-append remove-btn d-none">
                                        <span class="mdi mdi-minus remove_field"></span>
                                    </div>
                                </div>
                            </div>
                        @endfor

                    </div>

                    <div class="col-md-12 mt-2">
                        <button type="button" id="add_image_btn" onclick="addMore()" class="btn btn-primary btn-sm">
                            Add More Images
                        </button>
                    </div>

                </div>

                <div class="row mt-3">
                    <div class="col-md-12">
                        <center>
                            <input type="submit" value="Submit" class="btn btn-success">
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
                            <input type="hidden" name="formType" value="classAttendance">
                            <div class="table-responsive">
                                <table id="example" class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Sr No</th>
                                        <th>{{App\Helpers\get_string('grno','request')}}</th>
                                        <th>Roll No</th>
                                        <th>Student Image</th>
                                        <th>{{App\Helpers\get_string('studentname','request')}}</th>
                                        @if(isset($data['batch_id']) && !empty($data['batchs']))
                                        <th>Batch</th>
                                        @endif
                                        <th>Present <input id="checkall" name="attendance" onchange="checkAll(this,'Present');" type="radio"></th>
                                        <th class="text-left">Absent <input id="checkall" name="attendance" onchange="checkAll(this,'Absent');" type="radio"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($student_data as $key => $value)
                                        <tr>
                                            <td> {{$j++}} </td>
                                            <td> {{$value['enrollment_no']}} </td>
                                            <td> {{$value['roll_no']}} </td>
                                            @php
                                                $imgPath = isset($value['image']) ? '/storage/student/'.$value['image'] : null;
                                                $imgFullPath = $imgPath ? public_path($imgPath) : null;
                                                $imgExists = $imgFullPath && file_exists($imgFullPath);
                                            @endphp
                                            <td>
                                                <img src="{{ $imgExists ? asset($imgPath) : asset('/admin_dep/images/no-student.jpg') }}" alt="Student Image" style="width: 50px; height: 50px;">
                                            </td>
                                            <td> {{$value['first_name'] ?? '-'}} {{$value['middle_name'] ?? '-'}} {{$value['last_name'] ?? '-'}} </td>
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
    $(document).ready(function() {
        // Add remove button to first 3 image blocks
        $('#image_wrapper .image-block').each(function() {
            $(this).find('.remove-btn').removeClass('d-none');
        });
        
        // Remove image block
        $(document).on('click', '.remove_field', function() {
            let totalBlocks = $('#image_wrapper .image-block').length;
            if (totalBlocks <= 3) {
                alert('Minimum 3 images are required');
                return;
            }
            
            $(this).closest('.image-block').remove();
            
            // Update labels
            $('#image_wrapper .image-block').each(function(index) {
                $(this).find('label').html('Image ' + (index + 1) + ' <span class="text-danger">*</span>');
            });
        });
    });

    function removeMore(btn){
        $(btn).closest('.image-block').remove();
    }

    function addMore() {
        let total = $('#image_wrapper .image-block').length;
        if (total >= 5) {
            alert('Maximum 5 images allowed');
            return;
        }
        
        const newIndex = total + 1;
        const newBlock = `
            <div class="col-md-3 form-group image-block">
                <label>Image ${newIndex} <span class="text-danger">*</span></label>
                <div class="input-group">
                    <input type="file" name="images[]" class="form-control image-input" required>
                    <div class="input-group-append remove-btn" onclick="removeMore(this)">
                        <span class="mdi mdi-minus remove_field"></span>
                    </div>
                </div>
            </div>
        `;
        
        $('#image_wrapper').append(newBlock);
    }
</script>
<script>
    $(".mydatepicker").datepicker({
        maxDate: '0'
    });

    function checkIfSunday(input) {
        const d = $('.mydatepicker').datepicker('getDate');
        if (d && d.getDay() == 0) {
            alert("Sunday holiday.");
            input.value = '';
        }
    }
</script>
@include('includes.footer')
@endsection