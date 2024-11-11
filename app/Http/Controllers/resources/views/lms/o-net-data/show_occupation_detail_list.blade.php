{{--@include('includes.lmsheadcss')
@include('includes.header')
@include('includes.sideNavigation')--}}
@extends('lmslayout')
@section('container')
    use DB;
    <!-- Content main Section -->
    <div class="content-main flex-fill">
        <h1 class="h4 mb-3">{{$data['data'][0]['title']}}</h1>
        <p>{{$data['data'][0]['description']}}</p>

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

                                    <h4>{{$chdata['resource_title']}}</h4>
                                    @if ($chdata['resource_title'] == 'Tasks')
                                        @foreach($chdata['summary'] as $summary)
                                            <li class="text-dark"><b>+ {{$summary}}</b></li>
                                        @endforeach
                                    @endif
                                    @if ($chdata['resource_title'] == 'Technology Skills')
                                        @foreach($chdata['summary'] as $summary)
                                            <li class="text-dark"> <b>+{{$summary['name']}}</b> - <span>@foreach($summary['example'] as $example)
                                                        {{$example['name']}};
                                                    @endforeach</span></li>
                                        @endforeach
                                    @endif
                                    @if ($chdata['resource_title'] == 'Knowledge' ||$chdata['resource_title'] == 'Skills'|| $chdata['resource_title'] == 'Abilities' || $chdata['resource_title'] == 'Work Activities'|| $chdata['resource_title']== 'Work Styles'|| $chdata['resource_title'] == 'Interests'|| $chdata['resource_title'] == 'Work Values')
                                        @foreach($chdata['summary'] as $summary)
                                            <li class="text-dark"> <b>+{{$summary['name']}}</b> - {{$summary['description'] }}</li>
                                        @endforeach
                                    @endif

                                    @if ($chdata['resource_title']== 'Job Zone')
                                        @if(count($chdata['summary']) > 0)
                                            <li class="text-dark"> <b>Title</b> -<span>{{$chdata['summary'][0]['title']}}</span></li>
                                            <li class="text-dark"> <b>Education</b> -<span>{{$chdata['summary'][0]['education']}}</span></li>
                                            <li class="text-dark"> <b>Related Experience</b> -<span>{{$chdata['summary'][0]['related_experience']}}</span></li>
                                            <li class="text-dark"> <b>Job Training</b> -<span>{{$chdata['summary'][0]['job_training']}}</span></li>
                                            <li class="text-dark"> <b>Job Zone Examples</b> -<span>{{$chdata['summary'][0]['job_zone_examples']}}</span></li>
                                            <li class="text-dark"> <b>SVP Range</b> -<span>{{$chdata['summary'][0]['svp_range']}}</span></li>
                                        @endif
                                    @endif

                                    @if ($chdata['resource_title']== 'Education')
                                        @foreach($chdata['summary'] as $summary)
                                            <li class="text-dark">  - <span><b>{{$summary['name']}} ({{$summary['score_value']}} / 100%) </b>- {{$summary['description']}}</span></li>
                                        @endforeach
                                    @endif
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
