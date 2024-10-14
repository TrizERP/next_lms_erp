@extends('layout')
@section('container')
<div id="page-wrapper">
   <div class="container-fluid">
      <div class="row bg-title">
         <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
            <h4 class="page-title">LMS Syllabus</h4>
         </div>
      </div>
      <div class="card">
         <div class="card-body">
            @php 
                  $edit = $data['data'];
            @endphp
            <form action="{{route('lms_syllabus.update',$edit->id)}}" method="post">
               {{method_field("PUT")}}
               @csrf 
                <!-- select grade,standard,subject  -->
                {{ App\Helpers\SearchChainSubject('4','single','grade,std,sub',$edit->grade_id,$edit->standard_id,$edit->subject_id) }}
               <div class="row">
                  <!-- Select Board  -->
                  <div class="col-md-3 form-group">
                     <label for="curriculum_id">Curriculum Title</label>
                     <select name="curriculum_id" id="curriculum_id" required class="form-control">
                   
                     </select>
                  </div>
                  <!-- Syllabus Title  -->
                  <div class="col-md-3 form-group">
                    <label for="syllabus_title">Syllabus Title</label>
                    <input type="text" class="form-control" name="syllabus_title" id="syllabus_title" placeholder="Enter Syllabus title ...." value="{{$edit->title}}">
                  </div>
                <!-- Syllabus Objectives  -->
                <div class="col-md-3 form-group">
                    <label for="syllabus_obj">Syllabus Objectives</label>
                    <textarea name="syllabus_objectives" id="syllabus_objectives" class="form-control" placeholder='Enter Objectives'>{{substr($edit->objectives,0,100)}}</textarea>
                </div>
                <!-- learning outcomes  -->
                <div class="col-md-3 form-group">
                    <label for="learning_outcomes">Learning Outcomes</label>
                    <textarea name="learning_outcomes" id="learning_outcomes" class="form-control" placeholder='Learning Outcomes'>{{substr($edit->learning_outcomes,0,100)}}</textarea>
                </div>
                <!-- Suggested Materials -->
                  <div class="col-md-3 form-group">
                    <label for="suggested_materials">Suggested Materials</label>
                    <textarea name="suggested_materials" id="suggested_materials" class="form-control" placeholder='Suggested Materials'>{{substr($edit->suggested_materials,0,100)}}</textarea>
                </div>
                 <!-- Assesment Plan  -->
                 <div class="col-md-3 form-group">
                    <label for="assesment_plans">Assesment Plan</label>
                    <textarea name="assesment_plans" id="assesment_plans" class="form-control" placeholder='Assesment Plan'>{{substr($edit->assessment_plan,0,100)}}</textarea>
                </div>
                <!-- Progressing Track -->
                <div class="col-md-3 form-group">
                    <label for="progress_tracking">Progress Tracking</label>
                    <input type="number" name="progress_tracking" id="progress_tracking" class="form-control" step="0.01" min="0" max="999.99" placeholder="Enter progress" value="{{$edit->progress_tracking}}">
                </div>

               </div>
               <div class="col-md-12">
                  <center>
                     <input type="submit" value="Save" class="btn btn-primary">
                  </center>
               </div>
            </form>
         </div>
      </div>
   </div>
</div>
@include('includes.footerJs')
<script>
      $(document).ready(function(){
      @if(isset($edit->subject_id))
         var standardID = "{{$edit->standard_id}}";
         var subjectID = "{{$edit->subject_id}}";
         getCurriculum(standardID,subjectID);
      @endif 

      $('#subject').change(function () {
        var standardID = $("#standardS").val();
        var subjectID = $("#subject").val();
         getCurriculum(standardID,subjectID);
      });
    })
    function getCurriculum(standardID,subjectID){
        $("#curriculum_id").empty();
        $("#curriculum_id").append('<option value="">Select</option>');
        var selectedSubjects = "{{$edit->curriculum_id}}"; 
         console.log(selectedSubjects);
        if (standardID && subjectID) {
            $.ajax({
                type: "GET",
                url: "/api/get-curriculum-list?standard=" + standardID + "&subject="+subjectID,
                success: function (res) {
                  //   console.log(res);
                    if (res) {
                        $("#curriculum_id").empty();
                        $("#curriculum_id").append('<option value="">Select</option>');
                        $.each(res, function (key, value) {
                            // $("#curriculum_id").append('<option value="' + value.id + '">' + value.curriculum_name + '</option>');
                            var selected = (selectedSubjects==value.id) ? 'selected' : '';
                           $("#curriculum_id").append('<option value="' + value.id + '" ' + selected + '>' + value.curriculum_name + '</option>');
                        });

                    } else {
                        $("#curriculum_id").empty();
                    }
                }
            });
        } else {
            $("#subject_curricula").empty();
        }
    }
</script>
@include('includes.footer')
@endsection