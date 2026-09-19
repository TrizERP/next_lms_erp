@extends('lmslayout')
@section('container')
<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-12">
                <h4 class="page-title">Diagnostic Result</h4>
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('pal.index') }}">PAL Subjects</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('pal.diagnostic.subjects') }}">Take Diagnostic</a></li>
                    <li class="breadcrumb-item active">Result</li>
                </ol>
            </div>
        </div>

        <div class="row">
            <!-- Level Badge & Score -->
            <div class="col-md-4 mb-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body text-center p-4">
                        <div class="mb-3">
                            @php
                                $levelColors = [
                                    'advanced' => 'dark',
                                    'proficient' => 'primary',
                                    'developing' => 'warning',
                                    'beginner' => 'secondary'
                                ];
                                $levelIcons = [
                                    'advanced' => 'mdi-trophy',
                                    'proficient' => 'mdi-medal',
                                    'developing' => 'mdi-trending-up',
                                    'beginner' => 'mdi-seed'
                                ];
                                $levelColor = $levelColors[$result['level']] ?? 'secondary';
                                $levelIcon = $levelIcons[$result['level']] ?? 'mdi-help-circle';
                            @endphp
                            <span class="badge badge-{{ $levelColor }} p-3" style="font-size: 1.5rem;">
                                <i class="mdi {{ $levelIcon }} mr-2"></i> {{ ucfirst($result['level']) }}
                            </span>
                        </div>
                        <h2 class="mb-1">{{ number_format($result['percentage'], 1) }}%</h2>
                        <p class="text-muted mb-3">Score: {{ $result['correct'] }} / {{ $result['total_questions'] }}</p>
                        
                        <div class="row text-center">
                            <div class="col-4">
                                <div class="text-success">{{ $result['correct'] }}</div>
                                <small class="text-muted">Correct</small>
                            </div>
                            <div class="col-4">
                                <div class="text-danger">{{ $result['incorrect'] }}</div>
                                <small class="text-muted">Incorrect</small>
                            </div>
                            <div class="col-4">
                                <div class="text-warning">{{ $result['unanswered'] }}</div>
                                <small class="text-muted">Unanswered</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Difficulty Breakdown -->
            <div class="col-md-4 mb-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-light">
                        <h6 class="mb-0"><i class="mdi mdi-chart-bar mr-2"></i>Difficulty-wise Performance</h6>
                    </div>
                    <div class="card-body">
                        @foreach ($result['difficulty_breakdown'] as $difficulty => $stats)
                            <div class="mb-3">
                                <div class="d-flex justify-content-between mb-1">
                                    <span class="font-weight-medium">
                                        <span class="badge badge-{{ $difficulty === 'easy' ? 'success' : ($difficulty === 'medium' ? 'warning' : 'danger') }} badge-pill mr-2">
                                            {{ ucfirst($difficulty) }}
                                        </span>
                                        {{ $stats['label'] }}
                                    </span>
                                    <span>{{ $stats['served'] > 0 ? number_format($stats['percentage'], 1) : 'N/A' }}%</span>
                                </div>
                                <div class="progress" style="height: 6px;">
                                    <div class="progress-bar bg-{{ $difficulty === 'easy' ? 'success' : ($difficulty === 'medium' ? 'warning' : 'danger') }}" 
                                         role="progressbar" 
                                         style="width: {{ $stats['served'] > 0 ? $stats['percentage'] : 0 }}%"
                                         aria-valuenow="{{ $stats['served'] > 0 ? $stats['percentage'] : 0 }}" 
                                         aria-valuemin="0" aria-valuemax="100"></div>
                                </div>
                                <small class="text-muted">{{ $stats['correct'] }}/{{ $stats['served'] }} correct ({{ $stats['unanswered'] }} unanswered)</small>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <!-- Concept Breakdown Summary -->
            <div class="col-md-4 mb-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-light">
                        <h6 class="mb-0"><i class="mdi mdi-brain mr-2"></i>Concept Performance</h6>
                    </div>
                    <div class="card-body">
                        @php
                            $strengths = collect($result['strengths'] ?? []);
                            $weaknesses = collect($result['weaknesses'] ?? []);
                        @endphp
                        
                        @if ($strengths->isNotEmpty())
                            <div class="mb-3">
                                <h6 class="text-success"><i class="mdi mdi-thumb-up mr-1"></i> Strengths</h6>
                                @foreach ($strengths->take(5) as $strength)
                                    <div class="d-flex justify-content-between align-items-center py-1 border-bottom">
                                        <span class="small">{{ $strength['name'] }}</span>
                                        <span class="badge badge-success badge-pill">{{ number_format($strength['percentage'], 1) }}%</span>
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        @if ($weaknesses->isNotEmpty())
                            <div class="mb-3">
                                <h6 class="text-danger"><i class="mdi mdi-thumb-down mr-1"></i> Areas for Improvement</h6>
                                @foreach ($weaknesses->take(5) as $weakness)
                                    <div class="d-flex justify-content-between align-items-center py-1 border-bottom">
                                        <span class="small">{{ $weakness['name'] }}</span>
                                        <span class="badge badge-danger badge-pill">{{ number_format($weakness['percentage'], 1) }}%</span>
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        @if ($strengths->isEmpty() && $weaknesses->isEmpty())
                            <p class="text-muted text-center py-3">Complete more diagnostics to see concept-wise analysis.</p>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <!-- Detailed Concept Breakdown -->
        <div class="row">
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-light">
                        <h6 class="mb-0"><i class="mdi mdi-table-large mr-2"></i>Detailed Concept Performance</h6>
                    </div>
                    <div class="card-body">
                        @if (empty($result['concept_breakdown']))
                            <p class="text-muted text-center py-4">No concept-wise data available.</p>
                        @else
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="thead-light">
                                        <tr>
                                            <th>Concept / Chapter</th>
                                            <th class="text-center">Served</th>
                                            <th class="text-center">Correct</th>
                                            <th class="text-center">Incorrect</th>
                                            <th class="text-center">Unanswered</th>
                                            <th class="text-center">Percentage</th>
                                            <th class="text-center">Band</th>
                                            <th class="text-center">Exact Match</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($result['concept_breakdown'] as $concept)
                                            <tr>
                                                <td>
                                                    <strong>{{ $concept['name'] }}</strong>
                                                    @if (!$concept['exact'])
                                                        <span class="badge badge-secondary badge-pill ml-2">Via Chapter</span>
                                                    @endif
                                                </td>
                                                <td class="text-center">{{ $concept['served'] }}</td>
                                                <td class="text-center text-success">{{ $concept['correct'] }}</td>
                                                <td class="text-center text-danger">{{ $concept['incorrect'] }}</td>
                                                <td class="text-center text-warning">{{ $concept['unanswered'] }}</td>
                                                <td class="text-center font-weight-bold">{{ number_format($concept['percentage'], 1) }}%</td>
                                                <td class="text-center">
                                                    <span class="badge badge-{{ $concept['band'] === 'strong' ? 'success' : ($concept['band'] === 'moderate' ? 'warning' : 'danger') }} badge-pill">
                                                        {{ ucfirst($concept['band']) }}
                                                    </span>
                                                </td>
                                                <td class="text-center">
                                                    @if ($concept['exact'])
                                                        <i class="mdi mdi-check-circle text-success"></i>
                                                    @else
                                                        <i class="mdi mdi-minus-circle text-secondary"></i>
                                                    @endif
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

        <!-- Action Buttons -->
        <div class="row mt-4">
            <div class="col-12 text-center">
                <a href="{{ route('pal.diagnostic.subjects') }}" class="btn btn-outline-secondary mr-2">
                    <i class="mdi mdi-arrow-left mr-1"></i> Back to Subjects
                </a>
                <a href="{{ route('pal.diagnostic.history', ['chapterId' => $attempt->chapter_id]) }}" class="btn btn-outline-primary mr-2">
                    <i class="mdi mdi-history mr-1"></i> View History
                </a>
                <a href="{{ route('pal.adaptive.concepts', ['chapterId' => $attempt->chapter_id]) }}" class="btn btn-success">
                    <i class="mdi mdi-brain mr-1"></i> Start Adaptive Learning
                </a>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<style>
.card {
    border-radius: 10px;
}
.badge-pill {
    border-radius: 50rem;
}
.progress {
    border-radius: 3px;
}
.table th {
    font-weight: 600;
    font-size: 0.85rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
</style>
@endpush