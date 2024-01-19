{{--@include('includes.lmsheadcss')--}}
@extends('lmslayout')
@section('container')

<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.2/dist/umd/popper.min.js" integrity="sha384-IQsoLXl5PILFhosVNubq5LC7Qb9DXgDA9i+tQ8Zj3iwWAwPtgFTxbJ8NT4GN1R8p" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.min.js" integrity="sha384-cVKIPhGWiC2Al4u+LWgxfKTRIcfu0JTxR+EQDz/bgldoEyl4H0zUF0QKbrJ0EcQF" crossorigin="anonymous"></script>

<link href="{{ asset('css/style.css') }}" rel="stylesheet" />
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
      @php
        if(isset($_REQUEST['preload_lms'])){
            $preload_lms="preload_lms=preload_lms";
        }
      @endphp
        @if ($data['lessonplan_data']->id)
        <div class="col-md-3 mb-4 text-md-right">
            <a href="{{ route('lms_lessonplan.create', ['id' => $data['lessonplan_data']->id,$preload_lms ?? '']) }}" class="btn btn-info add-new"><i class="fa fa-plus"></i>Edit Form</a>
        </div>
        @else
        <div class="col-md-3 mb-4 text-md-right" style="@php echo $readonly ?? '' @endphp">
            <a href="{{ route('lms_lessonplan.create', ['standard_id' => $data['lessonplan_data']->standard_id, 'subject_id' => $data['lessonplan_data']->subject_id, 'chapter_id' => $data['lessonplan_data']->chapter_id]) }}" class="btn btn-info add-new"><i class="fa fa-plus"></i>Add Lesson Plan</a>
        </div>
        @endif
    </div>
</div>

