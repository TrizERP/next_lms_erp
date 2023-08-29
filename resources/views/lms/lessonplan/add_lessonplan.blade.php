@include('includes.lmsheadcss')
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

    .content-main {
        padding-bottom: 0px !important;
    }
   
</style>
@include('includes.header')
@include('includes.sideNavigation')
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

<section class="tbl__container">
    <div class="tbl__header">
        <h1>Lesson Plan</h1>
    </div>
    <div class="tbl__box">
        <svg width="18" height="18" class="expand-all" viewBox="0 0 256 256" xml:space="preserve" onclick="handleExpandAll()">
            <defs></defs>
            <g style="
              stroke: none;
              stroke-width: 0;
              stroke-dasharray: none;
              stroke-linecap: butt;
              stroke-linejoin: miter;
              stroke-miterlimit: 10;
              fill: none;
              fill-rule: nonzero;
              opacity: 1;
            " transform="translate(1.4065934065934016 1.4065934065934016) scale(2.81 2.81)">
                <path d="M 13.657 8 h 5.021 c 2.209 0 4 -1.791 4 -4 s -1.791 -4 -4 -4 H 4 C 3.984 0 3.968 0.005 3.952 0.005 C 3.706 0.008 3.46 0.031 3.217 0.079 c -0.121 0.024 -0.233 0.069 -0.35 0.104 C 2.734 0.222 2.6 0.252 2.47 0.306 c -0.132 0.055 -0.252 0.13 -0.377 0.198 c -0.104 0.057 -0.213 0.103 -0.312 0.17 C 1.58 0.808 1.395 0.963 1.222 1.13 C 1.206 1.145 1.187 1.156 1.171 1.171 C 1.155 1.188 1.145 1.207 1.129 1.224 C 0.962 1.396 0.808 1.58 0.674 1.78 c -0.07 0.104 -0.118 0.216 -0.176 0.325 C 0.432 2.226 0.359 2.341 0.306 2.469 c -0.057 0.137 -0.09 0.279 -0.13 0.42 C 0.144 2.998 0.102 3.103 0.079 3.216 C 0.028 3.475 0 3.738 0 4.001 v 14.677 c 0 2.209 1.791 4 4 4 s 4 -1.791 4 -4 v -5.021 l 23.958 23.958 c 0.781 0.781 1.805 1.171 2.829 1.171 s 2.047 -0.391 2.829 -1.171 c 1.562 -1.563 1.562 -4.095 0 -5.657 L 13.657 8 z" style="
                stroke: none;
                stroke-width: 1;
                stroke-dasharray: none;
                stroke-linecap: butt;
                stroke-linejoin: miter;
                stroke-miterlimit: 10;
                fill: rgb(0, 0, 0);
                fill-rule: nonzero;
                opacity: 1;
              " transform=" matrix(1 0 0 1 0 0) " stroke-linecap="round" />
                <path d="M 86 67.321 c -2.209 0 -4 1.791 -4 4 v 5.022 L 58.042 52.386 c -1.561 -1.563 -4.096 -1.563 -5.656 0 c -1.563 1.562 -1.563 4.095 0 5.656 L 76.344 82 h -5.022 c -2.209 0 -4 1.791 -4 4 s 1.791 4 4 4 H 86 c 0.263 0 0.525 -0.028 0.783 -0.079 c 0.117 -0.023 0.226 -0.067 0.339 -0.101 c 0.137 -0.04 0.275 -0.072 0.408 -0.127 c 0.133 -0.055 0.254 -0.131 0.38 -0.2 c 0.103 -0.056 0.21 -0.101 0.308 -0.167 c 0.439 -0.293 0.815 -0.67 1.109 -1.109 c 0.065 -0.097 0.109 -0.201 0.164 -0.302 c 0.07 -0.128 0.147 -0.251 0.203 -0.386 c 0.055 -0.132 0.086 -0.269 0.126 -0.405 c 0.034 -0.114 0.078 -0.223 0.101 -0.341 C 89.972 86.525 90 86.263 90 86 V 71.321 C 90 69.112 88.209 67.321 86 67.321 z" style="
                stroke: none;
                stroke-width: 1;
                stroke-dasharray: none;
                stroke-linecap: butt;
                stroke-linejoin: miter;
                stroke-miterlimit: 10;
                fill: rgb(0, 0, 0);
                fill-rule: nonzero;
                opacity: 1;
              " transform=" matrix(1 0 0 1 0 0) " stroke-linecap="round" />
                <path d="M 31.958 52.386 L 8 76.343 v -5.022 c 0 -2.209 -1.791 -4 -4 -4 s -4 1.791 -4 4 v 14.677 c 0 0.263 0.028 0.526 0.079 0.785 c 0.023 0.113 0.065 0.218 0.097 0.328 c 0.041 0.141 0.074 0.283 0.13 0.419 C 0.36 87.66 0.434 87.777 0.5 87.899 c 0.058 0.107 0.105 0.218 0.174 0.32 c 0.145 0.217 0.31 0.42 0.493 0.604 c 0.002 0.002 0.003 0.004 0.004 0.005 c 0 0 0 0 0 0 c 0.186 0.186 0.391 0.352 0.61 0.498 c 0.1 0.067 0.208 0.112 0.312 0.169 c 0.125 0.068 0.244 0.143 0.377 0.198 c 0.134 0.055 0.273 0.087 0.411 0.128 c 0.112 0.033 0.22 0.076 0.336 0.099 C 3.475 89.972 3.737 90 4 90 h 14.679 c 2.209 0 4 -1.791 4 -4 s -1.791 -4 -4 -4 h -5.022 l 23.958 -23.958 c 1.562 -1.562 1.562 -4.095 0 -5.656 C 36.052 50.823 33.52 50.823 31.958 52.386 z" style="
                stroke: none;
                stroke-width: 1;
                stroke-dasharray: none;
                stroke-linecap: butt;
                stroke-linejoin: miter;
                stroke-miterlimit: 10;
                fill: rgb(0, 0, 0);
                fill-rule: nonzero;
                opacity: 1;
              " transform=" matrix(1 0 0 1 0 0) " stroke-linecap="round" />
                <path d="M 89.921 3.217 c -0.023 -0.118 -0.067 -0.227 -0.101 -0.34 c -0.04 -0.136 -0.071 -0.274 -0.126 -0.406 c -0.056 -0.134 -0.132 -0.256 -0.201 -0.382 c -0.056 -0.102 -0.101 -0.209 -0.167 -0.307 c -0.147 -0.219 -0.313 -0.424 -0.498 -0.61 c 0 0 0 0 0 0 c -0.002 -0.002 -0.004 -0.003 -0.005 -0.004 c -0.184 -0.184 -0.387 -0.348 -0.604 -0.493 c -0.101 -0.068 -0.21 -0.114 -0.316 -0.171 C 87.78 0.435 87.661 0.36 87.53 0.306 c -0.131 -0.054 -0.267 -0.085 -0.401 -0.125 c -0.116 -0.034 -0.226 -0.079 -0.346 -0.102 c -0.242 -0.048 -0.489 -0.071 -0.735 -0.074 C 86.032 0.005 86.016 0 86 0 H 71.321 c -2.209 0 -4 1.791 -4 4 s 1.791 4 4 4 h 5.022 L 52.386 31.958 c -1.563 1.563 -1.563 4.095 0 5.657 c 0.78 0.781 1.805 1.171 2.828 1.171 s 2.048 -0.391 2.828 -1.171 L 82 13.657 v 5.022 c 0 2.209 1.791 4 4 4 s 4 -1.791 4 -4 V 4 C 90 3.737 89.972 3.475 89.921 3.217 z" style="
                stroke: none;
                stroke-width: 1;
                stroke-dasharray: none;
                stroke-linecap: butt;
                stroke-linejoin: miter;
                stroke-miterlimit: 10;
                fill: rgb(0, 0, 0);
                fill-rule: nonzero;
                opacity: 1;
              " transform=" matrix(1 0 0 1 0 0) " stroke-linecap="round" />
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

            <tbody id="table-body"></tbody>
        </table>
    </div>
    <div id="accordion-container" class="accordion-container"></div>
