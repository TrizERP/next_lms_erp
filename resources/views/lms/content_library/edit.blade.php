@extends('layout')
@section('container')
<link rel="stylesheet" href="../../../plugins/bower_components/dropify/dist/css/dropify.min.css">
<style>
   #movingAni {
  bottom: 15%;
  position: absolute;
  transform: rotateY(180deg);
  animation: linear infinite;
  animation-name: run;
  animation-duration: 7s;
}
@keyframes run {
  0% {
    left: 0;
  }
  50% {
    left: 100%;
  }
  100% {
    left: 0;    
  }
}
.activeBtn{
    box-shadow: 5px 10px #95c0d7;
    margin : 0px 10px 16px 0px;
}
.libraryBtn{
    padding: 10px 40px;
    border: 3px solid #20a5cc;
    color: #167aaf;
}
.headingH2{
    margin: 0px;
    padding: 10px 0px;
    font-family: cursive;
    color: #20a5cc;
    font-weight: bolder;
}
</style>
<div id="page-wrapper">
   <div class="container-fluid">

      <div class="white-box">

      @if ($sessionData = Session::get('data'))
        <div class="@if($sessionData['status_code']==1) alert alert-success alert-block @else alert alert-danger alert-block @endif ">
            <button type="button" class="close" data-dismiss="alert">×</button>
            <strong>{{ $sessionData['message'] }}</strong>
        </div>
      @endif
      @php 
        $grade=$standard=$subject='';
        if(isset($data['contentData']->grade_id)){
        $grade = $data['contentData']->grade_id;
        }
        if(isset($data['contentData']->standard_id)){
        $standard = $data['contentData']->standard_id;
        }
        if(isset($data['contentData']->subject_id)){
        $subject = $data['contentData']->subject_id;
        }
        @endphp
         <div class="panel-body">
                <form action="{{route('content_library.update',[$data['editData']->id])}}" method="POST" enctype="multipart/form-data">
                {{ method_field("PUT") }}
                    @csrf
                    <input type="hidden" name="insert_type" value="content_insert">
                    <div class="col-md-12">
                      <label for="head"><b>Map With Your Content :</b></label>
                    </div>
                    <div class="card">
                    {{ App\Helpers\SearchChainSubject('4','single','grade,std,sub',$grade,$standard,$subject) }}
                      <div class="row">
                        <div class="col-md-4">
                          <label for="">Select Chapter</label>
                          <select name="chapter_id" id="postchapter" class="form-control" required>
                              <option value="">Select Chapter</option>
                          </select>
                        </div>
                        <div class="col-md-3 form-group">
                          <label for="">Select Topic</label>
                            <select name="topic_id" id="search_topic" class="form-control mb-0">
                              
                            </select>
                        </div>
                        <div class="col-md-4">
                          <label for="">Display Status</label>
                          <br><input type="checkbox" id="show_hide" name="show_hide" value="1" checked>
                        </div>
                      </div>
                      <div class="row">
                        <div class="col-md-12">
                          <label for="title"><b>Content Data :</b></label>
                        </div>
                        <div class="col-md-4 form-group">
                          <label for="Title">Title</label>
                          <input type="text" class="form-control" name="title" required @if(isset($data['editData']->title)) value="{{$data['editData']->title}}" @endif>
                        </div>
                        <div class="col-md-4 form-group">
                          <label for="Description">Description</label>
                          <textarea name="description" rows="9" id="description" class="form-control resizableVertical">@if(isset($data['editData']->description)) {{$data['editData']->description}} @endif</textarea>
                        </div>

                        <div class="col-md-4">        
                          <label for="input-file-now">Add Attachment</label>
                              <input type="file" name="attachment" id="input-file-now" class="dropify" /> 
                              @if(isset($data['editData']->attachment)) <a target="_blank" href="https://s3-triz.fra1.cdn.digitaloceanspaces.com/public/content_library/{{$data['editData']->attachment}}">View File</a>
                            <input type="hidden" name="attached_file" value="{{$data['editData']->attachment}}">
                            @endif
                        </div>
                        @php 
                            $decodeJson = json_decode($data['editData']->keywords,true);
                        @endphp

                        {{--@foreach($data['mapType'] as $key=>$value)
                          @if(isset($data['mapValue'][$value->name]) && !empty($data['mapValue'][$value->name]))
                          <div class="col-md-4 form-group">
                            <label for="{{$value->name}}">Select  {{$value->name}}</label>
                            <select name="keywords[{{$value->name}}]" id="select_{{$key}}" class="form-control">
                              <option value="">Select any one</option>
                              @foreach($data['mapValue'][$value->name] as $k=>$val)
                              <option value="{{$val->name}}" @if(isset($decodeJson[$value->name]) && $decodeJson[$value->name]==$val->name) selected @endif>{{$val->name}}</option>
                              @endforeach
                            </select>
                          </div>
                          @endif
                        @endforeach --}}

                        @foreach($data['boards']['mapType'] as $key=>$value)
                        @php 
                            $board_name = str_replace(' ','_',$value->name);
                        @endphp 
                          @if(isset($data['boards']['mapValue'][$board_name]) && !empty($data['boards']['mapValue'][$board_name]))
                          <div class="col-md-4 form-group">
                            <label for="{{$board_name}}">Select {{$value->name}}</label>
                            <select name="keywords[{{$board_name}}]" id="select_{{$key}}" class="form-control">
                              <option value="">Select any one</option>
                              @foreach($data['boards']['mapValue'][$board_name] as $k=>$val)
                              <option value="{{$val->name}}" @if(isset($decodeJson[$board_name]) && $decodeJson[$board_name]==$val->name) selected @endif>{{$val->name}}</option>
                              @endforeach
                            </select>
                          </div>
                          @endif
                        @endforeach

                        @foreach($data['standards']['mapType'] as $key=>$value)
                        @php 
                            $std_name = str_replace(' ','_',$value->name);
                        @endphp 
                          @if(isset($data['standards']['mapValue'][$std_name]) && !empty($data['standards']['mapValue'][$std_name]))
                          <div class="col-md-4 form-group">
                            <label for="{{$std_name}}">Select {{$value->name}}</label>
                            <select name="keywords[{{$std_name}}]" id="Standards" class="form-control">
                              <option value="">Select any one</option>
                              @foreach($data['standards']['mapValue'][$std_name] as $k=>$val)
                              <option value="{{$val->name}}" data-type="{{$val->type}}" @if(isset($decodeJson[$std_name]) && $decodeJson[$std_name]==$val->name) selected @endif>{{$val->name}}</option>
                              @endforeach
                            </select>
                          </div>
                          @endif
                        @endforeach

                        @foreach($data['courses']['mapType'] as $key=>$value)
                        @php 
                            $course_name = str_replace(' ','_',$value->name);
                        @endphp 
                          @if(isset($data['courses']['mapValue'][$course_name]) && !empty($data['courses']['mapValue'][$course_name]))
                          <div class="col-md-4 form-group">
                            <label for="{{$course_name}}">Select {{$value->name}}</label>
                            <select name="keywords[{{$course_name}}]" id="Courses" class="form-control" onchange="getContents(this,'Subjects');">
                              <option value="">Select any one</option>
                              @foreach($data['courses']['mapValue'][$course_name] as $k=>$val)
                              <option value="{{$val->name}}" data-parentid="{{$val->id}}" @if(isset($decodeJson[$course_name]) && $decodeJson[$course_name]==$val->name) selected @endif>{{$val->name}}</option>
                              @endforeach
                            </select>
                          </div>
                          @endif
                        @endforeach

                        @if(!empty($data['courses']['mapValue']))
                        <div class="col-md-4">
                          <label for="subject">Select Subjects</label>
                          <select name="keywords[subject]" id="Subjects" class="form-control" onchange="getMappedChapter();">
                            <option value="">Select Subject</option>
                          </select>
                        </div>
                        <div class="col-md-4">
                          <label for="chapter">Select Chapters</label>
                          <select name="keywords[chapter]" id="Chapters" class="form-control">
                            <option value="">Select Chapter</option>
                          </select>
                        </div>
                        @endif

                        @foreach($data['content_type']['mapType'] as $key=>$value)
                        @php 
                            $type_name = str_replace(' ','_',$value->name);
                        @endphp 
                          @if(isset($data['content_type']['mapValue'][$type_name]) && !empty($data['content_type']['mapValue'][$type_name]))
                          <div class="col-md-4 form-group">
                            <label for="{{$type_name}}">Select  {{$value->name}}</label>
                            <select name="keywords[{{$type_name}}]" id="select_{{$key}}" class="form-control">
                              <option value="">Select any one</option>
                              @foreach($data['content_type']['mapValue'][$type_name] as $k=>$val)
                              <option value="{{$val->name}}"  @if(isset($decodeJson[$type_name]) && $decodeJson[$type_name]==$val->name) selected @endif>{{$val->name}}</option>
                              @endforeach
                            </select>
                          </div>
                          @endif
                        @endforeach

                        @foreach($data['otherMaps']['mapType'] as $key=>$value)
                        @php 
                            $otherMap = str_replace(' ','_',$value->name);
                        @endphp 
                            @if(isset($data['otherMaps']['mapValue'][$otherMap]) && !empty($data['otherMaps']['mapValue'][$otherMap]))
                            <div class="col-md-4 form-group">
                            <label for="{{$otherMap}}">Select {{$value->name}}</label>
                            <select name="keywords[{{$otherMap}}]" id="select_{{$key}}" class="form-control optionSelect" onchange="sendKeywords();">
                                <option value="">Select any {{$value->name}}</option>
                                @foreach($data['otherMaps']['mapValue'][$otherMap] as $k=>$val)
                                <option value="{{$val->name}}"  @if(isset($decodeJson[$otherMap]) && $decodeJson[$otherMap]==$val->name) selected @endif>{{$val->name}}</option>
                                @endforeach
                            </select>
                            </div>
                            @endif
                        @endforeach

                        <div class="col-md-12">
                          <center>
                            <input type="submit" name="submit" value="Update" class="btn btn-primary">
                          </center>
                        </div>

                      </div>
                    </div>
                </form>
         </div>
      </div>

   </div>
