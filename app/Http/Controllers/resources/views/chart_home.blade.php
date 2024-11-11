@include('includes.headcss')
<link rel="stylesheet" href="../../../tooltip/enjoyhint/jquery.enjoyhint.css">
@include('includes.header')
@include('includes.sideNavigation')

<style>
.divHide{
    display: none !important;
}
</style>

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="mt-30">
            <div class="white-box">
                <div class="row">
                    <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
					
                        <h4 class="page-title">Dashboard</h4>
                        <h5 class="welcome-msg">Welcome {{ Session::get('name') }}</h5>
                    </div>
                    <div class="col-lg-9 col-sm-8 col-md-8 col-xs-12">
                        <button
                            class="right-side-toggle waves-effect waves-light btn-info btn-circle pull-right m-l-20">
                            <i class="ti-settings text-white"></i>
                        </button>
                    </div>
                    <!-- /.col-lg-12 -->
                </div>
            </div>
        </div>

        <!-- /.row -->
        <!-- ============================================================== -->
        <!-- Different data widgets @if(!empty($data['message'])){{ $data['message'] }} @endif -->
        <!-- ============================================================== -->
        <!-- .row -->
        <div class="row slides">

            @if(isset($data['totalStudent']))
            <div class="col-lg-3 col-sm-6 col-xs-12 slide">
                <div class="white-box analytics-info box-radius">
                    <h3 class="box-title">Total Students</h3>
                    <ul class="list-inline two-part">
                        <li>
                            <img src="{{asset('admin_dep/images/student.png')}}" />
                            <!-- <div id="sparklinedash"></div> -->
                        </li>
                        <li class="text-left">
                            <!-- <i class="ti-arrow-up text-success"></i> --> <span
                                class="counter text-blue">{{$data['totalStudent']}}</span></li>
                    </ul>
                </div>
            </div>
            @endif
            @if(isset($data['totalUser']))
            <div class="col-lg-3 col-sm-6 col-xs-12 slide">
                <div class="white-box analytics-info yellow box-radius">
                    <h3 class="box-title">Total Employees</h3>
                    <ul class="list-inline two-part">
                        <li>
                            <img src="{{asset('admin_dep/images/employe.png')}}" />
                            <!-- <div id="sparklinedash2"></div> -->
                        </li>
                        <li class="text-left">
                            <!-- <i class="ti-arrow-up text-purple"></i> --> <span
                                class="counter text-yellow">{{$data['totalUser']}}</span></li>
                    </ul>
                </div>
            </div>
            @endif
            @if(isset($data['totalAdmission']))
            <div class="col-lg-3 col-sm-6 col-xs-12 slide">
                <div class="white-box analytics-info pink box-radius">
                    <h3 class="box-title">Admission Inquiry</h3>
                    <ul class="list-inline two-part">
                        <li>
                            <img src="{{asset('admin_dep/images/admnission.png')}}" />
                            <!-- <div id="sparklinedash3"></div> -->
                        </li>
                        <li class="text-left">
                            <!-- <i class="ti-arrow-up text-info"></i> --> <span
                                class="counter text-pink">{{$data['totalAdmission']}}</span></li>
                    </ul>
                </div>
            </div>
            @endif
            @if(isset($data['totalFees']))
            <div class="col-lg-3 col-sm-6 col-xs-12 slide">
                <div class="white-box analytics-info green box-radius">
                    <h3 class="box-title">Total Income</h3>
                    <ul class="list-inline two-part">
                        <li>
                            <img src="{{asset('admin_dep/images/income.png')}}" />
                            <!-- <div id="sparklinedash4"></div> -->
                        </li>
                        <li class="text-left">
                            <!-- <i class="ti-arrow-down text-danger"></i> --> <span
                                class="text-green">{{$data['totalFees']}}</span></li>
                    </ul>
                </div>
            </div>
            @endif
        </div>
        <!--/.row -->
        <!--row -->
        <!-- /.row -->
        <!-- <div class="row">
            <div class="col-md-12 col-lg-12 col-sm-12 col-xs-12">
                <div class="white-box">
                    <h3 class="box-title">Admission Comparission</h3>
                    <ul class="list-inline text-right">
                        <li>
                            <h5><i class="fa fa-circle m-r-5 text-info"></i>CBSE</h5> </li>
                        <li>
                            <h5><i class="fa fa-circle m-r-5 text-inverse"></i>GSEB</h5> </li>
                    </ul>
                    <div id="ct-visits" style="height: 405px;"></div>
                </div>
            </div>
        </div> -->
        <div class="col-md-12 col-lg-12 col-sm-12 slide ">
            <div class="white-box dashboard-box box-radius">
                <h3 class="box-title">School Chart</h3>
                <div id="containerSchool"></div>
            </div>
        </div>
        <!-- ============================================================== -->
        <!-- Recent comment, table & feed widgets -->
        <!-- ============================================================== -->
        <div class="row equal slides">
            @if(isset($data['standardsJson']))
            <div class="col-md-12 col-lg-6 col-sm-12 slide divHide" id="student-attendance">
                <div class="white-box dashboard-box box-radius">
                    <h3 class="box-title">Student Attendance</h3>
                    <div id="container"></div>
                </div>
            </div>
            @endif
            @if(isset($data['recentFeesCollection']))
            <div class="col-md-12 col-lg-6 col-sm-12 slide divHide"  id="fees-rcol">
                <div class="white-box dashboard-box box-radius">
                    <!-- <div class="col-md-3 col-sm-4 col-xs-6 pull-right">
                        <select class="form-control pull-right row b-none">
                            <option>March 2017</option>
                            <option>April 2017</option>
                            <option>May 2017</option>
                            <option>June 2017</option>
                            <option>July 2017</option>
                        </select>
                    </div> -->
                    <h3 class="box-title">Recent fees collection</h3>
                    <div class="row sales-report my-0">
                        <div class="col-md-6 col-sm-6 col-xs-6 p-0">
                            <img src="{{asset('admin_dep/images/fees-report.png')}}" class="fees-report-icon" />
                            <h2 class="mt-0 mb-0">{{date('M Y')}}</h2>
                            <p class="mb-0">FEES REPORT</p>
                        </div>
                        <div class="col-md-6 col-sm-6 col-xs-6 p-0">
                            <h1 class="text-right text-info m-t-20 mb-0">
                                <i class="mdi mdi-currency-inr fa-fw"></i> {{$data['totalFees']}}
                            </h1>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>PAYMENT MODE</th>
                                    <th>AMOUNT</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php
                                $j = 1;
                                @endphp
                                @foreach($data['recentFeesCollection'] as $key => $value)
                                <tr>
                                    <td>{{$j++}}</td>
                                    <!-- <td class="txt-oflo">{{$value['student_name']}}</td> -->
                                    <td>{{$value['payment_mode']}}</td>
                                    <!-- <td class="txt-oflo">{{$value['created_date']}}</td> -->
                                    <td><span class=""> <i
                                                class="mdi mdi-currency-inr fa-fw"></i>{{$value['total_fees']}}</span>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            @endif
            @if(isset($data['parentCommunications']))
            <div class="col-md-12 col-lg-6 col-sm-12 slide divHide"  id="student-pcom">
                <div class="white-box dashboard-box fix-height-box box-radius">
                    <h3 class="box-title">Recent Parent Communication</h3>
                    <div class="comment-center p-t-10">
                        @foreach($data['parentCommunications'] as $key => $value)
                        <div class="comment-body">
                            <div class="user-img"> <img src="storage/student/{{$value->student_image}}" alt="user"
                                    class="img-circle"></div>
                            <div class="mail-contnet">
                                <h5>{{$value->student_name}}</h5><span
                                    class="time">{{date('d-m-Y h:m:i',strtotime($value->created_at))}}</span>
                                <!-- <span class="label label-rouded label-info pull-right">PENDING</span> -->
                                <br /><span class="mail-desc">{{$value->message}}</span> <a href="javacript:void(0)"
                                    class="btn btn btn-rounded btn-default btn-outline m-r-5">{{$value->reply}}</a>
                                <!-- <i class="ti-check text-success m-r-5"></i> <a href="javacript:void(0)" class="btn-rounded btn btn-default btn-outline"><i class="ti-close text-danger m-r-5"></i> Reject</a>  -->
                            </div>
                        </div>
                        @endforeach

                    </div>
                </div>
            </div>
            @endif
            <!-- </div> -->
            <!-- ============================================================== -->
            <!-- calendar widgets -->
            <!-- ============================================================== -->
            <!-- <div class="row equal"> -->
            @if(isset($data['teacherBirthdays']))
            <div class="col-md-12 col-lg-6 col-sm-12 slide divHide" id="teacher-bday">
                <div class="white-box pb-0 dashboard-box box-radius">
                    <h3 class="box-title">Teachers Birthday</h3>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Teacher Name</th>
                                    <th>Designation</th>
                                    <th>Contact Number</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($data['teacherBirthdays'] as $key => $value)
                                <tr>
                                    <td><span>{{$value->teacher_name}}</span> </td>
                                    <td><span>{{$value->designation}}</span></td>
                                    <td><span>{{$value->contact_number}}</span></td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            @endif
            @if(isset($data['studentBirthdays']))
            <div class="col-md-12 col-lg-6 col-sm-12 slide divHide" id="student-bday">
                <div class="white-box pb-0 dashboard-box box-radius">
                    <h3 class="box-title">Students Birthday</h3>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Student Name</th>
                                    <th>Standard</th>
                                    <th>Division</th>
                                </tr>
                            </thead>
                            <tbody>

                                @foreach($data['studentBirthdays'] as $key => $value)
                                <tr>
                                    <td><span>{{$value->student_name}}</span> </td>
                                    <td><span>{{$value->standard_name}}</span></td>
                                    <td><span>{{$value->division_name}}</span></td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            @endif
            @if(isset($data['calendarEvents']))
            <div class="col-md-12 col-lg-6 col-sm-12 slide divHide" id="student-calendar">
                <div class="white-box pb-0 dashboard-box box-radius">
                    <h3 class="box-title">Events</h3>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Event Title</th>
                                    <th>Event Type</th>
                                    <th>Event Date</th>
                                </tr>
                            </thead>
                            <tbody>

                                @foreach($data['calendarEvents'] as $key => $value)
                                <tr>
                                    <td><span>{{$value->title}}</span> </td>
                                    <td><span>{{$value->event_type}}</span></td>
                                    <td><span>{{date('d-M-Y',strtotime($value->school_date))}}</span></td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            @endif

            @if(isset($data['studentLeaves']))
            <div class="col-md-12 col-lg-6 col-sm-12 slide divHide" id="student-leaves">
                <div class="white-box pb-0 dashboard-box box-radius">
                    <h3 class="box-title">Student Leaves</h3>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Student Name</th>
                                    <th>Standard - Division</th>
                                    <th>Reason</th>
                                </tr>
                            </thead>
                            <tbody>

                                @foreach($data['studentLeaves'] as $key => $value)
                                <tr>
                                    <td><span>{{$value->standard_name}}</span> </td>
                                    <td><span>{{$value->standard_name." - ".$value->division_name}}</span></td>
                                    <td><span>{{$value->message}}</span></td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            @endif

            <div class="col-md-12 col-lg-6 col-sm-12 slide divHide" id="fees-chart">
                <div class="white-box pb-0 dashboard-box box-radius">
                    <h3 class="box-title">Student Fees Chart</h3>
                    <div id="student_fees_chart">
                    </div>
                </div>
            </div>
            <div class="col-md-12 col-lg-6 col-sm-12 slide divHide" id="fees-chart1">
                <div class="white-box pb-0 dashboard-box box-radius">
                    <h3 class="box-title">Student Fees Chart</h3>
                    <div id="student_fees_chart1">
                    </div>
                </div>
            </div>
            <div class="col-md-12 col-lg-6 col-sm-12 slide divHide" id="fees-unpaid">
                <div class="white-box pb-0 dashboard-box box-radius">
                    <h3 class="box-title">Fees Paid Unpaid Data</h3>
                    <div id="fees_paid_unpaid_data">
                    </div>
                </div>
            </div>
        </div>
        <!-- ============================================================== -->
        <!-- chats, message & profile widgets -->
        <!-- ============================================================== -->

        <!-- ============================================================== -->
        <!-- start right sidebar -->
        <!-- ============================================================== -->
        <div class="right-sidebar">
            <div class="slimscrollright">
                <div class="rpanel-title"> Choose Theme <span><i class="ti-close right-side-toggle"></i></span> </div>
                <div class="r-panel-body">
                    <ul id="themecolors" class="m-t-20">
                        <li><b>With Light sidebar</b></li>
                        <li><a href="javascript:void(0)" data-theme="default" class="default-theme">1</a></li>
                        <li><a href="javascript:void(0)" data-theme="green" class="green-theme">2</a></li>
                        <li><a href="javascript:void(0)" data-theme="gray" class="yellow-theme">3</a></li>
                        <li><a href="javascript:void(0)" data-theme="blue" class="blue-theme">4</a></li>
                        <li><a href="javascript:void(0)" data-theme="purple" class="purple-theme">5</a></li>
                        <li><a href="javascript:void(0)" data-theme="megna" class="megna-theme">6</a></li>
                    </ul>
                </div>
            </div>
        </div>
        <!-- ============================================================== -->
        <!-- end right sidebar -->
        <!-- ============================================================== -->
    </div>
    <!-- /.container-fluid -->
    <!-- ============================================================== -->
    <!-- End Page Content -->
    <!-- ============================================================== -->