</section>
@include('includes.lmsfooterJs')
<script src="//cdnjs.cloudflare.com/ajax/libs/moment.js/2.9.0/moment-with-locales.js"></script>
<script src="//cdn.rawgit.com/Eonasdan/bootstrap-datetimepicker/e8bddc60e73c1ec2475f827be36e1957af72e2ea/src/js/bootstrap-datetimepicker.js">
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
<script>
    var lessonplan_data = "{{ $data['lessonplan_data'] }}";
    lessonplan_data = lessonplan_data.replace(/&quot;/ig, '"');
    lessonplan_data = JSON.parse(lessonplan_data);

    // function to create custom elements
    function createCustomElement(title, type, props = {}) {
        let element = document.createElement(type);
        Object.keys(props).forEach((prop) => (element[prop] = props[prop]));
        if (title) {
            var div = document.createElement("div");
            div.setAttribute("style", "display:flex;flex-direction:column;gap:3px");
            var label = document.createElement("label");
            label.innerHTML = title;
            if (type == 'label') {
                element.innerHTML = props.value;
                div.appendChild(element);
                return div;
            } else {
                element.setAttribute("placeholder", `Please Enter ${title}`);
                div.appendChild(label);
                div.appendChild(element);
                return div;
            }
            return div;
        }
        return element;
    }

    function createAccordion(items) {
        // get the accordion container
        let accordionContainer = document.querySelector("#accordion-container");
        // loop through the items
        items.forEach((item, index) => {
            if (item.header) {
                // create the accordion item
                let accordionItem = document.createElement("div");
                accordionItem.classList.add("accordion-item");
                accordionContainer.appendChild(accordionItem);
                // create the accordion header
                let accordionHeader = document.createElement("div");
                accordionHeader.classList.add("accordion-header");
                accordionHeader.innerHTML = `<svg class="arrow-icon" viewBox="0 0 24 24" width="24" height="24">
                                <path d="M7.41,8.58L12,13.17L16.59,8.58L18,10L12,16L6,10L7.41,8.58Z" />
                             </svg> ${item.header}`;
                accordionItem.appendChild(accordionHeader);
                // create the accordion content
                let accordionContent = document.createElement("div");
                accordionContent.classList.add("accordion-content");
                accordionItem.appendChild(accordionHeader);

                // call the createCustomElement function to add a custom element to the accordion content
                if (item.elementType === "select") {
                    let select = createCustomElement(
                        item.header,
                        item.elementType,
                        item.elementProps
                    );

                    let selectTag = select.querySelector("select");
                    let options = item.elementProps.options.forEach((option) => {
                        selectTag.appendChild(
                            createCustomElement(null, "option", {
                                value: option.value,
                                innerHTML: option.label,
                            })
                        );
                    });
                    accordionContent.appendChild(select);
                } else {
                    accordionContent.appendChild(
                        createCustomElement(item.header, item.elementType, item.elementProps)
                    );
                }
                accordionItem.appendChild(accordionContent);
                // add click event to the header
                accordionHeader.addEventListener("click", function() {
                    accordionContent.classList.toggle("open");
                    accordionHeader.classList.toggle("rotate");
                });
            }
        });
    }
    @php
$classroomactivity = isset($data['lessonplan_data']['classroomactivity']) ? $data['lessonplan_data']['classroomactivity'] : 0;
$selfstudyact1 = isset($data['lessonplan_data']['lessonDays'][0]['selfstudyactivity']) ? $data['lessonplan_data']['lessonDays'][0]['selfstudyactivity']: 0;
$selfstudyact2 = isset($data['lessonplan_data']['lessonDays'][1]['selfstudyactivity']) ? $data['lessonplan_data']['lessonDays'][1]['selfstudyactivity']: 0;
$selfstudyact3 = isset($data['lessonplan_data']['lessonDays'][2]['selfstudyactivity']) ? $data['lessonplan_data']['lessonDays'][2]['selfstudyactivity']: 0;
$selfstudyact4 = isset($data['lessonplan_data']['lessonDays'][3]['selfstudyactivity']) ? $data['lessonplan_data']['lessonDays'][3]['selfstudyactivity']: 0;
$selfstudyact5 = isset($data['lessonplan_data']['lessonDays'][4]['selfstudyactivity']) ? $data['lessonplan_data']['lessonDays'][4]['selfstudyactivity']: 0;
$selfstudyact6 = isset($data['lessonplan_data']['lessonDays'][5]['selfstudyactivity']) ? $data['lessonplan_data']['lessonDays'][5]['selfstudyactivity']: 0;
$selfstudyact7 = isset($data['lessonplan_data']['lessonDays'][6]['selfstudyactivity']) ? $data['lessonplan_data']['lessonDays'][6]['selfstudyactivity']: 0;
$selfstudyact8 = isset($data['lessonplan_data']['lessonDays'][7]['selfstudyactivity']) ? $data['lessonplan_data']['lessonDays'][7]['selfstudyactivity']: 0;
$selfstudyact9 = isset($data['lessonplan_data']['lessonDays'][8]['selfstudyactivity']) ? $data['lessonplan_data']['lessonDays'][8]['selfstudyactivity']: 0;
$selfstudyact10 = isset($data['lessonplan_data']['lessonDays'][9]['selfstudyactivity']) ? $data['lessonplan_data']['lessonDays'][9]['selfstudyactivity']: 0;
$selfstudyact11 = isset($data['lessonplan_data']['lessonDays'][10]['selfstudyactivity']) ? $data['lessonplan_data']['lessonDays'][10]['selfstudyactivity']: 0;
$selfstudyact12 = isset($data['lessonplan_data']['lessonDays'][11]['selfstudyactivity']) ? $data['lessonplan_data']['lessonDays'][11]['selfstudyactivity']: 0;

