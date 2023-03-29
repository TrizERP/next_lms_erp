@include('includes.lmsheadcss')
<link href="/plugins/bower_components/clockpicker/dist/jquery-clockpicker.min.css" rel="stylesheet">
<style>
.tooltip-inner {
    max-width: 1100px !important;
}

#example{
    table-layout: fixed;
}

#example tbody tr td:nth-child(2){
    text-align: unset;
}
</style>
@include('includes.header')
@include('includes.sideNavigation')
<!-- Content main Section -->
<div class="content-main flex-fill">
    <div class="row justify-content-between">
        <div class="col-md-6">
            <h1 class="h4 mb-3">             
            Add Lesson Plan            
            </h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb bg-transparent p-0">
                    <li class="breadcrumb-item"><a href="{{route('course_master.index')}}">LMS</a></li>                                 
                    <li class="breadcrumb-item">Lesson Plan</li>
                    <li class="breadcrumb-item active" aria-current="page">Add Lesson Plan</li>
                </ol>
            </nav>
        </div>  
        @if ( $data['form_data'] )
            <div class="col-md-3 mb-4 text-md-right">
                <a href="{{ route('view_form', ['id' => $data['form_data']['form_id'], 'chapter_id' => $data['form_data']['chapter_id']]) }}" class="btn btn-info add-new"><i class="fa fa-plus"></i>Edit Form</a>
            </div>      
        @endif
    </div>

    {{-- <div class="container-fluid mb-5">
        <div class="card border-0">
            <div class="card-body">
                @if ($message = Session::get('success'))
                <div class="alert alert-success alert-block">
                    <button type="button" class="close" data-dismiss="alert">×</button>
                    <strong>{{ $message }}</strong>
                </div>
                @endif
               <form action="{{ route('lms_lessonplan.store') }}" method="post" enctype='multipart/form-data' onsubmit="return check_validation();">                                                 
                        {{ method_field("POST") }}
                        @csrf
                                      
                    <div class="row  align-items-center">                                                                                                                                             

                        <div class="col-md-3 form-group">
                            <label>Standard</label>                            
                            <input type="text" class="form-control" name="standard_id" id="standard_id" value="@if(isset($data['lessonplan_data'])){{$data['lessonplan_data']['standard_name']}}@endif" readonly>                                                                                                            
                            <input type="hidden" class="form-control" name="hid_standard_id" id="hid_standard_id" value="@if(isset($data['lessonplan_data'])){{$data['lessonplan_data']['standard_id']}}@endif">                                                                                                            
                            <input type="hidden" class="form-control" name="hid_subject_id" id="hid_subject_id" value="@if(isset($data['lessonplan_data'])){{$data['lessonplan_data']['subject_id']}}@endif">                                                                                                        
                            <input type="hidden" class="form-control" name="hid_grade_id" id="hid_grade_id" value="@if(isset($data['lessonplan_data'])){{$data['lessonplan_data']['grade_id']}}@endif">                                                                                                        
                        </div>

                        <div class="col-md-3 form-group">
                            <label>Subject</label>                            
                            <input type="text" class="form-control" name="subject_id" id="subject_id" value="@if(isset($data['lessonplan_data'])){{$data['lessonplan_data']['subject_name']}}@endif" readonly>                                                                                                        
                            
                        </div>

                        <div class="col-md-3 form-group">
                            <label>Divsion</label>                            
                            <select name="division" id="division" class="form-control" required onchange="get_teacher();">
                                <option value="">Select Division</option> 
                                @if(isset($data['lessonplan_data']['division_data'])) 
                                    @foreach($data['lessonplan_data']['division_data'] as $key => $value)
                                        <option value="{{$value['id']}}">{{$value['division_name']}}</option>
                                    @endforeach                      
                                @endif                                                       
                            </select> 
                        </div>

                        <div class="col-md-3 form-group">
                            <label>Teacher</label>                            
                            <select name="teacher_id" id="teacher_id" class="form-control" required>
                                <option value="">Select Teacher</option> 
                            </select> 
                        </div>

                        <div class="col-md-2 form-group">
                            <label>From Date</label>
                            <div class="input-daterange input-group" id="date-range">
                                <input type="text" class="form-control mydatepicker text-left" name="from_date" id="from_date" autocomplete="off">
                                <span class="input-group-addon"><i class="icon-calender"></i></span> 
                            </div>                                                      
                        </div>
                            
                        <div class="col-md-2 form-group">
                            <label>To Date</label>                            
                            <div class="input-daterange input-group" id="date-range">
                                <input type="text" class="form-control mydatepicker text-left" name="to_date" id="to_date" autocomplete="off" onchange="get_timetable();">
                                <span class="input-group-addon"><i class="icon-calender"></i></span> 
                            </div>                                                        
                        </div>                         

                        <div class="col-md-2 form-group">
                            <label for='total_lectures'>Total Lectures</label>
                            <input type="text" id='total_lectures' name="total_lectures" class="form-control" readonly>
                        </div> 

                        <div class="col-md-2 form-group">
                            <label for='total_lectures'>Total Marks</label>
                            <input type="text" id='total_marks' name="total_marks" class="form-control">
                        </div>
                        
                        <div class="col-md-3 form-group">
                            <label for='total_lectures'>Book Reference</label>
                            <input type="text" id='book_link' name="book_link" class="form-control">
                        </div>
                       
                        <div class="col-md-12 border rounded mb-3 mb-md-4 mt-3 p-4 border-primary lecture_details_div"  style="display:none;">
                            <div class="col-md-12 form-group addButtonCheckbox">
                                <label>Lectures</label>                            
                                <select name="lectures" id="lectures" class="form-control" onchange="addNewRow();">
                                    <option value="">Select Lectures</option>                                                                                                     
                                </select> 
                            </div>                        
                        </div>                        

                        <div id="lecture_details">
                        </div>
                                               
                        <div class="col-md-12 form-group">
                            <center>
                                <input type="submit" name="submit" value="Save" class="btn btn-success" >
                            </center>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div> --}}

    @if ( $data['form_data'] )
    @php
        unset($data['form_data']['form_id']);
        unset($data['form_data']['chapter_id']);
    @endphp
    <div class="card">
        <div class="row">
            <div class="col-lg-12 col-sm-12 col-xs-12">
                <div class="table-responsive">
                    <table id="example" class="table table-striped">
                        @foreach ( $data['form_data'] as $key => $value )
                            @if ( $value != '' )
                                @if ( $key == 'header' )
                                    <tr>
                                        <td colspan="2" class="text-center"><h2>{{ $value }}</h2></td>
                                    </tr>
                                    @continue
                                @endif
                                <tr>
                                    <td style="width:30%">{{ $key }}</td>
                                    <td style="width:75%">{!! $value !!}</td>
                                </tr>
                            @endif
                        @endforeach
                    </table>
                </div>
            </div>
        </div>
    </div>
    @endif
