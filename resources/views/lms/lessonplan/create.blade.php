{{--@include('includes.lmsheadcss')--}}
@extends('lmslayout')
@section('container')
<link href="/plugins/bower_components/clockpicker/dist/jquery-clockpicker.min.css" rel="stylesheet">
<style>
    .tooltip-inner {
        max-width: 1100px !important;
    }

    #example {
        table-layout: fixed;
    }

    #example tbody tr td:nth-child(2) {
        text-align: unset;
    }

    .scroll {
        height: 200px;
        overflow-y: scroll;
    }
</style>
{{--@include('includes.header')
@include('includes.sideNavigation')--}}
<!-- Content main Section -->
<div class="content-main flex-fill">
    <div class="row justify-content-between">
        <div class="col-md-6">
            <h1 class="h4 mb-3">
                Add Lesson Plan
            </h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb bg-transparent p-0">
                    <li class="breadcrumb-item"><a href="{{ route('course_master.index') }}">LMS</a></li>
                    <li class="breadcrumb-item">Lesson Plan</li>
                    <li class="breadcrumb-item active" aria-current="page">Add Lesson Plan</li>
                </ol>
            </nav>
        </div>
    </div>
    @php
    $readonly = '';
if(isset($_REQUEST['preload_lms'])){
    $readonly="pointer-events: none";
}else if(isset($_REQUEST['id'])){
    $readonly="readonly ";
}
@endphp
    <div class="container-fluid mb-5">
        <div class="card border-0">
            <div class="card-body">
                @if ($message = Session::get('success'))
                    <div class="alert alert-success alert-block">
                        <button type="button" class="close" data-dismiss="alert">×</button>
                        <strong>{{ $message }}</strong>
                    </div>
                @endif

                 <div id="error_message"></div>
                <form id="addLessonPlan" class="addDayWiseFrm" method="post" enctype='multipart/form-data'>
                    {{ method_field('POST') }}
                    @csrf
                    <input type="hidden" name="day_count" id="day_count"
                        value="{{ $data['lessonplan_data']->lesson_days_count ?? 0 }}">
                    <input type="hidden" name="id" id="id" value="{{ $data['lessonplan_data']->id }}">
                    <input type="hidden" name="standard_id" id="standard_id"
                        value="{{ $data['lessonplan_data']->standard_id }}">
                    <input type="hidden" name="subject_id" id="subject_id"
                        value="{{ $data['lessonplan_data']->subject_id }}">
                    <input type="hidden" name="chapter_id" id="chapter_id"
                        value="{{ $data['lessonplan_data']->chapter_id }}">
                    <div class="row align-items-center">
                        <div class="col-md-3 form-group">
                            <label>Standard</label>
                            <select name="standard" id="standard" class="form-control" required {{$data['lessonplan_data']->standard_id ? $readonly : ''}}>
                                <option value="">Select Standard</option>
                                @if (isset($data['standards']))
                                    @foreach ($data['standards'] as $key => $value)
                                        <option value="{{ $value->value }}"
                                            {{ $data['lessonplan_data']->standard_id == $value->value ? 'selected' : '' }}>{{ $value->label }}</option>
                                    @endforeach
                                @endif
                            </select>
                        </div>
                        <div class="col-md-3 form-group">
                            <label>Subject</label>
                            <select name="subject" id="subject" class="form-control"  onchange="get_chapters();" required {{$data['lessonplan_data']->subject_id ? $readonly : ''}}>
                                <option value="">Select Subject</option>
                                @if (isset($data['subjects']))
                                    @foreach ($data['subjects'] as $key => $value)
                                        <option value="{{ $value->value }}"
                                            {{ $data['lessonplan_data']->subject_id == $value->value ? 'selected' : '' }}>{{ $value->label }}</option>
                                    @endforeach
                                @endif
                            </select>
                        </div>
                        <div class="col-md-3 form-group">
                            <label>Chapter</label>
                            <select name="chapter" id="chapter" class="form-control" onchange="get_topic();" {{ $data['lessonplan_data']->chapter_id ? $readonly : '' }}>
                                <option value="">Select Chapter</option>
                                @if (isset($data['chapters']))
                                    @foreach ($data['chapters'] as $key => $value)
                                        <option value="{{ $value->value }}"
                                            {{ $data['lessonplan_data']->chapter_id == $value->value ? 'selected' : '' }}>{{ $value->label }}</option>
                                    @endforeach
                                @endif
                            </select>
                        </div>
                        <div class="col-md-3 form-group">
                            <label>Topic</label>
                            <select name="topic" id="topic" class="form-control" onchange="topic_change();" {{$data['lessonplan_data']->topic_id ? $readonly : ''}} >
                                <option value="">Select Topic</option>
                                @if (isset($data['topics']))
                                    @foreach ($data['topics'] as $key => $value)
                                        <option value="{{ $value->value }}"
                                            {{ $data['lessonplan_data']->topic_id == $value->value ? 'selected' : '' }}>{{ $value->label }}</option>
                                    @endforeach
                                @endif
                            </select>
                        </div>
                        <div class="col-md-3 form-group">
                            <label>No. of Periods</label>
                            <input type="number" name="numberofperiod" id="numberofperiod" class="form-control"
                                value="{{ $data['lessonplan_data']->numberofperiod }}"
                                placeholder="Enter No of periods">
                        </div>
                        <div class="col-md-3 form-group">
                            <label>Teaching time</label>
                            <input type="number" name="teachingtime" id="teachingtime" class="form-control"
                                value="{{ $data['lessonplan_data']->teachingtime }}"
                                placeholder="Enter Teaching time (in minutes)">
                        </div>
                        <div class="col-md-3 form-group">
                            <label>Assessment time</label>
                            <input type="number" name="assessmenttime" id="assessmenttime" class="form-control"
                                value="{{ $data['lessonplan_data']->assessmenttime }}"
                                placeholder="Enter Assessment time (in minutes)">
                        </div>
                        <div class="col-md-3 form-group">
                            <label>Learning time</label>
                            <input type="number" name="learningtime" id="learningtime" class="form-control"
                                value="{{ $data['lessonplan_data']->learningtime }}"
                                placeholder="Enter Learning time (in minutes)">
                        </div>
                        <div class="col-md-6 form-group">
                            <label> <b>Assessment Qualifying</b></label>
                            <div class="form-group d-flex mb-0">
                             <input type="text" id="as_input" value="give Assessment Qualifying for students to Qualifying and understand chapter" class="form-control" name="aiPrompt[]" data-target="assessmentqualifying"><span onclick="getAIoutput('assessmentqualifying');" style="padding:2px 6px"><i class="mdi mdi-refresh"></i></span></div>
                            <textarea class="form-control tinymce" placeholder="Enter Assessment Qualifying" name="assessmentqualifying"
                                id="assessmentqualifying" cols="60" rows="4" required>{{ $data['lessonplan_data']->assessmentqualifying }}</textarea>
                        </div>
                        <div class="col-md-6 form-group">
                            <label><b>Focus point</b> <span class="text-danger">*</span></label>
                            <div class="form-group d-flex mb-0">
                            <input type="text" class="form-control" id="fp_input" value="get Focus point for students where they can focus to understand chapter" name="aiPrompt[]" data-target="focauspoint"><span onclick="getAIoutput('focauspoint');" style="padding:2px 6px"><i class="mdi mdi-refresh"></i></span></div>
                            <textarea class="form-control tinymce" placeholder="Enter Focus point" name="focauspoint" id="focauspoint"
                                cols="60" rows="4" required>{{ $data['lessonplan_data']->focauspoint }}</textarea>
                        </div>
                        <div class="col-md-6 form-group">
                            <label><b>Pedagogical process</b> <span class="text-danger">*</span> </label>
                            <div class="form-group d-flex mb-0">
                            <input type="text" class="form-control" id="pp_input" value="get Pedagogical process for students to process and understand chapter" name="aiPrompt[]" data-target="pedagogicalprocess"><span onclick="getAIoutput('pedagogicalprocess');" style="padding:2px 6px"><i class="mdi mdi-refresh"></i></span></div>
                            <textarea class="form-control tinymce" placeholder="Enter Pedagogical process" name="pedagogicalprocess"
                                id="pedagogicalprocess" cols="60" rows="4" required>{{ $data['lessonplan_data']->pedagogicalprocess }}</textarea>
                        </div>
                        <div class="col-md-6 form-group">
                            <label>Resource <span class="text-danger">*</span> </label>
                            <div class="form-group d-flex mb-0">
                            <input type="text" class="form-control" id="resource_input" value="get Resource for students to peoperly understand chapter" name="aiPrompt[]" data-target="resource"><span onclick="getAIoutput('resource');" style="padding:2px 6px"><i class="mdi mdi-refresh"></i></span></div>
                            <textarea class="form-control tinymce" placeholder="Enter Resource" name="resource" id="resource" cols="60"
                                rows="4" required>{{ $data['lessonplan_data']->resource }}</textarea>
                        </div>
                        <div class="col-md-12 form-group">
                            <label>Classroom presentation <span class="text-danger">*</span> </label>
                            <div class="form-group d-flex mb-0">
                            <input type="text" class="form-control" id="cp_input" value="get Classroom presentation for students to understand and presentation chapter" name="aiPrompt[]" data-target="classroompresentation"><span onclick="getAIoutput('classroompresentation');" style="padding:2px 6px"><i class="mdi mdi-refresh"></i></span></div>
                            <textarea class="form-control tinymce" placeholder="Enter Classroom presentation" name="classroompresentation"
                                id="classroompresentation" cols="60" rows="4" required>{{ $data['lessonplan_data']->classroompresentation }}</textarea>
                        </div>
                        <div class="col-md-12 form-group">
                            <button type="button" class="btn btn-success add_activity" id="classroomactivity">Add
                                Activity <i class="fa fa-plus"></i></button>
                        </div>
                        <div class="col-md-6 form-group">
                            <label>Clasroom diversity <span class="text-danger">*</span></label>
                            <div class="form-group d-flex mb-0">
                            <input type="text" class="form-control" id="cd_input" value="get Clasroom diversity for students to understand and presentation chapter" name="aiPrompt[]" data-target="classroomdiversity"><span onclick="getAIoutput('classroomdiversity');" style="padding:2px 6px"><i class="mdi mdi-refresh"></i></span></div>
                            <textarea class="form-control tinymce" placeholder="Enter Clasroom diversity" name="classroomdiversity"
                                id="classroomdiversity" cols="60" rows="4" required>{{ $data['lessonplan_data']->classroomdiversity }}</textarea>
                        </div>
                        <div class="col-md-6 form-group">
                            <label>Prerequisite lesson </label>
                            <div class="form-group d-flex mb-0">
                            <input type="text" class="form-control" id="pl_input" value="get Prerequisite lesson for students to do lesson and understand chapter" name="aiPrompt[]" data-target="prerequisite"><span onclick="getAIoutput('prerequisite');" style="padding:2px 6px"><i class="mdi mdi-refresh"></i></span></div>
                            <textarea class="form-control tinymce" placeholder="Enter Prerequisite lesson" name="prerequisite" id="prerequisite"
                                cols="60" rows="4">{{ $data['lessonplan_data']->prerequisite }}</textarea>
                        </div>
                        <div class="col-md-6 form-group">
                            <label>Leraning objective </label>
                            <div class="form-group d-flex mb-0">
                            <input type="text" class="form-control" id="lo_input" value="get Leraning objective for students to learn  objectives and understand chapter" name="aiPrompt[]" data-target="learningobjective"><span onclick="getAIoutput('learningobjective');" style="padding:2px 6px"><i class="mdi mdi-refresh"></i></span></div>
                            <textarea class="form-control tinymce" placeholder="Enter Leraning objective" name="learningobjective"
                                id="learningobjective" cols="60" rows="4">{{ $data['lessonplan_data']->learningobjective }}</textarea>
                        </div>
                        <div class="col-md-6 form-group">
                            <label>Learning outcome: Knowledge </label>
                            <div class="form-group d-flex mb-0">
                            <input type="text" class="form-control" id="lok_input" value="get Learning outcome: Knowledge for students to gain knowledge and understands chapter" name="aiPrompt[]" data-target="learningknowledge"><span onclick="getAIoutput('learningknowledge');" style="padding:2px 6px"><i class="mdi mdi-refresh"></i></span></div>
                            <textarea class="form-control tinymce" placeholder="Enter Learning outcome" name="learningknowledge"
                                id="learningknowledge" cols="60" rows="4">{{ $data['lessonplan_data']->learningknowledge }}</textarea>
                        </div>
                        <div class="col-md-6 form-group">
                            <label>Learning outcome: Skills </label>
                            <div class="form-group d-flex mb-0">
                                <input type="text" class="form-control" id="los_input" value="get Learning outcome: Skills for students to get skills and understand" name="aiPrompt[]" data-target="learningskill"><span onclick="getAIoutput('learningskill');" style="padding:2px 6px"><i class="mdi mdi-refresh"></i></span>
                            </div>
                            <textarea class="form-control tinymce" placeholder="Enter Learning outcome" name="learningskill" id="learningskill"
                                cols="60" rows="4">{{ $data['lessonplan_data']->learningskill }}</textarea>
                        </div>
                        <div class="col-md-12 form-group">
                            <label>Self-study & Homework </label>
                            <div class="form-group d-flex mb-0">
                            <input type="text" class="form-control" id="ssh_input" value="get Self-study & Homework for students to do self study and homework freely at home" name="aiPrompt[]" data-target="selfstudyhomework"><span onclick="getAIoutput('selfstudyhomework');" style="padding:2px 6px"><i class="mdi mdi-refresh"></i></span></div>
                            <textarea class="form-control tinymce" placeholder="Enter Self-study & Homework" name="selfstudyhomework"
                                id="selfstudyhomework" cols="60" rows="4">{{ $data['lessonplan_data']->selfstudyhomework }}</textarea>
                        </div>
                        <div class="col-md-12 form-group">
                            <button type="button" class="btn btn-success add_activity" id="selfstudyactivity">Add
                                Activity <i class="fa fa-plus"></i></button>
                        </div>
                        <div class="col-md-12 form-group">
                            <label>Assessment </label>
                            <div class="form-group d-flex mb-0">
                            <input type="text" class="form-control" id="ass_input" value="get Assessment for students to do assments and understand chapter" name="aiPrompt[]" data-target="assessment"><span onclick="getAIoutput('assessment');" style="padding:2px 6px"><i class="mdi mdi-refresh"></i></span></div>
                            <textarea class="form-control tinymce" placeholder="Enter Assessment" name="assessment" id="assessment"
                                cols="60" rows="4">{{ $data['lessonplan_data']->assessment }}</textarea>
                        </div>
                        <div class="col-md-12 form-group">
                            <button type="button" class="btn btn-success add_activity" id="assessmentactivity">Add
                                Activity <i class="fa fa-plus"></i></button>
                        </div>
                        <div class="col-md-6 form-group">
                            <button type="button" class="btn btn-primary pull-right add-day">Add Day
                                <i class="fa fa-plus"></i></button>
                        </div>

                        <div class="modal" id="day_mdl" tabindex="-1" role="dialog">
                            <div class="modal-dialog modal-lg" role="document">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Day <button type="button"
                                                class="btn btn-primary add-day-mdl"><i
                                                    class="fa fa-plus"></i></button></h5>
                                        <button type="button" class="btn-close-day close" data-dismiss="modal"
                                            aria-label="Close">
                                            <span aria-hidden="true">&times;</span>
                                        </button>
                                    </div>
                                    <div class="modal-body">
                                    <p id="msg_day"></p>
                                        <div id="daywise">
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-primary btn-close-day"
                                            data-dismiss="modal">Save
                                            changes</button>
                                        <button type="button" class="btn btn-secondary btn-close-day"
                                            data-dismiss="modal">Close</button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="modal" id="contentMasterMdl" tabindex="-1" role="dialog">
                            <div class="modal-dialog" role="document">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Modal title</h5>
                                        <button type="button" class="btn-close close" data-dismiss="modal"
                                            aria-label="Close">
                                            <span aria-hidden="true">&times;</span>
                                        </button>
                                    </div>
                                    <div class="modal-body">
                                        <div id="add_classroomactivity" class="form-group activityData">No data found
                                            !!</div>
                                        <div id="add_selfstudyactivity" class="form-group activityData">No data found
                                            !!</div>
                                        <div id="add_assessmentactivity" class="form-group activityData">No data found
                                            !!</div>
                                        <div id="add_classroomactivityday" class="form-group activityData">No data
                                            found
                                            !!</div>
                                        <div id="add_selfstudyactivityday" class="form-group activityData">No data
                                            found
                                            !!</div>
                                        <div id="add_assessmentactivityday" class="form-group activityData">No data
                                            found
                                            !!</div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-primary btn-close"
                                            data-dismiss="modal">Save changes</button>
                                        <button type="button" class="btn btn-secondary btn-close"
                                            data-dismiss="modal">Close</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row align-items-center">
                        <div class="col-md-6 form-group">
                            <label>Hard word  </label>
                            <div class="form-group d-flex mb-0">
                            <input type="text" class="form-control" id="hw_input" value="get Hard word for students to understand hard words from chapter" name="aiPrompt[]" data-target="hardword"><span onclick="getAIoutput('hardword');" style="padding:2px 6px"><i class="mdi mdi-refresh"></i></span></div>
                            <textarea class="form-control tinymce" placeholder="Enter Hard word" name="hardword" id="hardword" cols="60"
                                rows="4">{{ $data['lessonplan_data']->hardword }}</textarea>
                        </div>
                        <div class="col-md-6 form-group">
                            <label>Tag & metatag </label>
                            <div class="form-group d-flex mb-0">
                            <input type="text" class="form-control" id="tm_input" value="get Tag & metatag for students to understand chapter properly" name="aiPrompt[]" data-target="tagmetatag"><span onclick="getAIoutput('tagmetatag');" style="padding:2px 6px"><i class="mdi mdi-refresh"></i></span></div>
                            <textarea class="form-control tinymce" placeholder="Enter Tag & metatag" name="tagmetatag" id="tagmetatag"
                                cols="60" rows="4">{{ $data['lessonplan_data']->tagmetatag }}</textarea>
                        </div>
                        <div class="col-md-6 form-group">
                            <label>Value integration </label>
                            <div class="form-group d-flex mb-0">
                            <input type="text" class="form-control" id="vi_input" value="get Value integration for students to integrate value from chapter" name="aiPrompt[]" data-target="valueintegration"><span onclick="getAIoutput('valueintegration');" style="padding:2px 6px"><i class="mdi mdi-refresh"></i></span></div>
                            <textarea class="form-control tinymce" placeholder="Enter Value integration" name="valueintegration"
                                id="valueintegration" cols="60" rows="4">{{ $data['lessonplan_data']->valueintegration }}</textarea>
                        </div>
                        <div class="col-md-6 form-group">
                            <label>Global connection </label>
                            <div class="form-group d-flex mb-0">
                            <input type="text" class="form-control" id="gc_input" value="get Global connection for students to connect them globally from chapter" name="aiPrompt[]" data-target="globalconnection"><span onclick="getAIoutput('globalconnection');" style="padding:2px 6px"><i class="mdi mdi-refresh"></i></span></div>
                            <textarea class="form-control tinymce" placeholder="Enter Global connection" name="globalconnection"
                                id="globalconnection" cols="60" rows="4">{{ $data['lessonplan_data']->globalconnection }}</textarea>
                        </div>
                        <div class="col-md-6 form-group">
                            <label>SEL  </label>
                            <div class="form-group d-flex mb-0">
                            <input type="text" class="form-control" id="sel_input" value="get SEL(social emotional learning) for students" name="aiPrompt[]" data-target="sel"><span onclick="getAIoutput('sel');" style="padding:2px 6px"><i class="mdi mdi-refresh"></i></span></div>
                            <textarea class="form-control tinymce" placeholder="Enter SEL" name="sel" id="sel" cols="60"
                                rows="4">{{ $data['lessonplan_data']->sel }}</textarea>
                        </div>
                        <div class="col-md-6 form-group">
                            <label>STEM </label>
                            <div class="form-group d-flex mb-0">
                            <input type="text" class="form-control" id="stem_input" value="get STEM for students" name="aiPrompt[]" data-target="stem"><span onclick="getAIoutput('stem');" style="padding:2px 6px"><i class="mdi mdi-refresh"></i></span></div>
                            <textarea class="form-control tinymce" placeholder="Enter STEM" name="stem" id="stem" cols="60"
                                rows="4">{{ $data['lessonplan_data']->stem }}</textarea>
                        </div>
                        <div class="col-md-6 form-group">
                            <label>Vocational training </label>
                            <div class="form-group d-flex mb-0">
                            <input type="text" class="form-control" id="vt_input" value="get Vocational training for students" name="aiPrompt[]" data-target="vocationaltraining"><span onclick="getAIoutput('vocationaltraining');" style="padding:2px 6px"><i class="mdi mdi-refresh"></i></span></div>
                            <textarea class="form-control tinymce" placeholder="Enter Vocational training" name="vocationaltraining"
                                id="vocationaltraining" cols="60" rows="4">{{ $data['lessonplan_data']->vocationaltraining }}</textarea>
                        </div>
                        <div class="col-md-6 form-group">
                            <label>Simulation </label>
                            <div class="form-group d-flex mb-0">
                            <input type="text" class="form-control" id="s_input" value="get Simulation for students" name="aiPrompt[]" data-target="simulation"><span onclick="getAIoutput('simulation');" style="padding:2px 6px"><i class="mdi mdi-refresh"></i></span></div>
                            <textarea class="form-control tinymce" placeholder="Enter Simulation" name="simulation" id="simulation"
                                cols="60" rows="4">{{ $data['lessonplan_data']->simulation }}</textarea>
                        </div>
                        <div class="col-md-6 form-group">
                            <label>Games </label>
                            <div class="form-group d-flex mb-0">
                            <input type="text" class="form-control" id="g_input" value="get Games for students" name="aiPrompt[]" data-target="games"><span onclick="getAIoutput('games');" style="padding:2px 6px"><i class="mdi mdi-refresh"></i></span></div>
                            <textarea class="form-control tinymce" placeholder="Enter Games" name="games" id="games" cols="60"
                                rows="4">{{ $data['lessonplan_data']->games }}</textarea>
                        </div>
                        <div class="col-md-6 form-group">
                            <label>Activities </label>
                            <div class="form-group d-flex mb-0">
                            <input type="text" class="form-control" id="act_input" value="get Activities for students" name="aiPrompt[]" data-target="activities"><span onclick="getAIoutput('activities');" style="padding:2px 6px"><i class="mdi mdi-refresh"></i></span></div>
                            <textarea class="form-control tinymce" placeholder="Enter Activities" name="activities" id="activities"
                                cols="60" rows="4">{{ $data['lessonplan_data']->activities }}</textarea>
                        </div>
                        <div class="col-md-6 form-group">
                            <label>Real life application  </label>
                            <div class="form-group d-flex mb-0">
                            <input type="text" class="form-control" id="rla_input" value="Real life application for students" name="aiPrompt[]" data-target="reallifeapplication"><span onclick="getAIoutput('reallifeapplication');" style="padding:2px 6px"><i class="mdi mdi-refresh"></i></span></div>
                            <textarea class="form-control tinymce" placeholder="Enter Real life application" name="reallifeapplication"
                                id="reallifeapplication" cols="60" rows="4">{{ $data['lessonplan_data']->reallifeapplication }}</textarea>
                        </div>
                    </div>
                    <div class="col-md-12 form-group">
                        <center>
                            <input type="submit" name="submit" value="Save" class="btn btn-success" style="{{$readonly ?? ''}}">
                        </center>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@include('includes.lmsfooterJs')
