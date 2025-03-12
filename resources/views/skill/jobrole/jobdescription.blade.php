@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Job Description</h4>
            </div>
        </div>
        <style>
        body {
            background-color: #f4f7fc;
        }
        .job-header {
            background: linear-gradient(to right, #28a745, #007bff);
            color: white;
            padding: 30px;
            text-align: center;
            border-radius: 10px;
        }
        .icon-box {
            font-size: 40px;
            color: #007bff;
        }
        .info-card {
            border-radius: 10px;
            box-shadow: 0px 4px 6px rgba(0, 0, 0, 0.1);
            transition: 0.3s;
        }
        .info-card:hover {
            transform: translateY(-5px);
        }
        .progress {
            height: 10px;
        }
        .badge {
            font-size: 14px;
            padding: 6px 12px;
            text-align:left;
        }
    </style>
</head>
<body>

<div class="container mt-4">
    <!-- Job Role Header -->
    <div class="job-header">
        <h4>{{ $career->career_pathway }}</h3>
        <h2><i class="fas fa-microscope"></i> <u>{{ $career->title }}</u></h4>
        <p>{{ $career->description }}</p>
    </div>

    <!-- Job Details -->
    <div class="row mt-4">
        <div class="col-lg-6">
            <div class="card info-card p-4">
                <h5 class="text-danger"><i class="fas fa-tasks"></i> Perform Tasks</h5>
                <span class="badge bg-danger">➤ Research & Development</span>
                <ul>
                    <li>➤ Design & prototype medical devices</li>
                    <li>➤ Conduct lab experiments & analyze results</li>
                    <li>➤ Develop testing protocols</li>
                </ul>
                <span class="badge bg-danger">➤ Clinical Translation</span>
                <ul>
                    <li>➤ Collaborate with clinicians</li>
                    <li>➤ Conduct clinical trials</li>
                    <li>➤ Obtain regulatory approvals</li>
                </ul>
                <span class="badge bg-danger">➤ Technical Leadership</span>
                <ul>
                    <li>➤ Lead project teams</li>
                    <li>➤ Manage project timelines & budgets</li>
                    <li>➤ Mentor junior engineers</li>
                </ul>
                <div class="progress mt-2">
                    <div class="progress-bar bg-danger" style="width: 65%"></div>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card info-card p-4">
                <h5 class="text-primary"><i class="fas fa-tools"></i> Required Skills</h5>
                <span class="badge bg-primary">➤ Technical</span>
                <ul>
                    <li>➤ <a href="https://dev.triz.co.in/lms/skill_library/1/show" target="_blank">Helicopter Aerodynamics Structures and Systems Principles Application</a></li>
                    <li>➤ Biomaterials & Tissue Engineering</li>
                    <li>➤ Medical Device Manufacturing</li>
                    <li>➤ Electronics & Signal Processing</li>
                    <li>➤ Cell Culture Techniques</li>
                </ul>
                <span class="badge bg-primary">➤ Analytical</span>
                <ul>
                    <li>➤ Data Analysis & Interpretation</li>
                    <li>➤ Mathematical Modeling of Biological Systems</li>
                    <li>➤ Critical Thinking & Problem-Solving</li>
                </ul>
                <span class="badge bg-primary">➤ Communication</span>
                <ul>
                    <li>➤ Technical Writing</li>
                    <li>➤ Team Collaboration</li>
                    <li>➤ Presentation Skills</li>
                </ul>
                <div class="progress mt-2">
                    <div class="progress-bar bg-primary" style="width: 60%"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Required Abilities & Tasks -->
    <div class="row mt-4">
        <div class="col-lg-6">
            <div class="card info-card p-4">
                <h5 class="text-success"><i class="fas fa-book"></i> Required Knowledge</h5>
                <span class="badge bg-success">➤ Biological Sciences</span>
                <ul>
                    <li>➤ Cellular Biology & Genetics</li>
                    <li>➤ Microbiology & Pathology</li>
                    <li>➤ Physiology</li>
                </ul>
                <span class="badge bg-success">➤ Engineering Principles</span>
                <ul>
                    <li>➤ Fluid Mechanics & Thermodynamics</li>
                    <li>➤ Mechanics of Materials</li>
                    <li>➤ Signal Processing & Control Systems</li>
                </ul>
                <div class="progress mt-2">
                    <div class="progress-bar bg-success" style="width: 80%"></div>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card info-card p-4">
                <h5 class="text-warning"><i class="fas fa-lightbulb"></i> Required Abilities</h5>
                <ul>
                    <li>➤ Creativity – Conceptualizing novel designs</li>
                    <li>➤ Adaptability – Keeping up with evolving technologies</li>
                    <li>➤ Attention to Detail – Ensuring design accuracy</li>
                    <li>➤ Teamwork – Collaborating on projects</li>
                </ul>
                <div class="progress mt-2">
                    <div class="progress-bar bg-warning progress-bar-striped" style="width: 50%"></div>
                </div>
            </div>
        </div>
    </div>

</div>
    </div>
</div>

@include('includes.footerJs')
@include('includes.footer')
