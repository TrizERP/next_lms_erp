@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')
<style>
.title{
    font-weight:200;
}
</style>

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Create Timetable</h4>
            </div>
        </div>
        <div class="card">
   
            @if ($message = Session::get('data'))
            <div class="alert alert-success alert-block">
                <button type="button" class="close" data-dismiss="alert">×</button>
                <strong>{{ $message['message'] }}</strong>
            </div>
            @endif

             @if (isset($_REQUEST['message']))
            <div class="alert alert-success alert-block">
                <button type="button" class="close" data-dismiss="alert">×</button>
                <strong>{{ $_REQUEST['message'] }}</strong>
            </div>
            @endif
            <div class="row">
                <div class="col-lg-12 col-sm-12 col-xs-12">
                    <form action="{{ route('ajax_getTimetable') }}" enctype="multipart/form-data" method="post">
                        @csrf
                        <div class="row">
                            <div class="col-md-4 form-group">
                                <label class="box-title after-none mb-0">Academic Section</label>
                                <!-- <div class="custom-select"> -->
                                <select class="form-control" name="academic_section_id" id="academic_section_id" onchange="getAcademicwiseStandard(this.value);">
                                    <option value="">Select Academic Section</option>
                                    @php
                                    if ($message = Session::get('data'))
                                    {$data = $message;}
                                    @endphp

                                    @foreach($data['academic_section_data'] as $key =>$val)
                                        @php
                                            $selected = "";
                                            if(isset($data['academic_section_id']) && $data['academic_section_id'] == $val->id)
                                            {
                                                $selected = "selected";
                                            }
                                        @endphp
                                        <option {{$selected}} value="{{$val->id}}">{{$val->title}}</option>
                                    @endforeach
                                </select>
                                <!-- </div> -->
                            </div>
                            <input type="hidden" name="hidden_ac" value="{{ $data['academic_section_id'] ?? '' }}" id="hidden_ac">
                            <div class="col-md-4 form-group">
                                <!--<label>Standard</label> -->
                                <label class="box-title after-none mb-0">Standard</label>
                                <!-- <div class="custom-select"> -->
                                <select class="form-control" name="standard_id" id="standard_id" onchange="getStandardwiseDivision(this.value);">
                                    <option value="">Select Standard</option>
                                    @if(isset($data['standard_data']))
                                    @foreach($data['standard_data'] as $key =>$val)
                                        @php
                                            $selected = "";
                                            if(isset($data['standard_id']) && $data['standard_id'] == $val->id)
                                            {
                                                $selected = "selected";
                                            }
                                        @endphp
                                        <option {{$selected}} value="{{$val->id}}">{{$val->name}}</option>
                                    @endforeach
                                    @endif
                                </select>
                                <!-- </div> -->
                            </div>
                            <div class="col-md-4 form-group">
                                <!--<label>Division</label> -->
                                <label class="box-title after-none mb-0">Division</label>
                                <!-- <div class="custom-select"> -->
                                <select class="form-control" name="division_id" id="division_id">
                                    <option value="">Select Division</option>
                                    @if(isset($data['division_data']))
                                    @foreach($data['division_data'] as $key =>$val)
                                        @php
                                            $selected = "";
                                            if(isset($data['division_id']) && $data['division_id'] == $val->id)
                                            {
                                                $selected = "selected";
                                            }
                                        @endphp
                                        <option {{$selected}} value="{{$val->id}}">{{$val->name}}</option>
                                    @endforeach
                                    @endif
                                </select>
                                <!-- </div> -->
                            </div>
                            <div class="col-md-12 form-group">
                                <center>
                                    <input type="submit" name="submit" value="Submit" class="btn btn-success"/>
                                </center>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="col-lg-12 col-sm-12 col-xs-12">
                    <div class="row">
                        <div class="col-md-12 form-group">
                            <!-- <div class="panel"> -->
                            <div class="table-responsive">
                                @if( isset($data['HTML']) )
                                        @php echo $data['HTML'] @endphp
                                    @endif
                            </div>
                            <!-- </div> -->
                        </div>
                    </div>
                </div>
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
function getAcademicwiseStandard(academic_id){
    var path = "{{ route('ajax_AcademicwiseStandard') }}";
    $('#standard_id').find('option').remove().end().append('<option value="">Select Standard</option>').val('');
    $.ajax({url: path,data:'academic_id='+academic_id, success: function(result){
        console.log(result);
        for(var i=0;i < result.length;i++){
            $("#standard_id").append($("<option></option>").val(result[i]['id']).html(result[i]['name']));
        }
    }
    });
}
function getStandardwiseDivision(std_id){
    var path = "{{ route('ajax_StandardwiseDivision') }}";
    $('#division_id').find('option').remove().end().append('<option value="">Select Division</option>').val('');
    $.ajax({url: path,data:'standard_id='+std_id, success: function(result){
        for(var i=0;i < result.length;i++){
            $("#division_id").append($("<option></option>").val(result[i]['division_id']).html(result[i]['name']));
        }
    }
    });
}
function addNewStdandardDiv(id){
    var standard_id = $("#hid_standard_id").val();
    var division_id = $("#hid_division_id").val();
    var path = "{{ route('ajax_New_Standard_Div') }}";
    $.ajax({url: path,data:'standard_id='+standard_id+'&division_id='+division_id+'&id='+id,
    success: function(result){
        $("#"+id).html(result);
    }
    });
}

