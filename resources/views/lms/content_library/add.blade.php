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

         <div class="panel-body">
                <form action="{{route('content_library.store')}}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <input type="hidden" name="insert_type" value="content_insert">
                    <div class="card">
                      <div class="row">
                        <div class="col-md-4 form-group">
                          <label for="Title">Title</label>
                          <input type="text" class="form-control" name="title" required>
                        </div>
                        <div class="col-md-4 form-group">
                          <label for="Description">Description</label>
                          <textarea name="description" rows="9" id="description" class="form-control resizableVertical"></textarea>
                        </div>

                        <div class="col-md-4">        
                          <label for="input-file-now">Add Attachment</label>
                              <input type="file" name="attachment" id="input-file-now" class="dropify" /> 
                        </div>

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
                              <option value="{{$val->name}}">{{$val->name}}</option>
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
                              <option value="{{$val->name}}" data-type="{{$val->type}}">{{$val->name}}</option>
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
                            <select name="keywords[{{$course_name}}]" id="select_{{$key}}" class="form-control" onchange="getContents(this,'subject');">
                              <option value="">Select any one</option>
                              @foreach($data['courses']['mapValue'][$course_name] as $k=>$val)
                              <option value="{{$val->name}}" data-parentId="{{$val->id}}">{{$val->name}}</option>
                              @endforeach
                            </select>
                          </div>
                          @endif
                        @endforeach

                        @if(!empty($data['courses']['mapValue']))
                        <div class="col-md-4">
                          <label for="subject">Select Subjects</label>
                          <select name="keywords[subject]" id="subject" class="form-control" onchange="getMappedChapter();">
                            <option value="">Select Subject</option>
                          </select>
                        </div>
                        <div class="col-md-4">
                          <label for="chapter">Select Chapters</label>
                          <select name="keywords[chapter]" id="chapter" class="form-control">
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
                            <label for="{{$type_name}}">Select {{$value->name}}</label>
                            <select name="keywords[{{$type_name}}]" id="select_{{$key}}" class="form-control">
                              <option value="">Select any one</option>
                              @foreach($data['content_type']['mapValue'][$type_name] as $k=>$val)
                              <option value="{{$val->name}}">{{$val->name}}</option>
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
                                <option value="{{$val->name}}">{{$val->name}}</option>
                                @endforeach
                            </select>
                            </div>
                            @endif
                        @endforeach
                        
                        <div class="col-md-12">
                          <center>
                            <input type="submit" name="submit" value="Add" class="btn btn-primary">
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

    function getContents(event, content_type) {
        var selectedOption = $(event).find(':selected');
        var value = selectedOption.val();
        var parentId = selectedOption.data('parentid');

        $('#'+content_type).empty();

        $.ajax({
          url: "{{route('getMapVals')}}",
          data : {parent_id:parentId},
          type : 'GET',
          success : function(result){
            console.log(result);
            $('#'+content_type).find('option').remove().end().append('<option value="">Select '+content_type+'</option>').val('');
            if (result.length > 0) {
                result.forEach(function(item) {
                    $("#" + content_type).append(`<option value="${item['name']}" data-parentid="${item['id']}"  data-type="${item['type']}">${item['name']}</option>`); // Closing bracket correctly placed
                });
            }

          }
        })
    }

    function getMappedChapter(){
      var subjectID = $('#subject option:selected').attr('data-type');
      var standardID = $('#Standards option:selected').attr('data-type');
      console.log(standardID+'-'+subjectID);
      if (subjectID && standardID) {
            $.ajax({
                type: "GET",
                url: "/api/get-chapter-list?subject_id=" + subjectID + "&standard_id=" + standardID,
                success: function (res) {
                    if (res) {
                        $("#chapter").empty();
                        $("#chapter").append('<option value="">Select Chapter</option>');
                        $.each(res, function (key, value) {      
                            $("#chapter").append('<option value="' + value + '" >' + value + '</option>');
                        });

                    } else {
                        $("#chapter").empty();
                        $("#chapter").append('<option value="">Select Chapter</option>');
                    }
                }
            });
        } else {
            $("#chapter").empty();
            $("#chapter").append('<option value="">Select Chapter</option>');
        }
    }

</script>
@include('includes.footer')
@endsection