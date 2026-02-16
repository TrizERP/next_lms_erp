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
            <div class="alert {{isset($sessionData['status']) && $sessionData['status']==1?'alert-success':'alert-danger'}}">
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
        if (total >= 10) {
            alert('Maximum 10 images allowed');
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