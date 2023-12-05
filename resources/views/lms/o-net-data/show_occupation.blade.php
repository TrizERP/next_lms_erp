{{--@include('includes.lmsheadcss')
@include('includes.header')
@include('includes.sideNavigation')--}}
@extends('lmslayout')
@section('container')
    use DB;
    <!-- Content main Section -->
    <div class="content-main flex-fill">
        <h1 class="h4 mb-3">{{$data['data'][0]['category']}}</h1>

        <div class="container-fluid mb-5">
            <div class="coursr-chp-list" id="cource-chap-list">
                @php $i = 1; $collapse = 1; @endphp

                @if(isset($data['data']) && count($data['data']) > 0)
                    @foreach($data['data'][0]['sub_categories'] as $key => $chdata)
                        @if($chdata->parent_id == 0)
                            @if ($chdata->is_childs != 0)
                                <div class="row card single-chp">
                                    <div class="col-md-12 mb-2 chp-details" data-toggle="collapse"
                                         href="#collapseExample{{ $chdata->id }}" role="button" aria-expanded="false"
                                         aria-controls="collapseExample">
                                        <div class="count">@php echo $i++;@endphp</div>
                                        <div>
                                            {{--<a href="{{ route('o-net-data.show-occupation-detail',['id'=>$chdata->id,'category-name'=>$data['category']] )}}">{{$chdata->sub_category_name}}
                                            </a>--}}
                                            <span>{{$chdata->sub_category_name}}
                                        </span>

                                            <div class="help-arraw">
                                                <i class="mdi mdi-chevron-down"></i>
                                            </div>
                                            <span style="display: block">{{$chdata->description}}</span>
                                        </div>

                                    </div>
                                </div>
                            @else
                                <div class="row card single-chp">
                                    <div class="col-md-12 mb-2 chp-details" data-toggle="collapse"
                                         href="{{ route('o-net-data-table.show-list',['id' => $chdata->id])}}">


                                        <div>
                                            <a href="{{ route('o-net-data-table.show-list',['id' => $chdata->id])}}" class="stretched-link text-primary" style="position: relative;"> <i class="mdi mdi-file"></i>{{$chdata->sub_category_name}}
                                            </a>
                                            <span style="display: block">{{$chdata->description}}</span>
                                        </div>
                                    </div>
                                </div>
                            @endif
                            <div class="chapter-content-list mb-4 collapse" id="collapseExample{{ $chdata->id }}"
                                 style="">
                                @foreach($data['data'][0]['sub_categories'] as $key => $sub_category)
                                    @if($sub_category->parent_id ==  $chdata->id && $sub_category->sub_parent_id == 0)
                                        @if ($sub_category->is_childs != 0 || $sub_category->is_sub_childs != 0)
                                            <div class="row card single-chp">
                                                <div class="col-md-12 mb-2 chp-details" data-toggle="collapse"
                                                     href="#collapseExampleSubCategory{{ $sub_category->id }}"
                                                     role="button"
                                                     aria-expanded="false"
                                                     aria-controls="collapseExample">
                                                    <div>
                                                    <span>{{$sub_category->sub_category_name}}
                                        </span>
                                                        <div class="help-arraw">
                                                            <i class="mdi mdi-chevron-up"></i>
                                                        </div>
                                                        <span
                                                            style="display: block">{{$sub_category->description}}</span>
                                                    </div>

                                                </div>
                                            </div>
                                        @else
                                            <div class="row card single-chp">
                                                <div class="col-md-12 mb-2 chp-details" data-toggle="collapse"
                                                     href="{{ route('o-net-data-table.show-list',['id' => $sub_category->id])}}">

                                                    <div>
                                                        <a href="{{ route('o-net-data-table.show-list',['id' => $sub_category->id])}}" class="stretched-link text-primary" style="position: relative;"> <i class="mdi mdi-file"></i> {{$sub_category->sub_category_name}}
                                                        </a>
                                                        <span
                                                            style="display: block">{{$sub_category->description}}</span>

                                                    </div>

                                                </div>
                                            </div>
                                        @endif
                                        <div class="chapter-content-list mb-4 collapse"
                                             id="collapseExampleSubCategory{{ $sub_category->id }}"
                                             style="">
                                            @foreach($data['data'][0]['sub_categories'] as $key => $sub_parent_category)
                                                @if($sub_parent_category->sub_parent_id ==  $sub_category->id && $sub_parent_category->child_id == 0)
                                                    @if ($sub_parent_category->is_parent_sub_child != 0)
                                                        <div class="row card single-chp">
                                                            <div class="col-md-12 mb-2 chp-details"
                                                                 data-toggle="collapse"
                                                                 href="#collapseExampleSubChildCategory{{ $sub_parent_category->id }}"
                                                                 role="button"
                                                                 aria-expanded="false"
                                                                 aria-controls="collapseExample">
                                                                <div>
                                                    <span>{{$sub_parent_category->sub_category_name}}
                                        </span>
                                                                    <div class="help-arraw">
                                                                        <i class="mdi mdi-chevron-up"></i>
                                                                    </div>
                                                                    <span
                                                                        style="display: block">{{$sub_parent_category->description}}</span>
                                                                </div>

                                                            </div>
                                                        </div>
                                                        <div class="chapter-content-list mb-4 collapse"
                                                             id="collapseExampleSubChildCategory{{ $sub_parent_category->id }}"
                                                             style="">
                                                            @foreach($data['data'][0]['sub_categories'] as $key => $sub_child_category)
                                                                @if ($sub_child_category->child_id == $sub_parent_category->id)
                                                                    <div class="row card single-chp">
                                                                        <div class="col-md-12 mb-2 chp-details"
                                                                             data-toggle="collapse"
                                                                             href="{{ route('o-net-data-table.show-list',['id' => $sub_child_category->id])}}">
                                                                            <div>
                                                                                <a href="{{ route('o-net-data-table.show-list',['id' => $sub_child_category->id])}}" class="stretched-link text-primary" style="position: relative;">  <i class="mdi mdi-file"></i>{{$sub_child_category->sub_category_name}}
                                                                                </a>
                                                                                <span
                                                                                    style="display: block">{{$sub_child_category->description}}</span>
                                                                            </div>

                                                                        </div>
                                                                    </div>
                                                                @endif
                                                            @endforeach
                                                        </div>
                                                    @else
                                                        <div class="row card single-chp">
                                                            <div class="col-md-12 mb-2 chp-details"
                                                                 data-toggle="collapse"
                                                                 href="{{ route('o-net-data-table.show-list',['id' => $sub_parent_category->id])}}">
                                                                <div>
                                                                    <a href="{{ route('o-net-data-table.show-list',['id' => $sub_parent_category->id])}}" class="stretched-link text-primary" style="position: relative;">  <i class="mdi mdi-file"></i>{{$sub_parent_category->sub_category_name}}
                                                                    </a>
                                                                    <span
                                                                        style="display: block">{{$sub_parent_category->description}}</span>
                                                                </div>

                                                            </div>
                                                        </div>
                                                    @endif
                                                @endif

                                            @endforeach
                                        </div>
                                    @endif
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
@endsection
