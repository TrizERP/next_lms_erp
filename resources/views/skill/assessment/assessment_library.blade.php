@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')
<link href="https://stackpath.bootstrapcdn.com/bootstrap/4.4.1/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css" rel="stylesheet">
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.4.1/js/bootstrap.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.2.1/jquery.min.js"></script>
<style>
body {
  background: #f0f0f8;
}
.col-md-3 {
  margin-top: 26px;
}
.fade-up {
    transition: all 0.3s ease-in-out;
    border-radius: 10px;
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
    overflow: hidden;
}

.fade-up:hover {
    transform: translateY(-10px);
    box-shadow: 0px 5px 15px rgba(0, 0, 0, 0.2);
}
/* Custom styles */
.nav-tabs .nav-link {
            color: #555;
            font-weight: 500;
            padding: 10px 20px;
            transition: all 0.3s ease-in-out;
            border-radius: 8px 8px 0 0;
}
.nav-tabs .nav-link.active {
    background: #007bff;
    color: white;
    font-weight: bold;
}
</style>
<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Skill Assessment</h4>
            </div>
            <div class="col-lg-6 col-md-8 col-sm-8 col-xs-12">
                <h1 class="center"></h1>
            </div>
        </div>

        @php
            $badgeColors = ['primary','secondary','success','danger','warning','info','dark'];
        @endphp

        <div class="tab-title d-table mb-4 mx-auto">
        <ul class="nav nav-tabs justify-content-center" id="assessmentTabs">
            @php
                $types = ['All', 'Psychometric', 'Domain', 'Industry Specific', 'Core Corporate', 'Level of Exp.', 'Soft Skill', 'Tech Skill', '21st Century', 'Employability Skill'];
                $first = true;
            @endphp

            @foreach ($types as $type)
                <li class="nav-item">
                    <a class="nav-link {{ $first ? 'active' : '' }}" data-toggle="tab" href="#" data-filter="{{ $type }}">
                        {{ $type }}
                    </a>
                </li>
                @php 
                    $first = false; 
                    $randomKey = array_rand($badgeColors);
                    $typeBadges[$type] = $badgeColors[$randomKey];
                @endphp
            @endforeach

            </ul>
        </div>
        <div class="row mt-1">
        @foreach ($assessments as $assessment)
            @php
                $badgeClass = $typeBadges[$assessment->type];
            @endphp
            <div class="col-md-3 assessment-card fade-up" data-type="{{ $assessment->type }}">
                <div class="p-card bg-white p-2 rounded border border-{{ $badgeClass }}">
                    <div class="text-left"><span class="text-black-50 ml-2">{{ $assessment->level }}</span></div>
                    <h5 class="mt-2">{{ $assessment->title }}</h5>
                    <span class="badge badge-{{ $badgeClass }} py-1 mb-2">{{ $assessment->type }}</span>
                    <span class="d-block mb-5">{{ $assessment->description }}</span>
                    <!--<a href="#" target="_blank" class="btn btn-primary btn-sm">Start</a>-->
                    <div class="d-flex justify-content-between stats">
                        <div class="ml-1"><i class="fa fa-clock-o"></i><span class="ml-1">{{ $assessment->duration }} min</span></div>
                        <div class="ml-1"><i class="fa fa-question-circle"></i><span class="ml-1">{{ $assessment->total_questions }} Que</span></div>
                        <div class="ml-1"><i class="fa fa-users"></i><span class="ml-1">{{ $assessment->attempted_users }} user</span></div>
                    </div>
                </div>
            </div>
        @endforeach
        </div>
    </div>
</div>
<script>
    document.addEventListener("DOMContentLoaded", function () {
        let activeTab = localStorage.getItem("activeTab");
        
        if (activeTab) {
            document.querySelectorAll("#assessmentTabs .nav-link").forEach(link => {
                link.classList.remove("active");
                if (link.getAttribute("data-filter") === activeTab) {
                    link.classList.add("active");
                }
            });
        }

        document.querySelectorAll("#assessmentTabs .nav-link").forEach(link => {
            link.addEventListener("click", function () {
                localStorage.setItem("activeTab", this.getAttribute("data-filter"));
            });
        });
    });
</script>

<script>
    $(document).ready(function () {
        $(".nav-link").click(function (e) {
            e.preventDefault();
            let filter = $(this).attr("data-filter");
            $(".nav-link").removeClass("active");
            $(this).addClass("active");

            if (filter === "All") {
                $(".assessment-card").show();
            } else {
                $(".assessment-card").each(function () {
                    $(this).toggle($(this).attr("data-type") === filter);
                });
            }
        });
    });
</script>
@include('includes.footerJs')
@include('includes.footer')
