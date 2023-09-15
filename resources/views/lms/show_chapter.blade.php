@include('includes.lmsheadcss')
@include('includes.header')
@include('includes.sideNavigation')
use DB;
<!-- Content main Section -->
<div class="content-main flex-fill">
    <h1 class="h4 mb-3">Chapter List</h1>
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb bg-transparent p-0">
            <li class="breadcrumb-item"><a href="{{route('course_master.index')}}">LMS</a></li>
            @php $standard_name = DB::table('standard')->where('id',$_REQUEST['standard_id'])->get(); @endphp
            <li class="breadcrumb-item"><a href="#">{{ $standard_name[0]->name ?? '' }}</a></li>
            <li class="breadcrumb-item"><a href="#">{{$data['subject_name']}}</a></li>
        </ol>
    </nav>
    @php

        $user_profile = Session::get('user_profile_name');
        $show_block = 'NO';
        if(strtoupper($user_profile) == 'LMS TEACHER' || strtoupper($user_profile) == 'TEACHER')
        {
            $show_block = 'YES';
        }

        if(isset($_REQUEST['preload_lms'])) {
            $readonly="pointer-events: none";
            $preload_lms = "preload_lms=preload_lms";
        } 

    @endphp

    <div class="row align-items-center">
        <div class="col-md-6 mb-3">
        </div>
        <div class="col-md-6 mb-3">
        @if($show_block == 'YES')
            <!-- <a href="{{ route('chapter_master.create') }}" class="btn btn-info add-new"><i class="fa fa-plus"></i> Add New Chapter</a>                    -->
            <button type="button" class="btn btn-info float-right" data-toggle="modal" onclick="javascript:add_data();"><i class="fa fa-plus"></i> Add New Chapter</button>
            @endif
        </div>
    </div>


    <div class="container-fluid mb-5">
        <div class="coursr-chp-list" id="cource-chap-list">
            @php $i = 1; $collapse = 1; @endphp

            @if(isset($data['data']) && count($data['data']) > 0)
                @foreach($data['data'] as $key => $chdata)
                    @php
                        $blur_block_style = "";
                        if($chdata->show_hide != 1){
                           $blur_block_style = "background-color: #817979 !important;color:#fff";
                        }
                    @endphp
                    <div class="row card single-chp" style="{{$blur_block_style}}">
                        <div class="col-md-4 mb-2 chp-details" data-toggle="collapse"
                             href="#collapseExample{{ $collapse }}" role="button" aria-expanded="false"
                             aria-controls="collapseExample">
                            <div class="count">@php echo $i++;@endphp</div>
                            <div class="title">
                                @php if ( $data['show_content'] == 'chapterwise' ) { @endphp
                                <span>{{$chdata->chapter_name}}</span>
                                @php } else { @endphp
                                <a href="{{ route('topic_master.index',['id'=>$chdata->id,'standard_id'=>$_REQUEST['standard_id']]) }}">{{$chdata->chapter_name}}
                                    @php } @endphp
                                    {{-- @php
                                        if($chdata->chapter_desc){
                                        @endphp
                                            <br/><p>{{$chdata->chapter_desc}}</p>
                                        @php
                                        }
                                    @endphp --}}
                        </a>
                    </div>
                    @if ( $data['show_content'] == 'chapterwise' )
                                <div class="help-arraw">
                                    <i class="mdi mdi-chevron-down"></i>
                                </div>
                            @endif
                        </div>
                        <div class="col-md-8 mb-2 d-md-flex align-items-center justify-content-end total-count">

                            @if(Session::get('user_profile_name') != 'Student')
                                @if($chdata->total_triz_content > 0)
                                    <a href="{{ route('topic_master.index',['id'=>$chdata->id,'content_category'=>'Triz']) }}"
                                       class="btn btn-outline-dark mx-1 my-1">Triz</a>
                                @endif
                                @if($chdata->total_OER_content > 0)
                                    <a href="{{ route('topic_master.index',['id'=>$chdata->id,'content_category'=>'OER']) }}"
                                       class="btn btn-outline-dark mx-1 my-1">OER</a>
                                @endif
                                @if( $data['show_content'] == 'chapterwise' )
                                    <a target="_blank"
                                       href="{{ route('lms_teacherResource.index',['standard_id'=>$chdata->standard_id,'subject_id'=>$chdata->subject_id,'chapter_id'=>$chdata->id,$preload_lms ?? '']) }}"
                                       class="btn btn-outline-dark mx-1 my-1">Teacher Resource</a>
                                    <a target="_blank"
                                       href="{{ route('lms_lessonplan.index',['standard_id'=>$chdata->standard_id,'subject_id'=>$chdata->subject_id,'chapter_id'=>$chdata->id,$preload_lms ?? '']) }}"
                                       class="btn btn-outline-dark mx-1 my-1">Lesson Planning</a>
                                    <a target="_blank"
                                       href="{{ route('lmsmapping.index',['chapter_id'=>$chdata->id,$preload_lms ?? '']) }}"
                                       class="btn btn-outline-dark mx-1 my-1">Chapter-wise Mapping</a>
                                @endif
                            @endif

                            @if($show_block == 'YES')
                                @php
                                    $chapter_name = addslashes($chdata->chapter_name);
                                    $chapter_desc = addslashes($chdata->chapter_desc);
                                @endphp

                                @if( $data['show_content'] == 'chapterwise' )
                                    {{-- Add Chapter wise content : START --}}
                                    <a target="_blank"
                                       href="{{ route('create_content_master', ['chapter_id' => $chdata->id]) }}"
                                       class="btn btn-outline-dark mx-1 my-1">Add Content</a>
                                    {{-- Add Chapter wise content : END --}}

                                    {{-- Add Chapter wise Question : START --}}
                                    <a target="_blank"
                                       href="{{ route('question_chapter_master', ['chapter_id' => $chdata->id,'standard_id'=>$_REQUEST['standard_id']]) }}"
                                       class="btn btn-outline-dark mx-1 my-1">Question Answer</a>
                                    {{-- Add Chapter wise Question : END --}}
                                @endif

                                @php
                                    $sub_institute_id = Session::get('sub_institute_id');
                                    $syear = Session::get('syear');

                                    $booklist_data = DB::select("SELECT * FROM book_list
                                                            WHERE standard_id = $chdata->standard_id AND subject_id = $chdata->subject_id AND chapter_id = $chdata->id AND topic_id = 0 AND sub_institute_id = $sub_institute_id AND syear = $syear ");
                                    $booklist_data = json_decode(json_encode($booklist_data),true);
                                @endphp
                    @if(!empty($booklist_data))
                                    <span class="submenu-box pr-1 mr-1 border-right">
                        <i class="mdi mdi-dots-vertical-circle-outline"></i>
                        <ul>
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
                                    <a target="_blank" href="{{$file_name}}" class="text-dark">{{$book_data['title']}}</a>
                                </li>
                            @endforeach
                        </ul>
                    </span>
                    @endif
                    <span class="pr-2 mr-1 border-right">
                        <a target="_blank" href="{{ route('subjectwise_graph.show',['subjectwise_graph'=>$chdata->subject_id,'standard_id'=>$chdata->standard_id,'chapter_id'=>$chdata->id,'chapter_name'=>$chdata->chapter_name,'action'=>'chapterwise']) }}">
                            <img src="../../../admin_dep/images/graph_icon.png"
                                 style="float:right;height:25px;width:25px;margin-top:-12px;margin-right:5px;">
                        </a>
                    </span>
                                {{-- @if(Session::get('user_profile_name') != 'Student')
                                <span class="submenu-box pr-1 mr-1 border-right">
                                    <i class="mdi mdi-dots-vertical-circle-outline"></i>
                                    <!-- <a target="_blank" href="{{ route('lmsmapping.index',['chapter_id'=>$chdata->id]) }}" class="text-dark">Add LMS Mapping</a> -->
                                    <ul>
                                        @if($chdata->total_triz_content > 0)
                                        <li>
                                            <a href="{{ route('topic_master.index',['id'=>$chdata->id,'content_category'=>'Triz']) }}" class="text-dark">Triz</a>
                                        </li>
                                        @endif
                                        @if($chdata->total_OER_content > 0)
                                        <li>
                                            <a href="{{ route('topic_master.index',['id'=>$chdata->id,'content_category'=>'OER']) }}">OER</a>
                                        </li>
                                        @endif
                                        <li>
                                            <a target="_blank" href="{{ route('lmsmapping.index',['chapter_id'=>$chdata->id]) }}" class="text-dark">Add Chapter-wise LMS Mapping</a>
                                        </li>
                                        <li>
                                            <a target="_blank" href="{{ route('lms_lessonplan.index',['standard_id'=>$chdata->standard_id,'subject_id'=>$chdata->subject_id,'title'=>$chdata->chapter_name]) }}" class="text-dark">Add Lesson Planning</a>
                                        </li>
                                        <li>
                                            <a target="_blank" href="{{ route('lms_teacherResource.index',['standard_id'=>$chdata->standard_id,'subject_id'=>$chdata->subject_id,'chapter_id'=>$chdata->id]) }}" class="text-dark">Add Teacher Resource</a>
                                        </li>

                                    </ul>
                                </span>
                                @endif --}}
                    @php
                        $totalContentCount = 0;
                        if ( isset($data['content_data'][$chdata->id]) ) {
                            $totalContentCount = count($data['content_data'][$chdata->id]);
                        }
                        // echo "<pre>";
                        // print_r($data['show_content']);
                        // exit;
                    @endphp

                                @if ( $data['show_content'] == 'chapterwise' )
                                    <span class="pr-2 mr-1 border-right d-flex">
                        {{-- <i class="mdi mdi-file-video-outline"></i> <span>{{$chdata->total_content}}</span> --}}
                        <i class="mdi mdi-file-video-outline"></i> <span>{{ $totalContentCount }}</span>
                    </span>


                                @endif
                                <a href="javascript:edit_data('{{route('chapter_master.update',$chdata->id)}}','{{$chdata->id}}','{{$chdata->standard_id}}','{{$chapter_name}}','{{$chapter_desc}}','{{$chdata->availability}}','{{$chdata->show_hide}}','{{$chdata->sort_order}}');"
                                   class="pr-1 mr-1 border-right text-dark"><i
                                        class="mdi mdi-pencil-outline m-0"></i></a>
                                <form action="{{ route('chapter_master.destroy', $chdata->id)}}" method="post"
                                      onsubmit="return delete_chapter({{$chdata->id}});">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="pr-1 mr-1 border-right"
                                            style="border: none !important;background-color: transparent !important;">
                                        <i class="mdi mdi-delete-outline text-dark m-0"></i></button>
                                    <!-- <a href="#" onclick="document.myform.submit()" class="d-block mx-2"><i class="mdi mdi-delete-outline"></i></a> -->
                                </form>
                            @endif
                        </div>
                    </div>
                    @if( (isset($data['content_data'][$chdata->id]) && !empty($data['content_data'][$chdata->id])) &&  ($data['show_content'] == 'chapterwise') )
                <div class="chapter-content-list mb-4 collapse" id="collapseExample{{ $collapse }}" style="">
                    @php $subColapse = 1; @endphp
                    @foreach ( $data['content_data'][$chdata->id] as $con_key => $content )
                    @php
                        // echo "<pre>"; print_r($data['content_data'][$chdata->id]); exit;
                    @endphp
                        <div class="mb-2 mt-2 chapter-content-single p-3 d-flex align-items-center" data-collapse_id="{{ $chdata->id }}-{{ $subColapse }}" onclick="tarCollapse(this)" >
                            <div class="content-category">{{ $con_key }}</div>
                            <div class="help-arraw">
                                <i class="mdi mdi-chevron-down"></i>
                            </div>
                        </div>
                        {{-- @php
                            echo "<pre>"; print_r($con_key);
                            echo "<pre>"; print_r($content); exit;
                        @endphp --}}
                    <div class="chapter-content-list-cola mb-4 collapse" id="chapter-content-tar-list-{{ $chdata->id }}-{{ $subColapse }}" data-parent="#cource-chap-list" style="">
                         @php 
                        $no = 1;
                        @endphp
                        @foreach( $content as $single_content )
                            {{-- @php
                                echo "<pre>"; print_r($single_content); exit;
                            @endphp --}}

                        <div class="row chapter-content-box my-2 py-2 mx-0">
                            <div class="col-md-1 chapter-img-box">
                                @php
                                    if ( $single_content['url'] != '' ) {
                                        $content_file_url = $single_content['url'];
                                    } else {
                                        $content_file_url = url('storage'.$single_content['file_folder'].'/'.$single_content['filename']);
                                    }
                                    $icons = ['pdf'=> 'mdi mdi-file-pdf-box', 'mp4' => 'mdi mdi-video','link' => 'mdi mdi-file-link', 'html' => 'mdi mdi-language-html5', 'mov' => 'mdi mdi-movie', 'docx' => 'mdi mdi-file-document' ];
                                    $ext = pathinfo($single_content['title'], PATHINFO_EXTENSION);
                                    if(empty($ext)){
                                        $ext = "pdf";
                                    }
                                @endphp
                                <a href="{{ $content_file_url }}" class="view-box d-flex justify-content-center w-100 h-100" target="_blank">
                                    <i class="{{ isset($icons[$single_content['file_type']]) ? $icons[$single_content['file_type']] : '' }}"></i>
                                </a>
                            </div>
                            <div class="col-md-9 chapter-details">
                              <!--   <a href="{{ $content_file_url }}" class="chapter-title"
                                   target="_blank">{{ $single_content['title'] }}</a> -->
                                     <a href="{{ $content_file_url }}" class="chapter-title"
                                   target="_blank">{{ $con_key.' '.$no++.'.'.$ext}}</a>

                                <div class="chapter-des">{{ $single_content['description'] }}</div>
                            </div>
                           
                            <div class="col-md-2 time text-secondary d-flex justify-content-end"
                                 style="font-size: 20px;" >
                                <a href="{{ route('lms_flashcard.index',['content_id' => $single_content['id'],$preload_lms ?? '' ])}}"
                                   target="_blank"
                                   class="btn btn-outline-warning btn-sm mx-1" data-toggle="tooltip" title=""
                                   data-original-title="Add Flash Card"><i
                                        class="mdi mdi-cards-playing-outline"></i></a>
                                <a href="{{ route('content_master.edit',['content_master' => $single_content['id'],'std_id'=>$single_content['standard_id'],$preload_lms ?? ''])}}"
                                   class="btn btn-outline-success btn-sm mx-1"><i
                                        class="mdi mdi-pencil-outline"></i></a>
                                <form action="{{ route('content_master.destroy', $single_content['id'] )}}"
                                      method="post">
                                    @csrf
                                    @method('DELETE')
                                    <button onclick="return confirmDelete();" type="submit"
                                            class="btn btn-outline-danger btn-sm mx-1" style="{{$readonly ?? ''}}">
                                        <i class="mdi mdi-delete-outline"></i></button>
                                </form>
                            </div>
                        </div>
                        @endforeach
                    </div>
                    @php $subColapse++; @endphp
                    @endforeach
                </div>
                @endif
                @php $collapse++; @endphp
            @endforeach
            @else
            <div class="card single-chp">
                No Records Found.
            </div>
            @endif
        </div>
    </div>
</div>


<!--Modal: Add ChapterModal-->
<div class="modal fade right modal-scrolling" id="ChapterModal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" style="display: none;" aria-hidden="true">
    <div class="modal-dialog modal-side modal-bottom-right modal-notify modal-info" role="document">
        <!--Content-->
        <div class="modal-content">
            <!--Header-->
            <div class="modal-header">
                <h5 class="modal-title" id="heading">Add Chapter</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">x</span>
                </button>
            </div>

            <!--Body-->
            <form action="{{ route('chapter_master.store') }}" method="post" id="chapter_form">
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
                                    <div class="addButtonCheckbox">
                                        <input type="hidden" name="grade" id="grade" value="{{$data['grade']}}">
                                    <input type="hidden" name="standard" id="standard" value="{{$data['standard']}}">
                                    <input type="hidden" name="subject" id="subject" value="{{$data['subject']}}">

                                    <div class="col-md-12 form-group">
                                        <label>Chapter Name</label>
                                        <input type="text" id='chapter_name' required name="chapter_name[]" class="form-control">
                                    </div>
                                    <div class="col-md-12 form-group">
                                        <label>Chapter Description</label>
                                        <textarea id="chapter_desc" name="chapter_desc[]" class="form-control"></textarea>
                                    </div>
                                    <div class="col-md-12 form-group">
                                        <label>Sort Order</label>
                                        <input type="number" id='sort_order' required name="sort_order[]" class="form-control">
                                    </div>

                                    <div class="col-md-12 form-group">
                                        <label>Availability</label>
                                        <br><input type="checkbox" id="availability" name="availability[]" value="1">
                                    </div>

                                        <div class="col-md-12 form-group">
                                            <label>Show</label>
                                            <br><input type="checkbox" id="show_hide" name="show_hide[]" value="1">
                                        </div>

                                        <!-- <div class="col-md-1 form-group">
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
                    <input type="submit" id="submit" name="submit" value="Save" class="btn btn-success">
                </div>
            </form>
        </div>
        <!--/.Content-->
    </div>
</div>
<!--Modal: Add ChapterModal-->


<script>

    function edit_data(url, chapter_id, standard_id, chapter_name, chapter_desc, availability, show_hide, sort_order) {
        $("#chapter_name").val(chapter_name);
        $("#chapter_desc").val(chapter_desc);
        if (availability == 1) {
            $('#availability').prop('checked', true);
        }
        if (show_hide == 1) {
            $('#show_hide').prop('checked', true);
        }
        $("#sort_order").val(sort_order);
        $('#submit').val('Update');
        $('#heading').html('Update Chapter');
        $('#chapter_form').attr('action', url);
        $('#soni').html('{{ method_field("PUT") }}');
        $('#ChapterModal').modal('show');
    }

    function add_data() {
        var url = "{{ route('chapter_master.store') }}";
        $("#chapter_name").val("");
        $("#chapter_desc").val("");
        $("#sort_order").val("");
        $('#submit').val('Add');
        $('#heading').html('Add Chapter');
        $('#chapter_form').attr('action', url);
        $('#soni').html('{{ method_field("POST") }}');
        $('#availability').prop('checked', true);
        $('#show_hide').prop('checked', true);
        $('#ChapterModal').modal('show');
    }

    function delete_chapter(chapter_id) {
        if (confirm('Are you sure?')) {
            var error = 1;
            var path = "{{ route('ajax_chapterDependencies') }}";
            $.ajax({
                url: path,
                data: "chapter_id=" + chapter_id,
                async: false,
                success: function (result) {

                    if (result > 0) {
                        alert("You cannot delete Chapter.Chapter is having dependencies in Other Module");
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

    function tarCollapse(target) {
        var target_id = $(target).data('collapse_id');
        console.log(target_id);
        $('#chapter-content-tar-list-' + target_id).toggleClass('show');
    }


</script>
@include('includes.lmsfooterJs')
@include('includes.footer')
