@include('../includes.headcss')
@include('../includes.header')
@include('../includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">       
            <div class="card">
                @if(!empty($data['message']))
                <div class="alert alert-{{ $data['class'] }} alert-block">
                    <button type="button" class="close" data-dismiss="alert">×</button>
                    <strong>{{ $data['message'] }}</strong>
                </div>
                @endif
                @php
                    $term = $grade = $std = $div = $subject = $exam = "";
                    if(isset($data['term'])){
                        $term = $data['term'];
                    }
                    if(isset($data['grade'])){
                        $grade = $data['grade'];
                    }
                    if(isset($data['standard'])){
                        $std = $data['standard'];
                    }
                    if(isset($data['division'])){
                        $div = $data['division'];
                    }
                    if(isset($data['subject'])){
                        $subject = $data['subject'];
                    }
                    if(isset($data['exam'])){
                        $exam = $data['exam'];
                    }
                @endphp
                <div class="col-lg-12 col-sm-12 col-xs-12">
                    <form action="{{ route('getMarksApproval') }}" enctype="multipart/form-data" method="post">
                        {{ method_field("POST") }}
                        {{csrf_field()}}
                        <div class="row">
                            {{ App\Helpers\TermDD($term) }}
                        
                            {{ App\Helpers\SearchChain('4','single','grade,std,div',$grade,$std,$div) }}

                            <div class="col-md-12 form-group">
                                <center>
                                    <input type="submit" name="submit" value="Search" class="btn btn-success" >
                                </center>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- scholastic -->
                @if(isset($data['scholastic']) && isset($data['subject_head']) )
                <div class="table-responsive">
                {!!  App\Helpers\get_school_details($grade,$std,$div) !!}
                        <table class="table-bordered table" id="myTable">
                            <thead>
                            <tr>
                                <th>EXAM NAME</th>
                                @foreach ($data['subject_head'] as $subjects)
                                    <th>{{ $subjects->subject_name }}</th>
                                @endforeach
                            </tr>
                            </thead>
                           <tbody>
                            @foreach ($data['exam_type'] as $examType)
                            <tr>
                                <td>{{ $examType->ExamType }}</td>
                                @foreach ($data['subject_head'] as $subject)
                                @php
                                    $exam_titles['title'] = [];
                                    $exam_titles['id'] = [];
                                @endphp
                                    @foreach ($data['scholastic'] as $value)
                                        @if ($value->exam_type_id == $examType->Id && $subject->subject_name == $value->subject_name)
                                            @php
                                            $exam_titles['title'] = explode(',', $value->exam_title);
                                            $exam_titles['id'] = explode(',', $value->exam_id);
                                            @endphp
                                        @endif
                                    @endforeach
                                    <td>
                                    @for ($i = 0; $i < count($exam_titles['title']); $i++)
                                    @php 
                                    
                                    $sub_institute_id = session()->get('sub_institute_id');
                                    $status =  DB::table('result_exam_approve')->where(['standard_id' => $std, 'term_id' => $term, 'sub_institute_id' => $sub_institute_id,"module_name"=>"result_mark",'exam_id'=>$exam_titles['id'][$i]])->first();
                                    @endphp
                                    <div class="check-td" style="display: flex;justify-content: space-between;">
                                    <div class="check-text">
                                        {{ $exam_titles['title'][$i]}}
                                        </div>
                                        <div class="check-image">
                                        @if(isset($status) && $status->exam_id== $exam_titles['id'][$i] && $status->status == 1)
                                        <img src="{{asset('/Images/square-check.svg')}}">
                                        @else
                                        <img src="{{asset('/Images/close-square-icon.svg')}}">
                                        @endif
                                        </div>
                                        </div>
                                        <br>
                                    @endfor
                                    </td>
                                @endforeach
                            </tr>
                            @endforeach
                        </tbody>
                        </table>
                        </div>
                @endif

                    <!-- co-scholastic -->
                     <!-- scholastic -->
                @if(isset($data['co_scholastic']) && isset($data['grade_type']) )
                <div class="table-responsive" style="margin-top:100px">
                        <table class="table-bordered table" id="myTable">
                            <thead>
                            <tr>
                                <th>EXAM TYPE</th>
                                <th>Co Scholastic</th>                                
                               
                            </tr>
                            </thead>
                           <tbody>
                           @php
                           $exam_titles['title'] = [];
                                    $exam_titles['id'] = [];
                            @endphp
                           @foreach ($data['grade_type'] as $subjects)
                           <tr>
                           @php 
                           $exam_titles['title'] = explode(',', $subjects->title);
                            $exam_titles['id'] = explode(',', $subjects->grade_id);
                           @endphp
                                <td>{{ $subjects->mark_type }}</td>
                                <td>
                                @for ($i = 0; $i < count($exam_titles['title']); $i++)
                                    @php 
                                    
                                    $sub_institute_id = session()->get('sub_institute_id');
                                    $sub_institute_id = session()->get('sub_institute_id');
                            $status =  DB::table('result_exam_approve')->where(['standard_id' => $std, 'term_id' => $term, 'sub_institute_id' => $sub_institute_id,"module_name"=>"co_scholastic",'exam_id'=>$exam_titles['id'][$i]])->first();

                                    @endphp
                                    <div class="check-td" style="display: flex;justify-content: space-between;">
                                    <div class="check-text">
                                        {{ $exam_titles['title'][$i]}}
                                        </div>
                                        <div class="check-image">
                                        @if(isset($status) && $status->exam_id== $exam_titles['id'][$i] && $status->status == 1)
                                        <img src="{{asset('/Images/square-check.svg')}}">
                                        @else
                                        <img src="{{asset('/Images/close-square-icon.svg')}}">
                                        @endif
                                        </div>
                                        </div>
                                        <br>
                                    @endfor
                              </td>
                            <tr>
                            @endforeach
                        </tbody>
                        </table>
                        </div>
                @endif
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
        $("#subject").empty();
        $("#subject").append('<option value="">Select</option>');
        $("#exam").empty();
        $("#exam").append('<option value="">Select</option>');
    });
    $('#division').on('change', function () {
        $("#exam").empty();
        $("#exam").append('<option value="">Select</option>');
        var standardID = $("#standard").val();
        var divisionID = $("#division").val();
        if (standardID) {
            $.ajax({
                type: "GET",
                url: "/api/get-subject-list?standard_id=" + standardID + "&division_id="+ divisionID,
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
    $('#subject').on('change', function () {
        var standardID = $("#standard").val();
        var subjectID = $("#subject").val();
        var termID = $("#term").val();

        if (standardID && subjectID && termID) {
            $.ajax({
                type: "GET",
                url: "/api/get-exam-list?standard_id=" + standardID +
                        "&subject_id=" + subjectID + "&term_id=" + termID,
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
@include('includes.footer')
