@extends('lmslayout')
@section('container')
@php
    $diffColors = ['easy' => 'success', 'medium' => 'warning', 'hard' => 'danger'];
@endphp
<meta name="csrf-token" content="{{ csrf_token() }}">
<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-12">
                <h4 class="page-title">Concept Diagnostic</h4>
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('pal.index') }}">PAL Subjects</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('pal.diagnostic.subjects') }}">Take Chapter Diagnostic</a></li>
                    @if ($chapter_id)
                        <li class="breadcrumb-item"><a href="{{ route('pal.adaptive.concepts', ['chapterId' => $chapter_id]) }}">Concept Diagnostic</a></li>
                    @endif
                    <li class="breadcrumb-item active">{{ $concept_name }}</li>
                </ol>
            </div>
        </div>

        @if (empty($items))
            <div class="card">
                <div class="card-body text-center py-5">
                    <i class="mdi mdi-check-decagram text-success" style="font-size: 48px;"></i>
                    <h5 class="mt-3">Nothing left to practise here</h5>
                    <p class="text-muted">
                        You have worked through the available questions for this concept.
                    </p>
                    @if ($chapter_id)
                        <a href="{{ route('pal.adaptive.concepts', ['chapterId' => $chapter_id]) }}" class="btn btn-primary">
                            Choose Another Concept
                        </a>
                    @endif
                </div>
            </div>
        @else
            <div class="row">
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header d-flex align-items-center justify-content-between">
                            <div>
                                <h5 class="mb-0">{{ $concept_name }}</h5>
                                @unless ($concept_exact)
                                    <small class="text-muted">questions drawn from the chapter</small>
                                @endunless
                            </div>
                            <span class="badge badge-{{ $diffColors[$difficulty] ?? 'secondary' }}">
                                {{ ucfirst($difficulty) }}
                            </span>
                        </div>

                        <div class="card-body">
                            @foreach ($items as $index => $item)
                                <div class="pal-question mb-4 pb-3 {{ $loop->last ? '' : 'border-bottom' }}"
                                     data-question-id="{{ $item['question_id'] }}">
                                    <div class="d-flex justify-content-between mb-2">
                                        <strong>Question {{ $index + 1 }}</strong>
                                        <span class="badge badge-light pal-result-badge" style="display:none;"></span>
                                    </div>

                                    <div class="mb-3">{!! $item['title'] !!}</div>

                                    <div class="pal-options">
                                        @foreach ($item['options'] as $option)
                                            <div class="custom-control custom-radio mb-2 pal-option"
                                                 data-option-id="{{ $option['id'] }}">
                                                <input type="radio"
                                                       id="opt_{{ $item['question_id'] }}_{{ $option['id'] }}"
                                                       name="q_{{ $item['question_id'] }}"
                                                       value="{{ $option['id'] }}"
                                                       class="custom-control-input pal-answer">
                                                <label class="custom-control-label"
                                                       for="opt_{{ $item['question_id'] }}_{{ $option['id'] }}">
                                                    {!! $option['answer'] !!}
                                                </label>
                                            </div>
                                        @endforeach
                                    </div>

                                    <div class="pal-feedback alert alert-light mt-2 mb-0" style="display:none;"></div>
                                </div>
                            @endforeach
                        </div>

                        <div class="card-footer">
                            <a href="{{ route('pal.adaptive.questions', ['conceptId' => $concept_id]) }}"
                               class="btn btn-primary">
                                <i class="mdi mdi-arrow-right mr-1"></i> Next {{ count($items) }} Questions
                            </a>
                            @if ($chapter_id)
                                <a href="{{ route('pal.adaptive.concepts', ['chapterId' => $chapter_id]) }}"
                                   class="btn btn-outline-secondary">
                                    Back to Concepts
                                </a>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-header"><h6 class="mb-0">Your Progress</h6></div>
                        <div class="card-body" id="pal_progress">
                            @include('lms.pal.partials.adaptive-progress', ['progress' => $progress])
                        </div>
                    </div>

                    @if (! empty(trim((string) $rationale)))
                        <div class="card">
                            <div class="card-header"><h6 class="mb-0">Why these questions</h6></div>
                            <div class="card-body">
                                <p class="text-muted mb-0">{{ $rationale }}</p>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        @endif
    </div>
</div>

<script>
$(function () {
    $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') } });

    var conceptId = {{ (int) $concept_id }};
    var answerUrl = "{{ route('pal.adaptive.answer') }}";
    var progressUrl = "{{ route('pal.adaptive.progress', ['conceptId' => $concept_id]) }}";

    $('.pal-answer').on('change', function () {
        var $block = $(this).closest('.pal-question');

        // One answer per question: the server records the first response and
        // the point of practice is the feedback, not a second guess.
        if ($block.data('answered')) {
            return;
        }
        $block.data('answered', true);
        $block.find('.pal-answer').prop('disabled', true);

        $.post(answerUrl, {
            concept_id: conceptId,
            question_id: $block.data('question-id'),
            answer_master_id: $(this).val()
        }).done(function (res) {
            if (!res || res.status !== 1) {
                $block.data('answered', false);
                $block.find('.pal-answer').prop('disabled', false);
                alert((res && res.message) ? res.message : 'Could not record that answer.');
                return;
            }

            var $badge = $block.find('.pal-result-badge');
            $badge.removeClass('badge-light')
                  .addClass(res.is_correct ? 'badge-success' : 'badge-danger')
                  .text(res.is_correct ? 'Correct' : 'Incorrect')
                  .show();

            $block.find('.pal-option').each(function () {
                var id = String($(this).data('option-id'));
                if (id === String(res.correct_answer_id)) {
                    $(this).addClass('text-success font-weight-bold');
                }
            });

            if (res.feedback) {
                $block.find('.pal-feedback').text(res.feedback).show();
            }

            if (res.progress) {
                $('#pal_progress').load(progressUrl + ' #pal_progress_inner', function () {});
            }
        }).fail(function () {
            $block.data('answered', false);
            $block.find('.pal-answer').prop('disabled', false);
            alert('Could not reach the server. Please try again.');
        });
    });
});
</script>

<style>
    .pal-question .custom-control-label { cursor: pointer; }
</style>
@endsection
