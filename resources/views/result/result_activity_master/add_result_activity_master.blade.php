@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Add Result Activity Master</h4>
            </div>
        </div>
        <div class="card">
		    <!-- @TODO: Create a saperate tmplate for messages and include in all tempate -->
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
            <div class="row">                
                <div class="col-lg-12 col-sm-12 col-xs-12">
                    <form action="{{ route('result_activity_master.store') }}" enctype="multipart/form-data" method="post">
                    {{ method_field("POST") }}                        
                    @csrf
                        <!-- 2024-09-03 start -->
                        <div class="row">
                             <div class="col-md-4 form-group">
                                <label for="standard_list">Standard</label>
                                <select name="standard" id="standard" class="form-control" required>
                                    <option value="">Select Standard</option>
                                    @foreach($data['standardLists'] as $key=>$value)
                                    <option value="{{$value->id}}">{{$value->name}}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4 form-group">
                                <label for="levelLayers">Levels</label>
                                <select name="levels" id="levels" class="form-control" onchange="getLevelThree();">
                                    @foreach($data['levelLayers'] as $key=>$value)
                                    <option value="{{$value}}">{{$value}}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                            <!-- 2024-09-03 end -->
                        <div class="row" id="levelType3">
                            <div class="col-md-4 form-group">
                                <label>Title </label>
                                <input type="text" id='title' name="title" class="form-control" required>
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Skill Name</label>
                                <select id="skill_id" name="skill_id" class="form-control" required>
                                @php
                                    $uniqueTitles = [];
                                @endphp

                                @foreach ($data['result_skillsets'] as $item)
                                    @php
                                        $titleCombo = $item->main_title . '(' . $item->title . ')';
                                    @endphp

                                    @if (!in_array($titleCombo, $uniqueTitles))
                                        <option value="{{ $item->id }}" @if(isset($data['result_skillset']->main_title) && $data['result_skillset']->main_title == $item->main_title) selected @endif>
                                            {{ $titleCombo }}
                                        </option>
                                        @php
                                            $uniqueTitles[] = $titleCombo;
                                        @endphp
                                    @endif
                                @endforeach
                                </select>
                            </div>
                            <div class="col-md-4 form-group" id="levelType4">
                                <label>Sub Skill Name</label>
                                <select id="sub_skill_id" name="sub_skill_id" class="form-control">
                                
                                </select>
                            </div>

                            <div class="col-md-4 form-group">
                                <label>Sort Order </label>
                                <input type="text" id='sort_order' name="sort_order" class="form-control" required autocomplete="off">
                            </div>
                            <div class="col-md-12 form-group">
                                <center>                                    
                                    <input type="submit" name="submit" value="Save" class="btn btn-success">
                                </center>
                            </div>
                        </div>
                        
                    </form>
                </div>
            </div>    
        </div>
    </div>    
</div>

@include('includes.footerJs')
<script>
    $(document).ready(function(){
        $('#levelType4').hide();
    })
    function getLevelThree(){
        var levels = $('#levels').val();
        var standard = $('#standard').val();
        var skill_id = $('#skill_id').val();
        if(standard==""){
            alert('Please Select Standard !!');
            $('#levels').val(3);
        }
        if(levels==4){
            $('#levelType4').show();
            $.ajax({
                url : "{{route('getActivityLists')}}",
                data : {standard:standard,levels:3,skill_id:skill_id}, // add skill id
                type: "GET",
                success : function(result){
                    console.log(result);
                    if (Array.isArray(result) && result.length > 0) {
                        // Clear the select options before appending new ones
                        $('#sub_skill_id').empty();
                        result.forEach(element => {
                            $('#sub_skill_id').append(`<option value="${element.id}">${element.title}</option>`);
                        });
                    } else {
                        alert(`Add level 3 value first`);
                        $('#levels').val(3);
                    }
                }
            })
        }else{
            $('#levelType4').hide();
            $('#sub_skill_id').value('');
        }
    }
</script>
@include('includes.footer')
