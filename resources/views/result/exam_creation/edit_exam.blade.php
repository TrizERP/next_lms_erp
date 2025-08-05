<link rel="stylesheet" href="../../../plugins/bower_components/dropify/dist/css/dropify.min.css">
{{-- @include('includes.headcss') @include('includes.header') @include('includes.sideNavigation') --}} 
@extends('layout')
@section('container')

<div id="page-wrapper">
    <div class="container-fluid">        
        <div class="card">
            @if ($message = Session::get('success'))
            <div class="alert alert-success alert-block">
                <button type="button" class="close" data-dismiss="alert">×</button>
                <strong>{{ $message }}</strong>
            </div>
            @endif
            <div class="col-lg-12 col-sm-12 col-xs-12">
                <form action="{{ route('exam_creation.update', $data['id']) }}" enctype="multipart/form-data" method="post">
                    {{ method_field("PUT") }}
                    {{csrf_field()}}
                    <div class="row">
                        {{ App\Helpers\TermDD($data['term_id']) }}
                        <input type="hidden" value="{{$data['medium']}}" name="medium">

                        <div class="col-md-4 form-group">
                            <label>Exam Type : </label>
                            <select name="exam_id" class="form-control">
                                <option value="">Select</option>
                                @foreach ($data['exams'] as $key => $value)
                                <option value="{{ $key }}" @if ($data['exam_id'] == $key) selected="selected" @endif>{{ $value }}</option>
                                @endforeach
                            </select>
                        </div>
                       
                        <div class="col-md-12 form-group">
                            {{ App\Helpers\SearchChainSubject('3','single','grade,std,sub',$data['grade'],$data['standard_id'],$data['subject_id']) }}
                        </div>

                        <input type="hidden" value="{{$data['con_point']}}" name="con_point">
                        <input type="hidden" value="{{$data['app_disp_status']}}" name="app_disp_status">

                        <div class="col-md-3 form-group">
                            <label for="report_card_status">Report Card Status</label>
                            <select name="report_card_status" id="report_card_status" class="form-control">
                                @foreach($data['report_card_status_arr'] as $key=>$value)
                                <option value="{{$key}}" @if(isset($data['report_card_status']) && $data['report_card_status']==$key) Selected @endif>{{$value}}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-12 form-group mt-2">
                            <table id="myTable" class="table table-striped table-bordered">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Marks</th>
                                        <th>Sort Order</th>
                                        <th>Exam Date</th>
                                        <th>App Status</th> <!-- Added new column header -->
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>
                                            <input type="text" name="title" value="{{ $data['title'] }}" class="form-control" />
                                        </td>
                                        <td>
                                            <input type="text" name="points" value="{{ $data['points'] }}" class="form-control" />
                                        </td>
                                        <input type="hidden" value="{{$data['marks_type']}}" name="marks_type">
                                        <td>
                                            <input type="text" name="sort_order" value="{{ $data['sort_order'] }}" class="form-control" />
                                        </td>
                                        <td>
                                            <input type="text" name="exam_date" value="{{ $data['exam_date'] }}" class="form-control mydatepicker" autocomplete="off" />
                                        </td>
                                        <td>
                                            <select name="app_status" class="form-control">
                                                @foreach((array) $data['app_disp_status'] as $key => $value)
                                                <option value="{{ $key }}" @if(isset($data['app_status']) && $data['app_status'] == $key) selected @endif>{{ $value }}</option>
                                                @endforeach
                                            </select>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="col-md-12 form-group">
                            <center>
                                <input type="submit" name="submit" value="Save" class="btn btn-success" >
                            </center>
                        </div>
                    </div>
                </form>
            </div>

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
    $("#gradeS").prop('required', true);
    $("#standardS").prop('required', true);
    $("#subject").prop('required', true);
    $("#term").prop('required', true);
    $("#exam").prop('required', true);
    
    $('#term').change(function () {
        $("#grade").val("");
        $("#standard").empty().append('<option value="">Select</option>');
        $("#division").empty().append('<option value="">Select</option>');
        $("#subject").empty().append('<option value="">Select</option>');
        $("#exam").empty().append('<option value="">Select</option>');
    });

    $('#grade').change(function () {
        $("#subject").empty().append('<option value="">Select</option>');
        $("#exam").empty().append('<option value="">Select</option>');
    });

    $('#standard').change(function () {
        $("#exam").empty().append('<option value="">Select</option>');
        var standardID = $("#standard").val();
        if (standardID) {
            $.ajax({
                type: "GET",
                url: "/api/get-subject-list?standard_id=" + standardID,
                success: function (res) {
                    if (res) {
                        $("#subject").empty().append('<option value="">Select</option>');
                        $.each(res, function (key, value) {
                            $("#subject").append('<option value="' + key + '">' + value + '</option>');
                        });
                    } else {
                        $("#subject").empty();
                    }
                }
            });
        } else {
            $("#subject").empty();
        }
    });

    $('#standard').on('change', function () {
        var standardID = $("#standard").val();
        var termID = $("#term").val();
        if (standardID && termID) {
            $.ajax({
                type: "GET",
                url: "/api/get-exam-master-list?standard_id=" + standardID + "&term_id=" + termID,
                success: function (res) {
                    if (res) {
                        $("#exam").empty().append('<option value="">Select</option>');
                        $.each(res, function (key, value) {
                            $("#exam").append('<option value="' + key + '">' + value + '</option>');
                        });
                    } else {
                        $("#exam").empty();
                    }
                }
            });
        } else {
            $("#exam").empty().append('<option value="">Select</option>');
            if (termID == "") {
                alert("Please Select Term.");
            }
        }
    });
</script>

@include('includes.footer')
@endsection
