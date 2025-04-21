@extends('layout')
@section('container')
<div id="page-wrapper">
    <div class="container-fluid">       
        <div class="card">
            {{-- Display Alert Messages --}}
            @if(!empty($data['message']))
            <div class="alert alert-{{ $data['class'] }} alert-block">
                <button type="button" class="close" data-dismiss="alert">×</button>
                <strong>{{ $data['message'] }}</strong>
            </div>
            @endif

            {{-- Form Section --}}
            <div class="col-lg-12 col-sm-12 col-xs-12">
                <form action="{{ route('marks_entry.create') }}" enctype="multipart/form-data" method="post">
                    {{ method_field("GET") }}
                    {{ csrf_field() }}
                    <div class="row">
                        {{-- Helper Functions --}}
                        {{ App\Helpers\TermDD('', 3) }}
                        {{ App\Helpers\SearchChain('3', 'single', 'grade,std,div') }}

                        {{-- Subject Selection --}}
                        <div class="col-md-3 form-group">
                            <label for="subject">Select Subject:</label>
                            <select name="subject" id="subject" class="form-control" required>
                                <option value="">Select</option>
                            </select>
                        </div>

                        {{-- Exam Master Selection added on 22-04-2025--}}
                        <div class="col-md-3 form-group">
                            <label for="exam_master">Select Exam Master:</label>
                            <select name="exam_master" id="exam_master" class="form-control" required>
                                <option value="">Select</option>
                            </select>
                        </div>

                        {{-- Exam Selection --}}
                        <div class="col-md-3 form-group">
                            <label for="exam">Select Exam:</label>
                            <select name="exam" id="exam" class="form-control" required>
                                <option value="">Select</option>
                            </select>
                        </div>

                        {{-- Submit Button --}}
                        <div class="col-md-12 form-group">
                            <center>
                                <input type="submit" name="submit" value="Search" class="btn btn-success">
                            </center>
                        </div>
                    </div>
                </form>
            </div>

            {{-- Error Messages --}}
            @if (count($errors) > 0)
            <div class="alert alert-danger">
                <strong>Whoops!</strong> There were some problems with your input.<br><br>
                <ul>
                    @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
            @endif
        </div>       
    </div>
</div>

@include('includes.footerJs')

<script>
    // Set required fields
    $("#grade, #standard, #division, #subject, #exam").prop('required', true);

    // Reset subject and exam on grade or standard change
    $('#grade, #standard').change(function () {
        resetDropdowns(['#subject','#exam_master', '#exam']);
    });

    // Fetch subjects based on division
    $('#division').on('change', function () {
        fetchDropdownData('/api/get-subject-list', {
            standard_id: $("#standard").val(),
            division_id: $("#division").val()
        }, '#subject');
    });

    // Fetch exam master based on subject and term
    $('#subject').on('change', function () {
        fetchDropdownData('/api/get-exam-master-list', {
            standard_id: $("#standard").val(),
            term_id: $("#term").val()
        }, '#exam_master');

        $('#exam').empty().append('<option value="">Select</option>');
    });

    // Fetch exams based on exam master
    $('#exam_master').on('change', function () {
        fetchDropdownData('/api/get-exam-list', {
            standard_id: $("#standard").val(),
            subject_id: $("#subject").val(),
            term_id: $("#term").val(),
            exam_id: $("#exam_master").val()
        }, '#exam');
    });

    // Helper function to reset dropdowns
    function resetDropdowns(selectors) {
        selectors.forEach(selector => {
            $(selector).empty().append('<option value="">Select</option>');
        });
    }

    // Helper function to fetch dropdown data
    function fetchDropdownData(url, params, target) {
        if (Object.values(params).every(val => val)) {
            $.ajax({
                type: "GET",
                url: url + '?' + $.param(params),
                success: function (res) {
                    if (res) {
                        $(target).empty().append('<option value="">Select</option>');
                        $.each(res, function (key, value) {
                            $(target).append('<option value="' + key + '">' + value + '</option>');
                        });
                    } else {
                        $(target).empty().append('<option value="">Select</option>');
                    }
                }
            });
        } else {
            $(target).empty().append('<option value="">Select</option>');
        }
    }
</script>

@include('includes.footer')
@endsection
