@extends('lmslayout')
@section('container')
<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-12">
                <h4 class="page-title">PAL Diagnostic Assessment</h4>
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('pal.index') }}">PAL Subjects</a></li>
                    <li class="breadcrumb-item active">Take Diagnostic</li>
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
                        <h5>Select a Subject for Diagnostic Assessment</h5>
                        <p class="text-muted mb-0">The diagnostic consists of 15 multiple-choice questions (5 Easy, 5 Medium, 5 Hard) to determine your current level.</p>
                    </div>
                    <div class="card-body">
                        @if ($subjects->isEmpty())
                            <div class="text-center py-5">
                                <i class="mdi mdi-book-off text-muted" style="font-size: 48px;"></i>
                                <h5 class="mt-3 text-muted">No Subjects Available</h5>
                                <p class="text-muted">There are no subjects with multiple-choice questions in your curriculum.</p>
                            </div>
                        @else
                            <div class="row">
                                @foreach ($subjects as $subject)
                                    <div class="col-md-6 col-lg-4 mb-3">
                                        <div class="card diagnostic-subject-card h-100 {{ $subject['has_diagnostic'] ? 'border-success' : '' }}">
                                            <div class="card-body d-flex flex-column">
                                                <h6 class="card-title">{{ $subject['name'] }}</h6>
                                                
                                                @if ($subject['has_diagnostic'])
                                                    <div class="mb-2">
                                                        <span class="badge badge-success">
                                                            <i class="mdi mdi-check-circle mr-1"></i> Diagnostic Completed
                                                        </span>
                                                    </div>
                                                    <div class="mb-2">
                                                        <small class="text-muted">Level: </small>
                                                        <span class="badge badge-{{ $subject['level'] === 'advanced' ? 'dark' : ($subject['level'] === 'proficient' ? 'primary' : ($subject['level'] === 'developing' ? 'warning' : 'secondary')) }}">
                                                            {{ ucfirst($subject['level']) }}
                                                        </span>
                                                    </div>
                                                    <div class="mb-2">
                                                        <small class="text-muted">Score: </small>
                                                        <strong>{{ number_format($subject['percentage'], 1) }}%</strong>
                                                    </div>
                                                    <div class="mb-2">
                                                        <small class="text-muted">Last attempted: </small>
                                                        <small>{{ \Carbon\Carbon::parse($subject['last_attempted_at'])->format('M d, Y H:i') }}</small>
                                                    </div>
                                                @else
                                                    <div class="mb-2">
                                                        <span class="badge badge-info">
                                                            <i class="mdi mdi-play-circle mr-1"></i> Not Attempted
                                                        </span>
                                                    </div>
                                                @endif

                                                <div class="mt-auto">
                                                    <a href="{{ route('pal.diagnostic.start', ['subjectId' => $subject['subject_id']]) }}" 
                                                       class="btn {{ $subject['has_diagnostic'] ? 'btn-outline-primary' : 'btn-primary' }} btn-block">
                                                        {{ $subject['has_diagnostic'] ? 'Retake Diagnostic' : 'Take Diagnostic' }}
                                                    </a>
                                                    
                                                    @if ($subject['has_diagnostic'])
                                                        <a href="{{ route('pal.diagnostic.history', ['subjectId' => $subject['subject_id']]) }}" 
                                                           class="btn btn-outline-secondary btn-block btn-sm mt-2">
                                                            View History
                                                        </a>
                                                        <a href="{{ route('pal.adaptive.concepts', ['subjectId' => $subject['subject_id']]) }}" 
                                                           class="btn btn-success btn-block btn-sm mt-2">
                                                            <i class="mdi mdi-brain mr-1"></i> Adaptive Learning
                                                        </a>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<style>
.diagnostic-subject-card {
    transition: all 0.3s ease;
    border: 1px solid #e3e6f0;
}
.diagnostic-subject-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}
.diagnostic-subject-card.border-success {
    border-left: 4px solid #28a745 !important;
}
</style>
@endpush