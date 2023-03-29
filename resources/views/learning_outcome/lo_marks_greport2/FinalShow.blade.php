<meta content="utf-8" http-equiv="encoding">
<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/css/bootstrap.min.css" integrity="sha384-Gn5384xqQ1aoWXA+058RXPxPg6fy4IWvTNh0E263XmFcJlSAwiGgFAW/dAiS6JXm" crossorigin="anonymous">
<link href="{{ asset("/plugins/bower_components/report/css/aos.css") }}" rel="stylesheet" type="text/css" />
<link href="{{ asset("/plugins/bower_components/report/css/styleReport.css") }}" rel="stylesheet" type="text/css" />

<style>
    .highcharts-figure, .highcharts-data-table table {
    min-width: 360px; 
    max-width: 800px;
    margin: 1em auto;
}

.highcharts-data-table table {
	font-family: Verdana, sans-serif;
	border-collapse: collapse;
	border: 1px solid #EBEBEB;
	margin: 10px auto;
	text-align: center;
	width: 100%;
	max-width: 500px;
}
.highcharts-data-table caption {
    padding: 1em 0;
    font-size: 1.2em;
    color: #555;
}
.highcharts-data-table th {
	font-weight: 600;
    padding: 0.5em;
}
.highcharts-data-table td, .highcharts-data-table th, .highcharts-data-table caption {
    padding: 0.5em;
}
.highcharts-data-table thead tr, .highcharts-data-table tr:nth-child(even) {
    background: #f8f8f8;
}
.highcharts-data-table tr:hover {
    background: #f1f7ff;
}
</style>
<style>
    .node {
  cursor: pointer !important;
}

.node:hover {
  stroke: #000  !important;
  stroke-width: 1.5px  !important;
}

.node--leaf {
  fill: white  !important;
}

.label {
  font: 11px "Helvetica Neue", Helvetica, Arial, sans-serif  !important;
  text-anchor: middle  !important;
  text-shadow: 0 1px 0 #fff, 1px 0 0 #fff, -1px 0 0 #fff, 0 -1px 0 #fff  !important;
}

.label,
.node--root,
.node--leaf {
  pointer-events: none  !important;
}

    </style>