<div class="content-main flex-fill">
<div class="container-fluid mb-5">
    <div class="card border-0">
        <div class="card-body">
            <!-- section start  -->
            <section class="tbl__container" style="margin-left:6% !important">
                <div class="tbl__header">
                    <h1>Lesson Plan</h1>
                </div>
                <div class="tbl__box">
                    <svg width="18" height="18" class="expand-all" viewBox="0 0 256 256" xml:space="preserve" onclick="handleExpandAll()">
                        <defs></defs>
                        <g style="stroke: none;stroke-width: 0;stroke-dasharray: none;stroke-linecap: butt;stroke-linejoin: miter;stroke-miterlimit: 10;fill: none;fill-rule: nonzero;opacity: 1;" transform="translate(1.4065934065934016 1.4065934065934016) scale(2.81 2.81)">
                            <path d="M 13.657 8 h 5.021 c 2.209 0 4 -1.791 4 -4 s -1.791 -4 -4 -4 H 4 C 3.984 0 3.968 0.005 3.952 0.005 C 3.706 0.008 3.46 0.031 3.217 0.079 c -0.121 0.024 -0.233 0.069 -0.35 0.104 C 2.734 0.222 2.6 0.252 2.47 0.306 c -0.132 0.055 -0.252 0.13 -0.377 0.198 c -0.104 0.057 -0.213 0.103 -0.312 0.17 C 1.58 0.808 1.395 0.963 1.222 1.13 C 1.206 1.145 1.187 1.156 1.171 1.171 C 1.155 1.188 1.145 1.207 1.129 1.224 C 0.962 1.396 0.808 1.58 0.674 1.78 c -0.07 0.104 -0.118 0.216 -0.176 0.325 C 0.432 2.226 0.359 2.341 0.306 2.469 c -0.057 0.137 -0.09 0.279 -0.13 0.42 C 0.144 2.998 0.102 3.103 0.079 3.216 C 0.028 3.475 0 3.738 0 4.001 v 14.677 c 0 2.209 1.791 4 4 4 s 4 -1.791 4 -4 v -5.021 l 23.958 23.958 c 0.781 0.781 1.805 1.171 2.829 1.171 s 2.047 -0.391 2.829 -1.171 c 1.562 -1.563 1.562 -4.095 0 -5.657 L 13.657 8 z" style="stroke: none;stroke-width: 1;stroke-dasharray: none;stroke-linecap: butt;stroke-linejoin: miter;stroke-miterlimit: 10;fill: rgb(0, 0, 0);fill-rule: nonzero;opacity: 1;
                        " transform=" matrix(1 0 0 1 0 0) " stroke-linecap="round" />
                            <path d="M 86 67.321 c -2.209 0 -4 1.791 -4 4 v 5.022 L 58.042 52.386 c -1.561 -1.563 -4.096 -1.563 -5.656 0 c -1.563 1.562 -1.563 4.095 0 5.656 L 76.344 82 h -5.022 c -2.209 0 -4 1.791 -4 4 s 1.791 4 4 4 H 86 c 0.263 0 0.525 -0.028 0.783 -0.079 c 0.117 -0.023 0.226 -0.067 0.339 -0.101 c 0.137 -0.04 0.275 -0.072 0.408 -0.127 c 0.133 -0.055 0.254 -0.131 0.38 -0.2 c 0.103 -0.056 0.21 -0.101 0.308 -0.167 c 0.439 -0.293 0.815 -0.67 1.109 -1.109 c 0.065 -0.097 0.109 -0.201 0.164 -0.302 c 0.07 -0.128 0.147 -0.251 0.203 -0.386 c 0.055 -0.132 0.086 -0.269 0.126 -0.405 c 0.034 -0.114 0.078 -0.223 0.101 -0.341 C 89.972 86.525 90 86.263 90 86 V 71.321 C 90 69.112 88.209 67.321 86 67.321 z" style="stroke: none;stroke-width: 1;stroke-dasharray: none;stroke-linecap: butt;stroke-linejoin: miter;stroke-miterlimit: 10;fill: rgb(0, 0, 0);fill-rule: nonzero;opacity: 1;" transform=" matrix(1 0 0 1 0 0) " stroke-linecap="round" />
                            <path d="M 31.958 52.386 L 8 76.343 v -5.022 c 0 -2.209 -1.791 -4 -4 -4 s -4 1.791 -4 4 v 14.677 c 0 0.263 0.028 0.526 0.079 0.785 c 0.023 0.113 0.065 0.218 0.097 0.328 c 0.041 0.141 0.074 0.283 0.13 0.419 C 0.36 87.66 0.434 87.777 0.5 87.899 c 0.058 0.107 0.105 0.218 0.174 0.32 c 0.145 0.217 0.31 0.42 0.493 0.604 c 0.002 0.002 0.003 0.004 0.004 0.005 c 0 0 0 0 0 0 c 0.186 0.186 0.391 0.352 0.61 0.498 c 0.1 0.067 0.208 0.112 0.312 0.169 c 0.125 0.068 0.244 0.143 0.377 0.198 c 0.134 0.055 0.273 0.087 0.411 0.128 c 0.112 0.033 0.22 0.076 0.336 0.099 C 3.475 89.972 3.737 90 4 90 h 14.679 c 2.209 0 4 -1.791 4 -4 s -1.791 -4 -4 -4 h -5.022 l 23.958 -23.958 c 1.562 -1.562 1.562 -4.095 0 -5.656 C 36.052 50.823 33.52 50.823 31.958 52.386 z" style="stroke: none;stroke-width: 1;
                            stroke-dasharray: none;stroke-linecap: butt;stroke-linejoin: miter;stroke-miterlimit: 10;fill: rgb(0, 0, 0);fill-rule: nonzero;opacity: 1;" transform=" matrix(1 0 0 1 0 0) " stroke-linecap="round" />
                            <path d="M 89.921 3.217 c -0.023 -0.118 -0.067 -0.227 -0.101 -0.34 c -0.04 -0.136 -0.071 -0.274 -0.126 -0.406 c -0.056 -0.134 -0.132 -0.256 -0.201 -0.382 c -0.056 -0.102 -0.101 -0.209 -0.167 -0.307 c -0.147 -0.219 -0.313 -0.424 -0.498 -0.61 c 0 0 0 0 0 0 c -0.002 -0.002 -0.004 -0.003 -0.005 -0.004 c -0.184 -0.184 -0.387 -0.348 -0.604 -0.493 c -0.101 -0.068 -0.21 -0.114 -0.316 -0.171 C 87.78 0.435 87.661 0.36 87.53 0.306 c -0.131 -0.054 -0.267 -0.085 -0.401 -0.125 c -0.116 -0.034 -0.226 -0.079 -0.346 -0.102 c -0.242 -0.048 -0.489 -0.071 -0.735 -0.074 C 86.032 0.005 86.016 0 86 0 H 71.321 c -2.209 0 -4 1.791 -4 4 s 1.791 4 4 4 h 5.022 L 52.386 31.958 c -1.563 1.563 -1.563 4.095 0 5.657 c 0.78 0.781 1.805 1.171 2.828 1.171 s 2.048 -0.391 2.828 -1.171 L 82 13.657 v 5.022 c 0 2.209 1.791 4 4 4 s 4 -1.791 4 -4 V 4 C 90 3.737 89.972 3.475 89.921 3.217 z" style="stroke: none;stroke-width: 1;stroke-dasharray: none;stroke-linecap: butt;stroke-linejoin: miter;stroke-miterlimit: 10;fill: rgb(0, 0, 0);fill-rule: nonzero;opacity: 1;" transform=" matrix(1 0 0 1 0 0) " stroke-linecap="round" />
                        </g>
                    </svg>
                    <table>
                        <thead>
                            <tr class="table-head">
                                <th>
                                    <div>
                                        <p>Summary of lesson plan</p>
                                        <img onclick="handleAddAll(0)" src="{{ asset('admin_dep/images/expand.svg') }}" />
                                    </div>
                                </th>
                                <th>
                                    <div>
                                        <p>Teaching</p>
                                        <img onclick="handleAddAll(1)" src="{{ asset('admin_dep/images/expand.svg') }}" />
                                    </div>
                                </th>
                                <th>
                                    <div>
                                        <p>Learning</p>
                                        <img onclick="handleAddAll(2)" src="{{ asset('admin_dep/images/expand.svg') }}" />
                                    </div>
                                </th>
                                <th>
                                    <div>
                                        <p>Day Planning</p>
                                        <img onclick="handleAddAll(3)" src="{{ asset('admin_dep/images/expand.svg') }}" />
                                    </div>
                                </th>
                                <th>
                                    <div>
                                        <p>Map & alignment</p>
                                        <img onclick="handleAddAll(4)" src="{{ asset('admin_dep/images/expand.svg') }}" />
                                    </div>
                                </th>
                            </tr>
                        </thead>

                        <tbody id="table-body" style="max-height:340px !important">
                            @php 
                            $selfstudyact = [];
                            $count_days = count($data['lessonplan_data']['lessonDays'] ?? []);
                            $day1 = $count_days >= 1 ? 'Day 1' : '';
                            $day2 = $count_days >= 2 ? 'Day 2' : '';
                            $day3 = $count_days >= 3 ? 'Day 3' : ''; 
                            $day4 = $count_days >= 4 ? 'Day 4' : '';
                            $day5 = $count_days >= 5 ? 'Day 5' : '';
                            $day6 = $count_days >= 6 ? 'Day 6' : '';
                            $day7 = $count_days >= 7 ? 'Day 7' : '';
                            $day8 = $count_days >= 8 ? 'Day 8' : '';
                            $day9 = $count_days >= 9 ? 'Day 9' : '';
                            $day10 = $count_days >= 10 ? 'Day 10' : '';
                            $day11 = $count_days >= 11 ? 'Day 11' : '';
                            $day12 = $count_days >= 12 ? 'Day 12' : '';

                                $accordation_title = [
                                ['standard'=>'Standard','focauspoint'=>'Focus Point','learningobjective'=>'Learning Objective','lesson_days'=>$day1,'Hard Word With Image'],
                                ['subject'=>'Subject','pedagogicalprocess'=>'Pedagogical process','learningknowledge'=>'Learning outcome : knowledge','lesson_days'=>$day2,'tagmetatag'=>'Tag & Metatag'],
                                ['chapter'=>'Chapter','resource'=>'Resource','learningskill'=>'Learning outcome : skill','lesson_days'=>$day3,'valueintegration'=>'Value integrations'],
                                ['numberofperiod'=>'Number of period','classroompresentation'=>'Classroom presentation','prerequisite'=>'Prerequisite Lessons','lesson_days'=>$day4,'globalconnection'=>'Global connection'],
                                ['teachingtime'=>'Teaching time','classroomdiversity'=>'Classroom diversity','selfstudyhomework'=>'Self-Study & Homework','lesson_days'=>$day5,'crosscurriculum'=>'Cross curriculum'],
                                ['assessmenttime'=>'Assessment time','','assessment'=>'Assessment','lesson_days'=>$day6,'sel'=>'SEL ( Social & emotional learning)'],
                                ['learningtime'=>'Learning Time','','','lesson_days'=>$day7,'stem'=>'STEM'],
                                ['assessmentqualifying'=>'Assessment Qualifying','','','lesson_days'=>$day8,'vocationaltraining'=>'Vocational training'],
                                ['','','','lesson_days'=>$day9,'simulation'=>'Simulation'],
                                ['','','','lesson_days'=>$day10,'games'=>'Games'],
                                ['','','','lesson_days'=>$day11,'activities'=>'Activities'],
                                ['','','','lesson_days'=>$day12,'reallifeapplication'=>'Real life application']];
                                $i=0;
                            @endphp
                            @if(!empty($data['lessonplan_data']))
                            @foreach($accordation_title as $key => $value)
                            <tr>
                                @foreach($value as $key1 => $value1)                            
                                <td data-key="{{$key1}}" data-count="{{$i}}"><a onclick="get_accordation('{{$key1}}','{{$value1}}','{{$i}}','single');">{{$value1}}</a></td>
                                @php
                                $selfstudyact_data = isset($data['lessonplan_data']['lessonDays'][$i]['selfstudyactivity']) ? $data['lessonplan_data']['lessonDays'][$i]['selfstudyactivity'] ?? 0 : 0;
                                    $selfstudyact[$i] = DB::table('content_master')
                                    ->whereIn('id', explode(',', $selfstudyact_data)) ->where('sub_institute_id', session()->get('sub_institute_id')) ->where('syear', session()->get('syear'))
                                    ->selectRaw('id, title, file_folder, filename')
                                    ->get()
                                    @endphp
                                @endforeach                                                               
                            <tr>
                            @php $i++ @endphp
                            @endforeach
                            @endif                              
                        </tbody>
                    </table>
                </div>
                <div id="accordion-container" class="accordion-container">
                
                </div>
            </section>
            <!-- section ends -->
        </div>
    </div>        