</div>
@include('includes.lmsfooterJs')
<script src="//cdnjs.cloudflare.com/ajax/libs/moment.js/2.9.0/moment-with-locales.js"></script>
<script src="//cdn.rawgit.com/Eonasdan/bootstrap-datetimepicker/e8bddc60e73c1ec2475f827be36e1957af72e2ea/src/js/bootstrap-datetimepicker.js"></script>

<script src="//cdn.mathjax.org/mathjax/latest/MathJax.js"> 
 MathJax.Hub.Config({ 
   extensions: ["mml2jax.js"], 
   jax: ["input/MathML", "output/HTML-CSS"] 
 }); 
</script> 

<script type="text/javascript">
$(function () {    
    $('#datetimepicker').datetimepicker({  
        //format: 'DD/MM/YYYY hh:SS A'              
    });
    $('#datetimepicker1').datetimepicker({           
        //format: 'DD/MM/YYYY hh:SS A'                     
    });
     
}); 

function get_teacher()
{
    var division = $("#division").val();
    var standard_id = $("#hid_standard_id").val();
    var subject_id = $("#hid_subject_id").val();

    var path = "{{route('ajax_getTeacher')}}";
    $("#teacher_id").find("option").remove().end().append("<option value=''>Select Teacher</option>").val('');
    $.ajax({
        url:path,
        data:'division_id='+division+'&standard_id='+standard_id+'&subject_id='+subject_id, 
        success:function(result)
        {
            for(var i=0;i < result.length;i++)
            {
                $("#teacher_id").append($("<option></option>").val(result[i]['teacher_id']+'####'+result[i]['user_profile_id']).html(result[i]['teacher_name']));  
            }
        }
    });
}   

