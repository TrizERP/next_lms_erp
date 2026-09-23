@extends('lmslayout')
@section('container')
<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-12">
                <h4 class="page-title">PAL Chapter Diagnostic Assessment</h4>
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('pal.index') }}">PAL Subjects</a></li>
                    <li class="breadcrumb-item active">Take Chapter Diagnostic</li>
                </ol>
            </div>
        </div>

        @if (session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5>Choose a Chapter</h5>
                        <p class="text-muted mb-0">
                            The chapter diagnostic is 15 multiple-choice questions from one chapter
                            &mdash; 5 Easy, 5 Medium and 5 Hard &mdash; and sets your starting level for the Concept Diagnostic.
                        </p>
                    </div>
                    <div class="card-body">
                        @if (empty($subjects))
                            <div class="text-center py-5">
                                <i class="mdi mdi-book-off text-muted" style="font-size: 48px;"></i>
                                <h5 class="mt-3 text-muted">No Chapters Available</h5>
                                <p class="text-muted">There are no chapters in your curriculum yet.</p>
                            </div>
                        @else
                            @foreach ($subjects as $subject)
                                <h5 class="mb-3 pal-subject-heading">
                                    <i class="mdi mdi-bookshelf mr-1"></i> {{ $subject['subject_name'] }}
                                </h5>

                                <div class="row mb-4">
                                    @foreach ($subject['chapters'] as $chapter)
                                        <div class="col-md-6 col-lg-4 mb-3">
                                            <div class="card h-100 border {{ $chapter['servable'] ? '' : 'pal-chapter-muted' }}">
                                                <div class="card-body d-flex flex-column">
                                                    <h6 class="mb-2">{{ $chapter['name'] }}</h6>

                                                    <div class="mb-2 pal-pills">
                                                        <span class="badge badge-success" title="Easy questions available">E {{ $chapter['available']['easy'] }}</span>
                                                        <span class="badge badge-warning" title="Medium questions available">M {{ $chapter['available']['medium'] }}</span>
                                                        <span class="badge badge-danger" title="Hard questions available">H {{ $chapter['available']['hard'] }}</span>
                                                    </div>

                                                    @if ($chapter['has_diagnostic'])
                                                        <div class="mb-2">
                                                            @php
                                                                $levelColors = [
                                                                    'beginner' => 'secondary',
                                                                    'developing' => 'info',
                                                                    'proficient' => 'primary',
                                                                    'advanced' => 'success',
                                                                ];
                                                            @endphp
                                                            <span class="badge badge-{{ $levelColors[$chapter['level']] ?? 'secondary' }}">
                                                                {{ ucfirst($chapter['level'] ?? 'Unknown') }}
                                                            </span>
                                                            <strong class="ml-1">{{ number_format($chapter['percentage'], 1) }}%</strong>
                                                        </div>
                                                        <div class="mb-2">
                                                            <small class="text-muted">
                                                                Last attempted {{ \Carbon\Carbon::parse($chapter['last_attempted_at'])->format('M d, Y H:i') }}
                                                            </small>
                                                        </div>
                                                    @elseif ($chapter['servable'])
                                                        <div class="mb-2">
                                                            <span class="badge badge-info">
                                                                <i class="mdi mdi-play-circle mr-1"></i> Not Attempted
                                                            </span>
                                                        </div>
                                                    @endif

                                                    <div class="mt-auto">
                                                        @if ($chapter['servable'])
                                                            <a href="{{ route('pal.diagnostic.start', ['chapterId' => $chapter['chapter_id']]) }}"
                                                               class="btn {{ $chapter['has_diagnostic'] ? 'btn-outline-primary' : 'btn-primary' }} btn-block">
                                                                {{ $chapter['has_diagnostic'] ? 'Retake Chapter Diagnostic' : 'Take Chapter Diagnostic' }}
                                                            </a>

                                                            <a href="{{ route('pal.adaptive.concepts', ['chapterId' => $chapter['chapter_id']]) }}"
                                                               class="btn btn-success btn-block btn-sm mt-2">
                                                                <i class="mdi mdi-brain mr-1"></i> Concept Diagnostic
                                                            </a>

                                                            @if ($chapter['has_diagnostic'])
                                                                <a href="{{ route('pal.diagnostic.history', ['chapterId' => $chapter['chapter_id']]) }}"
                                                                   class="btn btn-outline-secondary btn-block btn-sm mt-2">
                                                                    View History
                                                                </a>
                                                            @endif
                                                        @else
                                                            {{-- Shown disabled rather than hidden: a missing button reads as
                                                                 a missing topic, a disabled one reads as "not ready yet". --}}
                                                            <button type="button" class="btn btn-secondary btn-block" disabled
                                                                    title="This chapter has no multiple-choice questions yet.">
                                                                No questions yet
                                                            </button>
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @endforeach
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .pal-subject-heading {
        border-bottom: 1px solid #e4e7ea;
        padding-bottom: .5rem;
    }
    .pal-chapter-muted {
        opacity: .55;
    }
    .pal-pills .badge {
        font-weight: 500;
    }
</style>
@endsection