@endphp
var convertclassroomactivity = {!! DB::table('content_master')
    ->whereRaw('id IN (' . $classroomactivity . ') and sub_institute_id = ' . session()->get('sub_institute_id') . ' and syear = ' . session()->get('syear'))
    ->selectRaw('id, title, file_folder, filename')
    ->get() !!};
var selfstudyact1 = {!! DB::table('content_master')
    ->whereRaw('id IN (' . $selfstudyact1 . ') and sub_institute_id = ' . session()->get('sub_institute_id') . ' and syear = ' . session()->get('syear'))
    ->selectRaw('id, title, file_folder, filename')
    ->get() !!};
    var selfstudyact2 = {!! DB::table('content_master')
    ->whereRaw('id IN (' . $selfstudyact2 . ') and sub_institute_id = ' . session()->get('sub_institute_id') . ' and syear = ' . session()->get('syear'))
    ->selectRaw('id, title, file_folder, filename')
    ->get() !!};
    var selfstudyact3 = {!! DB::table('content_master')
    ->whereRaw('id IN (' . $selfstudyact3 . ') and sub_institute_id = ' . session()->get('sub_institute_id') . ' and syear = ' . session()->get('syear'))
    ->selectRaw('id, title, file_folder, filename')
    ->get() !!};
    var selfstudyact4 = {!! DB::table('content_master')
    ->whereRaw('id IN (' . $selfstudyact4 . ') and sub_institute_id = ' . session()->get('sub_institute_id') . ' and syear = ' . session()->get('syear'))
    ->selectRaw('id, title, file_folder, filename')
    ->get() !!};
    var selfstudyact5 = {!! DB::table('content_master')
    ->whereRaw('id IN (' . $selfstudyact5 . ') and sub_institute_id = ' . session()->get('sub_institute_id') . ' and syear = ' . session()->get('syear'))
    ->selectRaw('id, title, file_folder, filename')
    ->get() !!};
    var selfstudyact6 = {!! DB::table('content_master')
    ->whereRaw('id IN (' . $selfstudyact6 . ') and sub_institute_id = ' . session()->get('sub_institute_id') . ' and syear = ' . session()->get('syear'))
    ->selectRaw('id, title, file_folder, filename')
    ->get() !!};
    var selfstudyact7 = {!! DB::table('content_master')
    ->whereRaw('id IN (' . $selfstudyact7 . ') and sub_institute_id = ' . session()->get('sub_institute_id') . ' and syear = ' . session()->get('syear'))
    ->selectRaw('id, title, file_folder, filename')
    ->get() !!};
    var selfstudyact8 = {!! DB::table('content_master')
    ->whereRaw('id IN (' . $selfstudyact8 . ') and sub_institute_id = ' . session()->get('sub_institute_id') . ' and syear = ' . session()->get('syear'))
    ->selectRaw('id, title, file_folder, filename')
    ->get() !!};
    var selfstudyact9 = {!! DB::table('content_master')
    ->whereRaw('id IN (' . $selfstudyact9 . ') and sub_institute_id = ' . session()->get('sub_institute_id') . ' and syear = ' . session()->get('syear'))
    ->selectRaw('id, title, file_folder, filename')
    ->get() !!};
    var selfstudyact10 = {!! DB::table('content_master')
    ->whereRaw('id IN (' . $selfstudyact10 . ') and sub_institute_id = ' . session()->get('sub_institute_id') . ' and syear = ' . session()->get('syear'))
    ->selectRaw('id, title, file_folder, filename')
    ->get() !!};
    var selfstudyact11 = {!! DB::table('content_master')
    ->whereRaw('id IN (' . $selfstudyact11 . ') and sub_institute_id = ' . session()->get('sub_institute_id') . ' and syear = ' . session()->get('syear'))
    ->selectRaw('id, title, file_folder, filename')
    ->get() !!};
    var selfstudyact12 = {!! DB::table('content_master')
    ->whereRaw('id IN (' . $selfstudyact12 . ') and sub_institute_id = ' . session()->get('sub_institute_id') . ' and syear = ' . session()->get('syear'))
    ->selectRaw('id, title, file_folder, filename')
    ->get() !!};
    const newData = [
        [{
                header: "Standard",
                elementType: "label",
                elementProps: {
                    class: "custom-select",
                    value: lessonplan_data.standard.name,
                },
            },
            {
                header: "Focus point",
                elementType: "label",
                elementProps: {
                    class: "custom-label",
                    value: lessonplan_data.focauspoint,
                },
            },
            {
                header: "Learning objectives",
                elementType: "label",
                elementProps: {
                    class: "custom-label",
                    value: lessonplan_data.learningobjective,
                },
            },
            {
                header: lessonplan_data.lesson_days.length >= 1 ? 'Day 1' : '',
                elementType: "label",
                elementProps: {
                    class: "custom-label",
                    value: lessonplan_data.lesson_days.length >= 1 ? `<table>
                                <tr><td><b>Topic:</b> ` + lessonplan_data.lesson_days[0].topicname + `</td></tr>
                                <tr><td><b>Class Time:</b> ` + lessonplan_data.lesson_days[0].classtime + `</td></tr>
                                <tr><td><b>During Content:</b> ` + lessonplan_data.lesson_days[0].duringcontent + `</td></tr>
                                <tr><td><b>Objective:</b> ` + lessonplan_data.lesson_days[0].assessmentqualifying + `</td></tr>
                                <tr><td><b>Class Time:</b> ` + lessonplan_data.lesson_days[0].learningobjective + `</td></tr>
                                <tr><td><b>Learning Outcome:</b> ` + lessonplan_data.lesson_days[0].learningoutcome + `</td></tr>
                                <tr><td><b>Pedagogical process:</b> ` + lessonplan_data.lesson_days[0]
                        .pedagogicalprocess + `</td></tr>
                                <tr><td><b>Resource:</b> ` + lessonplan_data.lesson_days[0].resource + `</td></tr>
                                <tr><td><b>Closure:</b> ` + lessonplan_data.lesson_days[0].closure + `</td></tr>
                                <tr><td><b>Self-study & Homework:</b> ` + lessonplan_data.lesson_days[0]
                        .selfstudyhomework + `</td></tr>
                                <tr><td><div  class="pb-2"><b>Self-study & Activity:</b></div>` +
            (selfstudyact1.length > 0 ?
            selfstudyact1.map(activity => `<div><a  style="color:black" target="_blank" href=${activity.file_folder}/${activity.filename}>${activity.title}</a></div>`).join('') :
                'No activities'
            ) +
            `</div></div></td></tr>
                                <tr><td><b>Assessment:</b> ` + lessonplan_data.lesson_days[0].assessment + `</td></tr>
                            </table>` : '',
                },
            },
            {
                header: "Hard word with images",
                elementType: "label",
            },
        ],
        [{
                header: "Subject",
                elementType: "label",
                elementProps: {
                    class: "custom-select",
                    value: lessonplan_data.subject.subject_name,
                },
            },
            {
                header: "Pedagogical process",
                elementType: "label",
                elementProps: {
                    class: "custom-label",
                    value: lessonplan_data.pedagogicalprocess,
                },
            },
            {
                header: "Learning outcome : knowledge",
                elementType: "label",
                elementProps: {
                    class: "custom-label",
                    value: lessonplan_data.learningknowledge,
                },
            },
            {
                header: lessonplan_data.lesson_days.length >= 2 ? 'Day 2' : '',
                elementType: "label",
                elementProps: {
                    class: "custom-label",
                    value: lessonplan_data.lesson_days.length >= 2 ? `<table>
                                <tr><td><b>Topic:</b> ` + lessonplan_data.lesson_days[1].topicname + `</td></tr>
                                <tr><td><b>Class Time:</b> ` + lessonplan_data.lesson_days[1].classtime + `</td></tr>
                                <tr><td><b>During Content:</b> ` + lessonplan_data.lesson_days[1].duringcontent + `</td></tr>
                                <tr><td><b>Objective:</b> ` + lessonplan_data.lesson_days[1].assessmentqualifying + `</td></tr>
                                <tr><td><b>Class Time:</b> ` + lessonplan_data.lesson_days[1].learningobjective + `</td></tr>
                                <tr><td><b>Learning Outcome:</b> ` + lessonplan_data.lesson_days[1].learningoutcome + `</td></tr>
                                <tr><td><b>Pedagogical process:</b> ` + lessonplan_data.lesson_days[1]
                        .pedagogicalprocess + `</td></tr>
                                <tr><td><b>Resource:</b> ` + lessonplan_data.lesson_days[1].resource + `</td></tr>
                                <tr><td><b>Closure:</b> ` + lessonplan_data.lesson_days[1].closure + `</td></tr>
                                <tr><td><b>Self-study & Homework:</b> ` + lessonplan_data.lesson_days[1]
                        .selfstudyhomework + `</td></tr>
                                <tr><td><b>Self-study & Activity:</b> ` +   (selfstudyact2.length > 0 ?
            selfstudyact2.map(activity => `<div><a  style="color:black" target="_blank" href=${activity.file_folder}/${activity.filename}>${activity.title}</a></div>`).join('') :
                'No activities'
            )  + `</td></tr>
                                <tr><td><b>Assessment:</b> ` + lessonplan_data.lesson_days[1].assessment + `</td></tr>
                            </table>` : '',
                },
            },
            {
                header: "Tag & Metatag",
                elementType: "label",
                elementProps: {
                    class: "custom-label",
                    value: lessonplan_data.tagmetatag,
                },
            },
        ],
        [{
                header: "Chapter",
                elementType: "label",
                elementProps: {
                    class: "custom-select",
                    value: lessonplan_data.chapter.chapter_name,
                },
            },
            {
                header: "Resource",
                elementType: "label",
                elementProps: {
                    class: "custom-label",
                    value: lessonplan_data.resource,
                },
            },
            {
                header: "Learning outcome : skill",
                elementType: "label",
                elementProps: {
                    class: "custom-label",
                    value: lessonplan_data.learningskill,
                },
            },
            {
                header: lessonplan_data.lesson_days.length >= 3 ? 'Day 3' : '',
                elementType: "label",
                elementProps: {
                    class: "custom-label",
                    value: lessonplan_data.lesson_days.length >= 3 ? `<table>
                                <tr><td><b>Topic:</b> ` + lessonplan_data.lesson_days[2].topicname + `</td></tr>
                                <tr><td><b>Class Time:</b> ` + lessonplan_data.lesson_days[2].classtime + `</td></tr>
                                <tr><td><b>During Content:</b> ` + lessonplan_data.lesson_days[2].duringcontent + `</td></tr>
                                <tr><td><b>Objective:</b> ` + lessonplan_data.lesson_days[2].assessmentqualifying + `</td></tr>
                                <tr><td><b>Class Time:</b> ` + lessonplan_data.lesson_days[2].learningobjective + `</td></tr>
                                <tr><td><b>Learning Outcome:</b> ` + lessonplan_data.lesson_days[2].learningoutcome + `</td></tr>
                                <tr><td><b>Pedagogical process:</b> ` + lessonplan_data.lesson_days[2]
                        .pedagogicalprocess + `</td></tr>
                                <tr><td><b>Resource:</b> ` + lessonplan_data.lesson_days[2].resource + `</td></tr>
                                <tr><td><b>Closure:</b> ` + lessonplan_data.lesson_days[2].closure + `</td></tr>
                                <tr><td><b>Self-study & Homework:</b> ` + lessonplan_data.lesson_days[2]
                        .selfstudyhomework + `</td></tr>
                                <tr><td><b>Self-study & Activity:</b> ` +   (selfstudyact3.length > 0 ?
            selfstudyact3.map(activity => `<div><a  style="color:black" target="_blank" href=${activity.file_folder}/${activity.filename}>${activity.title}</a></div>`).join('') :
                'No activities'
            )  + `</td></tr>
                                <tr><td><b>Assessment:</b> ` + lessonplan_data.lesson_days[2].assessment + `</td></tr>
                            </table>` : '',
                },
            },
            {
                header: "Value integrations",
                elementType: "label",
                elementProps: {
                    class: "custom-label",
                    value: lessonplan_data.valueintegration,
                },
            },
        ],
        [{
                header: "Number of period",
                elementType: "label",
                elementProps: {
                    class: "custom-label",
                    value: lessonplan_data.numberofperiod,
                },
            },
            {
                header: "Classroom presentation",
                elementType: "label",
                elementProps: {
                    class: "custom-label",
                    value: `<div><div class="pb-4">` + lessonplan_data.classroompresentation + `</div><div>` +
            (convertclassroomactivity.length > 0 ?`<h6><b>Classroom Activity</b></h6>`+
                convertclassroomactivity.map(activity => `<div><a target="_blank" href=${activity.file_folder}/${activity.filename}>${activity.title}</a></div>`).join('') :
                'No activities'
            ) +
            `</div></div>`,
                },
            },
            {
                header: "Prerequisite Lessons",
                elementType: "label",
                elementProps: {
                    class: "custom-label",
                    value: lessonplan_data.prerequisite,
                },
            },
            {
                header: lessonplan_data.lesson_days.length >= 4 ? 'Day 4' : '',
                elementType: "label",
                elementProps: {
                    class: "custom-label",
                    value: lessonplan_data.lesson_days.length >= 4 ? `<table>
                                <tr><td><b>Topic:</b> ` + lessonplan_data.lesson_days[3].topicname + `</td></tr>
                                <tr><td><b>Class Time:</b> ` + lessonplan_data.lesson_days[3].classtime + `</td></tr>
                                <tr><td><b>During Content:</b> ` + lessonplan_data.lesson_days[3].duringcontent + `</td></tr>
                                <tr><td><b>Objective:</b> ` + lessonplan_data.lesson_days[3].assessmentqualifying + `</td></tr>
                                <tr><td><b>Class Time:</b> ` + lessonplan_data.lesson_days[3].learningobjective + `</td></tr>
                                <tr><td><b>Learning Outcome:</b> ` + lessonplan_data.lesson_days[3].learningoutcome + `</td></tr>
                                <tr><td><b>Pedagogical process:</b> ` + lessonplan_data.lesson_days[3]
                        .pedagogicalprocess + `</td></tr>
                                <tr><td><b>Resource:</b> ` + lessonplan_data.lesson_days[3].resource + `</td></tr>
                                <tr><td><b>Closure:</b> ` + lessonplan_data.lesson_days[3].closure + `</td></tr>
                                <tr><td><b>Self-study & Homework:</b> ` + lessonplan_data.lesson_days[3]
                        .selfstudyhomework + `</td></tr>
                                <tr><td><b>Self-study & Activity:</b> ` +   (selfstudyact4.length > 0 ?
            selfstudyact4.map(activity => `<div><a  style="color:black" target="_blank" href=${activity.file_folder}/${activity.filename}>${activity.title}</a></div>`).join('') :
                'No activities'
            )  + `</td></tr>
                                <tr><td><b>Assessment:</b> ` + lessonplan_data.lesson_days[3].assessment + `</td></tr>
                            </table>` : '',
                },
            },
            {
                header: "Global connection",
                elementType: "label",
                elementProps: {
                    class: "custom-label",
                    value: lessonplan_data.globalconnection,
                },
            },
        ],
        [{
                header: "Teaching time",
                elementType: "label",
                elementProps: {
                    class: "custom-label",
                    value: lessonplan_data.teachingtime,
                },
            },
            {
                header: "Classroom diversity",
                elementType: "label",
                elementProps: {
                    class: "custom-label",
                    value: lessonplan_data.classroomdiversity,
                },
            },
            {
                header: "Self-Study & Homework",
                elementType: "label",
                elementProps: {
                    class: "custom-label",
                    value: lessonplan_data.selfstudyhomework,
                },
            },
            {
                header: lessonplan_data.lesson_days.length >= 5 ? 'Day 5' : '',
                elementType: "label",
                elementProps: {
                    class: "custom-label",
                    value: lessonplan_data.lesson_days.length >= 5 ? `<table>
                                <tr><td><b>Topic:</b> ` + lessonplan_data.lesson_days[4].topicname + `</td></tr>
                                <tr><td><b>Class Time:</b> ` + lessonplan_data.lesson_days[4].classtime + `</td></tr>
                                <tr><td><b>During Content:</b> ` + lessonplan_data.lesson_days[4].duringcontent + `</td></tr>
                                <tr><td><b>Objective:</b> ` + lessonplan_data.lesson_days[4].assessmentqualifying + `</td></tr>
                                <tr><td><b>Class Time:</b> ` + lessonplan_data.lesson_days[4].learningobjective + `</td></tr>
                                <tr><td><b>Learning Outcome:</b> ` + lessonplan_data.lesson_days[4].learningoutcome + `</td></tr>
                                <tr><td><b>Pedagogical process:</b> ` + lessonplan_data.lesson_days[4]
                        .pedagogicalprocess + `</td></tr>
                                <tr><td><b>Resource:</b> ` + lessonplan_data.lesson_days[4].resource + `</td></tr>
                                <tr><td><b>Closure:</b> ` + lessonplan_data.lesson_days[4].closure + `</td></tr>
                                <tr><td><b>Self-study & Homework:</b> ` + lessonplan_data.lesson_days[4]
                        .selfstudyhomework + `</td></tr>
                                <tr><td><b>Self-study & Activity:</b> ` +   (selfstudyact5.length > 0 ?
            selfstudyact5.map(activity => `<div><a  style="color:black" target="_blank" href=${activity.file_folder}/${activity.filename}>${activity.title}</a></div>`).join('') :
                'No activities'
            )  + `</td></tr>
                                <tr><td><b>Assessment:</b> ` + lessonplan_data.lesson_days[4].assessment + `</td></tr>
                            </table>` : '',
                },
            },
            {
                header: "Cross curriculum",
                elementType: "label",
                elementProps: {
                    class: "custom-label",
                    value: "Cross curriculum",
                },
            },
        ],
        [{
                header: "Assessment time",
                elementType: "label",
                elementProps: {
                    class: "custom-label",
                    value: lessonplan_data.assessmenttime,
                },
            },
            {},
            {
                header: "Assessment",
                elementType: "label",
                elementProps: {
                    class: "custom-label",
                    value: lessonplan_data.assessment,
                },
            },
            {
                header: lessonplan_data.lesson_days.length >= 6 ? 'Day 6' : '',
                elementType: "label",
                elementProps: {
                    class: "custom-label",
                    value: lessonplan_data.lesson_days.length >= 6 ? `<table>
                                <tr><td><b>Topic:</b> ` + lessonplan_data.lesson_days[5].topicname + `</td></tr>
                                <tr><td><b>Class Time:</b> ` + lessonplan_data.lesson_days[5].classtime + `</td></tr>
                                <tr><td><b>During Content:</b> ` + lessonplan_data.lesson_days[5].duringcontent + `</td></tr>
                                <tr><td><b>Objective:</b> ` + lessonplan_data.lesson_days[5].assessmentqualifying + `</td></tr>
                                <tr><td><b>Class Time:</b> ` + lessonplan_data.lesson_days[5].learningobjective + `</td></tr>
                                <tr><td><b>Learning Outcome:</b> ` + lessonplan_data.lesson_days[5].learningoutcome + `</td></tr>
                                <tr><td><b>Pedagogical process:</b> ` + lessonplan_data.lesson_days[5]
                        .pedagogicalprocess + `</td></tr>
                                <tr><td><b>Resource:</b> ` + lessonplan_data.lesson_days[5].resource + `</td></tr>
                                <tr><td><b>Closure:</b> ` + lessonplan_data.lesson_days[5].closure + `</td></tr>
                                <tr><td><b>Self-study & Homework:</b> ` + lessonplan_data.lesson_days[5]
                        .selfstudyhomework + `</td></tr>
                                <tr><td><b>Self-study & Activity:</b> ` +   (selfstudyact6.length > 0 ?
            selfstudyact6.map(activity => `<div><a  style="color:black" target="_blank" href=${activity.file_folder}/${activity.filename}>${activity.title}</a></div>`).join('') :
                'No activities'
            )  + `</td></tr>
                                <tr><td><b>Assessment:</b> ` + lessonplan_data.lesson_days[5].assessment + `</td></tr>
                            </table>` : '',
                },
            },
            {
                header: "SEL ( Social & emotional learning",
                elementType: "label",
                elementProps: {
                    class: "custom-label",
                    value: lessonplan_data.sel,
                },
            },
        ],
        [{
                header: "Learning Time",
                elementType: "label",
                elementProps: {
                    class: "custom-label",
                    value: lessonplan_data.learningtime,
                },
            },
            {},
            {},
            {
                header: lessonplan_data.lesson_days.length >= 7 ? 'Day 7' : '',
                elementType: "label",
                elementProps: {
                    class: "custom-label",
                    value: lessonplan_data.lesson_days.length >= 7 ? `<table>
                                <tr><td><b>Topic:</b> ` + lessonplan_data.lesson_days[6].topicname + `</td></tr>
                                <tr><td><b>Class Time:</b> ` + lessonplan_data.lesson_days[6].classtime + `</td></tr>
                                <tr><td><b>During Content:</b> ` + lessonplan_data.lesson_days[6].duringcontent + `</td></tr>
                                <tr><td><b>Objective:</b> ` + lessonplan_data.lesson_days[6].assessmentqualifying + `</td></tr>
                                <tr><td><b>Class Time:</b> ` + lessonplan_data.lesson_days[6].learningobjective + `</td></tr>
                                <tr><td><b>Learning Outcome:</b> ` + lessonplan_data.lesson_days[6].learningoutcome + `</td></tr>
                                <tr><td><b>Pedagogical process:</b> ` + lessonplan_data.lesson_days[6]
                        .pedagogicalprocess + `</td></tr>
                                <tr><td><b>Resource:</b> ` + lessonplan_data.lesson_days[6].resource + `</td></tr>
                                <tr><td><b>Closure:</b> ` + lessonplan_data.lesson_days[6].closure + `</td></tr>
                                <tr><td><b>Self-study & Homework:</b> ` + lessonplan_data.lesson_days[6]
                        .selfstudyhomework + `</td></tr>
                                <tr><td><b>Self-study & Activity:</b> ` +   (selfstudyact7.length > 0 ?
            selfstudyact7.map(activity => `<div><a  style="color:black" target="_blank" href=${activity.file_folder}/${activity.filename}>${activity.title}</a></div>`).join('') :
                'No activities'
            )  + `</td></tr>
                                <tr><td><b>Assessment:</b> ` + lessonplan_data.lesson_days[6].assessment + `</td></tr>
                            </table>` : '',
                },
            },
            {
                header: "STEM",
                elementType: "label",
                elementProps: {
                    class: "custom-label",
                    value: lessonplan_data.stem,
                },
            },
        ],
        [{
                header: "Assessment Qualifying",
                elementType: "label",
                elementProps: {
                    class: "custom-label",
                    value: lessonplan_data.assessmentqualifying,
                },
            },
            {},
            {},
            {
                header: lessonplan_data.lesson_days.length >= 8 ? 'Day 8' : '',
                elementType: "label",
                elementProps: {
                    class: "custom-label",
                    value: lessonplan_data.lesson_days.length >= 8 ? `<table>
                                <tr><td><b>Topic:</b> ` + lessonplan_data.lesson_days[7].topicname + `</td></tr>
                                <tr><td><b>Class Time:</b> ` + lessonplan_data.lesson_days[7].classtime + `</td></tr>
                                <tr><td><b>During Content:</b> ` + lessonplan_data.lesson_days[7].duringcontent + `</td></tr>
                                <tr><td><b>Objective:</b> ` + lessonplan_data.lesson_days[7].assessmentqualifying + `</td></tr>
                                <tr><td><b>Class Time:</b> ` + lessonplan_data.lesson_days[7].learningobjective + `</td></tr>
                                <tr><td><b>Learning Outcome:</b> ` + lessonplan_data.lesson_days[7].learningoutcome + `</td></tr>
                                <tr><td><b>Pedagogical process:</b> ` + lessonplan_data.lesson_days[7]
                        .pedagogicalprocess + `</td></tr>
                                <tr><td><b>Resource:</b> ` + lessonplan_data.lesson_days[7].resource + `</td></tr>
                                <tr><td><b>Closure:</b> ` + lessonplan_data.lesson_days[7].closure + `</td></tr>
                                <tr><td><b>Self-study & Homework:</b> ` + lessonplan_data.lesson_days[7]
                        .selfstudyhomework + `</td></tr>
                                <tr><td><b>Self-study & Activity:</b> ` +   (selfstudyact8.length > 0 ?
            selfstudyact8.map(activity => `<div><a  style="color:black" target="_blank" href=${activity.file_folder}/${activity.filename}>${activity.title}</a></div>`).join('') :
                'No activities'
            )  + `</td></tr>
                                <tr><td><b>Assessment:</b> ` + lessonplan_data.lesson_days[7].assessment + `</td></tr>
                            </table>` : '',
                },
            },
            {
                header: "Vocational training",
                elementType: "label",
                elementProps: {
                    class: "custom-label",
                    value: lessonplan_data.vocationaltraining,
                },
            },
        ],
        [{},
            {},
            {},
            {
                header: lessonplan_data.lesson_days.length >= 9 ? 'Day 9' : '',
                elementType: "label",
                elementProps: {
                    class: "custom-label",
                    value: lessonplan_data.lesson_days.length >= 9 ? `<table>
                                <tr><td><b>Topic:</b> ` + lessonplan_data.lesson_days[8].topicname + `</td></tr>
                                <tr><td><b>Class Time:</b> ` + lessonplan_data.lesson_days[8].classtime + `</td></tr>
                                <tr><td><b>During Content:</b> ` + lessonplan_data.lesson_days[8].duringcontent + `</td></tr>
                                <tr><td><b>Objective:</b> ` + lessonplan_data.lesson_days[8].assessmentqualifying + `</td></tr>
                                <tr><td><b>Class Time:</b> ` + lessonplan_data.lesson_days[8].learningobjective + `</td></tr>
                                <tr><td><b>Learning Outcome:</b> ` + lessonplan_data.lesson_days[8].learningoutcome + `</td></tr>
                                <tr><td><b>Pedagogical process:</b> ` + lessonplan_data.lesson_days[8]
                        .pedagogicalprocess + `</td></tr>
                                <tr><td><b>Resource:</b> ` + lessonplan_data.lesson_days[8].resource + `</td></tr>
                                <tr><td><b>Closure:</b> ` + lessonplan_data.lesson_days[8].closure + `</td></tr>
                                <tr><td><b>Self-study & Homework:</b> ` + lessonplan_data.lesson_days[8]
                        .selfstudyhomework + `</td></tr>
                                <tr><td><b>Self-study & Activity:</b> ` +  (selfstudyact9.length > 0 ?
            selfstudyact9.map(activity => `<div><a  style="color:black" target="_blank" href=${activity.file_folder}/${activity.filename}>${activity.title}</a></div>`).join('') :
                'No activities'
            )  + `</td></tr>
                                <tr><td><b>Assessment:</b> ` + lessonplan_data.lesson_days[8].assessment + `</td></tr>
                            </table>` : '',
                },
            },
            {
                header: "Simulation",
                elementType: "label",
                elementProps: {
                    class: "custom-label",
                    value: lessonplan_data.simulation,
                },
            },
        ],
        [{},
            {},
            {},
            {
                header: lessonplan_data.lesson_days.length >= 10 ? 'Day 10' : '',
                elementType: "label",
                elementProps: {
                    class: "custom-label",
                    value: lessonplan_data.lesson_days.length >= 10 ? `<table>
                                <tr><td><b>Topic:</b> ` + lessonplan_data.lesson_days[9].topicname + `</td></tr>
                                <tr><td><b>Class Time:</b> ` + lessonplan_data.lesson_days[9].classtime + `</td></tr>
                                <tr><td><b>During Content:</b> ` + lessonplan_data.lesson_days[9].duringcontent + `</td></tr>
                                <tr><td><b>Objective:</b> ` + lessonplan_data.lesson_days[9].assessmentqualifying + `</td></tr>
                                <tr><td><b>Class Time:</b> ` + lessonplan_data.lesson_days[9].learningobjective + `</td></tr>
                                <tr><td><b>Learning Outcome:</b> ` + lessonplan_data.lesson_days[9].learningoutcome + `</td></tr>
                                <tr><td><b>Pedagogical process:</b> ` + lessonplan_data.lesson_days[9]
                        .pedagogicalprocess + `</td></tr>
                                <tr><td><b>Resource:</b> ` + lessonplan_data.lesson_days[9].resource + `</td></tr>
                                <tr><td><b>Closure:</b> ` + lessonplan_data.lesson_days[9].closure + `</td></tr>
                                <tr><td><b>Self-study & Homework:</b> ` + lessonplan_data.lesson_days[9]
                        .selfstudyhomework + `</td></tr>
                                <tr><td><b>Self-study & Activity:</b> ` +   (selfstudyact10.length > 0 ?
            selfstudyact10.map(activity => `<div><a  style="color:black" target="_blank" href=${activity.file_folder}/${activity.filename}>${activity.title}</a></div>`).join('') :
                'No activities'
            )  + `</td></tr>
                                <tr><td><b>Assessment:</b> ` + lessonplan_data.lesson_days[9].assessment + `</td></tr>
                            </table>` : '',
                },
            },
            {
                header: "Games",
                elementType: "label",
                elementProps: {
                    class: "custom-label",
                    value: lessonplan_data.games,
                },
            },
        ],
        [{},
            {},
            {},
            {
                header: lessonplan_data.lesson_days.length >= 11 ? 'Day 11' : '',
                elementType: "label",
                elementProps: {
                    class: "custom-label",
                    value: lessonplan_data.lesson_days.length >= 11 ? `<table>
                                <tr><td><b>Topic:</b> ` + lessonplan_data.lesson_days[10].topicname + `</td></tr>
                                <tr><td><b>Class Time:</b> ` + lessonplan_data.lesson_days[10].classtime + `</td></tr>
                                <tr><td><b>During Content:</b> ` + lessonplan_data.lesson_days[10].duringcontent + `</td></tr>
                                <tr><td><b>Objective:</b> ` + lessonplan_data.lesson_days[10].assessmentqualifying + `</td></tr>
                                <tr><td><b>Class Time:</b> ` + lessonplan_data.lesson_days[10].learningobjective + `</td></tr>
                                <tr><td><b>Learning Outcome:</b> ` + lessonplan_data.lesson_days[10].learningoutcome + `</td></tr>
                                <tr><td><b>Pedagogical process:</b> ` + lessonplan_data.lesson_days[10]
                        .pedagogicalprocess + `</td></tr>
                                <tr><td><b>Resource:</b> ` + lessonplan_data.lesson_days[10].resource + `</td></tr>
                                <tr><td><b>Closure:</b> ` + lessonplan_data.lesson_days[10].closure + `</td></tr>
                                <tr><td><b>Self-study & Homework:</b> ` + lessonplan_data.lesson_days[10]
                        .selfstudyhomework + `</td></tr>
                                <tr><td><b>Self-study & Activity:</b> ` +   (selfstudyact11.length > 0 ?
            selfstudyact11.map(activity => `<div><a  style="color:black" target="_blank" href=${activity.file_folder}/${activity.filename}>${activity.title}</a></div>`).join('') :
                'No activities'
            )  + `</td></tr>
                                <tr><td><b>Assessment:</b> ` + lessonplan_data.lesson_days[10].assessment + `</td></tr>
                            </table>` : '',
                },
            },
            {
                header: "Activities",
                elementType: "label",
                elementProps: {
                    class: "custom-label",
                    value: lessonplan_data.activities,
                },
            },
        ],
        [{},
            {},
            {},
            {
                header: lessonplan_data.lesson_days.length >= 12 ? 'Day 12' : '',
                elementType: "label",
                elementProps: {
                    class: "custom-label",
                    value: lessonplan_data.lesson_days.length >= 12 ? `<table>
                                <tr><td><b>Topic:</b> ` + lessonplan_data.lesson_days[11].topicname + `</td></tr>
                                <tr><td><b>Class Time:</b> ` + lessonplan_data.lesson_days[11].classtime + `</td></tr>
                                <tr><td><b>During Content:</b> ` + lessonplan_data.lesson_days[11].duringcontent + `</td></tr>
                                <tr><td><b>Objective:</b> ` + lessonplan_data.lesson_days[11].assessmentqualifying + `</td></tr>
                                <tr><td><b>Class Time:</b> ` + lessonplan_data.lesson_days[11].learningobjective + `</td></tr>
                                <tr><td><b>Learning Outcome:</b> ` + lessonplan_data.lesson_days[11].learningoutcome + `</td></tr>
                                <tr><td><b>Pedagogical process:</b> ` + lessonplan_data.lesson_days[11]
                        .pedagogicalprocess + `</td></tr>
                                <tr><td><b>Resource:</b> ` + lessonplan_data.lesson_days[11].resource + `</td></tr>
                                <tr><td><b>Closure:</b> ` + lessonplan_data.lesson_days[11].closure + `</td></tr>
                                <tr><td><b>Self-study & Homework:</b> ` + lessonplan_data.lesson_days[11]
                        .selfstudyhomework + `</td></tr>
                                <tr><td><b>Self-study & Activity:</b> ` +   (selfstudyact12.length > 0 ?
            selfstudyact12.map(activity => `<div><a  style="color:black" target="_blank" href=${activity.file_folder}/${activity.filename}>${activity.title}</a></div>`).join('') :
                'No activities'
            )  + `</td></tr>
                                <tr><td><b>Assessment:</b> ` + lessonplan_data.lesson_days[11].assessment + `</td></tr>
                            </table>` : '',
                },
            },
            {
                header: "Real life application",
                elementType: "label",
                elementProps: {
                    class: "custom-label",
                    value: lessonplan_data.reallifeapplication,
                },
            },
        ],
    ];

    const handleAdd = (row, col) => {
        let accordionContainer = document.querySelector("#accordion-container");
        let data = newData[row][col];
        accordionContainer.innerHTML = "";
        // console.log(data);
        if (data.header) {
            createAccordion([data]);
        }
    };

    const handleAddAll = (col) => {
        let accordionContainer = document.querySelector("#accordion-container");
        let newColData = newData
            .map((item) => {
                return item[col];
            })
            .filter((item) => Object.keys(item).length !== 0);

        accordionContainer.innerHTML = "";
        if (newColData.length) {
            createAccordion(newColData);
        }
    };

    const handleExpandAll = (col) => {
        let accordionContainer = document.querySelector("#accordion-container");
        let newColData = newData
            .flat()
            .filter((item) => Object.keys(item).length !== 0);

        accordionContainer.innerHTML = "";
        if (newColData.length) {
            createAccordion(newColData);
        }
    };

    (function dynamicTable() {
        let tableBody = document.getElementById("table-body");
        tableBody.innerHTML = newData
            .map(
                (row, rowIndex) =>
                `<tr>${row
          .map(
            (col, colIndex) =>
              `<td style=${
                col?.header ? "cursor:pointer" : "cursor:not-allowed"
              } onclick={handleAdd(${rowIndex},${colIndex})}>${
                col?.header ?? ""
              }</td>`
          )
          .join("")}</tr>`
            )
            .join("");
    })();

    let body = document.getElementById("container");
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


        $(document).on('click', '.add-day', function() {
            day = parseInt($('#day_count').val());
            $('#day_count').val(day);
            let id = $('#id').val();
            dayWiseDiv(day = 1, id);
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
            formData.append('classroomactivity', classroomactivity);
            formData.append('selfstudyactivity', selfstudyactivity);
            formData.append('assessmentactivity', assessmentactivity);
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
        $(document).on('click', '.btn-close', function(e) {
            $('#contentMasterMdl').toggle();
        });
        $(document).on('click', '.btn-close-day', function(e) {
            $('#day_mdl').toggle();
        });
        $(document).on('click', '.add_activity', function(e) {
            var type = $(this).attr('id');
            $('#contentMasterMdl').toggle();
            $('.activityData').hide();
            $('#add_' + type).show();
            let standard_id = $('#standard').val();
            let chapter_id = $('#chapter').val();
            let subject_id = $('#subject').val();
            let topic_id = $('#topic').val();
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
                    console.log(result);
                    var html = '<h2>' + type + '</h2>';
                    result.forEach(element => {
                        if (type == 'classroomactivity') {
                            var checked = classroomactivity.some(item => item ==
                                element
                                .id) ? 'checked' : '';
                        } else if (type == 'selfstudyactivity') {
                            var checked = selfstudyactivity.some(item => item ==
                                element
                                .id) ? 'checked' : '';
                        } else if (type == 'assessmentactivity') {
                            var checked = assessmentactivity.some(item =>
                                item ==
                                element
                                .id) ? 'checked' : '';
                        }
                        html +=
                            `<div class="form-group"><input type="checkbox" name="` +
                            type + `[` + element.id + `]" id="" ` + checked +
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
    })

    function dayWiseDiv(day = 1, id = null) {
        $.ajax({
            url: "{{ route('ajax_daywisedata') }}",
            type: "GET",
            data: {
                day: day,
                id: id,
            },
            success: function(result) {
                $('#daywise').append(result);
            },
            error: function(errors, errResponse, err) {
                console.error(errors);
            }
        });
    }
</script>

@include('includes.footer')