</div>

<script>
    function get_accordation(key,value,count='',single=''){
        // alert(value);
        if(single!=='' || value ==''){
            $('#accordion-container').empty();
        }
      
        var lessonplan_data = "{{ $data['lessonplan_data'] }}";
            lessonplan_data = lessonplan_data.replace(/&quot;/ig, '"');
            lessonplan_data = JSON.parse(lessonplan_data);
            var data = ''
            if(value=="Standard"){
                var data = lessonplan_data[key]['name'];
            }
            else if(value=="Subject" || value=="Chapter"){
                name = key+'_name';
                var data = lessonplan_data[key][name];
            }else if(key=='lesson_days'){
               var count_day = lessonplan_data.lesson_days[count];
               var selfstudyact_main = @json($selfstudyact);
               var selfstudyact=selfstudyact_main[[count]];
                var data = `<table>
                                <tr><td><b>Topic:</b> ` + count_day.topicname + `</td></tr>
                                <tr><td><b>Class Time:</b> ` + count_day.classtime + `</td></tr>
                                <tr><td><b>During Content:</b> ` + count_day.duringcontent + `</td></tr>
                                <tr><td><b>Objective:</b> ` + count_day.assessmentqualifying + `</td></tr>
                                <tr><td><b>Class Time:</b> ` + count_day.learningobjective + `</td></tr>
                                <tr><td><b>Learning Outcome:</b> ` + count_day.learningoutcome + `</td></tr>
                                <tr><td><b>Pedagogical process:</b> ` + count_day
                        .pedagogicalprocess + `</td></tr>
                                <tr><td><b>Resource:</b> ` + count_day.resource + `</td></tr>
                                <tr><td><b>Closure:</b> ` + count_day.closure + `</td></tr>
                                <tr><td><b>Self-study & Homework:</b> ` + count_day
                        .selfstudyhomework + `</td></tr>
                                <tr><td><b>Self-study & Activity:</b> ` +   (selfstudyact.length > 0 ?
                            selfstudyact.map(activity => `<div><a  style="color:black" target="_blank" href=${activity.file_folder}/${activity.filename}>${activity.title}</a></div>`).join('') :
                                'No activities'
                            )  + `</td></tr>
                            <tr><td><b>Assessment:</b> ` + count_day.assessment + `</td></tr>
                    </table>`;
                
            }else if (typeof lessonplan_data[key] !== 'undefined' && lessonplan_data[key] !== null && lessonplan_data[key] !== '') {
                var data = lessonplan_data[key];
            }else{
                var data = 'No Data Added For '+value;
            }
  
        if(key!="no_data"){
        $('#accordion-container').append(`<div class="accordion accordion-flush" id="accordionFlushExample_`+key+`_`+count+`">
            <div class="accordion-item">
            <h2 class="accordion-header" id="flush-headingOne_`+key+`_`+count+`">
            <b class="accordion-button collapsed_`+key+`_`+count+`" type="button" data-bs-toggle="collapse" data-bs-target="#flush-collapseOne_`+key+`_`+count+`" aria-expanded="false" aria-controls="flush-collapseOne_`+key+`_`+count+`">
            <i class="fa fa-angle-right" aria-hidden="true"></i> `+value+`</b>
            </h2>
            <div id="flush-collapseOne_`+key+`_`+count+`" class="accordion-collapse collapse" aria-labelledby="flush-headingOne_`+key+`_`+count+`" data-bs-parent="#accordionFlushExample_`+key+`_`+count+`">
                <div class="accordion-body m-3">`+data+`</div>
                </div>
            </div>
        </div>`);
        }
    }