<script src="//cdnjs.cloudflare.com/ajax/libs/moment.js/2.9.0/moment-with-locales.js"></script>
<script
    src="//cdn.rawgit.com/Eonasdan/bootstrap-datetimepicker/e8bddc60e73c1ec2475f827be36e1957af72e2ea/src/js/bootstrap-datetimepicker.js">
</script>
<script src="{!! url('js/quill.js') !!}"></script>
<script src="{!! url('js/tinymce.min.js') !!}"></script>
{{-- TinyMCE Editior Script --}}
<script type="text/javascript">
    tinymce.init({
        selector: 'textarea.tinymce',
        promotion: false
    });
</script>
<script type="text/javascript">
    $(document).ready(function() {
        let day = 0;
        var classroomactivity = "{{ $data['lessonplan_data']->classroomactivity }}";
        var selfstudyactivity = "{{ $data['lessonplan_data']->selfstudyactivity }}";
        var assessmentactivity = "{{ $data['lessonplan_data']->assessmentactivity }}";
        classroomactivity = classroomactivity.split(',') ?? [];
        selfstudyactivity = selfstudyactivity.split(',') ?? [];
        assessmentactivity = assessmentactivity.split(',') ?? [];
        day = parseInt($('#day_count').val());
            $('#day_count').val(day);
            let id = $('#id').val();
            dayWiseDiv(day = 1, id);
        $(document).on('click', '.add-day', function() {
    
            $('#day_mdl').toggle();
        })

        $(document).on('click', '.add-day-mdl', function() {
            day = parseInt($('#day_count').val());
            day += 1;
            $('#day_count').val(day);
            dayWiseDiv(day);
        })

        $(document).on('click', '.remove-day', function() {
            let day_no = $(this).data('id');
            $('#day_' + day_no).remove();
        })

        $(document).on('submit', '#addLessonPlan', function(e) {
            e.preventDefault();
            var formData = new FormData(this);
            $.ajax({
                url: "{{ route('lms_lessonplan.store') }}",
                type: "POST",
                data: formData,
                dataType: "json",
                processData: false,
                contentType: false,
                success: function(result) {
                    if (result.status_code == 1) {
                        window.location.href = result.url;
                    }
                },
                error: function(errors, errResponse, err) {
                    console.error(errors);
                    $.each(errors.responseJSON.errors, function(field, val) {
                        $.each(val, function(i, value) {
                            $(`<span class="text-danger">` + value +
                                    `</span>`)
                                .insertAfter('#' +
                                    field);
                        })
                    })
                }
            });
        })

        $(document).on('change', '.classroomactivity', function(e) {
            var checked = this.checked;
            if (this.checked) {
                classroomactivity.push($(this).val());
            } else {
                classroomactivity.splice(classroomactivity.indexOf($(this).val()), 1);
            }
        });
        $(document).on('change', '.selfstudyactivity', function(e) {
            var checked = this.checked;
            if (this.checked) {
                selfstudyactivity.push($(this).val());
            } else {
                selfstudyactivity.splice(selfstudyactivity.indexOf($(this).val()), 1);
            }
        });
        $(document).on('change', '.assessmentactivity', function(e) {
            var checked = this.checked;
            if (this.checked) {
                assessmentactivity.push($(this).val());
            } else {
                assessmentactivity.splice(assessmentactivity.indexOf($(this).val()), 1);
            }
        });
        $(document).on('click', '.btn-close', function(e) {
            $('#contentMasterMdl').toggle();
        });
        $(document).on('click', '.btn-close-day', function(e) {
            $('#day_mdl').toggle();
        });
        $(document).on('click', '.add_activity', function(e) {
            var type = $(this).attr('id');
            var day = $(this).attr('data-id');
            $('#contentMasterMdl').toggle();
            $('.activityData').hide();
            $('#add_' + type).show();
            let standard_id = $('#standard_id').val();
            let chapter_id = $('#chapter_id').val();
            let subject_id = $('#subject_id').val();
            let topic_id = $('#topic_id').val();
            let url = "{{ route('ajax_contentmasterdata') }}";
            if (type == 'assessmentactivity') {
                url = "{{ route('ajax_questionpaperdata') }}";
            }
            $.ajax({
                url: url,
                type: "GET",
                data: {
                    standard_id: standard_id,
                    chapter_id: chapter_id,
                    subject_id: subject_id,
                    topic_id: topic_id
                },
                success: function(result) {
                    var html = '';
                    result.forEach(element => {
                        if (type == 'classroomactivity') {
                            var checked = classroomactivity.some(item => item ==
                                element
                                .id) ? 'checked' : '';
                            $('.modal-title').html('Classroom Activity');
                        } else if (type == 'selfstudyactivity') {
                            var checked = selfstudyactivity.some(item => item ==
                                element
                                .id) ? 'checked' : '';
                            $('.modal-title').html('Self study & Activity');
                        } else if (type == 'assessmentactivity') {
                            var checked = assessmentactivity.some(item =>
                                item ==
                                element
                                .id) ? 'checked' : '';
                            $('.modal-title').html('Assessment Activity');
                        }
                        html +=
                            `<div class="form-group"><input type="checkbox" name="` +
                            type + `[]" id="" ` +
                            checked +
                            ` value="` + element
                            .id + `" class="` + type + `"> <span>` + element.title +
                            `</span></div>`;
                    });
                    $('#add_' + type).html(html);
                },
                error: function(errors, errResponse, err) {
                    console.error(errors);
                }
            });
        });
        @if(isset($_REQUEST['chapter_id']))
            all_input();
        @endif
      
    })
    function get_chapters() {
            // api/get-chapter-list
            $('#chapter').empty();
            var subject = $("#subject").val();
            var standard = $("#standard").val();

            // START Bind subject-wise chapter
            var getchapter_path = "{{ route('ajax_LMS_SubjectwiseChapter') }}";
            $('#chapter').find('option').remove().end().append('<option value="">Select Chapter</option>').val('');
            $.ajax({
                url:getchapter_path,
                data:'sub_id='+subject+'&std_id='+standard,
                success:function(result)
                {
                    for(var i=0;i < result.length;i++){
                        $("#chapter").append($("<option></option>").val(result[i]['id']).html(result[i]['chapter_name']));
                    }
                }
            });
              
        }

        function get_topic() {
            var chapter_id = $("#chapter").val();
                var path = "{{ route('ajax_LMS_ChapterwiseTopic') }}";

                $('#topic').find('option').remove().end().append('<option value="">Select Topic</option>').val('');

                $.ajax({
                    url: path,
                    data:'chapter_id='+chapter_id,
                    success: function(result){
                    for(var i=0;i < result.length;i++){
                        $("#topic").append($("<option></option>").val(result[i]['id']).html(result[i]['name']));
                    }
                }
                });

                @if(!isset($_REQUEST['id']))
                all_input();
                @endif 
        }

        function topic_change(){
            var selectedTopic = $('#topic option:selected').text();
            @if(!isset($_REQUEST['id']))
            all_input();                
            @endif  
        }
      
        // get chat data 
        function getAIoutput(selectedId){
            var selectedStd = $('#standard option:selected').text();
            var selectedSub = $('#subject option:selected').text();
            var selectedChap = $('#chapter option:selected').text();
            var selectedTopic = $('#topic option:selected').text();     
            var inputValue = $('input[data-target="' + selectedId + '"]').val();
            $.ajax({
            type: 'get',  // Change to 'GET' if needed
            url: '{{route("get_chat_data")}}',  // Specify your AJAX handler URL
            data: {standard:selectedStd,subject:selectedSub,chapter:selectedChap,topic:selectedTopic,prompt:inputValue},
            success: function(response) {
                if (response['error']) {
                    alert(response['error']);
                }else{
                    $('#'+selectedId).empty();                    
                    $('#' + selectedId).val(response);
                }
            },
            error: function(xhr, status, error) {
                 error =1;
                 alert(JSON.parse(xhr.responseText).message);
            }
            });
        }
           
    function all_input() {
        var inputData = {};
        var selectedStd = $('#standard option:selected').text();
        var selectedSub = $('#subject option:selected').text();
        var selectedChap = $('#chapter option:selected').text();
        var selectedTopic = $('#topic option:selected').text();
        var error = 0;
        var msg = '';

        var ajaxRequests = [];

        $('input[name^="aiPrompt\\[\\]"]').each(function () {
            var inputName = $(this).attr('name');
            var inputId = $(this).attr('data-target');
            var inputValue = $(this).val();

            var ajaxPromise = $.ajax({
                type: 'get',
                url: '{{route("get_chat_data")}}',
                data: {standard: selectedStd, subject: selectedSub, chapter: selectedChap, topic: selectedTopic, prompt: inputValue},
                success: function (response) {
                    if (response['error']) {
                        error = 1;
                        msg = response['error'];
                    } else {
                        $('#' + inputId).empty();
                        $('#' + inputId).val(response);
                    }
                },
                error: function (xhr, status, error) {
                    error = 1;
                    msg = JSON.parse(xhr.responseText).message;
                }
            });

            ajaxRequests.push(ajaxPromise);
        });

        // Wait for all AJAX requests to complete
        $.when.apply($, ajaxRequests).then(function () {
            // Now error and msg will be the values from the last completed AJAX request
            if (error == 1) {
                alert(msg);
            }
        });
    }

    function dayWiseDiv(day = 1, id = null) {
        var standard_id = $('#standard option:selected').val();
        var subject_id = $('#subject option:selected').val();
        var chapter_id = $('#chapter option:selected').val();
        $('#msg_day').append(`Please Wait a moment !`);
        $.ajax({
            url: "{{ route('ajax_daywisedata') }}",
            type: "GET",
            data: {
                day: day,
                id: id,
                standard_id: standard_id,
                chapter_id: chapter_id,
                subject_id: subject_id
            },
            success: function(result) {
                $('#msg_day').empty();                
                $('#daywise').append(result);
                $('#day_count').val(day);
            },
            error: function(errors, errResponse, err) {
                console.error(errors);
            }
        });
    } 
    
</script>

@include('includes.footer')
@endsection
