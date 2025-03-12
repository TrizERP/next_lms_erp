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
        <h2 class="text-center">Skills Framework for Aerospace</h2>
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
                    <td>Apply principles of theory of flight, airframe structures and systems towards maintenance, repair, overhaul or manufacturing of helicopter structures and systems</td>
                    <td>Apply principles of theory of flight, airframe structures and systems towards maintenance, repair, overhaul or manufacturing of helicopter structures and systems</td>
                </tr>
                <tr>
                    <th><b>Knowledge</b></th>
                    <td>
                        <ul>
                            <li>Tools & programming for Big Data</li>
                            <li>Data quality & modeling</li>
                            <li>Principles of theory of flight</li>
                            <li>Principles of blade tracking and vibration analysis</li>
                            <li>General concepts of transmissions and airframe structures</li>
                            <li>Principles of air supply and air conditioning (ATA 21)</li>
                            <li>Functions of avionic, instrument, landing gear and lighting systems (ATA 22 - 23, 31 - 34)</li>
                            <li>Aircraft electrical power systems, equipment and cabin furnishings and fire detection methods (ATA 24 - 26)</li>
                            <li>Types of fuel, hydraulic power and ice and rain protection systems (ATA 28 - 30)</li>
                            <li>Types of pneumatic/vacuum systems (ATA 36)</li>
                            <li>Specification of water and waste systems (ATA 38)</li>
                            <li>Concepts of integrated modular avionics (ATA 42)</li>
                            <li>On-board maintenance and information systems (ATA 45 - 46)</li>
                        </ul>
                    </td>
                    <td>
                        <ul>
                            <li>Concepts of rotary wing aerodynamics and flight control system design and operation.</li>
<li>Principles of blade tracking and vibration analysis.</li>
<li>Installation, construction, and assembly methods for transmissions and airframe structures.</li>
<li>Principles of air supply and air conditioning.</li>
<li>Construction and application of avionic, instrument, landing gear, and lighting systems.</li>
<li>Installation and handling requirements of power systems, equipment, furnishings, and fire protection systems.</li>
<li>Operation of fuel, hydraulic power, and ice and rain protection systems.</li>
<li>Layout and operation of pneumatic/vacuum systems.</li>
<li>Layout and operation of water and waste systems.</li>
<li>Functions of Integrated Modular Avionics (IMA) system.</li>
<li>Functions of on-board maintenance and information systems.</li>
                        </ul>
                    </td>
                </tr>
                <tr>
                    <td><b>Abilities</b></td>
                    <td>
                        <ul>
                            <li>Apply theory of flight to assess torque reaction, directional control, and ground effect.</li> <li>Describe the function and operation of flight control systems.</li> <li>Explain techniques for rotor tracking, alignment, and balancing.</li> <li>Explain characteristics of transmissions and airframe structures.</li> <li>Explain characteristics of air conditioning systems.</li> <li>Describe the operating characteristics of electro-avionic systems.</li> <li>Describe the operating characteristics of electro-mechanical systems.</li> <li>Explain the operation of flight control systems.</li> <li>Identify functions that may be integrated in the Integrated Modular Avionic (IMA) modules.</li> <li>Describe the functions of on-board maintenance and information systems.</li>
                        </ul>
                    </td>
                    <td>
                        <ul>
                            <li>Evaluate the operation and effects of rotary wing aerodynamics.</li>
                            <li>Guide the design, operation, and construction of flight control systems.</li>
                            <li>Recommend methods for vibration reduction and ground resonance.</li>
                            <li>Define work instructions for installation and construction of transmissions and airframe structures.</li>
                            <li>Evaluate the operation of air conditioning systems.</li>
                            <li>Discuss the layout and operation of electro-avionic systems.</li>
                            <li>Discuss the layout and operation of electro-mechanical airframe and powerplant systems.</li>
                            <li>Optimise the operation of flight control systems.</li>
                            <li>Define the function of various Integrated Modular Avionic (IMA) modules.</li>
                            <li>Guide the configuration of on-board maintenance and information systems.</li>
                        </ul>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>




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