function handleAddAll(columnIndex) {
    $('#accordion-container').empty();
    $('#table-body tr').each(function() {
        var cellVal = $(this).find('td:eq('+columnIndex+')').text();
        var cellKey = $(this).find('td:eq('+columnIndex+')').attr('data-key');
        var cellCount = $(this).find('td:eq('+columnIndex+')').attr('data-count');
        console.log(cellVal);
        if(typeof cellVal  !== 'undefined' && cellVal !== '' && cellVal !== null && cellKey != 0){
            get_accordation(cellKey,cellVal,cellCount,'');
            // console.log(cellVal+'-key-'+cellKey+'-count-'+cellCount);
        }
    });

}

function handleExpandAll(){
    $('#accordion-container').empty();
    $('#table-body tr').each(function (rowIndex) {
        $(this).find('td').each(function (columnIndex) {
            var cellVal = $(this).text();
            var cellKey = $(this).attr('data-key');
            var cellCount = $(this).attr('data-count');

            if (typeof cellVal !== 'undefined' && cellVal !== '' && cellVal !== null && cellKey !== 0) {
                get_accordation(cellKey, cellVal, cellCount, '');
                // console.log(cellVal + '-key-' + cellKey + '-count-' + cellCount);
            }
        });
    });
}

</script>
@include('includes.lmsfooterJs')
@include('includes.footer')
@endsection