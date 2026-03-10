@extends('layout')
@section('container')
<link rel="stylesheet" href="/admin_dep/css/h5pCSS.css">
<div id="page-wrapper">
    <!-- Background Decorations - Blue Theme -->
    <div class="bg-decoration">
        <div class="decoration-circle circle-1"></div>
        <div class="decoration-circle circle-2"></div>
        <div class="decoration-circle circle-3"></div>
    </div>

    <div class="container-fluid mb-4">
        <!-- Animated Header without GIF -->
        <div class="modern-header">
            <div class="header-content">
                <div class="header-left">
                    <div class="header-icon">
                        <i class="fas fa-robot"></i>
                    </div>
                    <div class="header-text">
                        <h1> {{ App\Helpers\getTableFieldFromId('chapter_master','chapter_name',$data['chapter_id']) }}</h1>
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
                <div class="header-right">
                    <div class="header-stats">
                        <i class="fas fa-cubes"></i>
                        {{ count($data['contentLists']) }} Modules
                    </div>
                </div>
            </div>
        </div>

        <!-- Modules Section -->
        <div class="modules-grid">
            <div class="section-title">
                <h2>Choose Your Path</h2>
                <p>Interactive learning modules designed for you</p>
            </div>

            <div class="row g-3">
                @foreach($data['contentLists'] as $key=>$item)
                    @php 
                        $routeExists = Route::has($item['route']) ? $item['route'] : null;
                    @endphp 
                    <div class="col-lg-4 col-md-6">
                        <a @if($routeExists) href="{{ route($item['route'], ['chapter_id'=>$data['chapter_id'],'standard_id'=>$data['standard_id'],'subject_id'=>$data['subject_id']]) }}" @endif class="modern-card">
                            <div class="card-inner" data-color="{{ ($key % 6) + 1 }}">
                                <span class="card-number">{{ str_pad($key + 1, 2, '0', STR_PAD_LEFT) }}</span>
                                
                                <!-- Title and Icon in Flex -->
                                <div class="card-header-flex">
                                    <div class="card-icon-wrapper">
                                        <i class="{{$item['icon']}}"></i>
                                    </div>
                                    <h3 class="card-title">{{$item['title']}}</h3>
                                </div>
                                
                                <p class="card-description">Interactive learning with engaging activities and assessments</p>
                                
                                <div class="card-footer">
                                    <span class="explore-btn">
                                        Explore Module <i class="fas fa-arrow-right"></i>
                                    </span>
                                    <span class="card-category">
                                        <i class="far fa-star"></i> Interactive
                                    </span>
                                </div>
                            </div>
                        </a>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>

@include('includes.lmsfooterJs')
@include('includes.footer')

@endsection