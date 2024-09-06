@include('../includes.headcss')
@include('../includes.header')
@include('../includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">       
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
            <div class="col-lg-12 col-sm-12 col-xs-12">
                <form action="{{ route('result_activity_marks.create') }}" enctype="multipart/form-data" method="post">
                    {{ method_field("GET") }}
                    {{csrf_field()}}
                    <div class="row">
                        {{ App\Helpers\SearchChain('4','single','grade,std,div') }}
                    
                        <div class="col-md-4 form-group">
                            <label for="title">Select Skillset:</label>
                            <select name="skillset_id" id="skillset_id" class="form-control">
                                <option value="">Select</option>
                                @foreach($data['result_skillsets'] as $key => $result_skillset)
                                    <option value="{{ $result_skillset->id }}">{{ $result_skillset->title }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4 form-group">
                            <label for="title">Select Activity Master:</label>
                            <select name="activity_master" id="activity_master" class="form-control">
                                <option value="">Select</option>
                            </select>
                        </div>
                        <div class="col-md-4 form-group" id="sub_activity">
                            <label for="title">Select Sub Activity Master:</label>
                            <select name="sub_activity_master" id="sub_activity_master" class="form-control">
                                <option value="">Select</option>
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
    $(document).ready(function(){
        $('#sub_activity').hide();
    })
    $('#standard').on('change',function(){
        var standard = $('#standard').val();
        $('#skillset_id').empty();
        $('#activity_master').empty();
        $.ajax({
            url : "{{route('getActivityLists')}}",
            data : {standard:standard,level:2}, // add skill id
            type: "GET",
            success : function(result){
                // console.log(result);
                $('#skillset_id').empty();
                if (Array.isArray(result) && result.length > 0) {
                    // Clear the select options before appending new ones
                    $('#skillset_id').append(`<option value="">Select Skill Set</option>`);
                    result.forEach(element => {
                        $('#skillset_id').append(`<option value="${element.id}">${element.main_title} (${element.title})</option>`);
                    });
                } else {
                    alert(`No Skill Sets Found`);
                    $('#skillset_id').empty();
                }
            }
        })
    });
    
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