</div>

@include('includes.footerJs')

<!-- <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/2.1.3/jquery.min.js"></script> -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.11.2/jquery-ui.min.js"></script>

<script type="text/javascript">
    $(".slides").sortable({
     placeholder: 'slide-placeholder',
    axis: "y",
    revert: 150,
    start: function(e, ui){

        placeholderHeight = ui.item.outerHeight();
        ui.placeholder.height(placeholderHeight + 15);
        $('').insertAfter(ui.placeholder);

    },
    change: function(event, ui) {

        ui.placeholder.stop().height(0).animate({
            height: ui.item.outerHeight() + 15
        }, 300);

        placeholderAnimatorHeight = parseInt($(".slide-placeholder-animator").attr("data-height"));

        $(".slide-placeholder-animator").stop().height(placeholderAnimatorHeight + 15).animate({
            height: 0
        }, 300, function() {
            $(this).remove();
            placeholderHeight = ui.item.outerHeight();
            $('').insertAfter(ui.placeholder);
        });

    },
    stop: function(e, ui) {

        $(".slide-placeholder-animator").remove();

    },
});
</script>

<script src="https://code.highcharts.com/highcharts.js"></script>
<script src="https://code.highcharts.com/highcharts-more.js"></script>
<script src="https://code.highcharts.com/modules/sunburst.js"></script>
<script src="https://code.highcharts.com/modules/exporting.js"></script>
<script src="https://code.highcharts.com/modules/export-data.js"></script>
<script src="https://code.highcharts.com/modules/accessibility.js"></script>

