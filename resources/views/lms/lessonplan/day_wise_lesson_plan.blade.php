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
                <label>Topic name</label>
                <input type="text" name="topicname[{{ $value->days }}]" id="topicname"
                    value="{{ $value->topicname }}" class="form-control" placeholder="Enter Topic Name">
            </div>
            <div class="col-md-6 form-group">
                <label>Class Time</label>
                <input type="number" name="classtime[{{ $value->days }}]" id="classtime"
                    value="{{ $value->classtime }}" class="form-control" placeholder="Enter Class Time (in minutes)">
            </div>
            <div class="col-md-6 form-group">
                <label>During Content</label>
                <textarea name="duringcontent[{{ $value->days }}]" id="duringcontent" class="form-control"
                    value="{{ $value->duringcontent }}" placeholder="Enter During Content" col="3" row="2"></textarea>
            </div>
            <div class="col-md-6 form-group">
                <label>Assessment Qualifying</label>
                <textarea name="assessmentqualifyingday[{{ $value->days }}]" id="assessmentqualifyingday" class="form-control"
                    placeholder="Enter Assessment Qualifying" col="3" row="2">{{ $value->assessmentqualifying }}</textarea>
            </div>
            <div class="col-md-6 form-group">
                <label>Objective</label>
                <textarea name="learningobjectiveday[{{ $value->days }}]" id="learningobjectiveday" class="form-control"
                    placeholder="Enter Objective" col="3" row="2">{{ $value->learningobjective }}</textarea>
            </div>
            <div class="col-md-6 form-group">
                <label>Learning Outcome</label>
                <textarea name="learningoutcome[{{ $value->days }}]" id="learningoutcome" class="form-control"
                    placeholder="Enter Learning Outcome" col="3" row="2">{{ $value->learningoutcome }}</textarea>
            </div>
            <div class="col-md-6 form-group">
                <label>Pedagogical process</label>
                <textarea name="pedagogicalprocessday[{{ $value->days }}]" id="pedagogicalprocessday" class="form-control"
                    placeholder="Enter Pedagogical process" col="3" row="2">{{ $value->pedagogicalprocess }}</textarea>
            </div>
            <div class="col-md-6 form-group">
                <label>Resource</label>
                <textarea name="resourceday[{{ $value->days }}]" id="resourceday" class="form-control" placeholder="Enter Resource"
                    col="3" row="2">{{ $value->resource }}</textarea>
            </div>
            <div class="col-md-6 form-group">
                <label>Closure</label>
                <textarea name="closure[{{ $value->days }}]" id="closure" class="form-control" placeholder="Enter Closure"
                    col="3" row="2">{{ $value->closure }}</textarea>
            </div>
            <div class="col-md-6 form-group">
                <label>Self-study & Homework</label>
                <textarea name="selfstudyhomeworkday[{{ $value->days }}]" id="selfstudyhomeworkday" class="form-control"
                    placeholder="Enter Self-study & Homework" col="3" row="2">{{ $value->selfstudyhomework }}</textarea>
            </div>
            <div class="col-md-12 form-group scroll">
                <label for="">Self-study Activity</label>
                @foreach ($content_master as $item)
                    <div class="form-group"><input type="checkbox"
                            name="selfstudyactivityday[{{ $value->days }}][]" {{ in_array($item->id, explode(',',$value->selfstudyactivity)) ? 'checked' : '' }} id=""
                            value="{{ $item->id }}" class="selfstudyactivityday"> <span>{{ $item->title }}</span>
                    </div>
                @endforeach
            </div>
            <div class="col-md-6 form-group">
                <label>Assessment</label>
                <textarea name="assessmentday[{{ $value->days }}]" id="assessmentday" class="form-control"
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
                <label>Topic name</label>
                <input type="text" name="topicname[{{ $day }}]" id="topicname" class="form-control"
                    placeholder="Enter Topic Name">
            </div>
            <div class="col-md-6 form-group">
                <label>Class Time</label>
                <input type="number" name="classtime[{{ $day }}]" id="classtime" class="form-control"
                    placeholder="Enter Class Time (in minutes)">
            </div>
            <div class="col-md-6 form-group">
                <label>During Content</label>
                <textarea name="duringcontent[{{ $day }}]" id="duringcontent" class="form-control"
                    placeholder="Enter During Content" col="3" row="2"></textarea>
            </div>
            <div class="col-md-6 form-group">
                <label>Assessment Qualifying</label>
                <textarea name="assessmentqualifyingday[{{ $day }}]" id="assessmentqualifyingday" class="form-control"
                    placeholder="Enter Assessment Qualifying" col="3" row="2"></textarea>
            </div>
            <div class="col-md-6 form-group">
                <label>Objective</label>
                <textarea name="learningobjectiveday[{{ $day }}]" id="learningobjectiveday" class="form-control"
                    placeholder="Enter Objective" col="3" row="2"></textarea>
            </div>
            <div class="col-md-6 form-group">
                <label>Learning Outcome</label>
                <textarea name="learningoutcome[{{ $day }}]" id="learningoutcome" class="form-control"
                    placeholder="Enter Learning Outcome" col="3" row="2"></textarea>
            </div>
            <div class="col-md-6 form-group">
                <label>Pedagogical process</label>
                <textarea name="pedagogicalprocessday[{{ $day }}]" id="pedagogicalprocessday" class="form-control"
                    placeholder="Enter Pedagogical process" col="3" row="2"></textarea>
            </div>
            <div class="col-md-6 form-group">
                <label>Resource</label>
                <textarea name="resourceday[{{ $day }}]" id="resourceday" class="form-control"
                    placeholder="Enter Resource" col="3" row="2"></textarea>
            </div>
            <div class="col-md-6 form-group">
                <label>Closure</label>
                <textarea name="closure[{{ $day }}]" id="closure" class="form-control" placeholder="Enter Closure"
                    col="3" row="2"></textarea>
            </div>
            <div class="col-md-6 form-group">
                <label>Self-study & Homework</label>
                <textarea name="selfstudyhomeworkday[{{ $day }}]" id="selfstudyhomeworkday" class="form-control"
                    placeholder="Enter Self-study & Homework" col="3" row="2"></textarea>
            </div>
            @foreach ($content_master as $item)
                <div class="form-group"><input type="checkbox"
                        name="selfstudyactivityday[{{ $day }}][]" id=""
                        value="{{ $item->id }}" class="selfstudyactivityday"> <span>{{ $item->title }}</span>
                </div>
            @endforeach
            <div class="col-md-6 form-group">
                <label>Assessment</label>
                <textarea name="assessmentday[{{ $day }}]" id="assessmentday" class="form-control"
                    placeholder="Enter Assessment" col="3" row="2"></textarea>
            </div>
            <div class="col-md-12 form-group scroll">
                <label for="">Assessment Activity</label>
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
