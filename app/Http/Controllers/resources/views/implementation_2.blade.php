@include('includes.headcss')
    <link rel="stylesheet" href="../../../tooltip/enjoyhint/jquery.enjoyhint.css">
    <link href="../../../plugins/bower_components/horizontal-timeline/css/horizontal-timeline.css" rel="stylesheet">
@include('includes.header')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="card">
            <div class="row">
                <div class="col-md-6">
                    <h4 class="page-title">Welcome to TRIZ ERP, {{ Session::get('name') }}</h4>
                    <h5 class="welcome-msg">You're almost there. 
                        <a href="{{route('implementation_2')}}?moduleId=1"><span class="text-muted">Implementation</span></a> 
                    </h5>
                </div>
                <div class="col-md-6">
                    <button class="right-side-toggle waves-effect waves-light btn-info btn-circle pull-right m-l-20">
                        <i class="mdi mdi-settings fa-fw"></i>
                    </button>
                </div>
            </div>
        </div>

        <div class="card">            
            <div class="row">
                <div class="col-md-12">
                    <div class="box-title text-center">
                        Progress
                    </div>
                    <section class="cd-horizontal-timeline mt-0 mb-0">
                        <div class="timeline">
                            <div class="events-wrapper">
                                <div class="events">
                                    <ol>
                                        <li><a href="#0" data-date="08/01/2020" class="older-event">Welcome</a></li>
                                        <li><a href="#1" @if(app('request')->input('moduleId') == 1) class="selected" @elseif(app('request')->input('moduleId') > 1) class="older-event"  @endif data-date="09/04/2020">Data</a></li>
                                        <li><a href="#2" @if(app('request')->input('moduleId') == 2) class="selected" @elseif(app('request')->input('moduleId') > 2) class="older-event"  @endif data-date="09/06/2020">Fees</a></li>
                                        <li><a href="#3" @if(app('request')->input('moduleId') == 3) class="selected" @elseif(app('request')->input('moduleId') > 3) class="older-event"  @endif data-date="09/08/2020">Result</a></li>
                                        <li><a href="#4" @if(app('request')->input('moduleId') == 4) class="selected" @elseif(app('request')->input('moduleId') > 4) class="older-event"  @endif data-date="09/10/2020">Report</a></li>
                                        <li><a href="#5" @if(app('request')->input('moduleId') == 5) class="selected" @elseif(app('request')->input('moduleId') > 5) class="older-event"  @endif data-date="09/12/2020">Rights</a></li>
                                    </ol> <span class="filling-line" aria-hidden="true"></span>
                                </div>
                            </div>
                        </div>
                    </section>
                </div>
            </div>
        </div>
        <div class="card">            
            <div class="row">
                @php
                    $mId = app('request')->input('moduleId');
                    $mId = $mId + 1;
                @endphp
                @if(app('request')->input('moduleId') == 1)
                <div class="col-md-12">
                    <h4 class="page-title">Upload Data</h4>
                    <!-- <div class="pull-right"> -->
                        <!-- <a href="#"><i class="mdi mdi-check-all"></i></a> -->
                        <!-- <a href="#" data-perform="panel-collapse"><i class="ti-minus"></i></a> -->
                    <!-- </div> -->
                    <div class="row">
                        <div class="col-md-4">
                            <label>
                                <a target="blank" href="javascript:void(0);" onclick="window.open('http://apps.triz.co.in/excel_upload/export_xlsx.php?module=tbluser','scrollbars=yes,resizable=no,status=no,location=no,toolbar=no,menubar=no','width=600,height=300,left=100,top=100')">1) Staff Data</a>
                            </label>
                        </div>
                        <div class="col-md-4">
                            <label>Tutorial </label>
                        </div>
                        <div class="col-md-4">
                            <label>
                                <span class="label label-rouded label-success pull-right">
                                    <i class="mdi mdi-check-all"></i>
                                </span>
                            </label>
                        </div>
                        <div class="col-md-4">
                            <label>
                                <a target="blank" style="text-decoration: underline;" href="javascript:void(0);" onclick="window.open('http://apps.triz.co.in/excel_upload/export_xlsx.php?module=tblstudent','scrollbars=yes,resizable=no,status=no,location=no,toolbar=no,menubar=no','width=600,height=300,left=100,top=100')">2) Student Data</a>
                            </label>
                        </div>
                        <div class="col-md-4">
                            <label>Tutorial </label>
                        </div>
                        <div class="col-md-4">
                            <label>
                                <span class="label label-rouded label-success pull-right">
                                    <i class="mdi mdi-check-all"></i>
                                </span>
                            </label>
                        </div>
                    </div>
                </div>
                @endif

                @if(app('request')->input('moduleId') == 2)
                    <div class="col-lg-12">
                        <h4 class="page-title">Fees Setup</h4>
                        <!-- <div class="pull-right">
                            <a href="#" ><i class="mdi mdi-check-all"></i></a>
                            <a href="#" data-perform="panel-collapse"><i class="ti-minus"></i></a>
                        </div> -->
                        <div class="row">
                            <div class="col-md-4">
                                <label>
                                    <a target="blank" href="javascript:void(0);" onclick="window.open('{{route('fees_title.index')}}?implementation=1','scrollbars=yes,resizable=no,status=no,location=no,toolbar=no,menubar=no','width=1200,height=1200,left=100,top=100');">1) Fees Title</a> 
                                </label>
                            </div>
                            <div class="col-md-4">
                                <label>Tutorial </label>
                            </div>
                            <div class="col-md-4">
                                <label>
                                    <span class="label label-rouded label-success pull-right">
                                    <i class="mdi mdi-check-all"></i></span> 
                                </label>
                            </div>
                            <div class="col-md-4">
                                <label>
                                    <a target="blank"  onclick="window.open('{{route('map_year.index')}}?implementation=1','scrollbars=yes,resizable=no,status=no,location=no,toolbar=no,menubar=no','width=1200,height=1200,left=100,top=100');" href="javascript:void(0);">2) Fees Map</a> 
                                </label>
                            </div>
                            <div class="col-md-4">
                                <label>Tutorial </label>
                            </div>
                            <div class="col-md-4">
                                <label>
                                    <span class="label label-rouded label-success pull-right"><i class="mdi mdi-check-all"></i></span> 
                                </label>
                            </div>
                            <div class="col-md-4">
                                <label>
                                    <a target="blank" onclick="window.open('{{route('fees_breackoff.index')}}?implementation=1','scrollbars=yes,resizable=no,status=no,location=no,toolbar=no,menubar=no','width=1200,height=1200,left=100,top=100');" href="javascript:void(0);">3) Fees Structure</a> 
                                </label>
                            </div>
                            <div class="col-md-4">
                                <label>Tutorial </label>
                            </div>
                            <div class="col-md-4">
                                <label>
                                    <span class="label label-rouded label-success pull-right"><i class="mdi mdi-check-all"></i></span>
                                </label>
                            </div>
                            <div class="col-md-4">
                                <label>
                                    <a target="blank" onclick="window.open('{{route('fees_receipt_book_master.index')}}?implementation=1','scrollbars=yes,resizable=no,status=no,location=no,toolbar=no,menubar=no','width=1200,height=1200,left=100,top=100');" href="javascript:void(0);">4) Fees Receipt</a> 
                                </label>
                            </div>
                            <div class="col-md-4">
                                <label>Tutorial </label>
                            </div>
                            <div class="col-md-4">
                                <label>
                                    <span class="label label-rouded label-success pull-right"><i class="mdi mdi-check-all"></i></span> 
                                </label>
                            </div>
                            <div class="col-md-4">
                                <label>
                                    <a target="blank" onclick="window.open('{{route('fees_collect.index')}}?implementation=1','scrollbars=yes,resizable=no,status=no,location=no,toolbar=no,menubar=no','width=1200,height=1200,left=100,top=100');" href="javascript:void(0);">5) Fees Collect</a> 
                                </label>
                            </div>
                            <div class="col-md-4">
                                <label>Tutorial </label>
                            </div>
                            <div class="col-md-4">
                                <label>
                                    <span class="label label-rouded label-success pull-right"><i class="mdi mdi-check-all"></i></span>
                                </label>
                            </div>
                        </div>
                    </div>
                @endif

                @if(app('request')->input('moduleId') == 3)
                    <div class="col-md-12">
                        <h4 class="page-title">Result</h4>
                        <!-- <div class="pull-right">
                            <a href="#" ><i class="mdi mdi-check-all"></i></a>
                            <a href="#" data-perform="panel-collapse"><i class="ti-minus"></i></a>
                        </div> -->
                        <div class="row">
                            <div class="col-md-4">
                                <label>
                                    <a target="blank" href="{{route('exam_type_master.index')}}">1) Exam Type Master</a> 
                                </label>
                            </div>
                            <div class="col-md-4">
                                <label>Tutorial </label>
                            </div>
                            <div class="col-md-4">
                                <label>
                                    <span class="label label-rouded label-danger pull-right"><i class="mdi mdi-close"></i></span> 
                                </label>
                            </div>
                            <div class="col-md-4">
                                <label>
                                    <a target="blank" href="{{route('exam_creation.index')}}">2) Create Exam</a> 
                                </label>
                            </div>
                            <div class="col-md-4">
                                <label>Tutorial </label>
                            </div>
                            <div class="col-md-4">
                                <label>
                                    <span class="label label-rouded label-success pull-right"><i class="mdi mdi-check-all"></i></span> 
                                </label>
                            </div>
                            <div class="col-md-4">
                                <label>
                                    <a target="blank" href="{{route('grade_master.index')}}">3) Grade Scale Master</a> 
                                </label>
                            </div>
                            <div class="col-md-4">
                                <label>Tutorial </label>
                            </div>
                            <div class="col-md-4">
                                <label>
                                    <span class="label label-rouded label-success pull-right"><i class="mdi mdi-check-all"></i></span> 
                                </label>
                            </div>
                            <div class="col-md-4">
                                <label>
                                    <a target="blank" href="{{route('result_master.index')}}">4) Result Format</a> 
                                </label>
                            </div>
                            <div class="col-md-4">
                                <label>Tutorial </label>
                            </div>
                            <div class="col-md-4">
                                <label>
                                    <span class="label label-rouded label-success pull-right"><i class="mdi mdi-check-all"></i></span> 
                                </label>
                            </div>
                        </div>
                    </div>
                @endif

                @if(app('request')->input('moduleId') == 4)
                    <div class="col-md-12">
                        <h4 class="page-title">Report View</h4>
                        <!-- <div class="pull-right">
                            <a href="#" ><i class="mdi mdi-check-all"></i></a>
                            <a href="#" data-perform="panel-collapse"><i class="ti-minus"></i></a>
                        </div> -->
                        <div class="row">
                            <div class="col-md-4">
                                <label>
                                    <a target="blank" href="{{route('student_report.index')}}">1) Report Getting Started</a> 
                                </label>
                            </div>
                            <div class="col-md-4">
                                <label>Tutorial </label>
                            </div>
                            <div class="col-md-4">
                                <label>
                                    <span class="label label-rouded label-danger pull-right"><i class="mdi mdi-close"></i></span> 
                                </label>
                            </div>
                            <div class="col-md-4">
                                <label>
                                    <a target="blank" href="{{route('student_report.index')}}">2) Report Fields </a> 
                                </label>
                            </div>
                            <div class="col-md-4">
                                <label>Tutorial </label>
                            </div>
                            <div class="col-md-4">
                                <label>
                                    <span class="label label-rouded label-success pull-right"><i class="mdi mdi-check-all"></i></span> 
                                </label>
                            </div>
                        </div>
                    </div>
                @endif

                @if(app('request')->input('moduleId') == 5)
                    <div class="col-md-12">
                        <h4 class="page-title">Rights</h4>
                        <!-- <div class="pull-right">
                            <a href="#" ><i class="mdi mdi-check-all"></i></a>
                            <a href="#" data-perform="panel-collapse"><i class="ti-minus"></i></a>
                        </div> -->
                        <div class="row">
                            <div class="col-md-4">
                                <label>
                                    <a target="blank" href="{{route('add_groupwise_rights.index')}}">1) Groupwise Rights</a> 
                                </label>
                            </div>
                            <div class="col-md-4">
                                <label>Tutorial </label>
                            </div>
                            <div class="col-md-4">
                                <label>
                                    <span class="label label-rouded label-danger pull-right"><i class="mdi mdi-close"></i></span>
                                </label>
                            </div>
                            <div class="col-md-4">
                                <label>
                                    <a target="blank" href="{{route('add_individual_rights.index')}}">2) Individual Rights </a>
                                </label>
                            </div>
                            <div class="col-md-4">
                                <label>Tutorial </label>
                            </div>
                            <div class="col-md-4">
                                <label>
                                    <span class="label label-rouded label-success pull-right"><i class="mdi mdi-check-all"></i></span>
                                </label>
                            </div>
                        </div>
                    </div>
                @endif


               <div class="col-md-12">                
                    @if(app('request')->input('moduleId') == 5)
                    <a href="{{route('dashboard')}}"><input type="submit" name="submit" value="Finish" class="btn btn-success"></a>
                    @else
                    <a href="{{route('implementation_2')}}?moduleId={{$mId}}"><input type="submit" name="submit" value="Next" class="btn btn-info"></a>
                    @endif                    
                </div>    
            </div>
        </div>
    </div>
</div>

@include('includes.footerJs')
<script src="../../../plugins/bower_components/horizontal-timeline/js/horizontal-timeline.js"></script>
<script type="text/javascript">
    document.body.className = document.body.className.replace("fix-header", "fix-header show-sidebar hide-sidebar");
    document.getElementById('main-header').style.display = 'none';
</script>
@include('includes.footer')