@if(isset($data['standardsJson']))
<script type="text/javascript">
    var a = <?php echo $data['standardsJson']; ?>;
//alert(a);
</script>
<script type="text/javascript">
    Highcharts.chart('container', {
    chart: {
        type: 'column'
    },
    credits : false,
    title: {
        text: 'Student Attendance'
    },
    xAxis: {
        categories: <?php echo $data['standardsJson']; ?>//['CBSE-1', 'CBSE-2']
    },
    yAxis: {
        title: {
            text: 'Number of students'
        }
    },
    legend: {
        reversed: true
    },
    plotOptions: {
        series: {
            stacking: 'normal'
        }
    },
    series: [{
        name: 'Presant',
        color : '#0facf3',
        data: {{$data['presantsJson']}}//[5, 2]
    }, {
        name: 'Absent',
        color : '#e6346294',
        data: {{$data['absentsJson']}}//[2, 3]
    }]
});


</script>
<script>
    var chart = Highcharts.chart('fees_paid_unpaid_data', {

chart: {
    type: 'column'
},

title: {
    text: 'Fees Paid Unpaid Report'
},

subtitle: {
    text: 'Resize the frame or click buttons to change appearance'
},

legend: {
    align: 'right',
    verticalAlign: 'middle',
    layout: 'vertical'
},

xAxis: {
    categories:<?php echo $data['std_data']; ?>,
    labels: {
        x: -10
    }
},

yAxis: {
    allowDecimals: false,
    title: {
        text: 'Amount'
    }
},

series: [{
    name: 'Paid',
    data: <?php echo $data['paid_fee_data']; ?>
}, {
    name: 'UnPiad',
    data: <?php echo $data['unpaid_fees_data']; ?>
}],

responsive: {
    rules: [{
        condition: {
            maxWidth: 500
        },
        chartOptions: {
            legend: {
                align: 'center',
                verticalAlign: 'bottom',
                layout: 'horizontal'
            },
            yAxis: {
                labels: {
                    align: 'left',
                    x: 0,
                    y: -5
                },
                title: {
                    text: null
                }
            },
            subtitle: {
                text: null
            },
            credits: {
                enabled: false
            }
        }
    }]
}
});



