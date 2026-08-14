@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')
<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-6 col-md-6 col-sm-6 col-xs-12">
                <h4 class="page-title">PAL Misconception Library</h4>
            </div>
            <div class="col-lg-6 col-md-6 col-sm-6 col-xs-12 text-right">
                <a href="{{ route('pal_content.index') }}" class="btn btn-default btn-sm">Dashboard</a>
                <a href="{{ route('pal_content.review') }}" class="btn btn-primary btn-sm">Review Queue</a>
            </div>
        </div>

        @include('lms.pal.content._flash')

        <div class="white-box">
            <p class="text-muted">
                When a learner's wrong answer matches a known error pattern, the engine serves the linked
                corrective in a <strong>different format</strong> — it never repeats the explanation that
                already failed. An entry with no approved corrective cannot be approved: detecting the
                error and then showing nothing is worse than not detecting it.
            </p>

            <div class="row" style="margin-bottom:12px;">
                <div class="col-md-8">
                    <span class="label label-default">{{ $data['health']['total'] }} entries</span>
                    <span class="label label-success">{{ $data['health']['approved'] }} approved</span>
                    <span class="label label-info">{{ $data['health']['servable_with_corrective'] }} servable</span>
                    @if($data['health']['c6_pass'])
                        <span class="label label-success">C6 pass</span>
                    @else
                        <span class="label label-danger">{{ $data['health']['c6_violations'] }} approved without a corrective</span>
                    @endif
                </div>
            </div>

            <form method="GET" action="{{ route('pal_content.misconceptions') }}" class="form-inline" style="margin-bottom:16px;">
                <div class="form-group">
                    <input type="text" name="q" value="{{ request('q') }}" class="form-control input-sm"
                           placeholder="Search tag or description" style="width:240px;">
                </div>
                <div class="form-group" style="margin-left:8px;">
                    <select name="subject" class="form-control input-sm">
                        <option value="">All subjects</option>
                        @foreach($data['subjects'] as $s)
                            <option value="{{ $s }}" {{ request('subject')===$s ? 'selected':'' }}>{{ $s }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group" style="margin-left:8px;">
                    <select name="status" class="form-control input-sm">
                        <option value="">All stages</option>
                        @foreach(array_keys(config('pal_content.quality_statuses')) as $s)
                            <option value="{{ $s }}" {{ request('status')===$s ? 'selected':'' }}>{{ ucwords(str_replace('_',' ',$s)) }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="btn btn-sm btn-default" style="margin-left:8px;">Filter</button>
            </form>

            @if($data['items']->isEmpty())
                <div class="alert alert-info">
                    No misconceptions found. Run <code>php artisan pal:seed-misconceptions</code> to load the
                    starter library.
                </div>
            @else
            <div class="panel-group" id="mcAccordion">
                @foreach($data['items'] as $m)
                    @php
                        $approvedCorrectives = $m->correctives->where('quality_status','approved')->count();
                        $isGlobal = (int) $m->sub_institute_id === 0;
                    @endphp
                    <div class="panel panel-default">
                        <div class="panel-heading" style="cursor:pointer;" data-toggle="collapse"
                             data-parent="#mcAccordion" data-target="#mc{{ $m->id }}">
                            <div class="row">
                                <div class="col-md-5">
                                    <strong><code>{{ $m->tag }}</code></strong>
                                    @if($isGlobal)<span class="label label-default">global</span>@endif
                                </div>
                                <div class="col-md-3">
                                    <small class="text-muted">{{ $m->subject ?? '—' }} &middot; grade {{ $m->grade_band ?? '—' }}</small>
                                </div>
                                <div class="col-md-2">
                                    @if($m->prevalence_rate)
                                        <small class="text-muted">{{ round($m->prevalence_rate * 100) }}% of students</small>
                                    @endif
                                </div>
                                <div class="col-md-2 text-right">
                                    <span class="label label-{{ $m->quality_status === 'approved' ? 'success' : ($m->quality_status === 'deprecated' ? 'default' : 'warning') }}">
                                        {{ $m->quality_status }}
                                    </span>
                                    @if($approvedCorrectives === 0)
                                        <span class="label label-danger" title="No approved corrective — cannot be served">C6</span>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <div id="mc{{ $m->id }}" class="panel-collapse collapse">
                            <div class="panel-body">
                                <div class="row">
                                    <div class="col-md-7">
                                        <p><strong>What the student does:</strong><br>{{ $m->description }}</p>
                                        @if($m->error_pattern)
                                            <p><strong>Error pattern:</strong><br><em>{{ $m->error_pattern }}</em></p>
                                        @endif
                                        @if($m->corrective_action)
                                            <p><strong>Corrective approach:</strong><br>{{ $m->corrective_action }}</p>
                                        @endif
                                        @if(!empty($m->typical_wrong_answers))
                                            <p>
                                                <strong>Detected on these answers:</strong><br>
                                                @foreach($m->typical_wrong_answers as $w)
                                                    <span class="label label-default" style="margin-right:4px;">{{ $w }}</span>
                                                @endforeach
                                            </p>
                                        @endif
                                        @if($m->error_regex)
                                            <p><small class="text-muted">Pattern match: <code>{{ $m->error_regex }}</code></small></p>
                                        @endif
                                        <p><small class="text-muted">
                                            Detected {{ $m->detection_count }} time(s)
                                            &middot; priority {{ $m->priority_level }}
                                            &middot; preferred format {{ $m->corrective_format ?? '—' }}
                                        </small></p>

                                        @if($data['can_author'] && !$isGlobal)
                                            <form method="POST" action="{{ route('pal_content.approveMisconception') }}" class="form-inline">
                                                @csrf
                                                <input type="hidden" name="misconception_id" value="{{ $m->id }}">
                                                <select name="to_status" class="form-control input-sm">
                                                    @foreach(config('pal_content.quality_transitions')[$m->quality_status] ?? [] as $next)
                                                        <option value="{{ $next }}">Move to {{ $next }}</option>
                                                    @endforeach
                                                </select>
                                                <button type="submit" class="btn btn-sm btn-primary">Apply</button>
                                            </form>
                                        @elseif($isGlobal)
                                            <div class="alert alert-info" style="padding:8px;margin-bottom:0;">
                                                <small>Shared vocabulary across all institutes — edited centrally.
                                                To customise it here, author an institute-specific entry with the same tag.</small>
                                            </div>
                                        @endif
                                    </div>

                                    <div class="col-md-5">
                                        <h5>Corrective content ({{ $m->correctives->count() }})</h5>
                                        @if($m->correctives->isEmpty())
                                            <div class="alert alert-danger" style="padding:8px;">
                                                <small><strong>C6 violation.</strong> No corrective content — this entry
                                                can never be served.</small>
                                            </div>
                                        @else
                                            @foreach($m->correctives->sortBy('priority_level') as $c)
                                                <div style="border:1px solid #eee;padding:8px;margin-bottom:8px;border-radius:3px;">
                                                    <div>
                                                        <strong>{{ $c->title }}</strong>
                                                        <span class="label label-info">{{ $c->format }}</span>
                                                        <span class="label label-{{ $c->quality_status === 'approved' ? 'success' : 'warning' }}">
                                                            {{ $c->quality_status }}
                                                        </span>
                                                    </div>
                                                    @if($c->body)
                                                        <p style="margin:6px 0 0;"><small>{{ \Illuminate\Support\Str::limit($c->body, 220) }}</small></p>
                                                    @endif
                                                    <small class="text-muted">
                                                        priority {{ $c->priority_level }}
                                                        @if($c->estimated_duration_minutes) &middot; {{ $c->estimated_duration_minutes }} min @endif
                                                        @if($c->h5p_type) &middot; {{ $c->h5p_type }} @endif
                                                        &middot; served {{ $c->served_count }}x
                                                        @if($c->resolution_rate !== null) &middot; resolved {{ round($c->resolution_rate*100) }}% @endif
                                                    </small>
                                                    @if($data['can_author'] && !$isGlobal)
                                                        <form method="POST" action="{{ route('pal_content.approveCorrective') }}" class="form-inline" style="margin-top:6px;">
                                                            @csrf
                                                            <input type="hidden" name="corrective_id" value="{{ $c->id }}">
                                                            <select name="to_status" class="form-control input-sm" style="width:130px;">
                                                                @foreach(config('pal_content.quality_transitions')[$c->quality_status] ?? [] as $next)
                                                                    <option value="{{ $next }}">{{ $next }}</option>
                                                                @endforeach
                                                            </select>
                                                            <button type="submit" class="btn btn-xs btn-default">Apply</button>
                                                        </form>
                                                    @endif
                                                </div>
                                            @endforeach
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="text-center">
                {{ $data['items']->links() }}
            </div>
            @endif
        </div>
    </div>
</div>
@include('includes.footer')
