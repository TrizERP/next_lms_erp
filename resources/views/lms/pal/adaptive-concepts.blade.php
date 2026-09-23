@extends('lmslayout')
@section('container')
@php
    // Same palette the result screen uses, so a level reads identically
    // wherever the learner meets it.
    $levelColors = [
        'beginner' => 'secondary',
        'developing' => 'info',
        'proficient' => 'primary',
        'advanced' => 'success',
    ];
    $bandColors = ['weak' => 'danger', 'moderate' => 'warning', 'strong' => 'success'];
    $diffColors = ['easy' => 'success', 'medium' => 'warning', 'hard' => 'danger'];
@endphp
<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-12">
                <h4 class="page-title">Concept Diagnostic</h4>
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('pal.index') }}">PAL Subjects</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('pal.diagnostic.subjects') }}">Take Chapter Diagnostic</a></li>
                    <li class="breadcrumb-item active">{{ $chapter_name }}</li>
                </ol>
            </div>
        </div>

        @if (session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        @if (! $has_diagnostic)
            {{-- Never a hard block: practice still works without a diagnostic,
                 it just opens at easy until there is something to go on. --}}
            <div class="alert alert-warning d-md-flex align-items-center justify-content-between">
                <div>
                    <i class="mdi mdi-information-outline mr-1"></i>
                    You have not taken the chapter diagnostic yet, so practice will start at
                    <strong>Easy</strong> and adjust as you answer.
                </div>
                <a href="{{ route('pal.diagnostic.start', ['chapterId' => $chapter_id]) }}" class="btn btn-primary btn-sm mt-2 mt-md-0">
                    Take Chapter Diagnostic
                </a>
            </div>
        @else
            <div class="card mb-3">
                <div class="card-body d-md-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted">Your chapter diagnostic level:</span>
                        <span class="badge badge-{{ $levelColors[$diagnostic_level] ?? 'secondary' }} ml-1">
                            {{ ucfirst($diagnostic_level ?? 'Unknown') }}
                        </span>
                    </div>
                    <div class="mt-2 mt-md-0">
                        <a href="{{ route('pal.diagnostic.result', ['attemptId' => $attempt_id]) }}" class="btn btn-outline-primary btn-sm">
                            View Chapter Diagnostic Result
                        </a>
                    </div>
                </div>
            </div>
        @endif

        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5>Choose a Concept to Practise</h5>
                        <p class="text-muted mb-0">
                            {{ $availability['concepts_servable'] }} of {{ $availability['concepts_total'] }}
                            concepts in this chapter have practice questions ready. The difficulty is chosen
                            from your chapter diagnostic and how the practice has been going.
                        </p>
                    </div>
                    <div class="card-body">
                        @if (empty($concepts))
                            <div class="text-center py-5">
                                <i class="mdi mdi-book-off text-muted" style="font-size: 48px;"></i>
                                <h5 class="mt-3 text-muted">No Concepts Yet</h5>
                                <p class="text-muted">This chapter does not have concepts mapped yet.</p>
                                <a href="{{ route('pal.diagnostic.subjects') }}" class="btn btn-outline-secondary">Back to Chapters</a>
                            </div>
                        @else
                            <div class="row">
                                @foreach ($concepts as $concept)
                                    <div class="col-md-6 col-lg-4 mb-3">
                                        <div class="card h-100 border {{ $concept['servable'] ? '' : 'pal-concept-muted' }}">
                                            <div class="card-body d-flex flex-column">
                                                <h6 class="mb-1">{{ $concept['name'] }}</h6>

                                                @unless ($concept['availability']['exact'] ?? false)
                                                    {{-- Most questions carry no concept_id, so they are reached
                                                         through the chapter. Say so rather than imply precision. --}}
                                                    <small class="text-muted d-block mb-2">
                                                        <i class="mdi mdi-source-branch mr-1"></i>practice drawn from the chapter
                                                    </small>
                                                @endunless

                                                <div class="mb-2 pal-pills">
                                                    <span class="badge badge-success">E {{ $concept['availability']['easy'] }}</span>
                                                    <span class="badge badge-warning">M {{ $concept['availability']['medium'] }}</span>
                                                    <span class="badge badge-danger">H {{ $concept['availability']['hard'] }}</span>
                                                </div>

                                                @if (! empty($concept['diagnostic']))
                                                    <div class="mb-2">
                                                        <small class="text-muted">Chapter Diagnostic:</small>
                                                        <span class="badge badge-{{ $bandColors[$concept['diagnostic']['band']] ?? 'secondary' }}">
                                                            {{ ucfirst($concept['diagnostic']['band']) }}
                                                            {{ number_format($concept['diagnostic']['percentage'], 0) }}%
                                                        </span>
                                                    </div>
                                                @endif

                                                @if ($concept['practice']['attempts'] > 0)
                                                    <div class="mb-2">
                                                        <small class="text-muted">
                                                            Practice: {{ $concept['practice']['correct'] }}/{{ $concept['practice']['attempts'] }}
                                                            ({{ number_format($concept['practice']['percentage'], 0) }}%)
                                                        </small>
                                                        <div class="progress mt-1" style="height: 6px;">
                                                            <div class="progress-bar bg-info" role="progressbar"
                                                                 style="width: {{ $concept['practice']['percentage'] }}%"
                                                                 aria-valuenow="{{ $concept['practice']['percentage'] }}"
                                                                 aria-valuemin="0" aria-valuemax="100"></div>
                                                        </div>
                                                    </div>
                                                @endif

                                                <div class="mt-auto">
                                                    @if ($concept['servable'] && $concept['next_difficulty'])
                                                        <div class="mb-2">
                                                            <small class="text-muted">Recommended:</small>
                                                            <span class="badge badge-{{ $diffColors[$concept['next_difficulty']] ?? 'secondary' }}">
                                                                {{ ucfirst($concept['next_difficulty']) }}
                                                            </span>
                                                        </div>
                                                        <a href="{{ route('pal.adaptive.questions', ['conceptId' => $concept['concept_id']]) }}"
                                                           class="btn btn-primary btn-block">
                                                            <i class="mdi mdi-brain mr-1"></i> Practise
                                                        </a>
                                                    @else
                                                        <button type="button" class="btn btn-secondary btn-block" disabled
                                                                title="No multiple-choice questions are available for this concept yet.">
                                                            No questions yet
                                                        </button>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                    <div class="card-footer">
                        <a href="{{ route('pal.diagnostic.subjects') }}" class="btn btn-outline-secondary">
                            <i class="mdi mdi-arrow-left mr-1"></i> Back to Chapters
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .pal-concept-muted { opacity: .55; }
    .pal-pills .badge { font-weight: 500; }
</style>
@endsection