</script>
<script>
    var data = <?php echo $data['chartData']; ?>;
    Highcharts.getOptions().colors.splice(0, 0, 'transparent');


Highcharts.chart('student_fees_chart', {

    chart: {
        height: '100%'
    },

    title: {
        text: 'Fees Student Chart'
    },

    series: [{
        type: "sunburst",
        data: data,
        allowDrillToNode: true,
        cursor: 'pointer',
        dataLabels: {
            format: '{point.name}',
            filter: {
                property: 'innerArcLength',
                operator: '>',
                value: 16
            }
        },
        levels: [{
            level: 1,
            levelIsConstant: false,
            dataLabels: {
                filter: {
                    property: 'outerArcLength',
                    operator: '>',
                    value: 64
                }
            }
        }, {
            level: 2,
            colorByPoint: true
        },
        {
            level: 3,
            colorVariation: {
                key: 'brightness',
                to: -0.5
            }
        }, {
            level: 4,
            colorVariation: {
                key: 'brightness',
                to: 0.5
            }
        }]

    }],
    tooltip: {
        headerFormat: "",
        pointFormat: 'The Data of <b>{point.name}</b> is <b>{point.value}</b>'
    }
});
</script>
<script>
    var data1 = <?php echo $data['chart1Data']; ?>;
    Highcharts.getOptions().colors.splice(0, 0, 'transparent');


