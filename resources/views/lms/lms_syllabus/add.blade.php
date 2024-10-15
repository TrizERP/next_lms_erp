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
            <form action="{{route('lms_syllabus.store')}}" method="post">
               @csrf 
               <!-- select grade,standard,subject  -->
               @php 
               $grade=$standard=$subject='';
               if(isset($data['grade_id'])){
                $grade = $data['grade_id'];
               }
               if(isset($data['standard_id'])){
                $standard = $data['standard_id'];
               }
               if(isset($data['subject_id'])){
                $subject = $data['subject_id'];
               }
               @endphp
               {{ App\Helpers\SearchChainSubject('4','single','grade,std,sub',$grade,$standard,$subject) }}
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
                    <input type="text" class="form-control" name="syllabus_title" id="syllabus_title" placeholder="Enter Syllabus title ....">
                  </div>
                <!-- Syllabus Objectives  -->
                <div class="col-md-3 form-group">
                    <label for="syllabus_obj">Syllabus Objectives</label>
                    <textarea name="syllabus_objectives" id="syllabus_objectives" class="form-control" placeholder='Enter Objectives'></textarea>
                </div>
                <!-- learning outcomes  -->
                <div class="col-md-3 form-group">
                    <label for="learning_outcomes">Learning Outcomes</label>
                    <textarea name="learning_outcomes" id="learning_outcomes" class="form-control" placeholder='Learning Outcomes'></textarea>
                </div>
                <!-- Suggested Materials -->
                  <div class="col-md-3 form-group">
                    <label for="suggested_materials">Suggested Materials</label>
                    <textarea name="suggested_materials" id="suggested_materials" class="form-control" placeholder='Suggested Materials'></textarea>
                </div>
                 <!-- Assesment Plan  -->
                 <div class="col-md-3 form-group">
                    <label for="assesment_plans">Assesment Plan</label>
                    <textarea name="assesment_plans" id="assesment_plans" class="form-control" placeholder='Assesment Plan'></textarea>
                </div>
                <!-- Progressing Track -->
                <div class="col-md-3 form-group">
                    <label for="progress_tracking">Progress Tracking</label>
                    <input type="number" name="progress_tracking" id="progress_tracking" class="form-control" step="0.01" min="0" max="999.99" placeholder="Enter progress">
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
        $('#subject').change(function () {
        $("#curriculum_id").empty();
        $("#curriculum_id").append('<option value="">Select</option>');
        var standardID = $("#standardS").val();
        var subjectID = $("#subject").val();
        if (standardID && subjectID) {
            $.ajax({
                type: "GET",
                url: "/api/get-curriculum-list?standard=" + standardID + "&subject="+subjectID,
                success: function (res) {
                    console.log(res);
                    if (res) {
                        $("#curriculum_id").empty();
                        $("#curriculum_id").append('<option value="">Select</option>');
                        $.each(res, function (key, value) {
                            $("#curriculum_id").append('<option value="' + value.id + '">' + value.curriculum_name + '</option>');
                        });

                    } else {
                        $("#curriculum_id").empty();
                    }
                }
            });
        } else {
            $("#subject_curricula").empty();
        }

    });
    })
</script>
@include('includes.footer')
@endsection