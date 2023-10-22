{{--@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation') --}}
@extends('layout')
@section('container')
<!-- Content main Section -->
<div class="content-main flex-fill">
    <div id="pdf-viewer"></div>
    <input type="hidden" name="hid_url" id="hid_url" value="../../../storage{{$data['content_data']['file_folder']}}/{{$data['content_data']['filename']}}#toolbar=0&navpanes=0">
</div>
<!-- <div class="content-main flex-fill">
    <h1 class="h4 mb-3">LMS</h1>
    <nav aria-label="breadcrumb">

    </nav>

    <div class="container-fluid mb-5">
        <div class="row">
            <div class="col-md-12 mb-3 mb-md-4">
                <div class="video-box mb-4">
                    <div class="embed-responsive embed-responsive-16by9">
                        <iframe id="main_div" autoplay="false" class="embed-responsive-item"
                        src="../../../storage{{$data['content_data']['file_folder']}}/{{$data['content_data']['filename']}}#toolbar=0&navpanes=0"
                        allowfullscreen onload="disableContextMenu();" onMyLoad="disableContextMenu();"></iframe>


                    </div>
                </div>
                <div class="video-title h4 mb-3">{{$data['content_data']['description']}}</div>
                <div class="course-box p-0">
                    <div class="course-bottom course-bottom justify-content-start p-0">
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
 -->

@include('includes.lmsfooterJs')
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.3.200/pdf.js"></script>
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.4.1/jquery.min.js"></script>
<script>
$(document).ready(function() {
document.onmousedown = function (e) {
        //Check the Mouse Button which is clicked.
        if (e.which == 3) {
            // alert(e.which);
            //If the Button is middle or right then disable.
            return false;
        }

};

document.onkeydown = function (e) {
    // if (e.which == 83 || e.which == 80 || e.which == 123 || e.which == 44) {
            // alert(e.which);
            //If the Button is middle or right then disable.
            return false;
        // }
    // return false;
}
window.onload = function() {
    document.addEventListener("contextmenu", function(e){
        e.preventDefault();
        if(event.keyCode == 123) {
            disableEvent(e);
        }
    }, false);
    function disableEvent(e)
    {
        if(e.stopPropagation) {
            e.stopPropagation();
        } else if(window.event) {
            window.event.cancelBubble = true;
        }
    }

    $(document).contextmenu(function() { return false;});

    var url = $("#hid_url").val();
    var thePdf = null;
    var scale = 2;

        pdfjsLib.getDocument(url).promise.then(function(pdf) {
          thePdf = pdf;
          viewer = document.getElementById('pdf-viewer');
          for(page = 1; page <= pdf.numPages; page++) {
            canvas = document.createElement("canvas");
            canvas.className = 'pdf-page-canvas';
            viewer.appendChild(canvas);
            renderPage(page, canvas);
          }
      });
      function renderPage(pageNumber, canvas)
      {
          thePdf.getPage(pageNumber).then(function(page) {
            viewport = page.getViewport(scale);
            canvas.height = viewport.height;
            canvas.width = viewport.width;
            page.render({canvasContext: canvas.getContext('2d'), viewport: viewport});
            });
      }
  }
});
</script>
@include('includes.footer')
@endsection