</div>
@include('includes.lmsfooterJs')
<script src="../../../plugins/bower_components/dropify/dist/js/dropify.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.3/js/select2.min.js"></script>
<script>
   $(document).ready(function() {
      // onload in value are in keywords
      @if(isset($decodeJson['Courses']) && isset($decodeJson['subject']) && $decodeJson['subject']!='')
        getContents('', 'Subjects',"{{$decodeJson['subject']}}","{{$decodeJson['Courses']}}");
      @elseif(isset($decodeJson['Courses']))
        getContents('', 'Subjects',"","{{$decodeJson['Courses']}}");
      @endif
    
        // Basic
        $('.dropify').dropify();
        // Translated
        $('.dropify-fr').dropify({
            messages: {
                default: 'Glissez-déposez un fichier ici ou cliquez',
                replace: 'Glissez-déposez un fichier ou cliquez pour remplacer',
                remove: 'Supprimer',
                error: 'Désolé, le fichier trop volumineux'
            }
        });
        // Used events
        var drEvent = $('#input-file-events').dropify();
        drEvent.on('dropify.beforeClear', function(event, element) {
            return confirm("Do you really want to delete \"" + element.file.name + "\" ?");
        });
        drEvent.on('dropify.afterClear', function(event, element) {
            alert('File deleted');
        });
        drEvent.on('dropify.errors', function(event, element) {
            console.log('Has Errors');
        });
        var drDestroy = $('#input-file-to-destroy').dropify();
        drDestroy = drDestroy.data('dropify')
        $('#toggleDropify').on('click', function(e) {
            e.preventDefault();
            if (drDestroy.isDropified()) {
                drDestroy.destroy();
            } else {
                drDestroy.init();
            }
        })
    });
