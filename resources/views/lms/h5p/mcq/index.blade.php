@extends('layout')
@section('container')
<link rel="stylesheet" href="/admin_dep/css/h5pCSS.css">
<div id="page-wrapper">
    <div class="container-fluid mb-5">
        <div class="modern-header">
            <div class="header-content">
                <div class="header-left">
                    <div class="header-icon">
                        <i class="fas fa-graduation-cap"></i>
                    </div>
                    <div class="header-text">
                        <h1>{{ App\Helpers\getTableFieldFromId('chapter_master','chapter_name',$data['chapter_id']) }}</h1>
                        <div class="header-badges">
                            <span class="header-badge">
                                <i class="fas fa-book-open"></i>
                                {{ App\Helpers\getTableFieldFromId('sub_std_map','display_name',$data['subject_id'],'subject_id') }}
                            </span>
                            <span class="header-badge">
                                <i class="fas fa-graduation-cap"></i>
                                {{ App\Helpers\getTableFieldFromId('standard','name',$data['standard_id']) }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        @if(!isset($data['selectedLevel']) && !empty($data['mcq_levels']))
        <!-- Modules Section -->
        <div class="modules-grid">
            <div class="section-title">
                <h2>Choose Your Path</h2>
                <p>Select your preferred MCQ level and start</p>
            </div>

            <div class="row g-3">
                @foreach($data['mcq_levels'] as $key=>$item)

                <div class="col-lg-4 col-md-6">
                    <form action="{{route('h5p_mcq.index')}}">
                        <input type="hidden" name="chapter_id" value="{{$data['chapter_id']}}">
                        <input type="hidden" name="standard_id" value="{{$data['standard_id']}}">
                        <input type="hidden" name="subject_id" value="{{$data['subject_id']}}">
                        <a class="modern-card">
                            <div class="card-inner" data-color="{{ ($key % 6) + 1 }}">
                                <span class="card-number">{{ str_pad($key + 1, 2, '0', STR_PAD_LEFT) }}</span>

                                <!-- Title and Icon in Flex -->
                                <div class="card-header-flex">
                                    <div class="card-icon-wrapper">
                                        <span class="first-letter" style="color: #fff; font-weight: bold;">{{ strtoupper(substr($item->name, 0, 1)) }}</span>
                                    </div>
                                    <h3 class="card-title">{{$item->name}}</h3>
                                </div>

                                <div class="card-footer">
                                    <input type="hidden" name="selectedLevel" value="{{$item->id}}">
                                    <button type="submit" class="explore-btn">
                                        Start <i class="fas fa-arrow-right"></i>
                                    </button>
                                </div>
                            </div>
                        </a>
                    </form>

                </div>
                @endforeach
            </div>
        </div>
        @endif

        @if(isset($data['selectedLevel']) && !empty($data['question_arr']) && !empty($data['answer_arr']))
        @include('lms.h5p.mcq.create')
        @endif
    </div>
</div>
@include('includes.lmsfooterJs')
@include('includes.footer')
@endsection