<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row">
            <div class="w-100">
                <div class="w-100">
                    <header class="header">
                        <div class="container">
                            <div class="row">
                                <div class="col-lg-4 col-md-6">
                                    <div class="student-details">
                                        <div class="student-dp">
                                            {{-- <img src="images/student-dp.png" alt=""> --}}
                                            <?php 
                                                $image_path =  asset("/storage/student/");
                                                $image_path .= "/".$data['data']['student_info']['image'];
                                            ?>
                                            <img src="{{$image_path}}"
                                                alt="" height="80px" width="80px">
                                        </div>
                                        
                                        <?php 
                                        // echo ('<pre>');print_r($data);exit;
                                        ?>
                                        <table class="profile-info">
                                            <tbody>
                                                <tr>
                                                    <td width="90"><strong>Name :-</strong></td>
                                                    <td>{{ $data['data']['student_info']['student_name'] }}</td>
                                                </tr>
                                                <tr>
                                                    <td><strong>Reg No :-</strong></td>
                                                    <td>{{ $data['data']['student_info']['enrollment_no'] }}</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                                <div class="col-sm-6 col-lg-4 col-md-6">
                                    <div class="student-details">
                                        <table class="profile-info">
                                            <tbody>
                                                <tr>
                                                    <td width="140"><strong>Admission No. :-</strong></td>
                                                    <td>{{ $data['data']['student_info']['enrollment_no'] }}</td>
                                                </tr>
                                                <tr>
                                                    <td><strong>DISE :-</strong></td>
                                                    <td>-</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                                <div class="col-sm-6 col-lg-4 col-md-6">
                                    <div class="student-details">
                                        <table class="profile-info">
                                            <tbody>
                                                <tr>
                                                    <td width="120"><strong>Adhaar No. :-</strong> </td>
                                                    <td>-</td>
                                                </tr>
                                                <tr>
                                                    <td><strong>District :-</strong></td>
                                                    <td>{{ $data['data']['student_info']['city'] }}</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-3">
                                    <div class="select-box">
                                        <h5>Select Exam</h5>
                                        <select name="exam_type" onchange="redirectthis();">
                                            <option value="" >--Select--</option>
                                            @foreach ($data['data']['all_dd']['exam_type_dd'] as $item=>$val)
                                            @php
                                            if ($data['data']['selected_exam_type'] == $item){
                                            $selected = "selected=selected";
                                            }else{
                                            $selected = "";
                                            }
                                            @endphp

                                            <option {{ $selected }} value="{{ $item }}">{{ $val }}</option>
                                            @endforeach
                                        </select>
                                        {{-- <select name="exam">
                                            <option value="PAT 1">PAT 1</option>
                                            <option value="PAT 2">PAT 2</option>
                                            <option value="PAT 3">PAT 3</option>
                                            <option value="PAT 4">PAT 4</option>
                                        </select> --}}
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    {{-- <div class="select-box">
                                        <h5>Class Division</h5>
                                        <select name="exam">
                                            <option value="PAT 1">7/A</option>
                                            <option value="PAT 2">7/B</option>
                                            <option value="PAT 3">8/A</option>
                                            <option value="PAT 4">8/B</option>
                                        </select>
                                    </div> --}}
                                </div>
                                <div class="col-md-6">
                                    <div class="subject-list">
                                        <h5>Subjects</h5>
                                        <ul>
                                            <?php
                                            $color_arr = array(
                                                "red-btn","navy-btn","yellow-btn","pink-btn","blue-btn"
                                            );
                                            $i = 0;
                                             ?>
                                             @foreach ($data['data']['subject_dd'] as $item=>$val)
                                             <li><a href="#" class={{$color_arr[$i]}}>{{$val}}</a></li>     
                                             <?php $i++; ?>
                                             @endforeach
                                            {{-- <li><a href="#" class="red-btn">Gujarati</a></li>
                                            <li><a href="#" class="navy-btn">Maths</a></li>
                                            <li><a href="#" class="yellow-btn">Science</a></li>
                                            <li><a href="#" class="pink-btn">English</a></li>
                                            <li><a href="#" class="blue-btn">S.S.</a></li> --}}
                                        </ul>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </header>

                    <div class="container-fluid" data-aos="fade-up" data-aos-duration="2000" >
                        <div class="row justify-content-center">
                            <div class="col-md-10 text-center">
                                <h2 class="std-details">
                                    <strong>{{ $data['data']['student_info']['student_name'] }}</strong> has scored 
                                    {{$data['data']['total_subject_marks']}}
                                    in Learning Outcome
                                </h2>
                            </div>
                        </div>
                    </div>

                    {{-- <svg width="500" height="500"></svg> --}}
                    <div class="subject-report py-4" data-aos="fade-up" data-aos-duration="2000" >
                        <div class="container">
                            <div class="row justify-content-center">
                                <div class="col-md-12">
                                    <center> <svg width="500" height="500"></svg> </center>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Subject Report Start -->
                    <div class="subject-report py-4" data-aos="fade-up" data-aos-duration="2000" >
                        <div class="container">
                            <div class="row justify-content-center">
                                <div class="col-md-12">
                                    <div class="school-heading">
                                        <h2>Subject Report</h2>
                                    </div>
                                   
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Subject Report End -->

                    <!-- Subject Graph Start -->
                    <div class="sub-graph mb-5" data-aos="fade-up" data-aos-duration="2000" >
                        <div class="container">
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="school-heading">
                                        <h2>Subject Report</h2>
                                        <p>Lorem ipsum dolor sit amet, consectetuer adipiscing elit, sed diam nonummy
                                            nibh euismod tincidunt ut laoreet dolore magna aliquam erat volutpat.</p>
                                    </div>
                                </div>
                                <div class="col-md-8">
                                    <div id="chart-container">FusionCharts XT will load here!</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Subject Graph Start -->

                    <!-- Learning Outcome Start -->
                    <div class="learning-outcome py-5" data-aos="fade-up" data-aos-duration="2000">
                        <div class="container">
                            <div class="row">
                                <div class="col-md-3">
                                    <div class="school-heading">
                                        <h2>Learning Outcome</h2>
                                    </div>
                                </div>
                                <div class="col-md-8 text-center">
                                    <div class="subject-list">
                                        <ul>
                                            <?php $i = 0; ?>
                                            @foreach ($data['data']['subject_dd'] as $item=>$val)
                                            <li><a href="#" class="{{$color_arr[$i]}}">{{$val}}</a></li>
                                            <?php $i++; ?>
                                            @endforeach

                                            {{-- <li><a href="#" class="red-btn">L.O. -1</a></li> --}}
                                            {{-- <li><a href="#" class="navy-btn">L.O. -2</a></li>
                                            <li><a href="#" class="yellow-btn">L.O. -3</a></li>
                                            <li><a href="#" class="pink-btn">L.O. -4</a></li>
                                            <li><a href="#" class="blue-btn">L.O. -5</a></li> --}}
                                        </ul>
                                    </div>
                                </div>
                                <div class="col-md-1">
                                    {{-- <div class="select-box">
                                        <h5 class="text-dark">Select Subject</h5>
                                        <select name="subject">
                                            <option value="PAT 1">Science</option>
                                            <option value="PAT 2">Maths</option>
                                            <option value="PAT 3">Gujarati</option>
                                            <option value="PAT 4">English</option>
                                        </select>
                                    </div> --}}
                                </div>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-hover table-borderless student-table">
                                    <?php $i = 0; ?>
                                    @foreach ($data['data']['all_subject_lo'] as $subject=>$lo_arr)
                                    <tr>
                                        <th width="200">
                                            <div class="chap-btn {{$color_arr[$i]}}">{{$subject}}</div>
                                        </th>
                                        <td>
                                            <ul class="student-total-per">
                                                @foreach ($lo_arr as $lo=>$mar_ar)
                                                <li>
                                                    <div class="total-per-box">
                                                    <h5>{{$lo}}</h5>
                                                    <div class="total-per">{{ $mar_ar['PER'] }}%</div>
                                                    </div>
                                                    {{-- <div class="text-success">Archived</div> --}}
                                                </li>
                                                @endforeach
                                                {{-- <li>
                                                    <div class="total-per-box">
                                                        <h5>L.O. 1</h5>
                                                        <div class="total-per">100%</div>
                                                    </div>
                                                    <div class="text-success">Archived</div>
                                                </li>
                                                <li>
                                                    <div class="total-per-box">
                                                        <h5>L.O. 1</h5>
                                                        <div class="total-per">100%</div>
                                                    </div>
                                                    <div class="text-danger">Archived</div>
                                                </li> --}}
                                            </ul>
                                        </td>
                                    </tr>
                                    @endforeach
                                    

                                    {{-- <tr>
                                        <th width="200">
                                            <div class="chap-btn navy-btn">Chapter 1</div>
                                        </th>
                                        <td>
                                            <ul class="student-total-per">
                                                <li>
                                                    <div class="total-per-box">
                                                        <h5>L.O. 1</h5>
                                                        <div class="total-per">100%</div>
                                                    </div>
                                                    <div class="text-danger">Archived</div>
                                                </li>
                                                <li>
                                                    <div class="total-per-box">
                                                        <h5>L.O. 1</h5>
                                                        <div class="total-per">100%</div>
                                                    </div>
                                                    <div class="text-success">Archived</div>
                                                </li>
                                                <li>
                                                    <div class="total-per-box">
                                                        <h5>L.O. 1</h5>
                                                        <div class="total-per">100%</div>
                                                    </div>
                                                    <div class="text-success">Archived</div>
                                                </li>
                                            </ul>
                                        </td>
                                    </tr> --}}
                                </table>
                            </div>
                        </div>
                    </div>
                    <!-- Learning Outcome End -->
                </div>
                <div class="remedation-section container-fluid">
                    <div class="row">
                        <div class="col-md-12 col-lg-6 remedation" data-aos="fade-right" data-aos-duration="2000">
                            <div class="py-5 px-5">
                                <div class="school-heading">
                                    <h2>Remedation</h2>
                                </div>
                                <ul class="performance-receiving">
                                    <li>
                                        <h5>Practice Required</h5>
                                        <div class="stars text-right">
                                            <img src="{{ asset("/plugins/bower_components/report/images/star.svg") }}" height="20" alt="">
                                            <img src="{{ asset("/plugins/bower_components/report/images/star.svg") }}" height="20" alt="">
                                            <img src="{{ asset("/plugins/bower_components/report/images/star.svg") }}" height="20" alt="">
                                            <img src="{{ asset("/plugins/bower_components/report/images/star.svg") }}" height="20" alt="">
                                        </div>
                                    </li>
                                    <li>
                                        <h5>Intervention Required</h5>
                                        <div class="stars text-right">
                                            <img src="{{ asset("/plugins/bower_components/report/images/star.svg") }}" height="20" alt="">
                                            <img src="{{ asset("/plugins/bower_components/report/images/star.svg") }}" height="20" alt="">
                                            <img src="{{ asset("/plugins/bower_components/report/images/star.svg") }}" height="20" alt="">
                                            <img src="{{ asset("/plugins/bower_components/report/images/star.svg") }}" height="20" alt="">
                                        </div>
                                    </li>
                                    <li>
                                        <h5>Conceptual Clerity Required</h5>
                                        <div class="stars text-right">
                                            <img src="{{ asset("/plugins/bower_components/report/images/star.svg") }}" height="20" alt="">
                                            <img src="{{ asset("/plugins/bower_components/report/images/star.svg") }}" height="20" alt="">
                                            <img src="{{ asset("/plugins/bower_components/report/images/star.svg") }}" height="20" alt="">
                                            <img src="{{ asset("/plugins/bower_components/report/images/star.svg") }}" height="20" alt="">
                                        </div>
                                    </li>
                                </ul>
                
                                <ul class="performance-star text-center">
                                    <li>
                                        <h5>Best Performance</h5>
                                        <div class="stars text-center">
                                                <img src="{{ asset("/plugins/bower_components/report/images/star.svg") }}" height="20" alt="">
                                                <img src="{{ asset("/plugins/bower_components/report/images/star.svg") }}" height="20" alt="">
                                                <img src="{{ asset("/plugins/bower_components/report/images/star.svg") }}" height="20" alt="">
                                                <img src="{{ asset("/plugins/bower_components/report/images/star.svg") }}" height="20" alt="">
                                            </div>
                                    </li>
                                    <li>
                                        <h5>Best Performance</h5>
                                        <div class="stars text-center">
                                            <img src="{{ asset("/plugins/bower_components/report/images/star.svg") }}" height="20" alt="">
                                            <img src="{{ asset("/plugins/bower_components/report/images/star.svg") }}" height="20" alt="">
                                            <img src="{{ asset("/plugins/bower_components/report/images/star.svg") }}" height="20" alt="">
                                            <img src="{{ asset("/plugins/bower_components/report/images/star.svg") }}" height="20" alt="">
                                        </div>
                                    </li>
                                    <li>
                                        <h5>Best Performance</h5>
                                        <div class="stars text-center">
                                            <img src="{{ asset("/plugins/bower_components/report/images/star.svg") }}" height="20" alt="">
                                            <img src="{{ asset("/plugins/bower_components/report/images/star.svg") }}" height="20" alt="">
                                            <img src="{{ asset("/plugins/bower_components/report/images/star.svg") }}" height="20" alt="">
                                            <img src="{{ asset("/plugins/bower_components/report/images/star.svg") }}" height="20" alt="">
                                        </div>
                                    </li>
                                    <li>
                                        <h5>Best Performance</h5>
                                        <div class="stars text-center">
                                            <img src="{{ asset("/plugins/bower_components/report/images/star.svg") }}" height="20" alt="">
                                            <img src="{{ asset("/plugins/bower_components/report/images/star.svg") }}" height="20" alt="">
                                            <img src="{{ asset("/plugins/bower_components/report/images/star.svg") }}" height="20" alt="">
                                            <img src="{{ asset("/plugins/bower_components/report/images/star.svg") }}" height="20" alt="">
                                        </div>
                                    </li>
                                </ul>
                
                            </div>
                        </div>
                        <div class="col-md-12 col-lg-6 subject-analysis" data-aos="fade-left" data-aos-duration="2000">
                            <div class="py-5 px-5">
                                <div class="subject-list style-2">
                                    <h5>Subjects</h5>
                                    <ul class="nav nav-tabs" id="myTab" role="tablist">
                                        <li class="nav-item">
                                            <a class="nav-link active" id="tab-gujarati" data-toggle="tab" href="#gujarati" role="tab" aria-controls="gujarati" aria-selected="true">Gujarati</a>
                                        </li>
                                        <li class="nav-item">
                                            <a class="nav-link" id="tab-maths" data-toggle="tab" href="#maths" role="tab" aria-controls="maths" aria-selected="false">Maths</a>
                                        </li>
                                        <li class="nav-item">
                                            <a class="nav-link" id="tab-science" data-toggle="tab" href="#science" role="tab" aria-controls="science" aria-selected="false">Science</a>
                                        </li>
                                    </ul>
                                </div>
                                <div class="tab-content std-process" id="myTabContent">
                                    <div class="tab-pane fade show active" id="gujarati" role="tabpanel" aria-labelledby="tab-gujarati">
                                        <h5>Teacher Help</h5>
                                        <div class="progress mb-3">
                                            <div class="progress-bar progress-bar-striped bg-warning progress-bar-animated" role="progressbar" style="width: 75%" aria-valuenow="75" aria-valuemin="0" aria-valuemax="100"></div>
                                        </div>
                                        <h5>Parent Help</h5>
                                        <div class="progress mb-3">
                                            <div class="progress-bar progress-bar-striped bg-warning progress-bar-animated" role="progressbar" style="width: 50%" aria-valuenow="50" aria-valuemin="0" aria-valuemax="100"></div>
                                        </div>
                                        <h5>They can Do</h5>
                                        <div class="progress mb-3">
                                            <div class="progress-bar progress-bar-striped bg-warning progress-bar-animated" role="progressbar" style="width: 80%" aria-valuenow="80" aria-valuemin="0" aria-valuemax="100"></div>
                                        </div>
                                    </div>
                                    <div class="tab-pane fade" id="maths" role="tabpanel" aria-labelledby="tab-maths">Maths</div>
                                    <div class="tab-pane fade" id="science" role="tabpanel" aria-labelledby="tab-science">Science</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <footer class="footer school-footer" data-aos="fade-up" data-aos-duration="2000" >
                    <div class="top-footer">
                        <div class="container text-center">
                            <div class="top-footer-menu">
                                <h5>Top Students</h5>
                                <ul class="navbar navbar-nav">
                                    <li><a href="#" class="nav-item">Top Students</a></li>
                                    <li><a href="#" class="nav-item">Top Class</a></li>
                                    <li><a href="#" class="nav-item">Top School</a></li>
                                    <li><a href="#" class="nav-item">Top District</a></li>
                                </ul>
                            </div>
                            <div class="top-footer-menu">
                                <h5>Attended Team</h5>
                                <ul class="navbar navbar-nav">
                                    <li><a href="#" class="nav-item">Teacher</a></li>
                                    <li><a href="#" class="nav-item">CRC</a></li>
                                    <li><a href="#" class="nav-item">Head Master</a></li>
                                    <li><a href="#" class="nav-item">Admin</a></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <div class="bottom-footer py-3 text-center bg-white d-none">
                        <div class="container">
                            <div class="row">
                                <div class="col-md-12 col-lg-4 text-left text-sm-center">
                                    <p class="mb-0">2019 © Triz Innovation PVT LTD.</p>
                                </div>
                                <div class="col-md-12 col-lg-8 text-md-center">
                                    <ul class="footer-menu">
                                        <li><a href="#" class="nav-item">Dashboard</a></li>
                                        <li><a href="#" class="nav-item">School</a></li>
                                        <li><a href="#" class="nav-item">Student Academics</a></li>
                                        <li><a href="#" class="nav-item">Teachers</a></li>
                                        <li><a href="#" class="nav-item">Reports</a></li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </footer>
            </div>
        </div>
    </div>
