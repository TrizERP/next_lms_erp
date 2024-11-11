{{--@include('includes.lmsheadcss')
@include('includes.header')
@include('includes.sideNavigation')--}}
@extends('lmslayout')
@section('container')
    use DB;
    <!-- Content main Section -->
    <div class="content-main flex-fill">
        <h1 class="h4 mb-3">Browse by {{$data['category']}}</h1>

        <div class="container-fluid mb-5">
            <div class="coursr-chp-list" id="cource-chap-list">
                @php $i = 1; $collapse = 1; @endphp

                @if(isset($data['data']) && count($data['data']) > 0)
                    @foreach($data['data'] as $key => $chdata)

                        <div class="row card single-chp" >
                            <div class="col-md-12 mb-2 chp-details" data-toggle="collapse"
                                 href="#collapseExample{{ $collapse }}" role="button" aria-expanded="false"
                                 aria-controls="collapseExample">
                                <div class="count">@php echo $i++;@endphp</div>
                                <div>
                                    <a href="{{ route('o-net-data.show-occupation-detail-list',['id'=>$chdata->id,'occupation-detail'=>$chdata->title] )}}">{{$chdata->title}}
                                    </a>
                                </div>

                            </div>
                        </div>

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
