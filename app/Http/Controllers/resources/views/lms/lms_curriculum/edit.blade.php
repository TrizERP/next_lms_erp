@extends('layout')
@section('container')
<div id="page-wrapper">
   <div class="container-fluid">
      <div class="row bg-title">
         <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
            <h4 class="page-title">LMS Curriculum</h4>
         </div>
      </div>
      <div class="card">
         <div class="card-body">
            <form action="{{route('lms_curriculum.update',$data['data']->id)}}" method="post">
               {{ method_field("PUT") }}
               @csrf 
               @php 
                  $edit = $data['data'];
                  $modelArr=$subjectArr = [];
                  if(isset($edit->subject_curricula)){
                     $subjectArr = explode(',',$edit->subject_curricula);
                  }
                  if(isset($edit->model_integration)){
                     $modelArr = explode(',',$edit->model_integration);
                  }
               @endphp 
               <!-- select grade,standard,subject  -->
               {{ App\Helpers\SearchChainSubject('4','single','grade,std,sub',$edit->grade_id,$edit->standard_id,$edit->subject_id) }}
               <div class="row">
                  <!-- Select Board  -->
                  <div class="col-md-3 form-group">
                     <label for="board">Board</label>
                     <select name="board_id" id="board_id" required class="form-control">
                        <option value="">Select</option>
                        @foreach($data['boards'] as $key=>$value)
                        <option value="{{$key}}" @if($edit->board_id==$key) selected @endif>{{$value}}</option>
                        @endforeach
                     </select>
                  </div>
                  <!-- enter curriculum name -->
                  <div class="col-md-3 form-group">
                     <label for="curriculum_name">Curriculum Name</label>
                     <input type="text" name="curriculum_name" id="curriculum_name" placeholder="Enter curriculum name" class="form-control" autocomplete="off" value="{{$edit->curriculum_name}}">
                  </div>
                  <!-- enter curriculum Alignment -->
                  <div class="col-md-3 form-group">
                     <label for="curriculum_alignment">Curriculum Alignment</label>
                     <textarea name="curriculum_alignment" id="curriculum_alignment" class="form-control resizableVertical" row="4">{{$edit->curriculum_alignment}}</textarea>
                  </div>
                  <!-- enter Holistic curriculum -->
                  <div class="col-md-3 form-group">
                     <label for="holistic_curriculum">Holistic Curriculum</label>
                     <textarea name="holistic_curriculum" id="holistic_curriculum" class="form-control resizableVertical" row="4">{{$edit->holistic_curriculum}}</textarea>
                  </div>
                  <!-- Select Subject Curricula  -->
                  {{--  <div class="col-md-3 form-group">
                     <label for="subject_curricula">Subject Curricula</label>
                     <select name="subject_curricula[]" id="subject_curricula" required class="form-control" multiple>
                        <option value="">Select</option>
                       
                     </select>
                  </div> --}}
                  <!-- Select Board  -->
                  <div class="col-md-3 form-group">
                     <label for="model_integration">Model Integration</label>
                     <select name="model_integration[]" id="model_integration" required class="form-control" multiple>
                        <option value="">Select</option>
                        @foreach($data['model_integrations'] as $key=>$value)
                        <option value="{{$key}}" @if(in_array($key,$modelArr)) selected @endif>{{$value}}</option>
                        @endforeach
                     </select>
                  </div>
                  <!-- added on 18-10-2024  -->
                  <!-- enter objective -->
                  <div class="col-md-3 form-group">
                     <label for="objective">Objective</label>
                     <textarea name="objective" id="objective" class="form-control resizableVertical" row="4">{{$edit->objective}}</textarea>
                  </div>
                     <!-- enter chapter -->
                     <div class="col-md-3 form-group">
                     <label for="chapter">Chapter</label>
                     <textarea name="chapter" id="chapter" class="form-control resizableVertical" row="4">{{$edit->chapter}}</textarea>
                  </div>
                     <!-- enter outcome -->
                     <div class="col-md-3 form-group">
                     <label for="outcome">Outcome</label>
                     <textarea name="outcome" id="outcome" class="form-control resizableVertical" row="4">{{$edit->outcome}}</textarea>
                  </div>
                     <!-- enter assessment_tool -->
                     <div class="col-md-3 form-group">
                     <label for="assessment_tool">Assessment Tool</label>
                     <textarea name="assessment_tool" id="assessment_tool" class="form-control resizableVertical" row="4">{{$edit->assessment_tool}}</textarea>
                  </div>
                  <!-- end 18-10-2024 -->
               </div>
               <div class="col-md-12">
                  <center>
                     <input type="submit" value="Update" class="btn btn-primary">
                  </center>
               </div>
            </form>
         </div>
      </div>
   </div>
</div>
@include('includes.footerJs')
<script>
   //  $(document).ready(function(){
   //    @if(isset($edit->standard_id))
   //       var standardID = "{{$edit->standard_id}}";
   //       getStandard(standardID);
   //    @endif 
   //    $('#standardS').change(function () {
   //       var standardID = $("#standardS").val();
   //       getStandard(standardID);
   //    });

   //  })

   //  function getStandard(standardID){
   //    var selectedSubjects = @json($subjectArr); 
   //    $("#subject_curricula").empty();
   //      $("#subject_curricula").append('<option value="">Select</option>');
   //      if (standardID) {
   //          $.ajax({
   //              type: "GET",
   //              url: "/api/get-all-subject-list?standard_id=" + standardID,
   //              success: function (res) {
   //                //   console.log(res);
   //                  if (res) {
   //                      $("#subject_curricula").empty();
   //                      $("#subject_curricula").append('<option value="">Select</option>');
   //                      $.each(res, function (key, value) {
   //                         //  $("#subject_curricula").append('<option value="' + key + '">' + value + '</option>');
   //                         var selected = selectedSubjects.includes(key.toString()) ? 'selected' : '';
   //                         $("#subject_curricula").append('<option value="' + key + '" ' + selected + '>' + value + '</option>');
   //                      });

   //                  } else {
   //                      $("#subject_curricula").empty();
   //                  }
   //              }
   //          });
   //      } else {
   //          $("#subject_curricula").empty();
   //      }
   //  }
</script>
@include('includes.footer')
@endsection