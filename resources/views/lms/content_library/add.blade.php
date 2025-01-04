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

                        @foreach($data['mapType'] as $key=>$value)
                          @if(isset($data['mapValue'][$value->name]) && !empty($data['mapValue'][$value->name]))
                          <div class="col-md-4 form-group">
                            <label for="{{$value->name}}">{{$value->name}}</label>
                            <select name="keywords[{{$value->name}}]" id="select_{{$key}}" class="form-control">
                              <option value="">Select any one</option>
                              @foreach($data['mapValue'][$value->name] as $k=>$val)
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
</script>
@include('includes.footer')
@endsection