</div>




@include('includes.footerJs')

{{-- <script src="https://cdn.datatables.net/1.10.19/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.10.19/js/dataTables.bootstrap.min.js"></script> --}}


<script src="{{ asset("/plugins/bower_components/report/js/popper.min.js") }}" type="text/javascript"></script>
<script src="{{ asset("/plugins/bower_components/report/js/bootstrap.min.js") }}" type="text/javascript"></script>
<script src="{{ asset("/plugins/bower_components/report/js/school.js") }}" type="text/javascript"></script>
<script src="{{ asset("/plugins/bower_components/report/js/fusioncharts.js") }}" type="text/javascript"></script>
<script src="{{ asset("/plugins/bower_components/report/js/fusioncharts.theme.fusion.js") }}" type="text/javascript">
</script>
<script src="{{ asset("/plugins/bower_components/report/js/fusioncharts.widgets.js") }}" type="text/javascript"></script>

<script src="//code.highcharts.com/highcharts.js"></script>
<script src="//code.highcharts.com/modules/series-label.js"></script>
<script src="//code.highcharts.com/modules/exporting.js"></script>
<script src="//code.highcharts.com/modules/export-data.js"></script>
<script src="//code.highcharts.com/modules/accessibility.js"></script>
<script src="{{ asset("/plugins/bower_components/report/js/aos.js") }}"></script>
{{-- <script src="https://cdnjs.cloudflare.com/ajax/libs/d3/4.13.0/d3.min.js" charset="utf-8"></script> --}}
<script src="https://cdnjs.cloudflare.com/ajax/libs/d3/4.13.0/d3.min.js"></script>

