@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<style>
    .thumbnail {
        cursor: pointer;
        transition: 0.3s;
        border: 2px solid transparent; /* Default border */
        border-radius: 5px;
    }

    .thumbnail.active {
        border: 3px solid #007bff; /* Blue border for active image */
        box-shadow: 0px 0px 10px rgba(0, 123, 255, 0.5);
    }

    .main-display {
        max-width: 100%;
        height: auto;
        border: 2px solid #ddd;
        border-radius: 10px;
        padding: 5px;
    }
</style>

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Skill Gap Analysis</h4>
            </div>
            <div class="col-lg-6 col-md-8 col-sm-8 col-xs-12">
                <h1 class="center"></h1>
            </div>
        </div>
        <div class="row">
        <!-- Left Side: Thumbnails -->
            <div class="col-md-2">
                <div class="row">
                    <div class="col-12">
                        <img src="{{asset('transport_onboard/svg_to_png/SkillsGapAnalysis01.WEBP')}}" class="thumbnail img-fluid mb-2" onclick="changeImage(this)">
                    </div>
                    <div class="col-12">
                        <img src="{{asset('transport_onboard/svg_to_png/SkillsGapAnalysis02.WEBP')}}" class="thumbnail img-fluid mb-2" onclick="changeImage(this)">
                    </div>
                    <div class="col-12">
                        <img src="{{asset('transport_onboard/svg_to_png/SkillsGapAnalysis03.WEBP')}}" class="thumbnail img-fluid mb-2" onclick="changeImage(this)">
                    </div>
                    <div class="col-12">
                        <img src="{{asset('transport_onboard/svg_to_png/SkillsGapAnalysis04.WEBP')}}" class="thumbnail img-fluid mb-2" onclick="changeImage(this)">
                    </div>
                </div>
            </div>

            <!-- Right Side: Main Display -->
            <div class="col-md-10">
                <img id="mainImage" src="{{asset('transport_onboard/svg_to_png/SkillsGapAnalysis01.WEBP')}}" class="main-display img-fluid">
            </div>
        </div>
    </div>
</div>
<script>
    function changeImage(img) {
        // Update the main display image
        document.getElementById("mainImage").src = img.src;

        // Remove active class from all thumbnails
        document.querySelectorAll('.thumbnail').forEach(thumbnail => {
            thumbnail.classList.remove('active');
        });

        // Add active class to the clicked thumbnail
        img.classList.add('active');
    }

    // Set the first image as active on page load
    document.addEventListener("DOMContentLoaded", function() {
        let firstThumbnail = document.querySelector(".thumbnail");
        if (firstThumbnail) {
            firstThumbnail.classList.add("active");
        }
    });
</script>
@include('includes.footerJs')
@include('includes.footer')