function getMappingTeachers(subject_id,id)
{
    var standard_id = $("#hid_standard_id").val();
    var path = "{{ route('ajax_Mapping_Teachers') }}";
    $.ajax({
        url: path, data: 'standard_id=' + standard_id + '&subject_id=' + subject_id + '&id=' + id,
        success: function (result) {
            var check_res = result.split("///");
            if (check_res[0] > 0) {
                $("#" + id).html(check_res[1]);
            }
        }
    });
}

function deleteTimetable(id) {
    var standard_id = $("#hid_standard_id").val();
    var division_id = $("#hid_division_id").val();
    var grade_id = $('#hidden_ac').val();
    var path = "{{ route('ajax_Delete_Timetable') }}";
    $.ajax({url: path,data:'standard_id='+standard_id+'&division_id='+division_id+'&grade_id='+grade_id+'&id='+id,
    success: function(result){
        var queryString = '?standard_id=' + standard_id +
                      '&division_id=' + division_id +
                      '&grade_id=' + grade_id + '&message=Record Deleted Sucessfully';

        window.location.href ='/school_setup'+result.redirect+queryString;
    }
    });
}

function addNewRow(id){
    var standard_id = $("#hid_standard_id").val();
    var division_id = $("#hid_division_id").val();
    var path = "{{ route('ajax_Batch_Timetable') }}";
    $.ajax({url: path,data:'mode=batchwise&standard_id='+standard_id+'&division_id='+division_id+'&id='+id,
    success: function(result){
        $("#"+id).html(result);
    }
    });
}
function removeNewRow(id){
    var standard_id = $("#hid_standard_id").val();
    var division_id = $("#hid_division_id").val();
    var path = "{{ route('ajax_Batch_Timetable') }}";
    $.ajax({
        url: path, data: 'mode=normal&standard_id=' + standard_id + '&division_id=' + division_id + '&id=' + id,
        success: function (result) {
            $("#" + id).html(result);
        }
    });
}

$(".teacher_capacity_check").change(function () {
    var teacher_id = this.value;
    var total_lect = $("#hid_total_lecture_" + teacher_id).val();
    if (total_lect != "Unlimited") {
        total_lect = total_lect - 1;
        $("#hid_total_lecture_" + teacher_id).val(total_lect);
        if ($("#hid_total_lecture_" + teacher_id).val() < 0) {
            alert("Teacher Total Lecture Capacity is over.Please select another teacher.")
            this.value = "";
        }
    }
})
</script>
@include('includes.footer')
