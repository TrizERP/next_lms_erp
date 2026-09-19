@php
    $diffColors = ['easy' => 'success', 'medium' => 'warning', 'hard' => 'danger'];
    $p = $progress ?? [];
@endphp
<div id="pal_progress_inner">
    {{-- Overall --}}
    <div class="mb-3">
        <div class="d-flex justify-content-between mb-1">
            <span class="text-muted small">Overall Accuracy</span>
            <strong>{{ number_format($p['accuracy'] ?? 0, 1) }}%</strong>
        </div>
        <div class="progress" style="height: 8px;">
            <div class="progress-bar bg-info" role="progressbar"
                 style="width: {{ $p['accuracy'] ?? 0 }}%"
                 aria-valuenow="{{ $p['accuracy'] ?? 0 }}" aria-valuemin="0" aria-valuemax="100"></div>
        </div>
        <small class="text-muted">
            {{ $p['correct'] ?? 0 }} / {{ $p['attempted'] ?? 0 }} questions
        </small>
    </div>

    {{-- Streak --}}
    @if (($p['streak'] ?? 0) > 0)
        <div class="mb-3 p-2 bg-light rounded">
            <div class="d-flex align-items-center">
                <i class="mdi mdi-fire text-danger mr-2" style="font-size: 20px;"></i>
                <div>
                    <div class="small text-muted">Current Streak</div>
                    <strong>{{ $p['streak'] }}</strong> correct in a row
                </div>
            </div>
        </div>
    @endif

    {{-- Per-band --}}
    <div class="mb-3">
        <small class="text-muted d-block mb-2">By Difficulty</small>
        @foreach (['easy', 'medium', 'hard'] as $band)
            @php $b = $p['by_difficulty'][$band] ?? ['attempted' => 0, 'correct' => 0, 'accuracy' => 0.0]; @endphp
            <div class="mb-2">
                <div class="d-flex justify-content-between mb-1">
                    <span class="badge badge-{{ $diffColors[$band] ?? 'secondary' }}">{{ ucfirst($band) }}</span>
                    <span class="small">{{ $b['correct'] }}/{{ $b['attempted'] }} ({{ number_format($b['accuracy'], 1) }}%)</span>
                </div>
                <div class="progress" style="height: 4px;">
                    <div class="progress-bar bg-{{ $diffColors[$band] ?? 'secondary' }}" role="progressbar"
                         style="width: {{ $b['accuracy'] }}%"
                         aria-valuenow="{{ $b['accuracy'] }}" aria-valuemin="0" aria-valuemax="100"></div>
                </div>
            </div>
        @endforeach
    </div>

    {{-- Mastery --}}
    @if (! empty($p['mastered']))
        <div class="alert alert-success mb-0 p-2">
            <i class="mdi mdi-check-circle mr-1"></i>
            <strong>Mastered!</strong> You've reached 80% accuracy on {{ $p['by_difficulty']['hard']['attempted'] ?? 0 }}+ hard questions.
        </div>
    @elseif ($p['current_difficulty'] === 'hard')
        <div class="alert alert-warning mb-0 p-2">
            <i class="mdi mdi-target-account mr-1"></i>
            Working towards mastery: need 80% on 5+ hard questions.
        </div>
    @endif
</div>