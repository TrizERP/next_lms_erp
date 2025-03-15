@extends('layout')
@section('container')
<style>
    .ActiveTab{
        color : #2f99de;
        border-bottom : 2px solid #2f99de;
    }
td ul {
    list-style: none !important;
    padding-left: 0 !important;
}

td ul li::before {
    content: "• ";
    color: black;
    font-weight: bold;
    display: inline-block;
    width: 1em;
}
.category{background-color: #90D5FF}
</style>
<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">View Skill</h4>
            </div>
        </div>

       <!-- header card starts  -->
       <div class="card shadow-sm my-3 tab-bar-card">
            <div class="card-body p-0">
                <div class="row">
                    <div class="tab-bar-accessory-left col-auto ml-2 mr-2 mt-2">
                        <i class="mdi mdi-star-outline pr-2" style="font-size:45px"></i>
                    </div>
                    <div class="col-auto">
                        <div class="row tab-bar-entity-name">
                        Skill
                        ( {{$data['editData']['category']}} @if($data['editData']['sub_category'])> {{$data['editData']['sub_category']}} @endif
                        )
                        </div>
                        <div class="row">
                            {{$data['editData']['title']}}
                        </div>
                    </div>
                    <div class="tab-bar-accessory-right col-auto ml-auto ml-xl-0 order-xl-1">
                        <div class="dropdown">
                        <button type="button" class="btn btn-sm btn-outline-dark dropdown-toggle" data-toggle="dropdown" data-boundary="viewport" aria-haspopup="true" aria-expanded="false" style="padding-right:30px !important">
                        Actions
                        </button>
                        <div class="dropdown-menu dropdown-menu-right">
                            <a class="dropdown-item" href="{{route('skill_library.edit',[$data['editData']['id']])}}">
                                <i class="fa fa-edit"></i> Edit skill
                            </a><a class="dropdown-item no-target-icon" onclick="printDiv('DetailsDiv')">
                                <i class="fa fa-download"></i> Export PDF
                            </a><a class="dropdown-item" href="{{route('skill_library.destroy',[$data['editData']['id']])}}">
                                <i class="fa fa-trash"></i> Delete skill
                            </a>
                        </div>
                        </div>
                    </div>
                    <div class="tab-bar-main col-12 col-xl mt-2 mt-xl-0">
                        <div id="dashboard_tabs" class="tab-bar mdc-tab-bar" data-container-id="dashboard_tab_content" role="tablist">
                            <div class="mdc-tab-scroller">
                                    <div class="mdc-tab-scroller__scroll-area mdc-tab-scroller__scroll-area--scroll" style="margin-bottom: 0px;">
                                    <!-- <a class="mdc-tab mdc-ripple-upgraded ActiveTab" role="tab" id="mdc-tab-3"><span class="mdc-tab__content"><span class="mdc-tab__text-label">About</span></span><span class="mdc-tab-indicator"><span class="mdc-tab-indicator__content mdc-tab-indicator__content--underline"></span></span></a> -->
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            </div>
        <!-- header card end  -->
        
        <div class="card" id="DetailsDiv">
           <div class="abuotDiv">
                <table class="table table-bordered"  cellspacing="0"  border="1">
                    <tr>
                        <th width="15%"><strong>Sector</strong></th>
                        <th width="85%">{{$data['editData']['sector']}}</th>
                    </tr>
                    <tr>
                        <th><strong>Sub-Sector</strong></th>
                        <th>{{$data['editData']['tsc_ccs_category']}}</th>
                    </tr>
                    <tr>
                        <th style="background-color: #90EE90"><strong><h4>Skill Name</h4></strong></th>
                        <th style="background-color: #90EE90"><h4>{{$data['editData']['title']}}</h4></th>
                    </tr>
                    <tr>
                        <th><strong>Category</strong></th>
                        <th>{{$data['editData']['category']}}</th>
                    </tr>
                    <tr>
                        <th><strong>Sub Category</strong></th>
                        <th>{{$data['editData']['sub_category']}}</th>
                    </tr>
                    <tr>
                        <th><strong>Description</strong></th>
                        <th>{{$data['editData']['description']}}</th>
                    </tr>
                </table>
           </div>

<div class="container mt-5">
        <h2 class="text-center">Skills Framework for {{$data['editData']['title']}}</h2>
        <h4 class="text-center">Technical Skills & Competencies (TSC) Reference Document</h4>

        <table class="table table-bordered">
            <tbody>
                <!--<tr >
                    <th>TSC Category</th>
                    <td colspan="6">Aerospace and Engineering Fundamentals</td>
                </tr>
                <tr >
                    <th>TSC</th>
                    <td colspan="6">Helicopter Aerodynamics Structures and Systems Principles Application</td>
                </tr>
                <tr>
                    <th>TSC Description</th>
                    <td colspan="6">Apply and use principles of helicopter aerodynamics, structures and systems for maintenance, repair, overhaul or manufacturing in accordance with the original equipment manufacturer (OEM) manuals and organisational procedures</td>
                </tr>-->
                <tr class="category">
                    <th>Proficiency Levels</th>
                    <th>Level 3</th>
                    <th>Level 4</th>
                </tr>
                <tr>
                    <th>TSC Proficiency Description</th>
                    <td>Conduct holistic case history for routine cases independently. Seek guidance for case history taking for complex cases</td>
                    <td>Conduct holistic case history taking for routine and complex cases independently. Provide guidance to junior therapists where necessary</td>
                </tr>
                <tr>
                    <th><b>Knowledge</b></th>
                    <td>
                        <ul>
                            <li>World Health Organisation's International Classification of Functioning, Disability and Health Framework (WHO ICF framework)</li>
                            <li>Principles of effective interviewing</li>
                            <li>Relevant elements in the clients' medical or therapy history</li>
                            <li>Code of conduct and other relevant ethical and legislative guidelines</li>
                            <li>Difficulties or considerations that must be taken into account for different target groups</li>
                            <li>Modifications to the traditional interview process for different client groups</li>
                            <li>Organisational practice guidelines of case documentation</li>
                            <li>Workplace safety and health measures and workplace violence policies for handling difficult or potentially violent clients</li>
                        </ul>
                    </td>
                    <td style="vertical-align:top">
                        <ul>
                            <li>Multidisciplinary approaches to case history taking for cases that require treatment from multiple professions</li>
                            <li>Models and/or frameworks of history taking</li>
                        </ul>
                    </td>
                </tr>
                <tr>
                    <td><b>Abilities</b></td>
                    <td>
                        <ul>
                            <li>Identify purpose of interviews and structure the interviews to achieve required outcomes</li>
                            <li>Gather information and develop client profiles</li>
                            <li>Determine if additional assessments of performance skills are required under guidance</li>
                            <li>Identify client strengths and potential problem areas</li>
                            <li>Establish priorities for interventions as part of the client interviews</li>
                            <li>Review medical and academic records of clients</li>
                            <li>Document interview processes and findings appropriately</li>
                            <li>Identify client and/or caregiver concerns</li>
                            <li>Build trust and rapport with clients and caregivers</li>
                            <li>Adhere to the code of conduct and other ethical or legislative guidelines in handling client information from the interview</li>
                            <li>Apply workplace violence procedures or workplace safety and health measures to protect against violent clients</li>
                            <li>Maintain professional code of conduct and/or confidentiality</li>
                            <li>Show sensitivity to cultural background and practices of clients and adjust accordingly</li>
                            <li>Identify possible client conditions that may require modification to the interview structures</li>
                            <li>Identify suitable adjuncts for interviews where necessary</li>
                            <li>Reflect on personal effectiveness in performing the interviews</li>
                            <li>Identify areas for improvement or client-related limitations that need to be addressed</li>
                            <li>Identify the most reliable source of information to establish the clinical history in clients</li>
                            <li>Identify gaps in information and seek to fill these gaps</li>
                            <li>Hypothesize potential problem areas</li>
                        </ul>
                    </td>
                    <td style="vertical-align:top">
                        <ul>
                            <li>Integrate knowledge gathered by other professionals to form a holistic clinical impression of the clients</li>
                            <li>Collaborate with other professionals when validating interview findings for complex cases</li>
                            <li>Perform case history taking for complex cases requiring more in-depth knowledge or that have been escalated</li>
                        </ul>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

<!-- RANGE OF APPLICATION -->

<div class="container mt-5">
    <h2 class="text-center mb-4">Proficiency Level wise Range of Application</h2>
    
    <ul class="nav nav-tabs" id="testPlanningTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="test2-tab" data-bs-toggle="tab" data-bs-target="#test2" type="button" role="tab">
                Level 2
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="test3-tab" data-bs-toggle="tab" data-bs-target="#test3" type="button" role="tab">
                Level 3
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="test4-tab" data-bs-toggle="tab" data-bs-target="#test4" type="button" role="tab">
                Level 4
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="test5-tab" data-bs-toggle="tab" data-bs-target="#test5" type="button" role="tab">
                Level 5
            </button>
        </li>
    </ul>

    <div class="tab-content mt-3" id="testPlanningContent">
        <div class="tab-pane fade show active" id="test2" role="tabpanel">
            <div class="card">
                <div class="card-body">
                    <p class="card-title"><strong>Proficiency Description:</strong> Identify and document tools, testware, resources, and processes</p><hr>
                    <p class="card-text"><strong>Range of Application:</strong> Test planning may be applied but is not limited to:</p>
                    <ul>
                        <li>- Stress Tests</li>
                        <li>- Load Tests</li>
                        <li>- Volume Tests</li>
                        <li>- Baseline Tests</li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="tab-pane fade" id="test3" role="tabpanel">
            <div class="card">
                <div class="card-body">
                    <p class="card-title"><strong>Proficiency Description:</strong> Determine requirements and develop a phase test plan</p><hr>
                    <p class="card-text"><strong>Range of Application:</strong> Test planning may be applied but is not limited to:</p>
                    <ul>
                        <li>- Stress Tests</li>
                        <li>- Load Tests</li>
                        <li>- Volume Tests</li>
                        <li>- Baseline Tests</li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="tab-pane fade" id="test4" role="tabpanel">
            <div class="card">
                <div class="card-body">
                    <p class="card-title"><strong>Proficiency Description:</strong> Define testing objectives and design a master test plan</p><hr>
                    <p class="card-text"><strong>Range of Application:</strong> Test planning may be applied but is not limited to:</p>
                    <ul>
                        <li>- Stress Tests</li>
                        <li>- Load Tests</li>
                        <li>- Volume Tests</li>
                        <li>- Baseline Tests</li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="tab-pane fade" id="test5" role="tabpanel">
            <div class="card">
                <div class="card-body">
                    <p class="card-title"><strong>Proficiency Description:</strong> Develop a test strategy and establish testing policies</p><hr>
                    <p class="card-text"><strong>Range of Application:</strong> Test planning may be applied but is not limited to:</p>
                    <ul>
                        <li>- Stress Tests</li>
                        <li>- Load Tests</li>
                        <li>- Volume Tests</li>
                        <li>- Baseline Tests</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<!-- RANGE OF APPLICATION -->
        </div>
    </div>
</div>

@include('includes.footerJs')
<script>
    function printDiv(divName) {
        var divToPrint = document.getElementById(divName);
        var popupWin = window.open('', '_blank', 'width=300,height=300');
        popupWin.document.open();
        popupWin.document.write('<html>');
        
        popupWin.document.write('<body onload="window.print()">' + divToPrint.innerHTML + '</html>');
        popupWin.document.close();
    }
</script>

@include('includes.footer')
@endsection