function get_timetable(){   
    
    var from_date = $("#from_date").val();
    var to_date = $("#to_date").val();
    var division_id = $("#division").val();
    var standard_id = $("#hid_standard_id").val();
    var subject_id = $("#hid_subject_id").val();
    var teacher_id = $("#teacher_id").val();
    
    if(division_id == "" || from_date == "" || from_date == undefined || to_date == "" || to_date == undefined || teacher_id == "")
    {
        alert("Please Select Division,Teacher,From-Date,To-Date first");
        $("#to_date").val('');
        return false;
    }
    else
    {
        var t = teacher_id.split("####");   
        var teacher_id = t[0];
        var profile_id = t[1];
        var path = "{{ route('ajax_Timetable') }}";
        $('.lecture_details_div').css("display", "block");
        $('#lectures').find('option').remove().end().append('<option value="">Select Lectures</option>').val('');
        $.ajax({
            url: path,
            data:'from_date='+from_date+'&to_date='+to_date+'&division_id='+division_id+'&standard_id='+standard_id+'&subject_id='+subject_id+'&teacher_id='+teacher_id, 
            success: function(result)
            {                                
                for (const key in result) 
                {
                    //console.log(`${key}: ${result[key]}`);
                    $("#lectures").append($("<option></option>").val(`${key}`).html(`${result[key]}`));  
                } 
            }
        });        
    }
}

//START Multiple lectures
function addNewRow(){
      
    var lecture_ids = $("#lectures").val();  

    if(lecture_ids != "")//Dont add if lecture id is null
    {
        //check for already added lecture
        var inps = document.getElementsByName('lecture_ids[]');
        var err = 0;
        for (var i = 0; i <inps.length; i++) {
            var inp=inps[i];
            if(inp.value == lecture_ids)
            {
                err = 1;
            }
        }
       
        if(err == 0)
        {
            $("#total_lectures").val(inps.length + 1);

            var val = lecture_ids.split("####");     
            var dt = val[0];     
            var period = val[1];
            var lecture_title = $("#subject_id").val();   

            //Add html content
            var htmlcontent = '';    
            htmlcontent += '<div class="clearfix"></div><div class="addButtonCheckbox"><div class="row align-items-center">';
            htmlcontent += '<div class="col-md-2"><div class="form-group"><label for="LectureDate">Lecture Date</label><input type="hidden" name="lecture_ids[]" value="'+lecture_ids+'"><input type="text" class="cust-select form-control mb-0" name="lecture_date[]" value="'+dt+'" readonly></div></div>';
            htmlcontent += '<div class="col-md-4"><div class="form-group"><label for="LectureTitle">Lecture Title</label><input type="text" class="cust-select form-control mb-0" name="lecture_title[]" value="'+lecture_title+'" required></div></div>';
            htmlcontent += '<div class="col-md-4"><div class="form-group"><label for="LectureDesc">Lecture Desc</label><input type="text" class="cust-select form-control mb-0" name="lecture_desc[]"></div></div>';
            htmlcontent += '<div class="col-md-2"><a href="javascript:void(0);" onclick="removeNewRow();" class="btn btn-danger btn-sm"><i class="mdi mdi-minus"></i></a></div></div></div>';
                                     
            $('.addButtonCheckbox:last').after(htmlcontent);
        }
        else{
            alert("Lecture Already added");
        }
    }
}

function removeNewRow() 
{     
    $(".addButtonCheckbox:last" ).remove();
    var tot = $("#total_lectures").val();      
    var tot = tot - 1;
    $("#total_lectures").val(tot);
}
//END Multiple lectures

function check_validation()
{
    var inps = document.getElementsByName('lecture_ids[]');
    if(inps.length == 0)
    {
        alert("Please Select Atleast One lecture");
        return false;
    }
    else
    {
        return true;
    }

}

</script>

@include('includes.footer')
