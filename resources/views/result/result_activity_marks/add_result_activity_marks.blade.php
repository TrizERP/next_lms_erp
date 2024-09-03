@include('../includes.headcss')
@include('../includes.header')
@include('../includes.sideNavigation')

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
                    <form action="{{ route('result_activity_marks.create') }}" enctype="multipart/form-data" method="post">
                        {{ method_field("GET") }}
                        {{csrf_field()}}
                        <div class="row">
                            {{ App\Helpers\SearchChain('4','required','grade,std,div',$data['grade'],$data['standard'],$data['division']) }}
                            <div class="col-md-4 form-group">
                                <label for="title">Select Skillset:</label>
                                <select name="skillset_id" id="skillset_id" class="form-control" required>
                                    <option value="">Select</option>
                                    @foreach($data['result_skillsets'] as $key => $result_skillset)
                                        <option value="{{ $result_skillset->id }}" @if(isset($data['skillset_id']) && $data['skillset_id'] == $result_skillset->id) selected @endif>
                                        {{ $result_skillset->title }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4 form-group">
                                <label for="title">Select Activity Master:</label>
                                <select name="activity_master" id="activity_master" class="form-control">
                                    <option value="">Select</option>
                                    @php
                                    if (isset($data['activity_master'])) 
                                    {
                                        foreach ($data['activity_master'] as $key => $value) 
                                        {
                                            $selected = ($data['activity_value'] == $key) ? 'selected="selected"' : '';
                                            echo "<option $selected value={$key}>{$value}</option>";
                                        }
                                    }
                                    @endphp
                                </select>
                            </div>
                            @if (isset($data['sub_activity_value']))
                            <div class="col-md-4 form-group" id="sub_activity">
                            <label for="title">Select Sub Activity Master:</label>
                            <select name="sub_activity_master" id="sub_activity_master" class="form-control">
                                <option value="">Select</option>
                                @foreach($data['sub_activity_value'] as $key => $value) 
                                    <option value="{{$value->id}}" @if(isset($data['sub_activity_master']) && $data['sub_activity_master']==$value->id) Selected @endif>{{$value->title}}</option>
                                @endforeach
                            </select>
                        </div>
                        @endif
                            <div class="col-md-12 form-group">
                                <center>
                                    <input type="submit" name="submit" value="Search" class="btn btn-success" >
                                </center>
                            </div>
                        </div>
                    </form>
                </div>
                <form action="{{ route('result_activity_marks.store') }}" enctype="multipart/form-data" method="post">
                    {{ method_field("POST") }}
                    {{csrf_field()}}
                    <div class="table-responsive">
                    <table class="table-bordered table" id="myTable">
                        <tr>
                            <th>Roll No</th>
                            <th>Student Name</th>
                            <th colspan="{{ count($data['result_activity_groups']) }}" style="text-align:center;">Activity Group</th>
                        </tr>
                        <tr style="text-align:center;">
                            <th></th>
                            <th></th>
                            @foreach($data['result_activity_groups'] as $result_activity_group)
                                <th >{{ $result_activity_group->title }}</th>
                            @endforeach
                        </tr>
                        @foreach($data['student_datas'] as $student_data)
                        <tr>
                            <td>{{ $student_data['roll_no'] }}</td>
                            <td>{{ $student_data['first_name'] }} {{ $student_data['middle_name'] }} {{ $student_data['last_name'] }}</td>
                            @foreach($data['result_activity_groups'] as $result_activity_group)
                            @php
                            @endphp
                                <td style="text-align:center;">
                                    <label>
                                    <input type="radio" name="activity_group[{{ $student_data['id'] }}]" value="{{ $result_activity_group->id }}"
                                        @if(isset($data['get_activity_marks'][$student_data['id']]) && $data['get_activity_marks'][$student_data['id']]->group_id == $result_activity_group->id)
                                            checked
                                        @endif>
                                    </label>
                                    <input type="hidden" name="student_id" value="{{ $student_data['id'] }}">
                                    <input type="hidden" name="activity_id" value="{{ $data['activity_value'] }}">
                                </td>
                            @endforeach
                        </tr>
                        @endforeach
                    </table>
                    </div>
                    <div class="col-md-12 form-group mt-4">
                        <center>
                            <input type="submit" name="submit" value="Save" class="btn btn-success"  >
                        </center>
                    </div>
                </form>
            </div>
        </div>       
    </div>
</div>

@include('includes.footerJs')
<script>
   $('#standard').on('change', function () {
        $('#activity_master').empty();
     })
    $('#skillset_id').on('change', function () {
        var skillset_id = $("#skillset_id").val();
        var standard = $("#standard").val();

        if (skillset_id && standard) 
        {
            $.ajax({
                type: "GET",
                url: "/api/get-activity-master-list?skillset_id=" + skillset_id +"&standard="+standard,
                success: function (res) {
                    if (res) {
                        $("#activity_master").empty();
                        $("#activity_master").append('<option value="">Select</option>');
                        $.each(res, function (key, value) {
                            $("#activity_master").append('<option value="' + key + '">' + value + '</option>');
                        });

                    } else {
                        $("#activity_master").empty();
                    }
                }
            });
        } 
        else 
        {
            $("#activity_master").empty();
        }
    });

    $('#activity_master').on('change', function () {
        var activity_master = $("#activity_master").val();
        $('#sub_activity').hide();

        $.ajax({
                type: "GET",
                url: "{{route('getActivityLists')}}",
                data:{skill_id:activity_master,type:'API'},
                success: function (result) {
                    if (Array.isArray(result) && result.length > 0) {
                        // Clear the select options before appending new ones
                        $('#sub_activity').show();
                        $("#sub_activity_master").empty();
                        $("#sub_activity_master").append('<option value="">Select</option>');
                        result.forEach(element => {
                            $('#sub_activity_master').append(`<option value="${element.id}">${element.title}</option>`);
                        });
                    } else {
                        $("#sub_activity_master").empty();
                        $('#sub_activity').hide();
                    }
                }
            });
    })
</script>
@include('includes.footer')
