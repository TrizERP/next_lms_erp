{{--@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation') --}}
@extends('layout')
@section('container')
<!-- Content main Section -->

<div class="content-main flex-fill">
    <h1 class="h4 mb-3">LMS</h1>
    <nav aria-label="breadcrumb">

    </nav>

    <div class="container-fluid mb-5">
        <div class="row">
            <div class="col-md-12 mb-3 mb-md-4">
                <div class="video-box mb-4">
                    <div class="embed-responsive embed-responsive-16by9">
                        <video oncontextmenu="return false;" id="my-video-player" width="854" height="480" controls autoplay controlsList="nodownload">
                            <source src="../../../storage{{$data['content_data']['file_folder']}}/{{$data['content_data']['filename']}}#toolbar=0&navpanes=0" type="video/mp4">
                        </video>

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


@include('includes.lmsfooterJs')
<script>
$(document).bind("contextmenu",function(e){
  return false;
});
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
</script>
@include('includes.footer')
@endsection