Highcharts.chart('student_fees_chart1', {

    chart: {
        height: '100%'
    },

    title: {
        text: 'Fees Chart'
    },
    colors: ['#ffffff', '#0facf3', '#f089a4'],
    series: [{
        type: "sunburst",
        data: data1,
        allowDrillToNode: true,
        cursor: 'pointer',
        dataLabels: {
            format: '{point.name}',
            filter: {
                property: 'innerArcLength',
                operator: '>',
                value: 16
            }
        },
        levels: [{
            level: 1,
            levelIsConstant: false,
            dataLabels: {
                filter: {
                    property: 'outerArcLength',
                    operator: '>',
                    value: 64
                }
            }
        }, {
            level: 2,
            colorByPoint: true
        },
        {
            level: 3,
            colorVariation: {
                key: 'brightness',
                to: -0.5
            }
        }, {
            level: 4,
            colorVariation: {
                key: 'brightness',
                to: 0.5
            }
        }]

    }],
    tooltip: {
        headerFormat: "",
        pointFormat: 'The Data of <b>{point.name}</b> is <b>{point.value}</b>'
    }
});
</script>

@endif
@if(Session::get('erpTour')['dashboard']==0)
<script src="../../../tooltip/bower_components/todomvc-common/base.js"></script>
<!-- <script src="../../../tooltip/bower_components/jquery/jquery.js"></script> -->
<script src="../../../tooltip/bower_components/underscore/underscore.js"></script>
<script src="../../../tooltip/bower_components/backbone/backbone.js"></script>
<script src="../../../tooltip/bower_components/backbone.localStorage/backbone.localStorage.js"></script>
<script src="../../../tooltip/js/models/todo.js"></script>
<script src="../../../tooltip/js/collections/todos.js"></script>
<script src="../../../tooltip/js/views/todo-view.js"></script>
<script src="../../../tooltip/js/views/app-view.js"></script>
<script src="../../../tooltip/js/routers/router.js"></script>
<script src="../../../tooltip/js/app.js"></script>
<script src="../../../tooltip/enjoyhint/enjoyhint.js"></script>
<script src="../../../tooltip/enjoyhint/jquery.enjoyhint.js"></script>
<script src="../../../tooltip/enjoyhint/kinetic.min.js"></script>
<script>
    localStorage.clear();
      var enjoyhint_script_data = [
        {
            onBeforeStart: function(){
            $('#openToggle').click(function(e){

                enjoyhint_instance.trigger('new_todo');

            });
          },
          selector:'#openToggle',
          event:'new_todo',
          event_type:'custom',
          description:'You can see your profile here.'
        },
        {
          onBeforeStart: function(){
            $('#academicYears').click(function(e){

                enjoyhint_instance.trigger('new_todo');

            });
          },
          selector:'#academicYears',
          event:'new_todo',
          event_type:'custom',
          description:'You can change the Academic Years from here.',
        },
        {
          selector:'#academicTerms',
          event:'click',
          description:'You can change the Academic Terms from here.',
          timeout:100
        },
        {
          selector:'#openToggle',
          event:'click',
          description:'You can set up all ERP related things here. Click on SYSTEM SETTING.',
          timeout:100
        }
      ];
      var enjoyhint_instance = null;
      $(document).ready(function(){
        enjoyhint_instance = new EnjoyHint({});
        enjoyhint_instance.setScript(enjoyhint_script_data);
        enjoyhint_instance.runScript();
      });