<script>

var svg = d3.select("svg"),
    margin = 20,
    diameter = +svg.attr("width"),
    g = svg.append("g").attr("transform", "translate(" + diameter / 2 + "," + diameter / 2 + ")");

var color = d3.scaleLinear()
    .domain([-1, 5])
    .range(["hsl(152,80%,80%)", "hsl(228,30%,40%)"])
    .interpolate(d3.interpolateHcl);

var pack = d3.pack()
    .size([diameter - margin, diameter - margin])
    .padding(2);

d3.json("<?php echo asset('storage/data.json'); ?>", function(error, root) {
// d3.json(dataset, function(error, root) {
  if (error) throw error;

  root = d3.hierarchy(root)
      .sum(function(d) { return d.size; })
      .sort(function(a, b) { return b.value - a.value; });

  var focus = root,
      nodes = pack(root).descendants(),
      view;

  var circle = g.selectAll("circle")
    .data(nodes)
    .enter().append("circle")
      .attr("class", function(d) { return d.parent ? d.children ? "node" : "node node--leaf" : "node node--root"; })
      .style("fill", function(d) { return d.children ? color(d.depth) : null; })
      .on("click", function(d) { if (focus !== d) zoom(d), d3.event.stopPropagation(); });

  var text = g.selectAll("text")
    .data(nodes)
    .enter().append("text")
      .attr("class", "label")
      .style("fill-opacity", function(d) { return d.parent === root ? 1 : 0; })
      .style("display", function(d) { return d.parent === root ? "inline" : "none"; })
      .text(function(d) { return d.data.name; });

  var node = g.selectAll("circle,text");

  svg
      .style("background", color(-1))
      .on("click", function() { zoom(root); });

  zoomTo([root.x, root.y, root.r * 2 + margin]);

  function zoom(d) {
    var focus0 = focus; focus = d;

    var transition = d3.transition()
        .duration(d3.event.altKey ? 7500 : 750)
        .tween("zoom", function(d) {
          var i = d3.interpolateZoom(view, [focus.x, focus.y, focus.r * 2 + margin]);
          return function(t) { zoomTo(i(t)); };
        });

    transition.selectAll("text")
      .filter(function(d) { return d.parent === focus || this.style.display === "inline"; })
        .style("fill-opacity", function(d) { return d.parent === focus ? 1 : 0; })
        .on("start", function(d) { if (d.parent === focus) this.style.display = "inline"; })
        .on("end", function(d) { if (d.parent !== focus) this.style.display = "none"; });
  }

  function zoomTo(v) {
    var k = diameter / v[2]; view = v;
    node.attr("transform", function(d) { return "translate(" + (d.x - v[0]) * k + "," + (d.y - v[1]) * k + ")"; });
    circle.attr("r", function(d) { return d.r * k; });
  }
});
   
    </script>

    
