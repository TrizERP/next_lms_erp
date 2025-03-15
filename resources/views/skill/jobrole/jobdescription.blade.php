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
        <h4>{{ $career->track }}</h3>
        <h2><i class="fas fa-microscope"></i> <u>{{ $career->jobrole }}</u></h4>
        <p>{{ $career->description }}</p>
    </div>

    <!-- Job Details -->
    <div class="row mt-4">
        <div class="col-lg-6">
            <div class="card info-card p-4">
                <h5 class="text-danger"><i class="fas fa-tasks"></i> Perform Tasks</h5>
                <span class="badge bg-danger">➤ Advance nursing practices</span>
                <ul>
                    <li>➤ Establish funding models for new care models or nursing clinical services</li>
                    <li>➤ Implement new practices and care models or services in accordance to frameworks that include considerations for facilities, resourcing, funding, training, processes, outcomes and regulations</li>
                    <li>➤ Measure outcomes of advanced or specialised nursing practices to assess and improve new interventions</li>
                </ul>
                <span class="badge bg-danger">➤ Oversee nursing clinical care delivery</span>
                <ul>
                    <li>➤ Establish frameworks for hospital-to-community models of care that include funding, manpower, pilots, outcome measurements and implementation</li>
                    <li>➤ Oversee nursing practices and care delivery outcomes</li>
                    <li>➤ Establish frameworks for evidence-based nursing</li>
                    <li>➤ Develop strategies to empower and engage patients and caregivers</li>
                    <li>➤ Promote inter-professional collaboration in care delivery</li>
                </ul>
                <span class="badge bg-danger">➤ Drive nursing quality and patient safety</span>
                <ul>
                    <li>➤ Lead multi-disciplinary work groups to improve patient and staff safety</li>
                    <li>➤ Establish frameworks for critical communications</li>
                    <li>➤ Manage adverse events according to organisational frameworks</li>
                    <li>➤ Establish an open culture to facilitate quality and patient safety development</li>
                    <li>➤ Establish nursing infection prevention and control policies and procedures</li>
                    <li>➤ Lead nursing to achieve local and international accreditations</li>
                    <li>➤ Guide nursing clinical audits</li>
                    <li>➤ Adopt new technology and electronic tools and devices for better quality and patient safety outcomes</li>
                </ul>
                <div class="progress mt-2">
                    <div class="progress-bar bg-danger" style="width: 65%"></div>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card info-card p-4">
                <h5 class="text-primary"><i class="fas fa-tools"></i> Required Skills</h5>
                <span class="badge bg-primary">➤ Technical Skills</span>
                    <ul>
                        <li>➤ 
<a href="{{url('lms/skill_library/272/show')}}">Nursing Productivity and Innovation <span class="badge bg-danger">Level 5</span></a>

<a href="#" class="open-modal" data-toggle="modal" data-target="#dynamicModalKnowledge">&nbsp;<span class="badge bg-info">Knowledge</span></a>

