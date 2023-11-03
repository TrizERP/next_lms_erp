{{--@include('includes.lmsheadcss')
@include('includes.header')
@include('includes.sideNavigation')--}}
@extends('lmslayout')
@section('container')
<!-- Content main Section -->
<div class="content-main flex-fill">
    <div class="row">
        <div class="col-md-6">
            <h1 class="h4 mb-3">Topic List</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb bg-transparent p-0">
                    <li class="breadcrumb-item"><a href="{{route('course_master.index')}}">LMS</a></li>
                    <li class="breadcrumb-item"><a
                            href="{{ route('chapter_master.index',['standard_id'=>$data['breadcrum_data']->standard_id ?? '','subject_id'=>$data['breadcrum_data']->subject_id ?? '']) }}">{{$data['breadcrum_data']->subject_name ?? ''}}</a>
                    </li>
                    <li class="breadcrumb-item active"
                        aria-current="page">{{$data['breadcrum_data']->chapter_name ?? ''}}</li>
                </ol>
            </nav>
        </div>

        <div class="col-md-6 text-right">
            @php

                $user_profile = Session::get('user_profile_name');
                $show_block = 'NO';
                if(strtoupper($user_profile) == 'LMS TEACHER' || strtoupper($user_profile) == 'TEACHER')
                {
                    $show_block = 'YES';
                }
            @endphp

            <div class="course-select-grid">
                <!--  <select class="cust-select form-control mb-0">
                     <option>Semester-2</option>
                     <option>Semester-1</option>
                 </select> -->

                <div class="course-lg-tab d-table mb-4">
                    <ul class="nav nav-tabs border-0" id="lgTab" role="tablist">
                        @php
                            $user_profile = Session::get('user_profile_name');
                            $listview_active = $gridview_active = "";
                        @endphp
                        @if(strtoupper($user_profile) == 'LMS TEACHER' || strtoupper($user_profile) == 'TEACHER')
                            @php
                                $listview_active = "show active";
                            @endphp
                            <li class="nav-item">
                                <a class="nav-link active" id="oer" data-toggle="tab" href="#list" role="tab"
                                   aria-controls="profile" aria-selected="true"><i
                                        class="mdi mdi-format-list-bulleted-square"></i></a>
                            </li>
                        @else
                            @php
                                $gridview_active = "show active";
                            @endphp
                            <li class="nav-item">
                                <a class="nav-link active" id="my_course" data-toggle="tab" href="#grid" role="tab"
                                   aria-controls="home" aria-selected="false"><i class="mdi mdi-grid-large"></i></a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" id="oer" data-toggle="tab" href="#list" role="tab"
                                   aria-controls="profile" aria-selected="true"><i
                                        class="mdi mdi-format-list-bulleted-square"></i></a>
                            </li>
                        @endif
                        <li>
                            @if($show_block == 'YES')
                                <button type="button" class="btn btn-light" data-toggle="modal"
                                        onclick="javascript:add_data();"><i class="fa fa-plus"></i> Add New Topic
                                </button>
                            @endif
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <div class="container-fluid mb-5">
        <div class="tab-content" id="lgTab">
            <!--Grid view Display -->
            <div class="course-grid-tab tab-pane fade {{$gridview_active}}" id="grid" role="tabpanel"
                 aria-labelledby="grid-tab">
                <ul class="nav nav-pills mb-3" id="pills-tab" role="tablist">
                    @php $i = $j = 0;
                    @endphp
                    @foreach($data['topic_data'] as $topickey => $topicvalue)
                        @php
                            $selected = "false";
                            $active = "";
                            if($i == 0)
                            {
                                $selected = "true";
                                $active = "active";
                            }
                            $i++;
                        @endphp
                        <li class="nav-item">
                            <a class="nav-link {{$active}}" id="a{{$i}}-tab" data-toggle="pill" href="#a{{$i}}"
                               role="tab" aria-controls="pills-home"
                               aria-selected="{{$selected}}">{{$topicvalue->name}}</a>
                        </li>
                    @endforeach

                </ul>
                <div class="tab-content" id="pills-tabContent">
                    @foreach($data['topic_data'] as $topickey1 => $topicvalue1)
                        @php
                            $active_content = "";
                            if($j == 0)
                            {
                                $active_content = "show active";
                            }
                            $j++;
                        @endphp
                        <div class="tab-pane fade {{$active_content}}" id="a{{$j}}" role="tabpanel"
                             aria-labelledby="a{{$j}}-tab">

                        <!-- <button type="button" class="btn btn-info" data-toggle="modal" data-target="#TopicModal"><i class="fa fa-plus"></i> Add Content for {{$topicvalue1->name}}</button> -->
                            <div class="row">
                                @if(isset($data['content_data'][$topicvalue1->id]))
                                    @foreach($data['content_data'][$topicvalue1->id] as $ckey => $cval)
                                        <div class="col-md-3 mb-3">
                                            <div class="video-box">
                                                <div class="video-img-box">
                                                    <div class="video-img">
                                                        <!-- <img src="assets/images/slide1.jpg" alt=""> -->
                                                    <!-- <iframe width="560" height="315" src="../../../storage{{$cval['file_folder']}}/{{$cval['filename']}}" frameborder="0" allowfullscreen></iframe> -->
                                                        @if($cval['file_type'] == "link")
                                                            <center>
                                                                <a target="_blank" href="{{$cval['filename']}}"><img
                                                                        src="../admin_dep/images/clickhere.jpg"
                                                                        style="margin-top:30px;width:100px;height:100px;"/></a>
                                                            </center>
                                                        @else
                                                            <video controls="true" width="220" height="140"
                                                                   class="w-100 h-100 object-cover mh-100">
                                                                <source src="{{route('topic_master.show',$cval['id'])}}"
                                                                        type="video/mp4"/>
                                                            </video>
                                                        <!-- <a href="{{route('topic_master.show',$cval['id'])}}" class="view-box">
                                                                        <i class="mdi mdi-eye-outline"></i>
                                                                    </a> -->
                                                        @endif
                                                    </div>
                                                <!-- <a href="{{route('topic_master.show',$cval['id'])}}" class="view-box">
                                                                <i class="mdi mdi-eye-outline"></i>
                                                            </a> -->
                                                    @if($cval['file_type'] == "link")
                                                        <a href="{{$cval['filename']}}" target="_blank"
                                                           class="view-box">
                                                            <i class="mdi mdi-eye-outline"></i>
                                                        </a>
                                                    @else
                                                        <a href="{{route('topic_master.show',$cval['id'])}}"
                                                           class="view-box">
                                                            <i class="mdi mdi-eye-outline"></i>
                                                        </a>
                                                    <!-- <a href="../../../storage{{$cval['file_folder']}}/{{$cval['filename']}}" target="_blank" class="view-box">
                                                                    <i class="mdi mdi-eye-outline"></i>
                                                                </a> -->
                                                    @endif
                                                </div>
                                                <div class="video-details">
                                                <!-- <a href="{{route('topic_master.show',$cval['id'])}}" class="video-title">{{$cval['title']}}</a> -->
                                                    <a class="video-title">{{$cval['title']}}</a>
                                                    <div class="d-flex justify-content-between"></div>
                                                    <div class="row gutter-10">
                                                        @if(isset($cval['FLASHCARD']))
                                                            @foreach($cval['FLASHCARD'] as $fkey => $fval)
                                                                <div class="col-md-4">
                                                                    <div style="width:100%"
                                                                         onclick="openModal({{$ckey}},{{$fkey}});currentSlide({{$ckey}}{{$fkey}})"
                                                                         class="hover-shadow cursor card py-3 px-2">{{$fval['title']}}</div>
                                                                </div>
                                                            @endforeach
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
            <!--Grid view Display -->
            <!--List view Display -->
            @if(strtoupper($user_profile) == 'STUDENT' && strtoupper($user_profile) != 'TEACHER' && strtoupper($user_profile) != 'LMS TEACHER')
                <div class="tab-pane fade {{$listview_active}}" id="list" role="tabpanel" aria-labelledby="list-tab">
                    @php
                        $k = 1;
                    @endphp
                    @if(isset($data['topic_data']) && count($data['topic_data']) > 0)
                        @foreach($data['topic_data'] as $list_topickey => $list_topicvalue)
                            @php
                                $active_content = "";
                                if($j == 0)
                                {
                                    $active_content = "show active";
                                }
                                $j++;
                            @endphp
                            <div class="accordion-card collapsed card px-2 pt-2 border-0 course-box"
                                 data-toggle="collapse" href="#collapseExample{{$k}}" role="button"
                                 aria-expanded="false" aria-controls="collapseExample">
                                @php
                                    $blur_block_style = "";
                                    if($list_topicvalue->topic_show_hide != 1){
                                        $blur_block_style = "background-color: #817979 !important;";
                                    }
                                @endphp
                                <div class="row align-items-center" style="{{$blur_block_style}}">
                                    <div class="col-md-4 mb-2">
                                        <div class="video-sec-title mb-0">
                                            <div class="icon-box"><i class="mdi mdi-video-check-outline"></i></div>
                                            <div class="h4 mb-0 d-flex">{{$list_topicvalue->name}}</div>
                                        </div>
                                    </div>
                                    <div class="col-md-8 mb-2 d-md-flex align-items-center justify-content-end">
                                        @php
                                            $lp_title = $data['breadcrum_data']->chapter_name.' - '.$list_topicvalue->name;


                                            $sub_institute_id = Session::get('sub_institute_id');
                                            $syear = Session::get('syear');
                                            $chapter_id = $_REQUEST['id'];

                                            $booklist_data = DB::select("SELECT * FROM book_list
                                                                    WHERE standard_id = $list_topicvalue->standard_id AND subject_id = $list_topicvalue->subject_id AND chapter_id = $chapter_id AND topic_id = $list_topicvalue->id AND sub_institute_id = $sub_institute_id AND syear = $syear ");
                                            $booklist_data = json_decode(json_encode($booklist_data),true);
                                        @endphp
                                        @if(!empty($booklist_data))
                                            <div class="single-item position-relative mr-3">
                                                <i class="mdi mdi-dots-vertical-circle-outline"></i>
                                                <ul class="sub-menu">
                                                    @foreach($booklist_data as $k => $book_data)
                                                        @php
                                                            $file_name = '';
                                                            if($book_data['file_name'] != '')
                                                            {
                                                                $file_name = '/storage/book_list/'.$book_data['file_name'];
                                                            }else{
                                                                $file_name = $book_data['link'];
                                                            }
                                                        @endphp
                                                        <li>
                                                            <a target="_blank" href="{{$file_name}}"
                                                               class="text-dark">{{$book_data['title']}}</a>
                                                        </li>
                                                    @endforeach
                                                </ul>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                            <div class="video-list mb-4 collapse" id="collapseExample{{$k}}" data-parent="#list">
                                @if(isset($data['content_data'][$list_topicvalue->id]))
                                    @foreach($data['content_data'][$list_topicvalue->id] as $ckey => $cval)
                                        @php
                                            $blur_content_style = "";
                                            if($cval['show_hide'] != 1){
                                                $blur_content_style = "background-color: #817979 !important;";
                                            }
                                        @endphp
                                        <div class="video-box mb-2" style="{{$blur_content_style}}">
                                            @if($cval['file_type'] == "link")
                                                <a target="_blank" href="{{$cval['filename']}}"><img
                                                        src="../admin_dep/images/clickhere.jpg" width="100px"/></a>
                                            @else
                                                <div class="video-img-box">
                                                    <div class="video-img">
                                                        <video controls="true" width="220" height="140"
                                                               controlsList="nodownload">
                                                            <source
                                                                src="../../../storage{{$cval['file_folder']}}/{{$cval['filename']}}"
                                                                type="video/mp4"/>
                                                        </video>
                                                    </div>
                                                    <a href="{{route('topic_master.show',$cval['id'])}}" target="_blank"
                                                       class="view-box">
                                                        <i class="mdi mdi-eye-outline"></i>
                                                    </a>
                                                </div>
                                            @endif
                                            <div class="video-details">
                                                <a class="video-title">{{$cval['title']}}</a>
                                                <div class="video-des">{{$cval['description']}}</div>
                                            </div>
                                        </div>
                                    @endforeach
                                @endif
                            </div>
                            @php
                                $k++;
                            @endphp
                        @endforeach
                    @else
                        <div class="card col-md-12">
                            <div class="form-group mt-3">
                                <center>No Records Found.</center>
                            </div>
                        </div>
                    @endif
                </div>
            @endif
        <!--List view Display -->

            @if(isset($data['topic_data']))
                @foreach($data['topic_data'] as $topickey1 => $topicvalue1)
                    @if(isset($data['content_data'][$topicvalue1->id]))
                        @php
                            $f = 1;
                        @endphp
                        @foreach($data['content_data'][$topicvalue1->id] as $ckey => $cval)
                            <div id="myModal_{{$ckey}}" class="modal flash-card-modal">
                                <span class="close cursor" onclick="closeModal({{$ckey}})">&#10007;</span>
                                <div class="modal-content">
                                    <div class="container">
                                        <div class="tab-content">
                                            @if(isset($cval['FLASHCARD']))
                                                @php
                                                    $z=0;
                                                @endphp
                                                @foreach($cval['FLASHCARD'] as $fkey => $fval)
                                                    @php
                                                        $tab_active = "";
                                                        if($z==0)
                                                        {
                                                            $tab_active = "active";
                                                        }
                                                        $z++;
                                                    @endphp

                                                    <div class="tab-pane {{$tab_active}}"
                                                         id="mySlides_{{$ckey}}{{$fkey}}" role="tabpanel">
                                                    <!-- <div class="numbertext">{{$f}} / 3</div> -->
                                                        @php

                                                            $front_text = preg_replace("/<p[^>]*>(?:\s|&nbsp;)*<\/p>/", '', $fval['front_text']);
                                                            $back_text = preg_replace("/<p[^>]*>(?:\s|&nbsp;)*<\/p>/", '', $fval['back_text']);

                                                        @endphp
                                                        <div class="mySlides d-block">
                                                            <div class="front-image w-100 card text-center">
                                                                <h3>{!!$front_text!!}</h3></div>
                                                            <div class="back-image w-100 card text-center">
                                                                <h3>{!!$back_text!!}</h3></div>
                                                        </div>
                                                    </div>
                                                    @php $f++; @endphp
                                                @endforeach
                                            @endif
                                        </div>

                                        <!-- <div class="caption-container">
                                            <p id="caption"></p>
                                        </div> -->

                                        <ul class="nav nav-tabs customtab2 border-0 mt-3" role="tablist">
                                            @php
                                                $f = 1;
                                            @endphp
                                            @if(isset($cval['FLASHCARD']))
                                                @foreach($cval['FLASHCARD'] as $fkey => $fval)
                                                    @php
                                                        $li_active = "";
                                                        if($f == 1)
                                                        {
                                                            $li_active = "active";
                                                        }
                                                    @endphp
                                                    <li class="nav-item">
                                                        <a class="nav-link demo cursor {{$li_active}}" data-toggle="tab"
                                                           href="#mySlides_{{$ckey}}{{$fkey}}"
                                                           role="tab">{{$fval['title']}}</a>
                                                    </li>
                                                    @php $f++; @endphp
                                                @endforeach
                                            @endif
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    @endif
                @endforeach
            @endif

            <script>
                function openModal(k, slide_no) {
                    // document.querySelector(".flash-card-modal").style.display = "none";
                    document.getElementById("myModal_" + k).style.display = "block";
                    // document.getElementById("mySlides_"+k+slide_no).style.display = "block";
                }

                function closeModal(k) {
                    document.getElementById("myModal_" + k).style.display = "none";
                }

                function showSlides(n) {
                    var i;
                    var slides = document.getElementsByClassName("mySlides");
                    var dots = document.getElementsByClassName("demo");
                    var captionText = document.getElementById("caption");
                    if (n > slides.length) {
                        slideIndex = 1
                    }
                    if (n < 1) {
                        slideIndex = slides.length
                    }
                    for (i = 0; i < slides.length; i++) {
                        slides[i].style.display = "none";
                    }
                    for (i = 0; i < dots.length; i++) {
                        //dots[i].className = dots[i].className.replace(" active", "");
                    }
                    slides[slideIndex - 1].style.display = "block";
                    dots[slideIndex - 1].className += " active";
                    //captionText.innerHTML = dots[slideIndex-1].alt;
                }


                var slideIndex = 1;
                showSlides(slideIndex);

                function plusSlides(n) {
                    showSlides(slideIndex += n);
                }

                function currentSlide(n) {
                    showSlides(slideIndex = n);
                }

                $(document).ready(function () {
                    $(".flash-card-modal .tab-content .tab-pane:first-child").addClass("active");
                    $("ul.nav.nav-tabs li:first-child .nav-link").addClass("active");
                });
            </script>

            <div class="tab-pane fade {{$listview_active}}" id="list" role="tabpanel" aria-labelledby="list-tab">
                @php
                    $k = 1;
                @endphp
                @if(isset($data['topic_data']) && count($data['topic_data']) > 0)
                    @foreach($data['topic_data'] as $list_topickey => $list_topicvalue)
                        @php
                            $active_content = "";
                            if($j == 0)
                            {
                                $active_content = "show active";
                            }
                            $j++;
                        @endphp
                        @if(strtoupper($user_profile) == 'LMS TEACHER' || strtoupper($user_profile) == 'TEACHER')
                            <div class="accordion-card collapsed card px-2 pt-2 border-0 course-box"
                                 data-toggle="collapse" href="#collapseExample{{$k}}" role="button"
                                 aria-expanded="false" aria-controls="collapseExample">
                                @php
                                    $blur_block_style = "";
                                    if($list_topicvalue->topic_show_hide != 1){
                                        $blur_block_style = "background-color: #817979 !important;";
                                    }
                                @endphp
                                <div class="row align-items-center" style="{{$blur_block_style}}">
                                    <div class="col-md-4 mb-2">
                                        <div class="video-sec-title mb-0">
                                            <div class="icon-box"><i class="mdi mdi-video-check-outline"></i></div>
                                            <div class="h4 mb-0 d-flex">{{$list_topicvalue->name}}</div>
                                            <div class="help-arraw ml-auto">
                                                <i class="mdi mdi-chevron-down"></i>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-8 mb-2 d-md-flex align-items-center justify-content-end">
                                        @php
                                            $lp_title = $data['breadcrum_data']->chapter_name.' - '.$list_topicvalue->name;


                                            $sub_institute_id = Session::get('sub_institute_id');
                                            $syear = Session::get('syear');
                                            $chapter_id = $_REQUEST['id'];

                                            $booklist_data = DB::select("SELECT * FROM book_list
                                                                    WHERE standard_id = $list_topicvalue->standard_id AND subject_id = $list_topicvalue->subject_id AND chapter_id = $chapter_id AND topic_id = $list_topicvalue->id AND sub_institute_id = $sub_institute_id AND syear = $syear ");
                                            $booklist_data = json_decode(json_encode($booklist_data),true);
                                        @endphp
                                        @if(!empty($booklist_data))
                                            <div class="single-item position-relative mr-3">
                                                <i class="mdi mdi-dots-vertical-circle-outline"></i>
                                                <ul class="sub-menu">
                                                    @foreach($booklist_data as $k => $book_data)
                                                        @php
                                                            $file_name = '';
                                                            if($book_data['file_name'] != '')
                                                            {
                                                                $file_name = '/storage/book_list/'.$book_data['file_name'];
                                                            }else{
                                                                $file_name = $book_data['link'];
                                                            }
                                                        @endphp
                                                        <li>

                                                        </li>
                                                    @endforeach
                                                </ul>
                                            </div>
                                        @endif


                                        <a target="_blank"
                                           href="{{ route('subjectwise_graph.show',['subjectwise_graph'=>$list_topicvalue->subject_id,'topic_id'=> $list_topicvalue->id,'topic_name'=>$list_topicvalue->name,'action'=>'topicwise']) }}">
                                            <img src="../../../admin_dep/images/graph_icon.png"
                                                 style="float:right;height:25px;width:25px;margin-top:-12px;margin-right:5px;">
                                        </a>
                                        <a target="_blank"
                                           href="{{ route('lms_teacherResource.index',['standard_id'=>$list_topicvalue->standard_id,'subject_id'=>$list_topicvalue->subject_id,'chapter_id' => $_REQUEST['id'],'topic_id' => $list_topicvalue->id]) }}"
                                           class="btn btn-outline-dark  mx-1">Teacher Resource</a>
                                        <a target="_blank"
                                           href="{{ route('lms_lessonplan.index',['standard_id'=>$list_topicvalue->standard_id,'subject_id'=>$list_topicvalue->subject_id,'title'=>$lp_title]) }}"
                                           class="btn btn-outline-dark  mx-1">Lesson Planning</a>
                                        <a target="_blank"
                                           href="{{ route('lmsmapping.index',['topic_id'=>$list_topicvalue->id]) }}"
                                           class="btn btn-outline-dark  mx-1">Topic-wise Mapping</a>
                                        <a target="_blank"
                                           href="{{ route('content_master.create', ['chapter_id' => $_REQUEST['id'],'topic_id' => $list_topicvalue->id,'standard_id'=>$_REQUEST['standard_id']]) }}"
                                           class="btn btn-outline-dark mx-1 my-1">Add Content</a>
                                        <a target="_blank"
                                           href="{{route('question_master.index', ['chapter_id' => $_REQUEST['id'],'topic_id' => $list_topicvalue->id,'standard_id'=>$_REQUEST['standard_id']])}}"
                                           class="btn btn-outline-dark mx-1 my-1">Question Answer</a>
                                        <a target="_blank"
                                           href="{{ route('virtual_classroom_master.create', ['chapter_id' => $_REQUEST['id'],'topic_id' => $list_topicvalue->id]) }}"
                                           class="btn btn-outline-dark  mx-1 my-1">Virtual Classroom</a>
                                        <!-- <a target="_blank" class="btn btn-outline-dark mx-1 my-1">Flash Card</a> -->
                                        @if(strtoupper($user_profile) == 'LMS TEACHER' || strtoupper($user_profile) == 'TEACHER')
                                            <a href="javascript:edit_data('{{route('topic_master.update',$list_topicvalue->id)}}','{{$list_topicvalue->id}}','{{$list_topicvalue->name}}','{{$list_topicvalue->description}}','{{$list_topicvalue->topic_sort_order}}','{{$list_topicvalue->topic_show_hide}}');"
                                               class="btn btn-outline-success btn-sm mx-1 my-1"><i
                                                    class="mdi mdi-pencil-outline"></i></a>
                                            <form action="{{ route('topic_master.destroy', $list_topicvalue->id)}}"
                                                  method="post"
                                                  onsubmit="return delete_topic({{$list_topicvalue->id}});">
                                                @csrf
                                                @method('DELETE')
                                                <input type="hidden" name="standard_id" value="{{$_REQUEST['standard_id']}}">
                                                <button onclick="return confirmDelete();" type="submit"
                                                        class="btn btn-outline-danger btn-sm mx-1 my-1">
                                                    <i class="mdi mdi-delete-outline"></i></button>
                                                <!-- <a href="#" onclick="document.myform.submit()" class="d-block mx-2"><i class="mdi mdi-delete-outline"></i></a> -->
                                            </form>
                                        @endif
                                    </div>
                                </div>
                            <!-- <div id="topic1" class="collapse show" aria-labelledby="headingOne" data-parent="#accordion">
                                            <ul class="add-topic-box">
                                                <li>
                                                    <a href="{{ route('content_master.create', ['chapter_id' => $_REQUEST['id'],'topic_id' => $list_topicvalue->id]) }}">Add Content</a>
                                                </li>
                                                <li>
                                                    <a href="{{route('question_master.index', ['chapter_id' => $_REQUEST['id'],'topic_id' => $list_topicvalue->id])}}">Question Answer</a>
                                                </li>
                                                <li>
                                                    <a href="{{ route('virtual_classroom_master.create', ['chapter_id' => $_REQUEST['id'],'topic_id' => $list_topicvalue->id]) }}">Add Virtual Classroom</a>
                                                </li>
                                            </ul>
                                        </div> -->
                            </div>
                        @endif
                        <div class="video-list mb-4 collapse" id="collapseExample{{$k}}" data-parent="#list">
                        <div class="video-list mb-4" id="accordion">
                            @if(isset($data['content_data'][$list_topicvalue->id]))
                                @php
                                    $categories = collect($data['content_data'][$list_topicvalue->id])->groupBy('content_category');
                                @endphp

                                @foreach($categories as $category => $contentItems)
                                    <div class="card ml-5 mt-">
                                        <div class="mb-2  mt-2 chapter-content-single p-3 d-flex align-items-center" data-toggle="collapse" id="heading{{$category}}" aria-controls="collapse{{$category}}" data-target="#collapse{{str_replace(' ', '', $category)}}">
                                            <div class="content-category">{{ $category }}</div>
                                            <div class="help-arraw">
                                                <i class="mdi mdi-chevron-down"></i>
                                            </div>
                                        </div>

                                        <div id="collapse{{str_replace(' ', '', $category)}}" class="collapse" aria-labelledby="heading{{$category}}" data-parent="#accordion">
                                            @foreach($contentItems as $cval)
                                                <div class="video-box mb-2">

                                                    @if($cval['file_type'] == "link")
                                                        <a target="_blank" href="{{$cval['filename']}}"><img src="../admin_dep/images/clickhere.jpg" width="100px"/></a>
                                                    @else
                                                        <div class="video-img-box">
                                                            <div class="video-img">
                                                                <video controls="true" width="10" height="10" controlsList="nodownload">
                                                                    <source src="../../../storage{{$cval['file_folder']}}/{{$cval['filename']}}" type="video/mp4"/>
                                                                </video>
                                                            </div>
                                                            <a href="{{route('topic_master.show',$cval['id'])}}" target="_blank" class="view-box">
                                                                <i class="mdi mdi-eye-outline"></i>
                                                            </a>
                                                        </div>
                                                    @endif
                                                    <div class="video-details">
                                                        <a class="video-title">{{$cval['title']}}</a>
                                                        <div class="video-des">{{$cval['description']}}</div>
                                                    </div>
                                                    @if(strtoupper($user_profile) == 'LMS TEACHER' || strtoupper($user_profile) == 'TEACHER')
                                                        <div class="time text-secondary d-flex" style="font-size: 20px;">
                                                            <a href="{{ route('lms_flashcard.index',['content_id'=>$cval['id']])}}" target="_blank" class="btn btn-outline-warning btn-sm mx-1" data-toggle="tooltip" title="Add Flash Card"><i class="mdi mdi-cards-playing-outline"></i></a>
                                                            <a href="{{ route('content_master.edit',[$cval['id'],$cval['standard_id']])}}" class="btn btn-outline-success btn-sm mx-1"><i class="mdi mdi-pencil-outline"></i></a>
                                                            <form action="{{ route('content_master.destroy', $cval['id'] )}}" method="post">
                                                                @csrf
                                                                @method('DELETE')
                                                                <input type="hidden" name="standard_id" value="{{$_REQUEST['standard_id']}}">
                                                                <button onclick="return confirmDelete();" type="submit" class="btn btn-outline-danger btn-sm mx-1"><i class="mdi mdi-delete-outline"></i></button>
                                                            </form>
                                                        </div>
                                                    @endif
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endforeach
                            @endif
                        </div>
                        </div>


                        @php
                            $k++;
                        @endphp
                    @endforeach
                @else
                    <div class="card col-md-12">
                        <div class="form-group mt-3">
                            <center>No Records Found.</center>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>

</div>

<!--Modal: TopicModal-->
<div class="modal fade right modal-scrolling" id="TopicModal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel"
     style="display: none;" aria-hidden="true">
    <div class="modal-dialog modal-side modal-bottom-right modal-notify modal-info" role="document">
        <!--Content-->
        <div class="modal-content">
            <!--Header-->
            <div class="modal-header">
                <h5 class="modal-title" id="heading">Add Topic</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">x</span>
                </button>
            </div>

            <!--Body-->
            <form action="{{ route('topic_master.store') }}" method="post" id="topic_form">
                <div id="soni">
                    {{ method_field("POST") }}
                </div>
                @csrf
                <div class="modal-body">
                    <div class="row">
                        <div class="white-box">
                            <div class="panel-body">
                                @if ($message = Session::get('success'))
                                    <div class="alert alert-success alert-block">
                                        <button type="button" class="close" data-dismiss="alert">×</button>
                                        <strong>{{ $message }}</strong>
                                    </div>
                                @endif
                                <div class="col-lg-12 col-sm-12 col-xs-12">
                                    <input type="hidden" id='hidchapter_id' name='hidchapter_id'
                                           value="{{$_REQUEST['id']}}" class="form-control">
                                       <input type="hidden" name="standard_id" value="{{$_REQUEST['standard_id']}}">

                                    <div class="addButtonCheckbox">
                                        <div class="col-md-12 form-group">
                                            <label>Topic Name</label>
                                            <input type="text" id='topic_name' required name="topic_name[]"
                                                   class="form-control">
                                        </div>

                                        <div class="col-md-12 form-group">
                                            <label>Description</label>
                                            <textarea id="topic_desc" name="topic_desc[]"
                                                      class="form-control"></textarea>
                                        </div>
                                        <div class="col-md-12 form-group">
                                            <label>Sort Order</label>
                                            <input type="text" id="topic_sort_order" name="topic_sort_order[]"
                                                   class="form-control">
                                        </div>
                                        <div class="col-md-12 form-group">
                                            <label>Show</label><br>
                                            <input type="checkbox" value="1" id="topic_show_hide"
                                                   name="topic_show_hide[]">
                                        </div>

                                        <!--<div class="col-md-1 form-group">
                                            <br>
                                            <a href="javascript:void(0);" onclick="addNewRow();"><span class="circle circle-sm bg-success di form-control"><i class="ti-plus"></i></span></a>
                                        </div> -->
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!--Footer-->
                <div class="modal-footer flex-center">
                    <input type="submit" name="submit" id="submit" value="Save" class="btn btn-success">
                </div>
            </form>
        </div>
        <!--/.Content-->
    </div>
</div>

@include('includes.lmsfooterJs')
<script src="https://cdn.datatables.net/1.10.19/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.10.19/js/dataTables.bootstrap.min.js"></script>
<script>

    function edit_data(url, topic_id, topic_name, topic_desc, topic_sort_order, topic_show_hide) {
        $("#topic_name").val(topic_name);
        $("#topic_desc").val(topic_desc);
        $("#topic_sort_order").val(topic_sort_order);
        if (topic_show_hide == 1) {
            $('#topic_show_hide').prop('checked', true);
        }

        $('#submit').val('Update');
        $('#heading').html('Update Topic');
        $('#topic_form').attr('action', url);
        $('#soni').html('{{ method_field("PUT") }}');
        $('#TopicModal').modal('show');
    }

    function add_data() {
        var url = "{{ route('topic_master.store') }}";
        $("#topic_name").val("");
        $("#topic_desc").val("");
        $("#topic_sort_order").val("");
        $('#submit').val('Add');
        $('#heading').html('Add Topic');
        $('#topic_form').attr('action', url);
        $('#soni').html('{{ method_field("POST") }}');
        $('#topic_show_hide').prop('checked', true);
        $('#TopicModal').modal('show');
    }

    function delete_topic(topic_id) {
        if (confirm('Are you sure?')) {
            var error = 1;
            var path = "{{ route('ajax_topicDependencies') }}";
            $.ajax({
                url: path,
                data: "topic_id=" + topic_id,
                async: false,
                success: function (result) {
                    if (result > 0) {
                        alert("You cannot delete Topic.Topic is having dependencies in Other Module");
                        error = 1;
                    } else {
                        error = 0;
                    }
                },
                failure: function (er) {
                    alert('error' + er);
                    error = 1;
                }
            });
        } else {
            error = 1;
        }

        if (error == 1) {
            return false;
        } else {
            return true;
        }
    }

</script>

<script>
    $('.mySlides').click(function () {
        $(this).toggleClass('active');
    });
</script>

@include('includes.footer')
@endsection
