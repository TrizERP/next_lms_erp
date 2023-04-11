
<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/css/bootstrap.min.css" integrity="sha384-Gn5384xqQ1aoWXA+058RXPxPg6fy4IWvTNh0E263XmFcJlSAwiGgFAW/dAiS6JXm" crossorigin="anonymous">
<link href="{{ asset("/plugins/bower_components/report/css/aos.css") }}" rel="stylesheet" type="text/css" />
<link href="{{ asset("/plugins/bower_components/report/css/styleReport.css") }}" rel="stylesheet" type="text/css"/>
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
                                            <input type="hidden" name="student_id" id="student_id" value="{{$data['student_id']}}">
                                            <?php
                                            $image_path = asset("/storage/student/");
                                            if ($data['student_data']['image'] == "") {
                                                $data['student_data']['image'] = "no-image.jpg";
                                            }
                                            $image_path .= "/" . $data['student_data']['image'];
                                            ?>
                                            <img src="{{$image_path}}"
                                                alt="" height="80px" width="80px">
                                        </div>

                                        <table class="profile-info">
                                            <tbody>
                                                <tr>
                                                    <td width="90"><strong>Name :-</strong></td>
                                                    <td>{{ $data['student_data']['student_name'] }}</td>
                                                </tr>
                                                <tr>
                                                    <td><strong>Reg No :-</strong></td>
                                                    <td>{{ $data['student_data']['enrollment_no'] }}</td>
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
                                                    <td>{{ $data['student_data']['enrollment_no'] }}</td>
                                                </tr>
                                                <tr>
                                                    <td><strong>Mobile :-</strong></td>
                                                    <td>{{ $data['student_data']['mobile'] }}</td>
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
                                                    <td width="120"><strong>BirthDate. :-</strong> </td>
                                                    <td>{{ $data['student_data']['dob'] }}</td>
                                                </tr>
                                                <tr>
                                                    <td><strong>District :-</strong></td>
                                                    <td>{{ $data['student_data']['city'] }}</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-3">
                                    <div class="subject-list">
                                        <h5>Select Subject</h5>
                                        <select name="subject_type" id="subject_type" class="form-control w-50" style="display: inline-block !important;" onchange="redirectthis(this.value);">
                                            <option value="" >--Select--</option>
                                            @if(isset($data['all_subject_arr']))
                                                @foreach ($data['all_subject_arr'] as $item => $val)
                                                    @php
                                                if ($data['current_subject'] == $val['subject_id']){
                                                    $selected = "selected=selected";
                                                }else{
                                                    $selected = "";
                                                }
                                                    @endphp
                                                <option {{$selected}} value="{{$val['subject_id']}}">{{ $val['display_name'] }}</option>
                                                @endforeach
                                            @endif
                                        </select>

                                    </div>
                                </div>

                                <div class="col-md-9">
                                    <div class="subject-list">
                                        <h5>Subjects</h5>
                                        <ul>
                                            <?php
                                            $color_arr = array(
                                                "red-btn", "navy-btn", "yellow-btn", "pink-btn", "blue-btn"
                                            );
                                            $a = 0;

                                            foreach ($data['all_subject_arr'] as $item => $val) {
                                                if ($a == 5) {
                                                    $a = 0;
                                                }
                                                echo '<li><a href="javascript:redirectthis(' . $val['subject_id'] . ');" class="' . $color_arr[$a] . '">' . $val['display_name'] . '</a></li>';
                                                $a++;
                                            }
                                            ?>
                                        </ul>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </header>

                    <div class="container-fluid" data-aos="fade-up" data-aos-duration="2000">
                        <div class="row justify-content-center">
                            <div class="col-md-10 text-center">
                                <h2 class="std-details">
                                    <strong>{{ $data['student_data']['student_name'] }}</strong> has scored
                                    {{$data['grand_obtained']}} out of {{$data['grand_total']}}
                                </h2>
                            </div>
                        </div>
                    </div>

                    <!-- Subject Report Start -->
                    <div class="subject-report py-4" data-aos="fade-up" data-aos-duration="2000">
                        <div class="container">
                            <div class="row justify-content-center">
                                <div class="col-md-12">
                                    <div class="school-heading">
                                        <h2>Subject Report</h2>
                                    </div>
                                    <div class="row">
                                        @if(isset($data['exam_arr']))
                                            @foreach ($data['exam_arr'] as $id => $exam)
                                                <div class="col">
                                                    <div class="subject-area">
                                                        <div class="low"
                                                             style="height: {{ (100 - $exam['obtained_percentage']) }}%;"
                                                             tabindex="0" data-toggle="tooltip" data-placement="top">
                                                            <span>Not Achieved</span></div>
                                                        <div class="high"
                                                             style="height: {{ $exam['obtained_percentage'] }}%;"
                                                             tabindex="0" data-toggle="tooltip" data-placement="top">
                                                            <span>Achieved</span>
                                                        </div>
                                                    </div>
                                                    <div class="total-mark">
                                                        <h5>{{ $exam['paper_name'] }}</h5>
                                                        <div class="sub-mark"> {{ $exam['obtained_marks'] }}
                                                            / {{ $exam['total_marks'] }} </div>
                                                    </div>
                                                </div>
                                            @endforeach
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Subject Report End -->

                    <!-- Subject Graph Start -->
                    <div class="sub-graph mb-5" data-aos="fade-up" data-aos-duration="2000">
                        <div class="container">
                            <div class="row">
                                <!-- <div class="col-md-4">
                                    <div class="school-heading">
                                        <h2>Subject Report</h2>
                                        <p>Lorem ipsum dolor sit amet, consectetuer adipiscing elit, sed diam nonummy
                                            nibh euismod tincidunt ut laoreet dolore magna aliquam erat volutpat.</p>
                                    </div>
                                </div> -->
                                <div class="col-md-12">
                                    <div class="school-heading"><h2>Subject Report</h2></div>
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
                            </div>
                            <div class="table-responsive">
                                <table class="table table-hover table-borderless student-table">
                                    @php $k=0; @endphp
                                    @if( count($data['lo_arr']) > 0 )
                                        @foreach ($data['lo_arr'] as $exam => $lo_data)
                                        <tr>
                                            <th width="200">
                                                <div class="chap-btn {{$color_arr[$k]}}">{{$exam}}</div>
                                            </th>
                                            <td>
                                                <ul class="student-total-per">
                                                    @foreach ($lo_data as $lo => $per)
                                                        <li>
                                                            <div class="total-per-box">
                                                                <h5>{{$lo}}</h5>
                                                                <div class="total-per">{{ $per }}%</div>
                                                            </div>
                                                        </li>
                                                    @endforeach
                                                </ul>
                                            </td>
                                        </tr>
                                            @php $k++; @endphp
                                        @endforeach
                                    @endif
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
                                        @php $i=1; @endphp
                                    @foreach ($data['lo_arr'] as $exam => $lo_data)
                                        @php
                                            $newexam = str_replace(' ', '', $exam);
                                            $main_active = "";
                                            if($i == 1)
                                            {
                                                $main_active = "active";
                                            }
                                            $i++;
                                        @endphp
                                        <li class="nav-item">
                                            <a class="nav-link {{$main_active}}" id="tab-{{$newexam}}" data-toggle="tab" href="#{{$newexam}}" role="tab" aria-controls="{{$newexam}}" aria-selected="true">{{$exam}}</a>
                                        </li>
                                    @endforeach
                                    </ul>
                                </div>

                                <div class="tab-content std-process" id="myTabContent">
                                    @php $j=1; @endphp
                                    @foreach ($data['lo_arr'] as $exam1 => $lo_data1)
                                        @php
                                            $inner_div = str_replace(' ', '', $exam1);
                                            $inner_active = "";
                                            if($j == 1)
                                            {
                                                $inner_active = "show active";
                                            }
                                            $j++;
                                        @endphp
                                        <div class="tab-pane fade {{$inner_active}}" id="{{$inner_div}}" role="tabpanel" aria-labelledby="tab-{{$inner_div}}">
                                            @foreach ($lo_data1 as $lo => $per)
                                                <h5>{{$lo}}</h5>
                                                <div class="progress mb-3">
                                                    <div class="progress-bar progress-bar-striped bg-warning progress-bar-animated" role="progressbar" style="width: {{$per}}%" aria-valuenow="{{$per}}" aria-valuemin="0" aria-valuemax="100"></div>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <footer class="footer school-footer" data-aos="fade-up" data-aos-duration="2000" >
                    <div class="top-footer">
                        <div class="container text-center">
                           <!--  <div class="top-footer-menu">
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
                            </div> -->
                        </div>
                    </div>
                    <!-- <div class="bottom-footer py-3 text-center bg-white d-none">
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
                    </div> -->
                </footer>
            </div>
        </div>
    </div>
</div>

<footer class="footer text-center"> {{date('Y')}} &copy; Triz Innovation PVT LTD. <a href="{{route('siteMap')}}"> Site Map </a> </footer>
<!-- jQuery -->
<script src="{{ asset("/admin_dep/js/jquery-3.5.1.min.js") }}"></script>
<script src="{{ asset("/admin_dep/js/popper.min.js") }}"></script>
<script src="{{ asset("/admin_dep/js/bootstrap.min.js") }}"></script>
<script src="{{ asset("/admin_dep/js/bootstrap-select.min.js") }}"></script>
<script src="{{ asset("/admin_dep/js/custom.js") }}"></script>
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>


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
    text: 'Exam Wise Report'
},

yAxis: {
    title: {
        text: 'Marks'
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
    <?php echo $data['linechart_data']; ?>
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

function redirectthis(subject_id) {
    var student_id = $("#student_id").val();
    //var subject_id = $("#subject_type").val();
    var path1 = "{{route('lmsStudent_report.edit',$data['student_id'])}}";
    path1 = path1.replace('student_id', student_id);
    path1 = path1.replace('subject_ids', subject_id);
    document.location.href = path1;
}

</script>
</body>
