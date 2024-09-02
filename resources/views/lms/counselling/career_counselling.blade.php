@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

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
                    <div class="col-lg-12 col-sm-12 col-xs-12">
                        <div class="col-md-12 form-group">
                            @php
                                $chapter_link = "https://main--lms-portal-site.netlify.app";

                                $topic_link = "https://main--lms-portal-site.netlify.app/knowing-yourself";

                                $question_link = "https://main--lms-portal-site.netlify.app/education";

                                $lo_link = "";

                                $content_link = "";
                                @endphp
                            <a href="{{$chapter_link}}" target="_blank" class="btn btn-info add-new">Thinking about career plan</a>

                            <a href="{{$topic_link}}" target="_blank" class="btn btn-info add-new">Knowing yourself</a>

                            <a href="{{$question_link}}" target="_blank" class="btn btn-info add-new">Career explore</a>

                            <!--<a href="{{$lo_link}}" target="_blank" class="btn btn-info add-new">Match your profile</a>

                            {{-- Bulk content upload : START --}}
                            <a href="{{$content_link}}" target="_blank" class="btn btn-info add-new">Bulk Content Upload</a>-->
                            {{-- Bulk content upload : END --}}
                        </div>
                    </div>
                </div>


    </div>
</div>

@include('includes.footerJs')
@include('includes.footer')
