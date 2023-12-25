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
