{{--
    Teach/Learn → PAL → Learning Flow.

    Written for a principal, not an engineer. No profile keys, no stage names,
    no version ids anywhere on the page — a person choosing how their school
    teaches should not have to learn the engine's vocabulary to do it.

    Note on scripts: this layout does NOT render @push('scripts'), so anything
    pushed there is silently dropped. The small amount of JS this page needs is
    inline at the bottom of the container section, which is the working
    convention in the other lms/pal blades.
--}}
@extends('lmslayout')
@section('container')

<div class="container-fluid" style="padding:18px 22px;">

    <div style="margin-bottom:18px;">
        <h4 style="margin:0 0 4px;font-weight:600;">Learning Flow</h4>
        <div style="color:#6b7280;font-size:13px;">
            How your students move through a topic in Personalised Adaptive Learning.
        </div>
    </div>

    @if (session('pal_flow_status'))
        <div class="alert alert-success" style="border-radius:8px;">
            <i class="mdi mdi-check-circle mr-1"></i> {{ session('pal_flow_status') }}
        </div>
    @endif

    @if (session('pal_flow_error'))
        <div class="alert alert-danger" style="border-radius:8px;">
            <i class="mdi mdi-alert-circle mr-1"></i> {{ session('pal_flow_error') }}
        </div>
    @endif

    @unless ($installed)
        {{-- Honest rather than broken. Most of the estate is behind on
             migrations, and a school that sees this needs to know it is a
             setup gap and not a fault of theirs. --}}
        <div class="alert alert-warning" style="border-radius:8px;">
            <strong>Not set up on this server yet.</strong><br>
            The learning flow options have not been installed here. Your students are
            following the standard flow, exactly as before. Ask your system administrator
            to complete the PAL flow setup.
        </div>
    @else

        {{-- What is happening right now. The first thing anyone opening this
             page wants to know, so it goes first and it is large. --}}
        <div style="background:#f0f7ff;border:1px solid #cfe3ff;border-radius:10px;padding:16px 18px;margin-bottom:22px;">
            <div style="font-size:12px;letter-spacing:.04em;text-transform:uppercase;color:#4b6b96;margin-bottom:6px;">
                Your school is using
            </div>
            <div style="font-size:19px;font-weight:600;color:#1f3a5f;">
                @foreach ($profiles as $card)
                    @if ($card['is_current']) {{ $card['title'] }} @endif
                @endforeach
                @if (is_null($assigned))
                    <span style="font-size:12px;font-weight:400;color:#6b7280;">(the default — you have not changed this)</span>
                @endif
            </div>
            <div style="margin-top:8px;font-size:15px;color:#1f3a5f;">
                @foreach ($profiles as $card)
                    @if ($card['is_current']) {{ $card['steps'] }} @endif
                @endforeach
            </div>
        </div>

        {{-- The rule they cannot change, stated before they start looking for
             the dial. Saying nothing here just produces a support ticket. --}}
        <div style="border-left:3px solid #d1d5db;padding:2px 0 2px 14px;margin-bottom:22px;color:#4b5563;font-size:13px;line-height:1.6;">
            <strong style="color:#374151;">What is the same at every school</strong><br>
            A topic counts as <em>mastered</em> only when a student has answered it correctly
            at least 3 times, with at least one of those without any hint. You can change the
            path a student takes through a topic. You cannot change the finishing line —
            that is what lets results be compared fairly between schools.
        </div>

        <h6 style="font-weight:600;margin-bottom:12px;">Choose a flow</h6>

        <div class="row">
            @foreach ($profiles as $card)
                <div class="col-md-6" style="margin-bottom:16px;">
                    <div style="border:1px solid {{ $card['is_current'] ? '#4a90d9' : '#e5e7eb' }};
                                border-radius:10px;padding:16px;height:100%;
                                background:{{ $card['is_current'] ? '#fbfdff' : '#fff' }};">

                        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px;">
                            <div style="font-weight:600;font-size:15px;">{{ $card['title'] }}</div>
                            @if ($card['is_current'])
                                <span class="badge badge-primary" style="font-weight:500;">In use</span>
                            @endif
                        </div>

                        <div style="color:#4b5563;font-size:13px;line-height:1.55;margin-bottom:10px;">
                            {{ $card['blurb'] }}
                        </div>

                        <div style="font-size:13px;color:#1f3a5f;background:#f7f9fc;border-radius:6px;padding:8px 10px;margin-bottom:10px;">
                            {{ $card['steps'] }}
                        </div>

                        @if ($card['best_for'])
                            <div style="color:#6b7280;font-size:12px;margin-bottom:12px;">
                                <i class="mdi mdi-information-outline"></i> {{ $card['best_for'] }}
                            </div>
                        @endif

                        @if ($canWrite && ! $card['is_current'])
                            <form method="POST" action="{{ route('pal.flow.assign') }}" class="js-flow-form">
                                @csrf
                                <input type="hidden" name="profile_key" value="{{ $card['key'] }}">
                                <button type="submit"
                                        class="btn btn-sm btn-outline-primary js-flow-submit"
                                        data-title="{{ $card['title'] }}"
                                        data-steps="{{ $card['steps'] }}">
                                    Use this flow
                                </button>
                            </form>
                        @elseif (! $canWrite && ! $card['is_current'])
                            <div style="color:#9ca3af;font-size:12px;">
                                Only an administrator or principal can change this.
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        @if ($canWrite && ! is_null($assigned))
            <form method="POST" action="{{ route('pal.flow.assign') }}" class="js-flow-form" style="margin-top:6px;">
                @csrf
                <input type="hidden" name="profile_key" value="__default__">
                <button type="submit" class="btn btn-sm btn-link" style="padding-left:0;"
                        data-title="the standard flow"
                        data-steps="Learn → Practise → Check understanding">
                    Reset to the standard flow
                </button>
            </form>
        @endif

        {{-- The question every principal asks second: what happens to the
             children who are halfway through right now? --}}
        <div style="margin-top:24px;border-top:1px solid #eef0f3;padding-top:16px;color:#6b7280;font-size:13px;line-height:1.7;">
            <strong style="color:#374151;">If you change this</strong><br>
            Students who are part-way through a topic <strong>finish it the way they started</strong>.
            Nobody is taught one way and then tested another. The new flow applies to topics they
            begin after the change.
        </div>
    @endunless
</div>

{{-- Inline on purpose: @push('scripts') is not rendered by this layout. --}}
<script>
    document.querySelectorAll('.js-flow-form').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            var button = form.querySelector('[data-title]');
            var title = button ? button.getAttribute('data-title') : 'this flow';
            var steps = button ? button.getAttribute('data-steps') : '';

            var message = 'Change your school to ' + title + '?\n\n'
                + steps + '\n\n'
                + 'Students already part-way through a topic will finish it the way they started.';

            if (!window.confirm(message)) {
                event.preventDefault();
            }
        });
    });
</script>

@endsection