<script>
    var x, i, j, selElmnt, a, b, c;
/*look for any elements with the class "select-box":*/
x = document.getElementsByClassName("select-box");
for (i = 0; i < x.length; i++) {
    selElmnt = x[i].getElementsByTagName("select")[0];
    /*for each element, create a new DIV that will act as the selected item:*/
    a = document.createElement("DIV");
    a.setAttribute("class", "select-selected");
    a.innerHTML = selElmnt.options[selElmnt.selectedIndex].innerHTML;
    x[i].appendChild(a);
    /*for each element, create a new DIV that will contain the option list:*/
    b = document.createElement("DIV");
    b.setAttribute("class", "select-items select-hide");
    for (j = 1; j < selElmnt.length; j++) {
        /*for each option in the original select element,
        create a new DIV that will act as an option item:*/
        c = document.createElement("DIV");
        c.innerHTML = selElmnt.options[j].innerHTML;
        c.addEventListener("click", function (e) {
            /*when an item is clicked, update the original select box,
            and the selected item:*/
            var y, i, k, s, h;
            s = this.parentNode.parentNode.getElementsByTagName("select")[0];
            h = this.parentNode.previousSibling;
            for (i = 0; i < s.length; i++) {
                if (s.options[i].innerHTML == this.innerHTML) {
                    s.selectedIndex = i;
                    h.innerHTML = this.innerHTML;
                    y = this.parentNode.getElementsByClassName("same-as-selected");
                    for (k = 0; k < y.length; k++) {
                        y[k].removeAttribute("class");
                    }
                    this.setAttribute("class", "same-as-selected");
                    break;
                }
            }
            h.click();
        });
        b.appendChild(c);
    }
    x[i].appendChild(b);
    a.addEventListener("click", function (e) {
        /*when the select box is clicked, close any other select boxes,
        and open/close the current select box:*/
        e.stopPropagation();
        closeAllSelect(this);
        this.nextSibling.classList.toggle("select-hide");
        this.classList.toggle("select-arrow-active");
    });
}
function closeAllSelect(elmnt) {
    /*a function that will close all select boxes in the document,
    except the current select box:*/
    var x, y, i, arrNo = [];
    x = document.getElementsByClassName("select-items");
    y = document.getElementsByClassName("select-selected");
    for (i = 0; i < y.length; i++) {
        if (elmnt == y[i]) {
            arrNo.push(i)
        } else {
            y[i].classList.remove("select-arrow-active");
        }
    }
    for (i = 0; i < x.length; i++) {
        if (arrNo.indexOf(i)) {
            x[i].classList.add("select-hide");
        }
    }
}
/*if the user clicks anywhere outside the select box,
then close all select boxes:*/
document.addEventListener("click", closeAllSelect);
</script>

<script>
    $(function () {
    $('[data-toggle="tooltip"]').tooltip()
})
    AOS.init();
</script>

<script type="text/javascript">
$(document).ready(function() {
Highcharts.chart('chart-container', {

title: {
    text: 'Exam Report'
},

subtitle: {
    text: 'Subject Wise Exam Report'
},

yAxis: {
    title: {
        text: 'Percentage'
    }
},


xAxis: {
    pointStart:1,
        categories: [1,2,3,4,5,6,7,8,9,10,11,12,13,14,15]
    },

legend: {
    layout: 'vertical',
    align: 'right',
    verticalAlign: 'middle'
},


series: [
    <?php echo $data['data']['line_chart_data']; ?>
],


responsive: {
    rules: [{
        condition: {
            maxWidth: 500
        },
        chartOptions: {
            legend: {
                layout: 'horizontal',
                align: 'center',
                verticalAlign: 'bottom'
            }
        }
    }]
}

});
});
    function redirectthis(){
        // alert("asdasd");
        document.location.href="{!!route('lo_marks_greport.index')!!}";
    }
</script>


@include('includes.footer')