<a href="#" class="open-modal" data-toggle="modal" data-target="#dynamicModalAbility">&nbsp;<span class="badge bg-warning">Ability</span></a>
</li>
                        <li>➤ Medication Management in Nursing</li>
                        <li>➤ Patient Care Delivery in Nursing <span class="badge bg-danger">Level 4</span>&nbsp;<span class="badge bg-info">Knowledge</span>&nbsp;<span class="badge bg-warning">Ability</span></li>
                        <li>➤ Respiratory Care in Nursing</li>
                    </ul>

                    <span class="badge bg-primary">➤ Functional Skills</span>
                    <ul>
                        <li>➤ Learning Needs Analysis <span class="badge bg-danger">Level 5</span>&nbsp;<span class="badge bg-info">Knowledge</span>&nbsp;<span class="badge bg-warning">Ability</span></li>
                        <li>➤ Nursing Research and Statistics</li>
                    </ul>

                    <span class="badge bg-primary">➤ Soft Skills (Behavioral & Interpersonal Skills)</span>
                    <ul>
                        <li>➤ Inter-professional Collaboration <span class="badge bg-danger">Level 4</span>&nbsp;<span class="badge bg-info">Knowledge</span>&nbsp;<span class="badge bg-warning">Ability</span></li>
                        <li>➤ Effective Communication in Nursing</li>
                    </ul>

                    <span class="badge bg-primary">➤ Cognitive & Thinking Skills</span>
                    <ul>
                        <li>➤ Sense Making</li>
                        <li>➤ Decision Making</li>
                        <li>➤ Transdisciplinary Thinking</li>
                    </ul>

                    <span class="badge bg-primary">➤ Leadership & Management Skills</span>
                    <ul>
                        <li>➤ Change Management</li>
                        <li>➤ Clinical Teaching and Supervision</li>
                        <li>➤ Emergency Response and Crisis Management</li>
                        <li>➤ Department Financial Management</li>
                        <li>➤ Health Education Programme Development and Implementation</li>
                        <li>➤ Performance Management for Nursing</li>
                        <li>➤ Quality Improvement and Safe Practices</li>
                        <li>➤ Service Quality Management</li>
                        <li>➤ Clinical Services Development</li>
                        <li>➤ Strategy Management</li>
                        <li>➤ Developing People</li>
                    </ul>

                    <span class="badge bg-primary">➤ Compliance & Regulatory Skills</span>
                    <ul>
                        <li>➤ Clinical Governance</li>
                        <li>➤ Infection Prevention and Control in Nursing Practice</li>
                        <li>➤ Workplace Safety and Health</li>
                    </ul>
                <div class="progress mt-2">
                    <div class="progress-bar bg-primary" style="width: 60%"></div>
                </div>
            </div>
        </div>
    </div>
<!--
    
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
-->
</div>
    </div>
</div>
<!-- Dynamic Modal -->
<div class="modal fade" id="dynamicModalKnowledge" tabindex="-1" role="dialog" aria-labelledby="dynamicModalLabel" aria-hidden="true">
  <div class="modal-dialog" role="document" style="max-width: 600px;">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modalTitle">Required Knowledge</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <div class='card info-card p-4'>
                    <ul>
                        <li>➤ Innovation evidence, legal and intellectual property rights</li>
                        <li>➤ Clinical information technology analysis frameworks</li>
                        <li>➤ Best practices on the selection and implementation of technology to drive improved quality of care and operational efficiencies</li>
                        <li>➤ Ethical and social issues in nursing informatics and consumer informatics</li>
                        <li>➤ Informatics applications for telehealth, consumer health and community-based care</li>
                        <li>➤ Metrics to measure effectiveness of new technologies in the delivery of patient care</li>
                        <li>➤ Manpower optimisation through adoption of new technologies</li>
                    </ul>
                </div>
      </div>
    </div>
  </div>
</div>
<!-- Dynamic Modal -->
<div class="modal fade" id="dynamicModalAbility" tabindex="-1" role="dialog" aria-labelledby="dynamicModalLabel" aria-hidden="true">
  <div class="modal-dialog" role="document" style="max-width: 600px;">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modalTitle">Required Ability</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <div class="card info-card p-4">
                <ul>
                    <li>➤ Promote best practices to adopt new technology towards quality improvement and increase productivity</li>
                    <li>➤ Mitigate challenges in the adoption of new technology</li>
                    <li>➤ Facilitate transition of care in the use of new nursing informatics and medical scientific technology</li>
                    <li>➤ Evaluate data integrity with the adoption of new technologies</li>
                    <li>➤ Develop work plans for the implementation of nursing informatics and medical scientific technology by analysing the practicality, feasibility and risks of new technology adoption</li>
                </ul>
            </div>
      </div>
    </div>
  </div>
</div>
@include('includes.footerJs')
@include('includes.footer')
