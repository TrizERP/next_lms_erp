@extends('lmslayout')
@section('container')
@php
    $levelColors = [
        'beginner' => 'secondary',
        'developing' => 'info',
        'proficient' => 'primary',
        'advanced' => 'success',
    ];
@endphp
<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-12">
                <h4 class="page-title">Chapter Diagnostic History</h4>
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

        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex align-items-center justify-content-between">
                        <h5 class="mb-0">{{ $chapter_name }}</h5>
                        <a href="{{ route('pal.diagnostic.subjects') }}" class="btn btn-outline-secondary btn-sm">
                            <i class="mdi mdi-arrow-left mr-1"></i> Back to Chapters
                        </a>
                    </div>
                    <div class="card-body">
                        @if (empty($attempts))
                            <div class="text-center py-5">
                                <i class="mdi mdi-history text-muted" style="font-size: 48px;"></i>
                                <h5 class="mt-3 text-muted">No Chapter Diagnostic History</h5>
                                <p class="text-muted">You have not taken any chapter diagnostics for this chapter yet.</p>
                                <a href="{{ route('pal.diagnostic.start', ['chapterId' => $chapter_id]) }}" class="btn btn-primary mt-2">
                                    <i class="mdi mdi-play-circle mr-1"></i> Take Chapter Diagnostic
                                </a>
                            </div>
                        @else
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Score</th>
                                            <th>Level</th>
                                            <th>Correct / Total</th>
                                            <th>Details</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($attempts as $attempt)
                                            <tr>
                                                <td>
                                                    {{ \Carbon\Carbon::parse($attempt->submitted_at)->format('M d, Y H:i') }}
                                                </td>
                                                <td>
                                                    <strong>{{ number_format($attempt->percentage, 1) }}%</strong>
                                                </td>
                                                <td>
                                                    @php($lc = $levelColors[$attempt->level ?? 'beginner'] ?? 'secondary')
                                                    <span class="badge badge-{{ $lc }}">
                                                        {{ ucfirst($attempt->level ?? 'Unknown') }}
                                                    </span>
                                                </td>
                                                <td>
                                                    {{ $attempt->correct }} / {{ $attempt->total_questions }}
                                                </td>
                                                <td>
                                                    <a href="{{ route('pal.diagnostic.result', ['attemptId' => $attempt->id]) }}" class="btn btn-outline-primary btn-sm">
                                                        View Details
                                                    </a>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection