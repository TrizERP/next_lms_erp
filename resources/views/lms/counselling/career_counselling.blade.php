@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')
<!--

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">
                Career Counselling
                </h4>
            </div>
        </div>

        <div class="card">
            <div class="row">
                <div class="col-lg-3 col-sm-6 col-xs-12">
                    <div class="form-group">
                        @php
                            $chapter_link = "https://main--lms-portal-site.netlify.app";
                        @endphp
                        <a href="{{$chapter_link}}" target="_blank" class="btn btn-info add-new" style="width: 100%">Thinking about career plan</a>
                    </div>
                </div>
                <div class="col-lg-3 col-sm-6 col-xs-12">
                    <div class="form-group">
                        @php
                            $topic_link = "https://main--lms-portal-site.netlify.app/knowing-yourself";
                        @endphp
                        <a href="{{$topic_link}}" target="_blank" class="btn btn-info add-new" style="width: 100%">Knowing yourself</a>
                    </div>
                </div>
                <div class="col-lg-3 col-sm-6 col-xs-12">
                    <div class="form-group">
                        @php
                            $question_link = "https://main--lms-portal-site.netlify.app/education";
                        @endphp
                        <a href="{{$question_link}}" target="_blank" class="btn btn-info add-new" style="width: 100%">Career explore</a>
                    </div>
                </div>
                <div class="col-lg-3 col-sm-6 col-xs-12">
                    <div class="form-group">
                        @php
                            $lo_link = "https://main--lms-portal-site.netlify.app/match-profile"; 
                        @endphp
                        <a href="{{$lo_link}}" target="_blank" class="btn btn-info add-new" style="width: 100%">Match your profile</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>    

-->
<div class="container-fluid">
            <div class="col-lg-12 col-sm-12 col-xs-12">
            <div class="col-md-12 form-group iframe-container">
            <iframe src="https://careercounseling.vercel.app" class="responsive-iframe" style="border:none;"></iframe>
            </div>
            </div>
</div>
@include('includes.footerJs')
@include('includes.footer')

