{{-- @include('includes.headcss') @include('includes.header') @include('includes.sideNavigation') --}} 
@extends('layout')
@section('container')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Create Exam</h4>
            </div>            
        </div>      
            <div class="card">
                @if ($message = Session::get('success'))
                <div class="alert alert-success alert-block">
                    <button type="button" class="close" data-dismiss="alert">×</button>
                    <strong>{{ $message }}</strong>
                </div>
                @endif
                <div class="col-lg-12 col-sm-12 col-xs-12">
                    <form action="{{ route('exam_creation.store') }}" enctype="multipart/form-data" method="post">
                        {{ method_field("POST") }}
                        {{csrf_field()}}
                        <div class="row">
                        <!-- Below function will get term name and id from helper.php  -->                            
                        {{ App\Helpers\TermDD() }}

                            <input type="hidden" name="medium" value="CBSE" > 

                            <!-- Below function will get grade,standard,division name and id from helper.php  -->                            
                            {{ App\Helpers\SearchChain('4','single','grade,std') }} 
                            
                            <div class="col-md-4 form-group">
                                <label for="title" >Select Subject:</label>
                                <select name="subject[]" id="subject" class="form-control" multiple>
                                    <option value="">Select</option>
                                </select>
                            </div>

                            <div class="col-md-4 form-group">
                                <label for="title">Select Exam:</label>
                                <select name="exam" id="exam" class="form-control">
                                    <option value="">Select</option>
                                </select>
                            </div>

                            <input type="hidden" name="con_point" class="form-control" value="0">
                        
                            <input type="hidden" name="app_disp_status" value="Y" > 

                            <div class="col-md-12 form-group">
                                <div class="table-responsive">
                                    <table id="myTable" class="table table-striped table-bordered">
                                        <thead>
                                            <tr>
                                                <th>Name</th>
                                                <th>Marks</th>
                                                <th>Sort Order</th>
                                                <th>Exam Date</th>
                                            </tr>
                                        </thead>
                                        <tbody class="addButtonCheckbox">
                                            <tr>
                                                <td>
                                                    <input type="text" name="title[]" name="title[]" class="form-control" required/>
                                                </td>
                                                <td>
                                                    <input type="text" name="points[]" name="points[]" class="form-control" />
                                                </td>

                                                <input type="hidden" name="marks_type" class="form-control" value="MARKS">
                                                <input type="hidden" name="report_card_status" class="form-control" value="Y">

                                                <td>
                                                    <input type="text" name="sort_order[]" name="sort_order[]" class="form-control" />
                                                </td>
                                                <td>
                                                    <input type="text" name="exam_date[]" name="exam_date[]" class="form-control mydatepicker" autocomplete="off" />
                                                </td>
                                                <td><a href="javascript:void(0);" onclick="addNewRow();" class="btn btn-success btn-sm mr-2 mb-0"><i class="mdi mdi-plus"></i></a></td>
                                            </tr>
                                        </tbody>
                                        <tfoot>
                                            <tr>
                                            </tr>
                                            <tr>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
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
    $("#grade").prop('required', true);
    $("#standard").prop('required', true);
    $("#division").prop('required', true);
    $("#subject").prop('required', true);
    $("#term").prop('required', true);
    $("#exam").prop('required', true);
    $('#term').change(function () {
        $("#grade").val("");
        $("#standard").empty();
        $("#standard").append('<option value="">Select</option>');
        $("#division").empty();
        $("#division").append('<option value="">Select</option>');
        $("#subject").empty();
        $("#subject").append('<option value="">Select</option>');
        $("#exam").empty();
        $("#exam").append('<option value="">Select</option>');
    });
    $('#grade').change(function () {
        $("#subject").empty();
        $("#subject").append('<option value="">Select</option>');
        $("#exam").empty();
        $("#exam").append('<option value="">Select</option>');
    });
    $('#standard').change(function () {
        $("#exam").empty();
        $("#exam").append('<option value="">Select</option>');
        var standardID = $("#standard").val();
        var divisionID = $("#division").val();
        if (standardID) {
            $.ajax({
                type: "GET",
                url: "/api/get-subject-list?standard_id=" + standardID,
                success: function (res) {
                    if (res) {
                        $("#subject").empty();
                        $("#subject").append('<option value="">Select</option>');
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
                url: "/api/get-exam-master-list?standard_id=" + standardID +
                        "&term_id=" + termID,
                success: function (res) {
                    if (res) {
                        $("#exam").empty();
                        $("#exam").append('<option value="">Select</option>');
                        $.each(res, function (key, value) {
                            $("#exam").append('<option value="' + key + '">' + value + '</option>');
                        });

                    } else {
                        $("#exam").empty();
                    }
                }
            });
        } else {
            $("#exam").empty();
            $("#exam").append('<option value="">Select</option>');
            if (termID == "") {
                alert("Please Select Term.");
            }
        }

    });

</script>
<script>
    function addNewRow()
    {
        var html = '';
        
        html += '<tbody class="addButtonCheckbox">';
        html += '<td><input type="text" name="title[]" class="form-control" /></td>';
        html += '<td><input type="text" name="points[]" class="form-control" /></td>';
        html += '<td><input type="text" name="sort_order[]" class="form-control" /></td>';
        html += '<td><input type="text" name="exam_date[]" class="form-control mydatepicker" autocomplete="off"/></td>';
        html += '<td><a href="javascript:void(0);" onclick="removeNewRow();" class="btn btn-danger btn-sm mr-2   mb-0"><i class="mdi mdi-minus"></i></a></td>';
        html += '</tbody>';
        $('.addButtonCheckbox:last').after(html);
    }

    function removeNewRow() {
        $(".addButtonCheckbox:last" ).remove();
    }
</script>
@include('includes.footer')
@endsection