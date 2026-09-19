@extends('lmslayout')
@section('container')
<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-12">
                <h4 class="page-title">PAL Diagnostic Assessment</h4>
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('pal.index') }}">PAL Subjects</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('pal.diagnostic.subjects') }}">Take Diagnostic</a></li>
                    <li class="breadcrumb-item active">Assessment</li>
                </ol>
            </div>
        </div>

        <form id="diagnostic_form" method="post" action="{{ route('pal.diagnostic.submit', ['attemptId' => $attempt_id]) }}">
            @csrf

            <input type="hidden" name="attempt_id" value="{{ $attempt_id }}">
            <input type="hidden" name="chapter_id" value="{{ $chapter_id }}">

            <div class="row">
                <div class="col-md-8">
                    <div class="card border-0 rounded mb-4">
                        <div class="card-body">
                            <div class="d-md-flex align-items-center justify-content-between mb-3">
                                <div class="quiz-labels">
                                    <div class="h5 mb-1">Question Navigation</div>
                                    <ul class="quiz-navigation nav nav-pills flex-wrap" role="tablist">
                                        @php $i = 1; @endphp
                                        @foreach ($questions as $question)
                                            <li class="nav-item mb-1">
                                                <a class="nav-link question-nav-link {{ $i === 1 ? 'active' : '' }}" 
                                                   href="#question-{{ $question['question_id'] }}-tab" 
                                                   data-toggle="pill" 
                                                   role="tab"
                                                   data-question-id="{{ $question['question_id'] }}"
                                                   data-index="{{ $i }}">
                                                    {{ $i++ }}
                                                </a>
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                                <div class="quiz-time text-right">
                                    <div class="color-primary mb-2">Total Questions: {{ count($questions) }}</div>
                                    <div class="color-primary mb-2">Time Allowed: {{ $time_allowed }} mins</div>
                                    <div class="text-secondary">Time Left: <span id="showtimer" class="font-weight-bold"></span></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <input type="hidden" name="time_allowed" id="time_allowed" value="{{ $time_allowed }}">
                    <input type="hidden" name="total_questions" id="total_questions" value="{{ count($questions) }}">
                    <input type="hidden" name="session_quiz_start" id="session_quiz_start" value="{{ now()->format('Y-m-d H:i:s') }}">

                    <div class="tab-content" id="diagnostic-tabContent">
                        @php $i = 1; @endphp
                        @foreach ($questions as $question)
                            <div class="tab-pane fade {{ $i === 1 ? 'show active' : '' }}" id="question-{{ $question['question_id'] }}-tab" role="tabpanel">
                                <div class="card border-0 rounded mb-3 question-block" data-question-id="{{ $question['question_id'] }}">
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-12">
                                                <div class="d-flex justify-content-between align-items-center mb-3">
                                                    <span class="badge badge-{{ $question['difficulty'] === 'easy' ? 'success' : ($question['difficulty'] === 'medium' ? 'warning' : 'danger') }} badge-pill px-3 py-2">
                                                        {{ ucfirst($question['difficulty']) }}
                                                    </span>
                                                    <small class="text-muted">Question {{ $i }} of {{ count($questions) }}</small>
                                                </div>
                                                <div class="quiz-title mb-3">{!! $question['title'] !!}</div>
                                                
                                                <div class="quiz-options">
                                                    @if (isset($question['options']) && count($question['options']) > 0)
                                                        @foreach ($question['options'] as $option)
                                                            <div class="custom-control custom-radio mb-2">
                                                                <input type="radio" 
                                                                       class="custom-control-input" 
                                                                       id="answer_{{ $question['question_id'] }}_{{ $option['id'] }}" 
                                                                       name="answers[{{ $question['question_id'] }}]" 
                                                                       value="{{ $option['id'] }}">
                                                                <label class="custom-control-label" for="answer_{{ $question['question_id'] }}_{{ $option['id'] }}">
                                                                    {!! $option['answer'] !!}
                                                                </label>
                                                            </div>
                                                        @endforeach
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @php $i++; @endphp
                        @endforeach
                    </div>

                    <div class="text-center mt-4">
                        <button type="button" class="btn btn-secondary mr-2" id="prev_question">Previous</button>
                        <button type="button" class="btn btn-primary mr-2" id="next_question">Next</button>
                        <button type="submit" class="btn btn-success" id="submit_diagnostic">
                            <i class="mdi mdi-check-circle mr-1"></i> Submit Diagnostic
                        </button>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card border-0 rounded sticky-top" style="top: 20px;">
                        <div class="card-header">
                            <h6 class="mb-0">Progress</h6>
                        </div>
                        <div class="card-body">
                            <div class="progress mb-3" style="height: 10px;">
                                <div class="progress-bar progress-bar-striped progress-bar-animated bg-primary" 
                                     id="progress_bar" role="progressbar" style="width: 0%" 
                                     aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                            </div>
                            <div class="text-center mb-3">
                                <span id="answered_count">0</span> of {{ count($questions) }} answered
                            </div>
                            
                            <h6 class="mb-2">Question Status</h6>
                            <div class="quiz-navigation nav nav-pills flex-column" role="tablist">
                                @php $j = 1; @endphp
                                @foreach ($questions as $question)
                                    <a class="nav-link question-status-link p-2 mb-1 text-left {{ $j === 1 ? 'active' : '' }}" 
                                       href="#question-{{ $question['question_id'] }}-tab" 
                                       data-toggle="pill" 
                                       role="tab"
                                       data-question-id="{{ $question['question_id'] }}"
                                       data-index="{{ $j }}">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span>Q{{ $j++ }}</span>
                                            <span class="badge badge-light question-status-badge" id="status_{{ $question['question_id'] }}">Not Answered</span>
                                        </div>
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
$(document).ready(function() {
    // Timer
    var min_to_add = parseInt($("#time_allowed").val());
    var dt = new Date();
    dt.setMinutes(dt.getMinutes() + min_to_add);
    var countDownDate = dt.getTime();

    var x = setInterval(function() {
        var now = new Date().getTime();
        var distance = countDownDate - now;

        var minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
        var seconds = Math.floor((distance % (1000 * 60)) / 1000);

        document.getElementById("showtimer").innerHTML = minutes + "m " + seconds + "s ";

        if (distance < 0) {
            clearInterval(x);
            document.getElementById("showtimer").innerHTML = "EXPIRED";
            alert("Your diagnostic time has expired");
            $("#diagnostic_form").submit();
        }
    }, 1000);

    // Question navigation
    var questions = $('.question-block');
    var totalQuestions = questions.length;
    var currentIndex = 0;

    function updateProgress() {
        var answered = 0;
        questions.each(function() {
            var qId = $(this).data('question-id');
            var answeredOption = $('input[name="answers[' + qId + ']"]:checked').val();
            if (answeredOption) {
                answered++;
                $('#status_' + qId).removeClass('badge-light').addClass('badge-success').text('Answered');
            } else {
                $('#status_' + qId).removeClass('badge-success').addClass('badge-light').text('Not Answered');
            }
        });
        var percent = totalQuestions > 0 ? Math.round((answered / totalQuestions) * 100) : 0;
        $('#progress_bar').css('width', percent + '%').attr('aria-valuenow', percent);
        $('#answered_count').text(answered);
    }

    function showQuestion(index) {
        if (index < 0 || index >= totalQuestions) return;
        currentIndex = index;
        var qId = $(questions[index]).data('question-id');
        
        $('.tab-pane').removeClass('show active');
        $('#question-' + qId + '-tab').addClass('show active');
        
        $('.question-nav-link').removeClass('active');
        $('.question-nav-link[data-question-id="' + qId + '"]').addClass('active');
        
        $('.question-status-link').removeClass('active');
        $('.question-status-link[data-question-id="' + qId + '"]').addClass('active');
        
        $('#prev_question').prop('disabled', index === 0);
        $('#next_question').prop('disabled', index === totalQuestions - 1);
        
        updateProgress();
    }

    $('#prev_question').click(function() {
        showQuestion(currentIndex - 1);
    });

    $('#next_question').click(function() {
        showQuestion(currentIndex + 1);
    });

    $('.question-nav-link, .question-status-link').click(function(e) {
        e.preventDefault();
        var qId = $(this).data('question-id');
        var index = $(this).data('index') - 1;
        showQuestion(index);
    });

    // Track answer changes
    $('input[type="radio"]').change(function() {
        updateProgress();
    });

    // Initial progress
    updateProgress();

    // Submit confirmation
    $('#submit_diagnostic').click(function(e) {
        var answered = parseInt($('#answered_count').text());
        var total = parseInt($('#total_questions').val());
        
        if (answered < total) {
            if (!confirm('You have ' + (total - answered) + ' unanswered question(s). Are you sure you want to submit?')) {
                e.preventDefault();
                return false;
            }
        }
    });

    // Keyboard navigation
    $(document).keydown(function(e) {
        if (e.key === 'ArrowLeft') {
            $('#prev_question').click();
        } else if (e.key === 'ArrowRight') {
            $('#next_question').click();
        }
    });
});
</script>
<style>
.question-block {
    border: 1px solid #e3e6f0;
    border-radius: 8px;
    transition: all 0.2s ease;
}
.question-block:hover {
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
}
.quiz-title {
    font-size: 1.1rem;
    line-height: 1.6;
}
.quiz-options .custom-control-label {
    cursor: pointer;
    padding-left: 8px;
}
.question-status-link.active {
    background-color: #eef3ff;
    border-left: 3px solid #4e73df;
}
.question-status-badge {
    min-width: 80px;
    text-align: center;
}
@media (max-width: 768px) {
    .sticky-top {
        position: static !important;
    }
}
</style>
@endpush