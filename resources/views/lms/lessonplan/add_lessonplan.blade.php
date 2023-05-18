@include('includes.lmsheadcss')
<!-- <link href="/plugins/bower_components/clockpicker/dist/jquery-clockpicker.min.css" rel="stylesheet"> -->
    <link href="{{ asset('/css/style2.css') }}" rel="stylesheet">

<style>

    #collapse-0 td ,#collapse-1 td ,#collapse-2 td ,#collapse-3 td ,#collapse-4 td ,#collapse-5 td {
    text-align: left;
    border:1px solid #ddd;

    }

    .all-lists{
        width:20px;
        margin-left:10px;
    }
    .accordion-container {
    background: #fff;
    border-radius: 10px;
    width: 100%;
    padding: 0px;
    margin: 0 auto;
}
</style>
@include('includes.header')
@include('includes.sideNavigation')
<!-- <link href="{{ asset('css/style.css') }}" rel="stylesheet" /> -->
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
        @if ($data['lessonplan_data']->id)
            <div class="col-md-3 mb-4 text-md-right">
                <a href="{{ route('lms_lessonplan.create', ['id' => $data['lessonplan_data']->id]) }}"
                    class="btn btn-info add-new"><i class="fa fa-plus"></i>Edit Form</a>
            </div>
        @else
            <div class="col-md-3 mb-4 text-md-right">
                <a href="{{ route('lms_lessonplan.create', ['standard_id' => $data['lessonplan_data']->standard_id, 'subject_id' => $data['lessonplan_data']->subject_id, 'chapter_id' => $data['lessonplan_data']->chapter_id]) }}"
                    class="btn btn-info add-new"><i class="fa fa-plus"></i>Add Lesson Plan</a>
            </div>
        @endif
    </div>