</script>
<script type="text/javascript">
    var url = "http://202.47.117.124/tourUpdate?module=dashboard";
        var xhttp = new XMLHttpRequest();
        xhttp.onreadystatechange = function() {
            if (this.readyState == 4 && this.status == 200) {
              console.log("success");
            }
        };
        xhttp.open("GET", url, true);
        xhttp.send();
</script>
@endif


<script type="text/javascript">
    Highcharts.chart('containerSchool', {
    colors: ['#7cb5ec', '#434348', '#90ed7d', '#f7a35c'],
    chart: {
        type: 'column',
        inverted: true,
        polar: true
    },
    title: {
        text: 'Triz Erp'
    },
    tooltip: {
        outside: true
    },
    pane: {
        size: '85%',
        innerSize: '20%',
        endAngle: 270
    },
    xAxis: {
        tickInterval: 1,
        labels: {
            align: 'right',
            useHTML: true,
            allowOverlap: true,
            step: 1,
            y: 3,
            style: {
                fontSize: '13px'
            }
        },
        lineWidth: 0,
        categories: [
            'Student <span id="flag" style="color:#7cb5ec;" class="fa fa-user-circle">' +
            '</span>',
            'Fees <span id="flag" style="color:#434348;" class="fa fa-money">' +
            '</span>',
            'Admission <span id="flag" style="color:#90ed7d;" class="fa fa-address-card">' +
            '</span>',
            'Attendance <span id="flag" style="color:#f7a35c;" class="fa fa-adn">' +
            '</span>',
            'Homework <span id="flag" style="color:#7cb5ec;" class="fa fa-book">' +
            '</span>'
        ]
    },
    yAxis: {
        crosshair: {
            enabled: true,
            color: '#333'
        },
        lineWidth: 0,
        tickInterval: 25,
        reversedStacks: false,
        endOnTick: true,
        showLastLabel: true
    },
    plotOptions: {
        column: {
            stacking: 'normal',
            borderWidth: 0,
            pointPadding: 0,
            groupPadding: 0.15
        }
    },
    series: [{
        name: 'Pre Primary',
        data: [132, 105, 92, 73, 64]
    }, {
        name: 'Primary',
        data: [125, 110, 86, 64, 81]
    }, {
        name: 'Secondary',
        data: [111, 90, 60, 62, 87]
    }, {
        name: 'Higher Secondary',
        data: [111, 90, 60, 62, 87]
    }]
});
</script>

<script type="text/javascript">
    function alertValue(x){
        if(x == 'Student')
        {

            $("#student-pcom").toggleClass("divHide");
            $("#student-bday").toggleClass("divHide");
            $("#student-leaves").toggleClass("divHide");


                $([document.documentElement, document.body]).animate({
                scrollTop: $("#student-pcom").offset().top
                }, 1000);


        }

        if(x == 'Fees')
        {
            $("#fees-rcol").toggleClass("divHide");
            $("#fees-chart").toggleClass("divHide");
            $("#fees-chart1").toggleClass("divHide");
            $("#fees-unpaid").toggleClass("divHide");
            $([document.documentElement, document.body]).animate({
                scrollTop: $("#fees-rcol").offset().top
            }, 1000);
        }

        if(x == 'Attendance')
        {
            $("#student-attendance").toggleClass("divHide");
            $([document.documentElement, document.body]).animate({
                scrollTop: $("#student-attendance").offset().top
            }, 1000);
        }

    }
</script>
@include('includes.footer')