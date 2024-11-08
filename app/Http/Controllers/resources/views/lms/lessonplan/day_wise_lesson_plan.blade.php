@forelse ($objDayWise as $value)
    <div id="day_{{ $value->id }}">
        <div class="row align-items-center  p-2">
            <div class="col-md-3">
                <h2 for="">Day : {{ $value->days ?? 1 }}
                </h2>
            </div>
            <div class="col-md-9">
                <button type="button" class="btn btn-danger remove-day" data-id="{{ $value->id }}"><i
                        class="fa fa-trash"></i></button>
            </div>
        </div>
        <div class="row align-items-center  p-2">
            <input type="hidden" name="days[{{ $value->days }}]" id="days" value="{{ $value->days }}">
            <div class="col-md-6 form-group">
                <label>Topic name  <input type="text" id="topic_input" value="get Topic name for Day {{ $value->days ?? 1 }} for students" style="padding:4px 2px;width:331px;border:1px solid #ddd" name="aiPrompt[]" data-target="topicname_{{ $value->days ?? 1 }}"><span onclick="getAIoutput('topicname_{{ $value->days ?? 1 }}');" style="padding:2px 6px"><i class="mdi mdi-refresh"></i></span></label>
                <input type="text" name="topicname[{{ $value->days }}]" id="topicname_{{ $value->days ?? 1 }}"
                    value="{{ $value->topicname }}" class="form-control" placeholder="Enter Topic Name">
            </div>
            <div class="col-md-6 form-group">
                <label>Class Time</label>
                <input type="number" name="classtime[{{ $value->days }}]" id="classtime_{{ $value->days ?? 1 }}"
                    value="{{ $value->classtime }}" class="form-control" placeholder="Enter Class Time (in minutes)">
            </div>
            <div class="col-md-6 form-group">
                <label>During Content  <input type="text" id="dc_input" value="get During Content for Day {{ $value->days ?? 1 }} for students" style="padding:4px 2px;width:331px;border:1px solid #ddd" name="aiPrompt[]" data-target="duringcontent_{{ $value->days }}"><span onclick="getAIoutput('duringcontent_{{ $value->days }}');" style="padding:2px 6px"><i class="mdi mdi-refresh"></i></span></label>
                <textarea name="duringcontent[{{ $value->days }}]" id="duringcontent_{{ $value->days }}" class="form-control"
                    value="{{ $value->duringcontent }}" placeholder="Enter During Content" col="3" row="2"></textarea>
            </div>
            <div class="col-md-6 form-group">
                <label>Assessment Qualifying  <input type="text" id="asd_input" value="get Assessment Qualifying for Day {{ $value->days ?? 1 }} for students to understand chapter" style="padding:4px 2px;width:331px;border:1px solid #ddd" name="aiPrompt[]" data-target="assessmentqualifyingday_{{ $value->days }}"><span onclick="getAIoutput('assessmentqualifyingday_{{ $value->days }}');" style="padding:2px 6px"><i class="mdi mdi-refresh"></i></span></label>
                <textarea name="assessmentqualifyingday[{{ $value->days }}]" id="assessmentqualifyingday_{{ $value->days }}" class="form-control"
                    placeholder="Enter Assessment Qualifying" col="3" row="2">{{ $value->assessmentqualifying }}</textarea>
            </div>
            <div class="col-md-6 form-group">
                <label>Objective  <input type="text" id="objd_input" value="get Objective for Day {{ $value->days ?? 1 }} for students to understand objective of topic and chapter" style="padding:4px 2px;width:331px;border:1px solid #ddd" name="aiPrompt[]" data-target="learningobjectiveday_{{ $value->days }}"><span onclick="getAIoutput('learningobjectiveday_{{ $value->days }}');" style="padding:2px 6px"><i class="mdi mdi-refresh"></i></span></label>
                <textarea name="learningobjectiveday[{{ $value->days }}]" id="learningobjectiveday_{{ $value->days }}" class="form-control"
                    placeholder="Enter Objective" col="3" row="2">{{ $value->learningobjective }}</textarea>
            </div>
            <div class="col-md-6 form-group">
                <label>Learning Outcome  <input type="text" id="lod_input" value="get Learning Outcome for Day {{ $value->days ?? 1 }} for students to learn topic and chapter" style="padding:4px 2px;width:331px;border:1px solid #ddd" name="aiPrompt[]" data-target="learningoutcome_{{ $value->days }}"><span onclick="getAIoutput('learningoutcome_{{ $value->days }}');" style="padding:2px 6px"><i class="mdi mdi-refresh"></i></span></label>
                <textarea name="learningoutcome[{{ $value->days }}]" id="learningoutcome_{{ $value->days }}" class="form-control"
                    placeholder="Enter Learning Outcome" col="3" row="2">{{ $value->learningoutcome }}</textarea>
            </div>
            <div class="col-md-6 form-group">
                <label>Pedagogical process  <input type="text" id="ppd_input" value="get Pedagogical process for Day {{ $value->days ?? 1 }} for students to understand process of topic and chapter" style="padding:4px 2px;width:331px;border:1px solid #ddd" name="aiPrompt[]" data-target="pedagogicalprocessday_{{ $value->days }}"><span onclick="getAIoutput('pedagogicalprocessday_{{ $value->days }}');" style="padding:2px 6px"><i class="mdi mdi-refresh"></i></span></label>
                <textarea name="pedagogicalprocessday[{{ $value->days }}]" id="pedagogicalprocessday_{{ $value->days }}" class="form-control"
                    placeholder="Enter Pedagogical process" col="3" row="2">{{ $value->pedagogicalprocess }}</textarea>
            </div>
            <div class="col-md-6 form-group">
                <label>Resource  <input type="text" id="red_input" value="get Resource for Day {{ $value->days ?? 1 }} for students to get resources for topic and chapter" style="padding:4px 2px;width:331px;border:1px solid #ddd" name="aiPrompt[]" data-target="resourceday_{{ $value->days }}"><span onclick="getAIoutput('resourceday_{{ $value->days }}');" style="padding:2px 6px"><i class="mdi mdi-refresh"></i></span></label>
                <textarea name="resourceday[{{ $value->days }}]" id="resourceday_{{ $value->days }}" class="form-control" placeholder="Enter Resource"
                    col="3" row="2">{{ $value->resource }}</textarea>
            </div>
            <div class="col-md-6 form-group">
                <label>Closure  <input type="text" id="clouser_input" value="get Closure for Day {{ $value->days ?? 1 }} for students to understand chapter" style="padding:4px 2px;width:331px;border:1px solid #ddd" name="aiPrompt[]" data-target="closure_{{ $value->days }}"><span onclick="getAIoutput('closure_{{ $value->days }}');" style="padding:2px 6px"><i class="mdi mdi-refresh"></i></span></label>
                <textarea name="closure[{{ $value->days }}]" id="closure_{{ $value->days }}" class="form-control" placeholder="Enter Closure"
                    col="3" row="2">{{ $value->closure }}</textarea>
            </div>
            <div class="col-md-6 form-group">
                <label>Self-study & Homework  <input type="text" id="sshd_input" value="get Self-study & Homework for Day {{ $value->days ?? 1 }} for students to self study and do homework and understand topic and chapter" style="padding:4px 2px;width:331px;border:1px solid #ddd" name="aiPrompt[]" data-target="selfstudyhomeworkday_{{ $value->days }}"><span onclick="getAIoutput('selfstudyhomeworkday_{{ $value->days }}');" style="padding:2px 6px"><i class="mdi mdi-refresh"></i></span></label>
                <textarea name="selfstudyhomeworkday[{{ $value->days }}]" id="selfstudyhomeworkday_{{ $value->days }}" class="form-control"
                    placeholder="Enter Self-study & Homework" col="3" row="2">{{ $value->selfstudyhomework }}</textarea>
            </div>
            <div class="col-md-12 form-group scroll">
                <label for="">Self-study Activity </label>
                @foreach ($content_master as $item)
                    <div class="form-group"><input type="checkbox"
                            name="selfstudyactivityday[{{ $value->days }}][]" {{ in_array($item->id, explode(',',$value->selfstudyactivity)) ? 'checked' : '' }} id=""
                            value="{{ $item->id }}" class="selfstudyactivityday"> <span>{{ $item->title }}</span>
                    </div>
                @endforeach
            </div>
            <div class="col-md-6 form-group">
                <label>Assessment  <input type="text" id="ad_input" value="get Assessment for Day {{ $value->days ?? 1 }} for students to do assments and understand topic and chapter" style="padding:4px 2px;width:331px;border:1px solid #ddd" name="aiPrompt[]" data-target="assessmentday_{{ $value->days }}"><span onclick="getAIoutput('assessmentday_{{ $value->days }}');" style="padding:2px 6px"><i class="mdi mdi-refresh"></i></span></label>
                <textarea name="assessmentday[{{ $value->days }}]" id="assessmentday_{{ $value->days }}" class="form-control"
                    placeholder="Enter Assessment" col="3" row="2">{{ $value->assessment }}</textarea>
            </div>
            <div class="col-md-12 form-group scroll">
                <label for="">Assessment Activity</label>
                @foreach ($question_master as $item)
                    <div class="form-group"><input type="checkbox"
                            name="assessmentactivityday[{{ $value->days }}][]" {{ in_array($item->id, explode(',',$value->assessmentactivity)) ? 'checked' : '' }}  id=""
                            value="{{ $item->id }}" class="assessmentactivityday">
                        <span>{{ $item->title }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
@empty
    <div id="day_{{ $day }}">
        <div class="row align-items-center  p-2">
            <div class="col-md-3">
                <h2 for="">Day : {{ $day }}
                </h2>
            </div>
            <div class="col-md-9">
                <button type="button" class="btn btn-danger remove-day" data-id="{{ $day }}"><i
                        class="fa fa-trash"></i></button>
            </div>
        </div>
        <div class="row align-items-center  p-2">
            <input type="hidden" name="days[{{ $day }}]" id="days" value="{{ $day }}">
            <div class="col-md-6 form-group">
                <label>Topic name <input type="text" id="topic_input" value="get Topic name for Day {{ $day }} for students" style="padding:4px 2px;width:331px;border:1px solid #ddd" name="aiPrompt[]" data-target="topicname_{{ $day }}"><span onclick="getAIoutput('topicname_{{ $day }}');" style="padding:2px 6px"><i class="mdi mdi-refresh"></i></span></label>
                <input type="text" name="topicname[{{ $day }}]" id="topicname_{{ $day }}" class="form-control"
                    placeholder="Enter Topic Name">
            </div>
            <div class="col-md-6 form-group">
                <label>Class Time</label>
                <input type="number" name="classtime[{{ $day }}]" id="classtime_{{ $day }}" class="form-control"
                    placeholder="Enter Class Time (in minutes)">
            </div>
            <div class="col-md-6 form-group">
                <label>During Content  <input type="text" id="dc_input" value="get During Content for Day {{ $day }} for students to understand topic and chapter" style="padding:4px 2px;width:331px;border:1px solid #ddd" name="aiPrompt[]" data-target="duringcontent_{{ $day }}"><span onclick="getAIoutput('duringcontent_{{$day }}');" style="padding:2px 6px"><i class="mdi mdi-refresh"></i></span></label>
                <textarea name="duringcontent[{{ $day }}]" id="duringcontent_{{ $day }}" class="form-control"
                    placeholder="Enter During Content" col="3" row="2"></textarea>
            </div>
            <div class="col-md-6 form-group">
                <label>Assessment Qualifying  <input type="text" id="asd_input" value="get Assessment Qualifying for Day {{ $day }} for students to qualifying and understrand topic and chapter" style="padding:4px 2px;width:331px;border:1px solid #ddd" name="aiPrompt[]" data-target="assessmentqualifyingday_{{ $day }}"><span onclick="getAIoutput('assessmentqualifyingday_{{ $day }}');" style="padding:2px 6px"><i class="mdi mdi-refresh"></i></span></label>
                <textarea name="assessmentqualifyingday[{{ $day }}]" id="assessmentqualifyingday_{{ $day }}" class="form-control"
                    placeholder="Enter Assessment Qualifying" col="3" row="2"></textarea>
            </div>
            <div class="col-md-6 form-group">
                <label>Objective  <input type="text" id="objd_input" value="get Objective for Day {{ $day }} for students to understand objective of topic and chapter" style="padding:4px 2px;width:331px;border:1px solid #ddd" name="aiPrompt[]" data-target="learningobjectiveday_{{ $day }}"><span onclick="getAIoutput('learningobjectiveday_{{$day }}');" style="padding:2px 6px"><i class="mdi mdi-refresh"></i></span></label>
                <textarea name="learningobjectiveday[{{ $day }}]" id="learningobjectiveday_{{ $day }}" class="form-control"
                    placeholder="Enter Objective" col="3" row="2"></textarea>
            </div>
            <div class="col-md-6 form-group">
                <label>Learning Outcome    <input type="text" id="lod_input" value="get Learning Outcome for Day {{$day }} for students to learn topic and chapter" style="padding:4px 2px;width:331px;border:1px solid #ddd" name="aiPrompt[]" data-target="learningoutcome_{{$day }}"><span onclick="getAIoutput('learningoutcome_{{$day }}');" style="padding:2px 6px"><i class="mdi mdi-refresh"></i></span></label>
                <textarea name="learningoutcome[{{ $day }}]" id="learningoutcome_{{ $day }}" class="form-control"
                    placeholder="Enter Learning Outcome" col="3" row="2"></textarea>
            </div>
            <div class="col-md-6 form-group">
                <label>Pedagogical process  <input type="text" id="ppd_input" value="get Pedagogical process for Day {{$day }} for students to understand process of topic and chapter" style="padding:4px 2px;width:331px;border:1px solid #ddd" name="aiPrompt[]" data-target="pedagogicalprocessday_{{ $day }}"><span onclick="getAIoutput('pedagogicalprocessday_{{$day }}');" style="padding:2px 6px"><i class="mdi mdi-refresh"></i></span></label>
                <textarea name="pedagogicalprocessday[{{ $day }}]" id="pedagogicalprocessday_{{ $day }}" class="form-control"
                    placeholder="Enter Pedagogical process" col="3" row="2"></textarea>
            </div>
            <div class="col-md-6 form-group">
                <label>Resource  <input type="text" id="red_input" value="get Resource for Day {{$day }} for students to understand topic and chapter" style="padding:4px 2px;width:331px;border:1px solid #ddd" name="aiPrompt[]" data-target="resourceday_{{$day }}"><span onclick="getAIoutput('resourceday_{{$day }}');" style="padding:2px 6px"><i class="mdi mdi-refresh"></i></span></label>
                <textarea name="resourceday[{{ $day }}]" id="resourceday_{{ $day }}" class="form-control"
                    placeholder="Enter Resource" col="3" row="2"></textarea>
            </div>
            <div class="col-md-6 form-group">
                <label>Closure  <input type="text" id="clouser_input" value="get Closure for Day {{ $day }} for students to understand topic and chapter" style="padding:4px 2px;width:331px;border:1px solid #ddd" name="aiPrompt[]" data-target="closure_{{ $day }}"><span onclick="getAIoutput('closure_{{$day }}');" style="padding:2px 6px"><i class="mdi mdi-refresh"></i></span></label>
                <textarea name="closure[{{ $day }}]" id="closure_{{ $day }}" class="form-control" placeholder="Enter Closure"
                    col="3" row="2"></textarea>
            </div>
            <div class="col-md-6 form-group">
                <label>Self-study & Homework  <input type="text" id="sshd_input" value="get Self-study & Homework for Day {{$day}} for students to do self study and homework and understand topic and chapter" style="padding:4px 2px;width:331px;border:1px solid #ddd" name="aiPrompt[]" data-target="selfstudyhomeworkday_{{$day }}"><span onclick="getAIoutput('selfstudyhomeworkday_{{$day}}');" style="padding:2px 6px"><i class="mdi mdi-refresh"></i></span></label>
                <textarea name="selfstudyhomeworkday[{{ $day }}]" id="selfstudyhomeworkday_{{ $day }}" class="form-control"
                    placeholder="Enter Self-study & Homework" col="3" row="2"></textarea>
            </div>
            @foreach ($content_master as $item)
                <div class="form-group"><input type="checkbox"
                        name="selfstudyactivityday[{{ $day }}][]" id=""
                        value="{{ $item->id }}" class="selfstudyactivityday_{{ $day }}"> <span>{{ $item->title }}</span>
                </div>
            @endforeach
            <div class="col-md-6 form-group">
                <label>Assessment  <input type="text" id="ad_input" value="get Assessment for Day {{ $day  }} for students to do assment and understand topic and chapter" style="padding:4px 2px;width:331px;border:1px solid #ddd" name="aiPrompt[]" data-target="assessmentday_{{ $day }}"><span onclick="getAIoutput('assessmentday_{{$day }}');" style="padding:2px 6px"><i class="mdi mdi-refresh"></i></span></label>
                <textarea name="assessmentday[{{ $day }}]" id="assessmentday_{{ $day }}" class="form-control"
                    placeholder="Enter Assessment" col="3" row="2"></textarea>
            </div>
            <div class="col-md-12 form-group scroll">
                <label for="">Assessment Activity </label>
                @foreach ($question_master as $item)
                    <div class="form-group"><input type="checkbox"
                            name="assessmentactivityday[{{ $day }}][]" id=""
                            value="{{ $item->id }}" class="assessmentactivityday">
                        <span>{{ $item->title }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
@endforelse
<script>
   $(document).ready(function () {
    @if(isset($id) && $id =='')
        all_input();                
    @endif  
    });
</script>