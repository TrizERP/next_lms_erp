@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')
<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-6 col-md-6 col-sm-6 col-xs-12">
                <h4 class="page-title">PAL Content Intelligence</h4>
            </div>
            <div class="col-lg-6 col-md-6 col-sm-6 col-xs-12 text-right">
                <a href="{{ route('pal_content.review') }}" class="btn btn-primary btn-sm">Review Queue</a>
                <a href="{{ route('pal_content.misconceptions') }}" class="btn btn-info btn-sm">Misconception Library</a>
            </div>
        </div>

        @include('lms.pal.content._flash')

        {{-- ── Coverage ─────────────────────────────────────────────────── --}}
        <div class="row">
            <div class="col-lg-3 col-md-6 col-sm-6 col-xs-12">
                <div class="white-box">
                    <h3 class="box-title">Questions tagged</h3>
                    <h1 class="text-info">{{ number_format($data['questions']['tagged']) }}</h1>
                    <span class="text-muted">of {{ number_format($data['questions']['total']) }} — {{ $data['questions']['pct'] }}%</span>
                    <div class="progress" style="margin-top:8px;">
                        <div class="progress-bar progress-bar-info" style="width: {{ min(100, $data['questions']['pct']) }}%"></div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 col-sm-6 col-xs-12">
                <div class="white-box">
                    <h3 class="box-title">Content tagged</h3>
                    <h1 class="text-success">{{ number_format($data['content']['tagged']) }}</h1>
                    <span class="text-muted">of {{ number_format($data['content']['total']) }} — {{ $data['content']['pct'] }}%</span>
                    <div class="progress" style="margin-top:8px;">
                        <div class="progress-bar progress-bar-success" style="width: {{ min(100, $data['content']['pct']) }}%"></div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 col-sm-6 col-xs-12">
                <div class="white-box">
                    <h3 class="box-title">Awaiting review</h3>
                    <h1 class="text-warning">{{ number_format($data['pipeline']['question']['draft'] + $data['pipeline']['content']['draft']) }}</h1>
                    <span class="text-muted">draft proposals</span>
                    <div style="margin-top:8px;">
                        <a href="{{ route('pal_content.review') }}" class="btn btn-xs btn-warning">Open queue</a>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 col-sm-6 col-xs-12">
                <div class="white-box">
                    <h3 class="box-title">Misconceptions</h3>
                    <h1 class="{{ $data['health']['c6_pass'] ? 'text-success' : 'text-danger' }}">{{ $data['health']['total'] }}</h1>
                    <span class="text-muted">{{ $data['health']['approved'] }} approved &middot; {{ $data['health']['servable_with_corrective'] }} servable</span>
                    <div style="margin-top:8px;">
                        @if($data['health']['c6_pass'])
                            <span class="label label-success">C6 pass</span>
                        @else
                            <span class="label label-danger">C6 fail — {{ $data['health']['c6_violations'] }} without corrective</span>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            {{-- ── QA pipeline ──────────────────────────────────────────── --}}
            <div class="col-lg-6 col-md-12">
                <div class="white-box">
                    <h3 class="box-title">Quality pipeline</h3>
                    <p class="text-muted" style="margin-top:-8px;">
                        Only <strong>approved</strong> content is ever served to a learner.
                    </p>
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>Stage</th>
                                    <th class="text-right">Questions</th>
                                    <th class="text-right">Content</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach(array_keys(config('pal_content.quality_statuses')) as $status)
                                <tr>
                                    <td>
                                        {{ ucwords(str_replace('_',' ', $status)) }}
                                        @if(in_array($status, config('pal_content.servable_statuses')))
                                            <span class="label label-success">served</span>
                                        @endif
                                    </td>
                                    <td class="text-right">{{ number_format($data['pipeline']['question'][$status]) }}</td>
                                    <td class="text-right">{{ number_format($data['pipeline']['content'][$status]) }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {{-- ── Bloom distribution ───────────────────────────────────── --}}
            <div class="col-lg-6 col-md-12">
                <div class="white-box">
                    <h3 class="box-title">Bloom's distribution</h3>
                    <p class="text-muted" style="margin-top:-8px;">
                        Cognitive demand of tagged questions. This is a different axis from the
                        legacy easy/medium/hard score bands — both coexist.
                    </p>
                    @php $bloomTotal = max(1, array_sum($data['bloom']->toArray())); @endphp
                    <div class="table-responsive">
                        <table class="table">
                            <tbody>
                            @foreach($data['bloom_levels'] as $key => $def)
                                @php $n = (int) ($data['bloom'][$key] ?? 0); @endphp
                                <tr>
                                    <td style="width:34%;">
                                        <strong>L{{ $def['practice_level'] }}</strong>
                                        {{ ucfirst($key) }}
                                    </td>
                                    <td>
                                        <div class="progress" style="margin-bottom:0;">
                                            <div class="progress-bar progress-bar-info"
                                                 style="width: {{ round($n / $bloomTotal * 100) }}%"></div>
                                        </div>
                                    </td>
                                    <td class="text-right" style="width:18%;">{{ number_format($n) }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            {{-- ── Linkage warning ──────────────────────────────────────── --}}
            <div class="col-lg-6 col-md-12">
                <div class="white-box">
                    <h3 class="box-title">Curriculum linkage</h3>
                    <p class="text-muted" style="margin-top:-8px;">
                        What the engine can route on for this institute.
                    </p>
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr><th>Estate</th><th class="text-right">Has concept</th><th class="text-right">Has chapter</th></tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>Questions</td>
                                    <td class="text-right">{{ number_format($data['linkage']['questions_with_concept']) }}</td>
                                    <td class="text-right">{{ number_format($data['linkage']['questions_with_chapter']) }}</td>
                                </tr>
                                <tr>
                                    <td>Content</td>
                                    <td class="text-right">{{ number_format($data['linkage']['content_with_concept']) }}</td>
                                    <td class="text-right">{{ number_format($data['linkage']['content_with_chapter']) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    @if($data['linkage']['questions_with_concept'] < $data['linkage']['questions_with_chapter'] * 0.5)
                        <div class="alert alert-warning" style="margin-bottom:0;">
                            <strong>Routing on chapter.</strong> Concept ids are largely unpopulated in this
                            data, so the engine groups content by chapter instead. It sharpens to concept
                            level automatically once chapter&rarr;concept mapping is filled in — no code change needed.
                        </div>
                    @endif
                </div>
            </div>

            {{-- ── Recent review activity ───────────────────────────────── --}}
            <div class="col-lg-6 col-md-12">
                <div class="white-box">
                    <h3 class="box-title">Recent review activity</h3>
                    @if($data['recent_reviews']->isEmpty())
                        <p class="text-muted">No review activity yet for this institute.</p>
                    @else
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr><th>What</th><th>Change</th><th>By</th><th>When</th></tr>
                            </thead>
                            <tbody>
                            @foreach($data['recent_reviews'] as $log)
                                <tr>
                                    <td>{{ ucfirst($log->entity_type) }} #{{ $log->entity_id }}</td>
                                    <td>
                                        <small>{{ $log->from_status ?? 'new' }} &rarr; <strong>{{ $log->to_status }}</strong></small>
                                        @if($log->actor_type !== 'human')
                                            <span class="label label-default">{{ $log->actor_type }}</span>
                                        @endif
                                    </td>
                                    <td>{{ $log->reviewed_by ?: '—' }}</td>
                                    <td><small>{{ $log->created_at }}</small></td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- ── How to populate ──────────────────────────────────────────── --}}
        <div class="row">
            <div class="col-lg-12">
                <div class="white-box">
                    <h3 class="box-title">Populating this data</h3>
                    <p class="text-muted">
                        Tagging runs as a batch job, not from this screen — it processes tens of thousands of
                        rows. Everything it writes lands here as a <strong>draft</strong> for review; a batch can
                        never approve its own work.
                    </p>
                    <pre style="background:#f7f7f7;padding:12px;border-radius:3px;">php artisan pal:content-coverage --concepts     <span style="color:#888"># full status report</span>
php artisan pal:tag-content --order-by-usage    <span style="color:#888"># propose Bloom/difficulty/context</span>
php artisan pal:derive-irt                      <span style="color:#888"># difficulty from 2.4M answer history</span>
php artisan pal:seed-misconceptions             <span style="color:#888"># load the misconception library</span>
php artisan pal:vocab-check                     <span style="color:#888"># validation gate</span></pre>
                </div>
            </div>
        </div>

    </div>
</div>
@include('includes.footer')