</script>
<script>
    $(document).ready(function(){

     @if(isset($data['contentData']->chapter_id))
      var subjectID = "{{$data['contentData']->subject_id}}";
      var standardID = "{{$data['contentData']->standard_id}}";
      var chapter_id = "{{$data['contentData']->chapter_id}}";
      getChapter(subjectID,standardID,chapter_id);
     @endif

     @if(isset($data['contentData']->topic_id))
      var chapter_id = "{{$data['contentData']->chapter_id}}";
      var topic_id = "{{$data['contentData']->topic_id}}";
      getTopic(chapter_id,topic_id);
     @endif

      $('#subject').on('change', function () {
          var subjectID = $(this).val();
          var standardID = $('#standardS').val();
          getChapter(subjectID,standardID);
      });

      $("#postchapter").change(function(){
          var chapter_id = $("#postchapter").val();
          getTopic(chapter_id);
      })

    })

    function getChapter(subjectID,standardID,chapter_id=''){
      if (subjectID) {
            $.ajax({
                type: "GET",
                url: "/api/get-chapter-list?subject_id=" + subjectID + "&standard_id=" + standardID,
                success: function (res) {
                    if (res) {
                        $("#postchapter").empty();
                        $("#postchapter").append('<option value="">Select Chapter</option>');
                        $.each(res, function (key, value) {      
                        var selected='';
                          if(chapter_id!='' && chapter_id==key){
                            var selected='selected';
                          }                     
                            $("#postchapter").append('<option value="' + key + '" '+selected+'>' + value + '</option>');
                        });

                    } else {
                        $("#postchapter").empty();
                        $("#postchapter").append('<option value="">Select Chapter</option>');
                    }
                }
            });
        } else {
            $("#postchapter").empty();
            $("#postchapter").append('<option value="">Select Chapter</option>');
        }
    }

    function getTopic(chapter_id,topic_id=''){
      var path = "{{ route('ajax_LMS_ChapterwiseTopic') }}";

      $('#search_topic').find('option').remove().end().append('<option value="">Search By Topic</option>').val('');

      $.ajax({
          url: path,
          data:'chapter_id='+chapter_id,
          success: function(result) {
            $("#search_topic").empty(); 
            for (var i = 0; i < result.length; i++) {
                var selected = '';
                if (topic_id !== '' && topic_id == result[i]['id']) {
                    selected = 'selected';
                }
                $("#search_topic").append(
                    $("<option></option>")
                        .val(result[i]['id'])
                        .html(result[i]['name'])
                        .attr("selected", selected)
                );
            }
        }
      });
    }

    
    function getContents(event='', content_type,selVal='',parentName='') {
      var parentId = '';
      if(event!=''){
        var selectedOption = $(event).find(':selected');
        var value = selectedOption.val();
         parentId = selectedOption.data('parentid');
      }

        $('#'+content_type).empty();

        $.ajax({
          url: "{{route('getMapVals')}}",
          data : {parent_id:parentId,parentName:parentName},
          type : 'GET',
          success : function(result){
            // console.log(result);
            $('#'+content_type).find('option').remove().end().append('<option value="">Select '+content_type+'</option>').val('');
            if(result.length>0){
              result.forEach(function(item) {
                var selected = '';
                if(selVal!='' && item['name'] == selVal){
                  selected = 'selected';
                }
                $("#" + content_type).append(`<option value="${item['name']}" data-parentid="${item['id']}" data-type="${item['type']}" ${selected}>${item['name']}</option>`); 
              });
            }
              // onload in value are in keywords
              @if(isset($decodeJson['subject']) && isset($decodeJson['chapter']) && $decodeJson['chapter']!='')
                getMappedChapter("{{$decodeJson['chapter']}}");
              @elseif(isset($decodeJson['subject']))
                getMappedChapter();
              @endif
          }
        })
    }

    function getMappedChapter(chapter_id=''){
      var subjectID = $('#Subjects option:selected').attr('data-type');
      var standardID = $('#Standards option:selected').attr('data-type');
      console.log(standardID+'-'+subjectID);
      if (subjectID && standardID) {
            $.ajax({
                type: "GET",
                url: "/api/get-chapter-list?subject_id=" + subjectID + "&standard_id=" + standardID,
                success: function (res) {
                    if (res) {
                        $("#Chapters").empty();
                        $("#Chapters").append('<option value="">Select Chapter</option>');
                        $.each(res, function (key, value) {      
                        var selected='';
                          if(chapter_id!='' && chapter_id==value){
                            var selected='selected';
                          }                     
                            $("#Chapters").append('<option value="' + value + '" '+selected+'>' + value + '</option>');
                        });

                    } else {
                        $("#Chapters").empty();
                        $("#Chapters").append('<option value="">Select Chapter</option>');
                    }
                }
            });
        } else {
            $("#postchapter").empty();
            $("#postchapter").append('<option value="">Select Chapter</option>');
        }
    }
</script>
@include('includes.footer')
@endsection