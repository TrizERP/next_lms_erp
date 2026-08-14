@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')
<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-6 col-md-6 col-sm-6 col-xs-12">
                <h4 class="page-title">PAL Review Queue</h4>
            </div>
            <div class="col-lg-6 col-md-6 col-sm-6 col-xs-12 text-right">
                <a href="{{ route('pal_content.index') }}" class="btn btn-default btn-sm">Dashboard</a>
                <a href="{{ route('pal_content.misconceptions') }}" class="btn btn-info btn-sm">Misconception Library</a>
            </div>
        </div>

        @include('lms.pal.content._flash')

        <div class="white-box">
            <p class="text-muted">
                Machine-proposed tags, <strong>least confident first</strong> — that is where review time is
                worth spending. Nothing here reaches a learner until it is approved.
            </p>

            {{-- ── Filters ──────────────────────────────────────────────── --}}
            <form method="GET" action="{{ route('pal_content.review') }}" class="form-inline" style="margin-bottom:16px;">
                <div class="form-group">
                    <label>Estate</label>
                    <select name="entity_type" class="form-control input-sm" onchange="this.form.submit()">
                        <option value="question" {{ $data['entity_type']==='question' ? 'selected' : '' }}>Questions</option>
                        <option value="content" {{ $data['entity_type']==='content' ? 'selected' : '' }}>Content</option>
                    </select>
                </div>
                <div class="form-group" style="margin-left:10px;">
                    <label>Stage</label>
                    <select name="status" class="form-control input-sm" onchange="this.form.submit()">
                        @foreach(array_keys(config('pal_content.quality_statuses')) as $s)
                            <option value="{{ $s }}" {{ $data['status']===$s ? 'selected' : '' }}>
                                {{ ucwords(str_replace('_',' ',$s)) }} ({{ (int) ($data['counts'][$s] ?? 0) }})
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group" style="margin-left:10px;">
                    <label>Chapter id</label>
                    <input type="number" name="chapter_id" value="{{ request('chapter_id') }}" class="form-control input-sm" style="width:110px;">
                </div>
                <button type="submit" class="btn btn-sm btn-default" style="margin-left:8px;">Filter</button>
            </form>

            @if($data['items']->isEmpty())
                <div class="alert alert-info">
                    Nothing in <strong>{{ $data['status'] }}</strong> for {{ $data['entity_type'] }}s.
                    Run <code>php artisan pal:tag-content</code> to generate proposals.
                </div>
            @else

            {{-- ── Bulk action bar ──────────────────────────────────────── --}}
            <form method="POST" action="{{ route('pal_content.transition') }}" id="bulkForm">
                @csrf
                <input type="hidden" name="entity_type" value="{{ $data['entity_type'] }}">

                <div class="well well-sm">
                    <label style="margin-right:12px;">
                        <input type="checkbox" id="checkAll"> Select all on this page
                    </label>
                    <span id="selCount" class="text-muted" style="margin-right:12px;">0 selected</span>
                    <select name="to_status" class="form-control input-sm" style="width:190px;display:inline-block;">
                        @foreach(config('pal_content.quality_transitions')[$data['status']] ?? [] as $next)
                            <option value="{{ $next }}">Move to {{ ucwords(str_replace('_',' ',$next)) }}</option>
                        @endforeach
                    </select>
                    <input type="text" name="note" class="form-control input-sm" placeholder="Note (optional)" style="width:240px;display:inline-block;">
                    <button type="submit" class="btn btn-sm btn-primary">Apply</button>
                    <span class="text-muted" style="margin-left:10px;">
                        <small>Approval stamps your user id and timestamp on every row.</small>
                    </span>
                </div>

                <div class="table-responsive">
                    <table class="table table-striped table-bordered">
                        <thead>
                            <tr>
                                <th style="width:30px;"></th>
                                <th>Item</th>
                                <th style="width:110px;">Bloom</th>
                                <th style="width:70px;">Diff</th>
                                <th style="width:130px;">Context</th>
                                <th style="width:90px;">Confidence</th>
                                <th style="width:150px;">Blocking approval</th>
                                <th style="width:70px;"></th>
                            </tr>
                        </thead>
                        <tbody>
                        @foreach($data['items'] as $item)
                            @php
                                $m = $item['meta'];
                                $conf = $m->confidence;
                                $confClass = $conf === null ? 'default' : ($conf < 0.6 ? 'danger' : ($conf < 0.75 ? 'warning' : 'success'));
                            @endphp
                            <tr>
                                <td><input type="checkbox" name="metadata_ids[]" value="{{ $m->id }}" class="rowCheck"></td>
                                <td>
                                    <strong>#{{ $item['entity_id'] }}</strong>
                                    {{ \Illuminate\Support\Str::limit($item['title'], 95) }}
                                    <br>
                                    <small class="text-muted">
                                        chapter {{ $m->chapter_ref_id ?? '—' }}
                                        &middot; tagged by <em>{{ $m->tagged_by }}</em>
                                        @if(!empty($m->ai_rationale['bloom_evidence']))
                                            &middot; matched &ldquo;{{ $m->ai_rationale['bloom_evidence'] }}&rdquo;
                                        @endif
                                    </small>
                                </td>
                                <td>
                                    {{ $m->bloom_level ?? $m->bloom_level_served ?? '—' }}
                                    @if($m->practice_level)<br><small class="text-muted">L{{ $m->practice_level }}</small>@endif
                                </td>
                                <td>{{ $m->difficulty_1_to_5 ?? '—' }}</td>
                                <td><small>{{ $m->cultural_context ?? '—' }}</small></td>
                                <td>
                                    @if($conf !== null)
                                        <span class="label label-{{ $confClass }}">{{ number_format($conf * 100) }}%</span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td>
                                    @if(empty($item['missing']))
                                        <span class="label label-success">complete</span>
                                    @else
                                        <small class="text-danger">{{ implode(', ', array_slice($item['missing'], 0, 3)) }}@if(count($item['missing'])>3) +{{ count($item['missing'])-3 }}@endif</small>
                                    @endif
                                </td>
                                <td>
                                    <button type="button" class="btn btn-xs btn-default"
                                            data-toggle="collapse" data-target="#edit{{ $m->id }}">Edit</button>
                                </td>
                            </tr>
                            <tr>
                                <td colspan="8" style="padding:0;border-top:0;">
                                    <div id="edit{{ $m->id }}" class="collapse" style="padding:12px;background:#fafafa;">
                                        {{-- Nested forms are illegal, so this posts via a JS-built form on submit. --}}
                                        <div class="row editRow" data-id="{{ $m->id }}">
                                            <input type="hidden" class="f_entity_id" value="{{ $item['entity_id'] }}">
                                            <div class="col-md-2">
                                                <label>Bloom level</label>
                                                <select class="form-control input-sm f_bloom">
                                                    <option value="">—</option>
                                                    @foreach(array_keys($data['vocab']['bloom_levels']) as $b)
                                                        <option value="{{ $b }}" {{ ($m->bloom_level ?? $m->bloom_level_served)===$b ? 'selected':'' }}>{{ ucfirst($b) }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="col-md-1">
                                                <label>Difficulty</label>
                                                <select class="form-control input-sm f_diff">
                                                    <option value="">—</option>
                                                    @for($i=1;$i<=5;$i++)
                                                        <option value="{{ $i }}" {{ (int)$m->difficulty_1_to_5===$i ? 'selected':'' }}>{{ $i }}</option>
                                                    @endfor
                                                </select>
                                            </div>
                                            <div class="col-md-2">
                                                <label>Cultural context</label>
                                                <select class="form-control input-sm f_context">
                                                    <option value="">—</option>
                                                    @foreach($data['vocab']['cultural_contexts'] as $c)
                                                        <option value="{{ $c }}" {{ $m->cultural_context===$c ? 'selected':'' }}>{{ str_replace('_',' ',$c) }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="col-md-1">
                                                <label>Language</label>
                                                <select class="form-control input-sm f_lang">
                                                    <option value="">—</option>
                                                    @foreach($data['vocab']['languages'] as $l)
                                                        <option value="{{ $l }}" {{ $m->language===$l ? 'selected':'' }}>{{ $l }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="col-md-3">
                                                <label>Pedagogy</label>
                                                <select class="form-control input-sm f_pedagogy">
                                                    <option value="">—</option>
                                                    @foreach($data['vocab']['pedagogy'] as $pid => $pname)
                                                        <option value="{{ $pid }}" {{ (int)$m->pedagogy_mapping_id===(int)$pid ? 'selected':'' }}>{{ $pname }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="col-md-2">
                                                <label>HPC lens</label>
                                                <select class="form-control input-sm f_hpc">
                                                    <option value="">—</option>
                                                    @foreach($data['vocab']['hpc_lenses'] as $h)
                                                        <option value="{{ $h }}" {{ $m->hpc_lens_primary===$h ? 'selected':'' }}>{{ $h }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="col-md-1">
                                                <label style="display:block;">&nbsp;</label>
                                                <button type="button" class="btn btn-sm btn-success saveRow">Save</button>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </form>

            <div class="text-center">
                {{ $data['paginator']->links() }}
            </div>
            @endif
        </div>
    </div>
</div>

{{-- Hidden single-row save form. The edit panels sit inside the bulk form, and
     HTML forbids nesting forms, so saving copies the values into this one. --}}
<form method="POST" action="{{ route('pal_content.updateMetadata') }}" id="saveForm" style="display:none;">
    @csrf
    <input type="hidden" name="entity_type" value="{{ $data['entity_type'] }}">
    <input type="hidden" name="entity_id" id="s_entity_id">
    <input type="hidden" name="{{ $data['entity_type'] === 'question' ? 'bloom_level' : 'bloom_level_served' }}" id="s_bloom">
    <input type="hidden" name="difficulty_1_to_5" id="s_diff">
    <input type="hidden" name="cultural_context" id="s_context">
    <input type="hidden" name="language" id="s_lang">
    <input type="hidden" name="pedagogy_mapping_id" id="s_pedagogy">
    <input type="hidden" name="hpc_lens_primary" id="s_hpc">
</form>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var checks = document.querySelectorAll('.rowCheck');
    var counter = document.getElementById('selCount');

    function refreshCount() {
        var n = document.querySelectorAll('.rowCheck:checked').length;
        counter.textContent = n + ' selected';
    }

    var all = document.getElementById('checkAll');
    if (all) {
        all.addEventListener('change', function () {
            checks.forEach(function (c) { c.checked = all.checked; });
            refreshCount();
        });
    }
    checks.forEach(function (c) { c.addEventListener('change', refreshCount); });

    // Copy one edit panel's values into the hidden form and submit it.
    document.querySelectorAll('.saveRow').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var row = btn.closest('.editRow');
            document.getElementById('s_entity_id').value = row.querySelector('.f_entity_id').value;
            document.getElementById('s_bloom').value     = row.querySelector('.f_bloom').value;
            document.getElementById('s_diff').value      = row.querySelector('.f_diff').value;
            document.getElementById('s_context').value   = row.querySelector('.f_context').value;
            document.getElementById('s_lang').value      = row.querySelector('.f_lang').value;
            document.getElementById('s_pedagogy').value  = row.querySelector('.f_pedagogy').value;
            document.getElementById('s_hpc').value       = row.querySelector('.f_hpc').value;
            document.getElementById('saveForm').submit();
        });
    });

    // Guard against an empty bulk submit, which would just bounce off validation.
    var bulk = document.getElementById('bulkForm');
    if (bulk) {
        bulk.addEventListener('submit', function (e) {
            if (document.querySelectorAll('.rowCheck:checked').length === 0) {
                e.preventDefault();
                alert('Select at least one row first.');
            }
        });
    }
});
</script>
@include('includes.footer')
