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
    $('#skillset_id').on('change', function () {
        var skillset_id = $("#skillset_id").val();

        if (skillset_id) 
        {
            $.ajax({
                type: "GET",
                url: "/api/get-activity-master-list?skillset_id=" + skillset_id,
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
</script>
@include('includes.footer')