</div>
   <!-- Setup Your Details -->
    <section class="lesson-table">
        <div class="container">
            <div class="page-title">
                <h1 class="text-center  mb-0">Lesson plan</h1>
            </div>
            <div class="responsive-table">
          <table>
            <thead>
                <tr>
                    <th scope="col">Summary Of Lesson Plan <img onclick="handleAddAll(0)" src="{{ asset('admin_dep/images/expand_white.svg') }}" class="all-lists"/></th>
                    <th scope="col">Teaching<img onclick="handleAddAll(1)" src="{{ asset('admin_dep/images/expand_white.svg') }}" class="all-lists"/></th>
                    <th scope="col">Learning<img onclick="handleAddAll(2)" src="{{ asset('admin_dep/images/expand_white.svg') }}" class="all-lists"/></th>
                    <th scope="col">Data Planning<img onclick="handleAddAll(3)" src="{{ asset('admin_dep/images/expand_white.svg') }}" class="all-lists"/></th>
                    <th scope="col">Map & Alignment<img onclick="handleAddAll(4)" src="{{ asset('admin_dep/images/expand_white.svg') }}" class="all-lists"/></th>
                </tr>
            </thead>
            <tbody id="table-body" style="background: #fff;"></tbody>
          </table>
        </div>
        </div>
        <div id="accordion-container" class="accordion-container"></div>
    </section>


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

    // function createAccordion(items) {
    //     // get the accordion container
    //     let accordionContainer = document.querySelector("#accordion-container");
    //     // loop through the items
    //     items.forEach((item, index) => {
    //         if (item.header) {
    //             // create the accordion item
    //             let accordionItem = document.createElement("div");
    //             accordionItem.classList.add("accordion-item");
    //             accordionContainer.appendChild(accordionItem);
    //             // create the accordion header
    //             let accordionHeader = document.createElement("div");
    //             accordionHeader.classList.add("accordion-header");
    //             accordionHeader.innerHTML = `<svg class="arrow-icon" viewBox="0 0 24 24" width="24" height="24">
    //                             <path d="M7.41,8.58L12,13.17L16.59,8.58L18,10L12,16L6,10L7.41,8.58Z" />
    //                          </svg> ${item.header}`;
    //             accordionItem.appendChild(accordionHeader);
    //             // create the accordion content
    //             let accordionContent = document.createElement("div");
    //             accordionContent.classList.add("accordion-content");
    //             accordionItem.appendChild(accordionHeader);

    //             // call the createCustomElement function to add a custom element to the accordion content
    //             if (item.elementType === "select") {
    //                 let select = createCustomElement(
    //                     item.header,
    //                     item.elementType,
    //                     item.elementProps
    //                 );

    //                 let selectTag = select.querySelector("select");
    //                 let options = item.elementProps.options.forEach((option) => {
    //                     selectTag.appendChild(
    //                         createCustomElement(null, "option", {
    //                             value: option.value,
    //                             innerHTML: option.label,
    //                         })
    //                     );
    //                 });
    //                 accordionContent.appendChild(select);
    //             } else {
    //                 accordionContent.appendChild(
    //                     createCustomElement(item.header, item.elementType, item.elementProps)
    //                 );
    //             }
    //             accordionItem.appendChild(accordionContent);
    //             // add click event to the header
    //             accordionHeader.addEventListener("click", function() {
    //                 accordionContent.classList.toggle("open");
    //                 accordionHeader.classList.toggle("rotate");
    //             });
    //         }
    //     });
    // }
    function createAccordion(items) {
    // get the collapse container
    let collapseContainer = document.querySelector("#accordion-container");

    // loop through the items
    let serialNumber =1;
    items.forEach((item, index) => {
        if (item.header) {
            // create the collapse item
            let collapseItem = document.createElement("div");
            collapseItem.classList.add("collapse-item");
            collapseContainer.appendChild(collapseItem);

            // create the collapse header
            let collapseHeader = document.createElement("div");
            collapseHeader.classList.add("collapse-header");
            collapseHeader.innerHTML = `<a href="#collapse-${index}"><button class="btn btn-link" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-${index}" aria-expanded="false" aria-controls="collapse-${index}">
                              <div class="number">${serialNumber}</div><div class="d-flex align-items-center text ">${item.header}<img src="${item.elementProps.value ? "/Images/square-check.svg" : "/Images/close-square-icon.svg"
                }"></div></div>
                             </button></a>`;
                             
            collapseItem.appendChild(collapseHeader);

            // create the collapse content
            let collapseContent = document.createElement("div");
            collapseContent.classList.add("card", "card-body",'border-0');
            collapseContent.id = `collapse-${index}`;
            collapseItem.appendChild(collapseContent);

            // call the createCustomElement function to add a custom element to the collapse content
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
                collapseContent.appendChild(select);
            } else {
                collapseContent.appendChild(
                    createCustomElement(item.header, item.elementType, item.elementProps)
                );
            }
            serialNumber++;
        }
    });
}


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
                                <tr><td><b>Self-study & Activity:</b> ` + lessonplan_data.lesson_days[0]
                        .selfstudyactivity + `</td></tr>
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
                                <tr><td><b>Self-study & Activity:</b> ` + lessonplan_data.lesson_days[1]
                        .selfstudyactivity + `</td></tr>
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
                                <tr><td><b>Self-study & Activity:</b> ` + lessonplan_data.lesson_days[2]
                        .selfstudyactivity + `</td></tr>
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
                    value: lessonplan_data.classroompresentation,
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
                                <tr><td><b>Self-study & Activity:</b> ` + lessonplan_data.lesson_days[3]
                        .selfstudyactivity + `</td></tr>
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
                                <tr><td><b>Self-study & Activity:</b> ` + lessonplan_data.lesson_days[4]
                        .selfstudyactivity + `</td></tr>
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
                                <tr><td><b>Self-study & Activity:</b> ` + lessonplan_data.lesson_days[5]
                        .selfstudyactivity + `</td></tr>
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
                                <tr><td><b>Self-study & Activity:</b> ` + lessonplan_data.lesson_days[6]
                        .selfstudyactivity + `</td></tr>
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
                                <tr><td><b>Self-study & Activity:</b> ` + lessonplan_data.lesson_days[7]
                        .selfstudyactivity + `</td></tr>
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
                                <tr><td><b>Self-study & Activity:</b> ` + lessonplan_data.lesson_days[8]
                        .selfstudyactivity + `</td></tr>
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
                                <tr><td><b>Self-study & Activity:</b> ` + lessonplan_data.lesson_days[9]
                        .selfstudyactivity + `</td></tr>
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
                                <tr><td><b>Self-study & Activity:</b> ` + lessonplan_data.lesson_days[10]
                        .selfstudyactivity + `</td></tr>
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
                                <tr><td><b>Self-study & Activity:</b> ` + lessonplan_data.lesson_days[11]
                        .selfstudyactivity + `</td></tr>
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
              `<td  class="border ${
                  col?.header && col?.elementProps?.value ? "sucsees" : (col?.header ? "failed" : "")
                }" scope="row" style=${
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
