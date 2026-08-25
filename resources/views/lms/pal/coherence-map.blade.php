@extends('lmslayout') @section('container')
{{--
  Set Coherence Map — the LMS view.

  Renders through CoherenceMapWebController, which goes through the same
  CoherenceMapRepository / CoherenceRecommender the JSON API uses, so this page
  and /api/pal/coherence/* can never disagree about a learner's readiness.

  LAYOUT IS DELIBERATE, NOT A PRESET. The live data (measured 2026-08-24, scope
  standard 42 / subject 3976) has ZERO cross-chapter prerequisite edges: 84
  REQUIRES spread over 8 chapters plus 9 chapterless concepts, 16 disconnected
  components, 46 concepts with no link at all, longest chain 4 once the 13 nodes
  on cycles are pinned. A force-directed preset on that produces 16 blobs in
  positions that move on every reload and mean nothing. So the
  default view lays chapters out as columns in teaching order (chapter_master
  .sort_order) and stacks concepts inside each column by prerequisite depth.
  That is the only ordering the data actually supports, it is stable across
  reloads, and it reads the way a syllabus reads: left to right through the year,
  top to bottom through the dependencies.

  Cytoscape 3.23 is loaded from the same CDN already used by graph-view.blade.php
  — no new frontend dependency, no build step.
--}}

<div id="page-wrapper">
  <div class="container-fluid">

    @if (empty($payload))
      <div class="cmap-empty">
        <h4>Coherence Map unavailable</h4>
        <p>{{ $reason ?? 'No map could be resolved for this session.' }}</p>
      </div>
    @else

    <div class="cmap" id="cmap">

      {{-- ── scope bar ───────────────────────────────────────────── --}}
      <div class="cmap-bar">
        <div class="cmap-bar-title">
          <strong>Coherence Map</strong>
          <span class="cmap-scope-name">{{ $payload['scope']['standard_name'] }} &middot; {{ $payload['scope']['subject_name'] }}</span>
        </div>

        <select id="cmap-scope" class="cmap-select" aria-label="Class and subject">
          @foreach ($payload['scopes'] as $s)
            <option value="{{ $s['standard_id'] }}:{{ $s['subject_id'] }}"
              @if ($s['standard_id'] === $payload['scope']['standard_id'] && $s['subject_id'] === $payload['scope']['subject_id']) selected @endif>
              {{ $s['standard_name'] }} — {{ $s['subject_name'] }} ({{ $s['concepts'] }} concepts, {{ $s['requires'] }} links)
            </option>
          @endforeach
        </select>

        <input type="search" id="cmap-search" class="cmap-input" placeholder="Search concepts…" aria-label="Search concepts">

        <select id="cmap-learner" class="cmap-select" aria-label="Learner overlay">
          <option value="">No learner overlay</option>
          @foreach ($payload['learners'] as $l)
            <option value="{{ $l['id'] }}" @if ($payload['scope']['learner_id'] === $l['id']) selected @endif>
              {{ $l['name'] }}@if ($l['evidence'] > 0) — {{ $l['evidence'] }} tracked @endif
            </option>
          @endforeach
        </select>

        <button type="button" id="cmap-fit" class="cmap-btn" title="Fit to view (F)">Fit</button>
      </div>

      {{-- ── health banner ───────────────────────────────────────── --}}
      @php $h = $payload['health']; @endphp
      <div class="cmap-health {{ $h['fit_to_use'] ? 'is-ok' : 'is-warn' }}">
        <span class="cmap-health-dot"></span>
        <span>
          <strong>{{ $h['concepts'] }}</strong> concepts &middot;
          <strong>{{ $payload['graph']['stats']['requires'] }}</strong> prerequisite links &middot;
          <strong>{{ $payload['graph']['stats']['cross_links'] }}</strong> related links &middot;
          <strong>{{ $h['roots'] }}</strong> entry points &middot;
          deepest chain <strong>{{ $h['max_depth'] }}</strong>
        </span>
        @if (! $h['acyclic'])
          <span class="cmap-chip is-crit">{{ count($h['cycles']) }} concepts on a cycle</span>
        @endif
        @if ($h['isolated'] > 0)
          <span class="cmap-chip is-warn">{{ $h['isolated'] }} unlinked</span>
        @endif
        @if ($payload['graph']['stats']['draft_edges'] > 0)
          <span class="cmap-chip is-warn">{{ $payload['graph']['stats']['draft_edges'] }} draft links — AI-suggested, not reviewed</span>
        @endif
      </div>

      {{-- ── body ────────────────────────────────────────────────── --}}
      <div class="cmap-body">

        {{-- rail --}}
        <aside class="cmap-rail">
          <div class="cmap-group">
            <h6>View</h6>
            <label class="cmap-radio"><input type="radio" name="cmap-view" value="chapters" checked> Chapters in teaching order</label>
            <label class="cmap-radio"><input type="radio" name="cmap-view" value="depth"> Prerequisite depth only</label>
            <label class="cmap-radio"><input type="radio" name="cmap-view" value="lineage"> Selected concept lineage</label>
          </div>

          <div class="cmap-group">
            <h6>Links</h6>
            <label class="cmap-check"><input type="checkbox" id="cmap-show-requires" checked> <span class="cmap-key cmap-key-req"></span> Prerequisite</label>
            <label class="cmap-check"><input type="checkbox" id="cmap-show-cross" checked> <span class="cmap-key cmap-key-cross"></span> Related</label>
          </div>

          <div class="cmap-group" id="cmap-legend-state" hidden>
            <h6>Learner state</h6>
            <div class="cmap-check"><span class="cmap-dot is-mastered"></span> Mastered</div>
            <div class="cmap-check"><span class="cmap-dot is-ready"></span> Ready now</div>
            <div class="cmap-check"><span class="cmap-dot is-blocked"></span> Blocked</div>
          </div>

          <div class="cmap-group">
            <h6>Show only</h6>
            <label class="cmap-check"><input type="checkbox" id="cmap-f-content"> Has teaching content</label>
            <label class="cmap-check"><input type="checkbox" id="cmap-f-questions"> Has questions</label>
            <label class="cmap-check"><input type="checkbox" id="cmap-f-linked"> Has prerequisite links</label>
          </div>

          <div class="cmap-group">
            <h6>Chapters</h6>
            <div id="cmap-chapters"></div>
          </div>
        </aside>

        {{-- canvas --}}
        <div class="cmap-canvas-wrap">
          <div id="cmap-canvas" role="application" aria-label="Concept coherence graph"></div>
          <div class="cmap-hint">Click a concept for detail &middot; drag to pan &middot; scroll to zoom &middot; <kbd>Esc</kbd> clears</div>
        </div>

        {{-- drawer --}}
        <aside class="cmap-drawer" id="cmap-drawer" aria-live="polite">
          <div class="cmap-drawer-empty">
            <p>Select a concept to see what it needs, what it unlocks, and what to teach or ask.</p>
          </div>
        </aside>
      </div>
    </div>

    {{-- The first payload, so the graph paints without a round trip. --}}
    <script type="application/json" id="cmap-payload">@json($payload)</script>

    @endif
  </div>
</div>

<style>
/* Scoped under .cmap / .cmap- so nothing here can reach the LMS theme. */
.cmap { --c-line:#dcdfe3; --c-ink:#1d2125; --c-ink-2:#5b6570; --c-ink-3:#8b949e;
        --c-bg:#fff; --c-bg-2:#f5f6f7; --c-accent:#0b6b60; --c-accent-soft:#e2efec;
        --c-mastered:#2e7d4f; --c-ready:#b0790c; --c-blocked:#9aa3ad; --c-crit:#ae3527;
        font-size:13px; color:var(--c-ink); }
.cmap *,.cmap *::before,.cmap *::after { box-sizing:border-box; }

.cmap-empty { background:#fff; border:1px solid #dcdfe3; border-radius:6px; padding:24px; margin:20px 0; }
.cmap-empty h4 { margin:0 0 8px; font-size:16px; }
.cmap-empty p { margin:0; color:#5b6570; font-size:13px; }

.cmap-bar { display:flex; flex-wrap:wrap; align-items:center; gap:10px;
            background:var(--c-bg); border:1px solid var(--c-line); border-radius:6px 6px 0 0;
            padding:10px 14px; margin-top:16px; }
.cmap-bar-title { display:flex; flex-direction:column; line-height:1.25; margin-right:auto; }
.cmap-bar-title strong { font-size:14px; }
.cmap-scope-name { color:var(--c-ink-3); font-size:11.5px; }
.cmap-select,.cmap-input { border:1px solid var(--c-line); border-radius:4px; padding:6px 9px;
                           font-size:12.5px; background:var(--c-bg); color:var(--c-ink); max-width:280px; }
.cmap-input { min-width:170px; }
.cmap-btn { border:1px solid var(--c-line); background:var(--c-bg); border-radius:4px;
            padding:6px 12px; font-size:12.5px; cursor:pointer; color:var(--c-ink); }
.cmap-btn:hover { background:var(--c-bg-2); }
.cmap-select:focus-visible,.cmap-input:focus-visible,.cmap-btn:focus-visible
  { outline:2px solid var(--c-accent); outline-offset:1px; }

.cmap-health { display:flex; flex-wrap:wrap; align-items:center; gap:10px;
               border:1px solid var(--c-line); border-top:0; padding:8px 14px;
               background:var(--c-bg-2); font-size:12px; color:var(--c-ink-2); }
.cmap-health strong { color:var(--c-ink); font-variant-numeric:tabular-nums; }
.cmap-health-dot { width:8px; height:8px; border-radius:50%; flex:none; }
.cmap-health.is-ok .cmap-health-dot { background:var(--c-mastered); }
.cmap-health.is-warn .cmap-health-dot { background:var(--c-ready); }
.cmap-chip { font-size:10.5px; letter-spacing:.03em; text-transform:uppercase;
             padding:2px 7px; border-radius:3px; white-space:nowrap; }
.cmap-chip.is-warn { background:#f4ebd6; color:#8a5c07; }
.cmap-chip.is-crit { background:#f7e3e0; color:var(--c-crit); }

.cmap-body { display:grid; grid-template-columns:196px minmax(0,1fr) 300px;
             border:1px solid var(--c-line); border-top:0; border-radius:0 0 6px 6px;
             background:var(--c-bg); min-height:620px; }
@media (max-width:1200px) { .cmap-body { grid-template-columns:176px minmax(0,1fr); }
                            .cmap-drawer { grid-column:1 / -1; border-left:0 !important;
                                           border-top:1px solid var(--c-line); } }
@media (max-width:820px)  { .cmap-body { grid-template-columns:minmax(0,1fr); }
                            .cmap-rail { border-right:0; border-bottom:1px solid var(--c-line); } }

.cmap-rail { border-right:1px solid var(--c-line); padding:14px 12px; overflow-y:auto; max-height:760px; }
.cmap-group { margin-bottom:18px; }
.cmap-group h6 { margin:0 0 8px; font-size:10px; letter-spacing:.09em; text-transform:uppercase;
                 color:var(--c-ink-3); font-weight:600; }
.cmap-radio,.cmap-check { display:flex; align-items:center; gap:7px; font-size:12px;
                          padding:3px 0; cursor:pointer; color:var(--c-ink-2); }
.cmap-radio input,.cmap-check input { margin:0; flex:none; }
.cmap-dot { width:9px; height:9px; border-radius:50%; flex:none; }
.cmap-dot.is-mastered { background:var(--c-mastered); }
.cmap-dot.is-ready    { background:var(--c-ready); }
.cmap-dot.is-blocked  { background:var(--c-blocked); }
.cmap-key { width:16px; height:0; border-top:2px solid var(--c-accent); flex:none; }
.cmap-key-cross { border-top-style:dashed; border-top-color:var(--c-ink-3); }
.cmap-ch { display:flex; align-items:center; gap:6px; font-size:11.5px; padding:3px 0;
           color:var(--c-ink-2); cursor:pointer; }
.cmap-ch:hover { color:var(--c-accent); }
.cmap-ch-swatch { width:9px; height:9px; border-radius:2px; flex:none; }
.cmap-ch-n { margin-left:auto; color:var(--c-ink-3); font-variant-numeric:tabular-nums; }

.cmap-canvas-wrap { position:relative; min-width:0; }
#cmap-canvas { width:100%; height:660px; background:
   linear-gradient(var(--c-bg-2) 1px, transparent 1px) 0 0/100% 40px; }
.cmap-hint { position:absolute; left:0; right:0; bottom:0; padding:6px 12px;
             font-size:11px; color:var(--c-ink-3); background:rgba(255,255,255,.9);
             border-top:1px solid var(--c-line); }
.cmap-hint kbd { font-size:10px; border:1px solid var(--c-line); border-radius:3px;
                 padding:0 4px; background:var(--c-bg-2); }

.cmap-drawer { border-left:1px solid var(--c-line); padding:14px; overflow-y:auto; max-height:760px; }
.cmap-drawer-empty p { color:var(--c-ink-3); font-size:12.5px; margin:0; }
.cmap-d-title { font-size:15px; font-weight:600; margin:0 0 3px; line-height:1.25; }
.cmap-d-meta { font-size:11px; color:var(--c-ink-3); margin:0 0 10px; }
.cmap-d-desc { font-size:12.5px; color:var(--c-ink-2); margin:0 0 12px; line-height:1.5; }
.cmap-d-sec { border-top:1px solid var(--c-line); padding-top:10px; margin-top:12px; }
.cmap-d-sec h6 { margin:0 0 7px; font-size:10px; letter-spacing:.09em; text-transform:uppercase;
                 color:var(--c-ink-3); font-weight:600; }
.cmap-d-row { display:flex; align-items:baseline; gap:6px; font-size:12px; padding:3px 0;
              color:var(--c-ink-2); }
.cmap-d-link { background:none; border:0; padding:0; font:inherit; color:var(--c-accent);
               cursor:pointer; text-align:left; }
.cmap-d-link:hover { text-decoration:underline; }
.cmap-d-num { margin-left:auto; font-variant-numeric:tabular-nums; color:var(--c-ink-3); font-size:11px; }
.cmap-bar-track { height:8px; border-radius:4px; background:#e8eaed; position:relative; margin:6px 0 4px; }
.cmap-bar-fill { height:8px; border-radius:4px; background:var(--c-ready); }
.cmap-bar-gate { position:absolute; top:-3px; width:2px; height:14px; background:var(--c-ink); }
.cmap-asset { border:1px solid var(--c-line); border-radius:4px; padding:6px 8px;
              margin-bottom:5px; font-size:11.5px; color:var(--c-ink-2); }
.cmap-asset strong { color:var(--c-ink); font-weight:600; }
.cmap-start { display:block; width:100%; margin-top:8px; padding:7px 10px; font-size:12px;
              border:1px solid var(--c-accent); background:var(--c-accent-soft);
              color:var(--c-accent); border-radius:4px; cursor:pointer; text-align:left; }
.cmap-badge { display:inline-block; font-size:9.5px; letter-spacing:.05em; text-transform:uppercase;
              padding:2px 6px; border-radius:3px; background:var(--c-bg-2); color:var(--c-ink-2); }
.cmap-badge.is-draft { background:#f4ebd6; color:#8a5c07; }
.cmap-badge.is-ok { background:#e2efe7; color:var(--c-mastered); }
</style>

<script src="https://cdnjs.cloudflare.com/ajax/libs/cytoscape/3.23.0/cytoscape.min.js"></script>
<script>
(function () {
  'use strict';

  var el = document.getElementById('cmap-payload');
  if (!el) { return; }

  var DATA = JSON.parse(el.textContent);
  var CSRF = document.querySelector('meta[name="csrf-token"]');
  CSRF = CSRF ? CSRF.getAttribute('content') : '';

  // Chapter palette. Distinct hues at a common lightness so no chapter reads as
  // more important than another; state colours (mastered/ready/blocked) are a
  // separate scale and always win over the chapter tint when an overlay is on.
  var CH_COLORS = ['#0b6b60','#3d5a94','#8a5c07','#6d3f7a','#1f6f8b',
                   '#8a4230','#4a6b23','#7a4b6b','#2f6b4f','#5a5a8a'];

  var STATE_COLORS = { mastered:'#2e7d4f', ready:'#b0790c', blocked:'#9aa3ad' };

  // Whitespace normaliser plus a cheap guard for U+FFFD. Chapter names in
  // vivek_erp are correctly stored UTF-8 (verified: "I’m Up and Down" round
  // trips intact), but concept names come from an extraction pipeline and a
  // replacement char reaching a node label renders as a black diamond, which
  // reads as a rendering bug rather than as bad source data.
  function clean(s) {
    return String(s == null ? '' : s).replace(/�/g, "'").replace(/\s+/g, ' ').trim();
  }

  // ────────────────────────────────────────────────────────────────
  // State
  // ────────────────────────────────────────────────────────────────
  var state = {
    scope: DATA.scope,
    nodes: DATA.graph.nodes || [],
    edges: DATA.graph.edges || [],
    chapters: DATA.graph.chapters || [],
    readiness: DATA.readiness || {},
    learnerId: DATA.scope.learner_id,
    view: 'chapters',
    selected: null,
    showRequires: true,
    showCross: true,
    filters: { content: false, questions: false, linked: false },
    query: ''
  };

  var chColor = {};
  state.chapters.forEach(function (c, i) { chColor[c.id] = CH_COLORS[i % CH_COLORS.length]; });

  var byId = {};
  state.nodes.forEach(function (n) { byId[n.id] = n; });

  // ────────────────────────────────────────────────────────────────
  // Layout — computed here, not by a preset. See the Blade comment.
  // ────────────────────────────────────────────────────────────────
  var COL_W = 240, ROW_H = 86, PAD_X = 90, PAD_Y = 70;

  function positions(view) {
    var pos = {};

    if (view === 'depth') {
      // One global stack: every concept at the same prerequisite depth sits on
      // the same row, regardless of chapter. Shows the dependency structure of
      // the whole subject at once.
      var byDepth = {};
      state.nodes.forEach(function (n) {
        var d = n.depth || 0;
        (byDepth[d] = byDepth[d] || []).push(n);
      });
      Object.keys(byDepth).forEach(function (d) {
        byDepth[d].sort(function (a, b) {
          return (a.chapter_order || 99) - (b.chapter_order || 99) || a.id - b.id;
        });
        byDepth[d].forEach(function (n, i) {
          pos[n.id] = { x: PAD_X + i * 170, y: PAD_Y + Number(d) * (ROW_H + 24) };
        });
      });
      return pos;
    }

    // Default: chapter columns in teaching order, depth rows inside each.
    var cols = state.chapters.map(function (c) { return c.id; });
    // Concepts with no chapter link (9 of 118 live) get their own trailing
    // column rather than being dropped — they are real concepts that simply
    // have no :Chapter node, and hiding them hides a data gap.
    cols.push(null);

    cols.forEach(function (cid, ci) {
      var group = state.nodes.filter(function (n) { return (n.chapter_id || null) === cid; });
      if (!group.length) { return; }

      var byDepth = {};
      group.forEach(function (n) {
        var d = n.depth || 0;
        (byDepth[d] = byDepth[d] || []).push(n);
      });

      var row = 0;
      Object.keys(byDepth).sort(function (a, b) { return a - b; }).forEach(function (d) {
        byDepth[d].sort(function (a, b) { return a.id - b.id; });
        byDepth[d].forEach(function (n, i) {
          pos[n.id] = {
            x: PAD_X + ci * COL_W + (i % 2) * 74,
            y: PAD_Y + row * ROW_H
          };
          if (i % 2 === 1 || byDepth[d].length === 1) { row++; }
        });
        if (byDepth[d].length % 2 === 1 && byDepth[d].length > 1) { row++; }
      });
    });

    return pos;
  }

  // ────────────────────────────────────────────────────────────────
  // Cytoscape
  // ────────────────────────────────────────────────────────────────
  var cy = cytoscape({
    container: document.getElementById('cmap-canvas'),
    minZoom: 0.2,
    maxZoom: 2.6,
    wheelSensitivity: 0.22,
    style: [
      { selector: 'node', style: {
          'label': 'data(label)',
          'text-wrap': 'wrap',
          'text-max-width': '128px',
          'font-size': '10px',
          'font-family': 'system-ui, -apple-system, Segoe UI, sans-serif',
          'text-valign': 'center',
          'color': '#fff',
          'background-color': 'data(color)',
          'shape': 'round-rectangle',
          'width': 140,
          'height': 'data(h)',
          'padding': '6px',
          'border-width': 0,
          'transition-property': 'opacity, border-width',
          'transition-duration': '120ms'
      }},
      { selector: 'node[?dim]', style: { 'opacity': 0.16 } },
      { selector: 'node:selected', style: { 'border-width': 3, 'border-color': '#1d2125' } },
      { selector: 'node[?onCycle]', style: { 'border-width': 2, 'border-color': '#ae3527', 'border-style': 'dashed' } },
      { selector: 'edge', style: {
          'width': 1.4,
          'line-color': '#9aa3ad',
          'target-arrow-color': '#9aa3ad',
          'target-arrow-shape': 'triangle',
          'arrow-scale': 0.85,
          'curve-style': 'bezier',
          'opacity': 0.72
      }},
      { selector: 'edge[kind = "REQUIRES"]', style: { 'line-color': '#0b6b60', 'target-arrow-color': '#0b6b60', 'width': 1.8 } },
      { selector: 'edge[kind = "CROSS_LINKS"]', style: { 'line-style': 'dashed', 'target-arrow-shape': 'none', 'opacity': 0.45 } },
      { selector: 'edge[?dim]', style: { 'opacity': 0.06 } },
      { selector: 'edge[?lit]', style: { 'width': 3, 'opacity': 1, 'line-color': '#ae3527', 'target-arrow-color': '#ae3527' } }
    ]
  });

  function nodeColor(n) {
    if (state.learnerId && state.readiness[n.id]) {
      return STATE_COLORS[state.readiness[n.id].state] || '#9aa3ad';
    }
    return chColor[n.chapter_id] || '#6b7480';
  }

  function labelFor(n) {
    var name = clean(n.name) || ('Concept ' + n.id);
    return n.code ? name + '\n' + clean(n.code) : name;
  }

  function visible(n) {
    if (state.filters.content && !(n.content_n > 0)) { return false; }
    if (state.filters.questions && !(n.question_n > 0)) { return false; }
    if (state.filters.linked && !((n.prereq_ids || []).length || (n.unlocks_ids || []).length)) { return false; }
    return true;
  }

  function render() {
    var pos = positions(state.view);
    var keep = {};
    var els = [];

    state.nodes.forEach(function (n) {
      if (!visible(n)) { return; }
      // Lineage view: only the selected concept and its two closures.
      if (state.view === 'lineage' && state.selected) {
        if (!lineage()[n.id]) { return; }
      }
      keep[n.id] = true;
      els.push({
        group: 'nodes',
        data: {
          id: 'n' + n.id,
          raw: n.id,
          label: labelFor(n),
          color: nodeColor(n),
          h: 34 + (clean(n.name).length > 34 ? 14 : 0),
          onCycle: n.on_cycle ? true : undefined
        },
        position: pos[n.id] || { x: PAD_X, y: PAD_Y }
      });
    });

    state.edges.forEach(function (e, i) {
      if (!keep[e.source] || !keep[e.target]) { return; }
      if (e.kind === 'REQUIRES' && !state.showRequires) { return; }
      if (e.kind === 'CROSS_LINKS' && !state.showCross) { return; }
      els.push({
        group: 'edges',
        data: { id: 'e' + i, source: 'n' + e.source, target: 'n' + e.target, kind: e.kind }
      });
    });

    cy.elements().remove();
    cy.add(els);
    cy.layout({ name: 'preset', fit: true, padding: 40 }).run();
    applyEmphasis();
    paintChapters();
  }

  // The selected concept plus everything above and below it.
  function lineage() {
    var set = {};
    if (!state.selected) { return set; }
    set[state.selected] = true;

    var down = [state.selected], guard = 0;
    while (down.length && guard++ < 4000) {
      var cur = byId[down.pop()];
      if (!cur) { continue; }
      (cur.prereq_ids || []).forEach(function (p) { if (!set[p]) { set[p] = true; down.push(p); } });
    }
    var up = [state.selected]; guard = 0;
    while (up.length && guard++ < 4000) {
      var c2 = byId[up.pop()];
      if (!c2) { continue; }
      (c2.unlocks_ids || []).forEach(function (u) { if (!set[u]) { set[u] = true; up.push(u); } });
    }
    return set;
  }

  // Dim everything outside the current focus rather than removing it, so the
  // reader keeps their place on the canvas. Search and selection share this.
  function applyEmphasis() {
    var focus = null;

    if (state.query) {
      var q = state.query.toLowerCase();
      focus = {};
      state.nodes.forEach(function (n) {
        var hay = (clean(n.name) + ' ' + clean(n.code) + ' ' + clean(n.description) + ' ' + clean(n.chapter)).toLowerCase();
        if (hay.indexOf(q) !== -1) { focus[n.id] = true; }
      });
    } else if (state.selected && state.view !== 'lineage') {
      focus = lineage();
    }

    cy.batch(function () {
      cy.nodes().forEach(function (n) {
        n.data('dim', focus && !focus[n.data('raw')] ? true : undefined);
      });
      cy.edges().forEach(function (e) {
        var a = e.source().data('raw'), b = e.target().data('raw');
        e.data('dim', focus && !(focus[a] && focus[b]) ? true : undefined);
      });
    });
  }

  function paintChapters() {
    var box = document.getElementById('cmap-chapters');
    if (!box) { return; }
    box.innerHTML = '';
    state.chapters.forEach(function (c) {
      var row = document.createElement('button');
      row.type = 'button';
      row.className = 'cmap-ch';
      row.innerHTML = '<span class="cmap-ch-swatch" style="background:' + chColor[c.id] + '"></span>' +
        '<span>' + escapeHtml(
          clean(c.name)
            ? (c.order != null ? c.order + '. ' : '') + clean(c.name)
            : 'Chapter ' + c.id + ' — no chapter node'
        ) + '</span>' +
        '<span class="cmap-ch-n">' + c.concepts + '</span>';
      row.addEventListener('click', function () {
        var ns = cy.nodes().filter(function (n) { return byId[n.data('raw')].chapter_id === c.id; });
        if (ns.length) { cy.animate({ fit: { eles: ns, padding: 70 }, duration: 260 }); }
      });
      box.appendChild(row);
    });
  }

  function escapeHtml(s) {
    return String(s).replace(/[&<>"']/g, function (m) {
      return { '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[m];
    });
  }

  // ────────────────────────────────────────────────────────────────
  // Drawer
  // ────────────────────────────────────────────────────────────────
  var drawer = document.getElementById('cmap-drawer');

  function openDrawer(id) {
    var n = byId[id];
    if (!n) { return; }

    var r = state.readiness[id];
    var html = '';

    html += '<h5 class="cmap-d-title">' + escapeHtml(clean(n.name)) + '</h5>';
    html += '<p class="cmap-d-meta">' +
      (n.code ? escapeHtml(clean(n.code)) + ' &middot; ' : '') +
      // A concept can carry a chapter_id whose :Chapter node was never loaded —
      // real for chapter 8560 in the live scope (the legacy concepts 4-11). Say
      // which id is dangling rather than showing a blank, so the gap is fixable.
      (n.chapter ? escapeHtml(clean(n.chapter))
                 : (n.chapter_id ? 'chapter ' + n.chapter_id + ' — no chapter node in the graph'
                                 : 'no chapter id')) +
      (n.bloom ? ' &middot; ' + escapeHtml(n.bloom) : '') +
      (n.minutes ? ' &middot; ~' + n.minutes + ' min' : '') + '</p>';

    html += '<p>' + badge(n.status) + '</p>';

    if (n.description) {
      html += '<p class="cmap-d-desc">' + escapeHtml(clean(n.description)) + '</p>';
    }

    if (state.learnerId && r) {
      var pct = Math.round((r.mastery || 0) * 100);
      var gatePct = Math.round((r.gate || 0.7) * 100);
      html += '<div class="cmap-d-sec"><h6>Mastery — ' + escapeHtml(r.state) + '</h6>' +
        '<div class="cmap-bar-track"><div class="cmap-bar-fill" style="width:' + pct + '%;background:' +
        (STATE_COLORS[r.state] || '#9aa3ad') + '"></div>' +
        '<div class="cmap-bar-gate" style="left:' + gatePct + '%"></div></div>' +
        '<div class="cmap-d-row">p = ' + (r.mastery || 0).toFixed(2) + ' &middot; gate ' + (r.gate || 0.7).toFixed(2) +
        (n.attempts != null ? ' &middot; ' + n.attempts + ' attempt(s)' : '') + '</div></div>';
    }

    html += '<div class="cmap-d-sec"><h6>Needs first — ' + (n.prereq_ids || []).length + '</h6>' + refs(n.prereq_ids) + '</div>';
    html += '<div class="cmap-d-sec"><h6>Unlocks — ' + (n.unlocks_ids || []).length + ' direct' +
            (r ? ', ' + r.unlocks + ' in total' : '') + '</h6>' + refs(n.unlocks_ids) + '</div>';

    html += '<div class="cmap-d-sec" id="cmap-d-assets"><h6>Teach it &amp; assess it</h6>' +
            '<div class="cmap-d-row">' + (n.content_n || 0) + ' content, ' + (n.question_n || 0) + ' questions — loading…</div></div>';

    drawer.innerHTML = html;

    drawer.querySelectorAll('[data-goto]').forEach(function (b) {
      b.addEventListener('click', function () { select(Number(b.getAttribute('data-goto')), true); });
    });

    loadAssets(id);
  }

  function badge(status) {
    return status === 'approved'
      ? '<span class="cmap-badge is-ok">reviewed</span>'
      : '<span class="cmap-badge is-draft">draft &middot; AI-suggested</span>';
  }

  function refs(ids) {
    ids = ids || [];
    if (!ids.length) { return '<div class="cmap-d-row" style="color:#8b949e">none</div>'; }
    return ids.map(function (i) {
      var t = byId[i];
      var rr = state.readiness[i];
      var mark = '';
      if (state.learnerId && rr) {
        mark = rr.state === 'mastered'
          ? '<span class="cmap-dot is-mastered"></span>'
          : '<span class="cmap-dot is-' + rr.state + '"></span>';
      }
      return '<div class="cmap-d-row">' + mark +
        '<button type="button" class="cmap-d-link" data-goto="' + i + '">' +
        escapeHtml(t ? clean(t.name) : ('Concept ' + i)) + '</button>' +
        (state.learnerId && rr ? '<span class="cmap-d-num">' + rr.mastery.toFixed(2) + '</span>' : '') +
        '</div>';
    }).join('');
  }

  function loadAssets(id) {
    var url = '{{ url('/lms/coherence-map/concept') }}/' + id +
              (state.learnerId ? '?learner_id=' + state.learnerId : '');

    fetch(url, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        if (!res || !res.success || state.selected !== id) { return; }
        var box = document.getElementById('cmap-d-assets');
        if (!box) { return; }

        var d = res.data, html = '<h6>Teach it — ' + d.content.length + '</h6>';

        html += d.content.length
          ? d.content.map(function (c) {
              return '<div class="cmap-asset"><strong>' +
                escapeHtml(clean(c.title) || ('Content ' + c.id)) + '</strong>' +
                [c.content_type, c.h5p_type, c.format, c.minutes ? c.minutes + ' min' : '']
                  .filter(Boolean).map(escapeHtml).join(' &middot; ') + '</div>';
            }).join('')
          : '<div class="cmap-d-row" style="color:#8b949e">nothing attached to this concept yet</div>';

        html += '<h6 style="margin-top:12px">Assess it — ' + d.questions.length + '</h6>';
        html += d.questions.length
          ? d.questions.map(function (q) {
              return '<div class="cmap-asset"><strong>' +
                escapeHtml(clean(q.title) || ('Question ' + q.id)) + '</strong>' +
                [q.bloom, q.difficulty ? 'difficulty ' + q.difficulty : '',
                 q.irt_b != null ? 'b = ' + Number(q.irt_b).toFixed(2) : '']
                  .filter(Boolean).map(escapeHtml).join(' &middot; ') + '</div>';
            }).join('')
          : '<div class="cmap-d-row" style="color:#8b949e">no questions tagged to this concept yet</div>';

        if (d.root_blockers && d.root_blockers.length) {
          var root = d.root_blockers[0];
          html += '<h6 style="margin-top:12px">Why this is blocked</h6>' +
            '<div class="cmap-d-row">Nothing beneath &ldquo;' + escapeHtml(clean(root.name)) +
            '&rdquo; is unmastered, so that is where the chain breaks — ' +
            root.mastery.toFixed(2) + ' against a gate of ' + root.gate.toFixed(2) + '.</div>' +
            '<button type="button" class="cmap-start" data-goto="' + root.id + '">Start here → ' +
            escapeHtml(clean(root.name)) + '</button>';
        }

        box.innerHTML = html;
        box.querySelectorAll('[data-goto]').forEach(function (b) {
          b.addEventListener('click', function () { select(Number(b.getAttribute('data-goto')), true); });
        });
      })
      .catch(function () {
        var box = document.getElementById('cmap-d-assets');
        if (box) { box.innerHTML = '<h6>Teach it &amp; assess it</h6>' +
          '<div class="cmap-d-row" style="color:#ae3527">Could not load — the graph may be unreachable.</div>'; }
      });
  }

  function select(id, centre) {
    state.selected = id;
    cy.nodes().unselect();
    var n = cy.getElementById('n' + id);

    if (n.length) {
      n.select();
      if (centre) { cy.animate({ center: { eles: n }, duration: 220 }); }
    }

    if (state.view === 'lineage') { render(); } else { applyEmphasis(); }
    openDrawer(id);
    pushUrl();
  }

  function clearSelection() {
    state.selected = null;
    cy.nodes().unselect();
    drawer.innerHTML = '<div class="cmap-drawer-empty"><p>Select a concept to see what it needs, ' +
      'what it unlocks, and what to teach or ask.</p></div>';
    if (state.view === 'lineage') { render(); } else { applyEmphasis(); }
    pushUrl();
  }

  // ────────────────────────────────────────────────────────────────
  // URL state — a teacher can link straight to the concept they mean.
  // ────────────────────────────────────────────────────────────────
  function pushUrl() {
    var p = new URLSearchParams();
    p.set('standard_id', state.scope.standard_id);
    p.set('subject_id', state.scope.subject_id);
    if (state.learnerId) { p.set('learner_id', state.learnerId); }
    if (state.selected) { p.set('concept_id', state.selected); }
    if (state.view !== 'chapters') { p.set('view', state.view); }
    history.replaceState(null, '', location.pathname + '?' + p.toString());
  }

  function reload(params) {
    var p = new URLSearchParams(params);
    p.set('format', 'json');

    fetch(location.pathname + '?' + p.toString(),
          { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        if (!res || !res.success) { return; }
        var d = res.data;
        state.scope = d.scope;
        state.nodes = d.graph.nodes || [];
        state.edges = d.graph.edges || [];
        state.chapters = d.graph.chapters || [];
        state.readiness = d.readiness || {};
        state.learnerId = d.scope.learner_id;
        state.selected = null;

        chColor = {};
        state.chapters.forEach(function (c, i) { chColor[c.id] = CH_COLORS[i % CH_COLORS.length]; });
        byId = {};
        state.nodes.forEach(function (n) { byId[n.id] = n; });

        document.getElementById('cmap-legend-state').hidden = !state.learnerId;
        clearSelection();
        render();
      });
  }

  // ────────────────────────────────────────────────────────────────
  // Wiring
  // ────────────────────────────────────────────────────────────────
  cy.on('tap', 'node', function (evt) { select(evt.target.data('raw'), false); });
  cy.on('tap', function (evt) { if (evt.target === cy) { clearSelection(); } });

  document.getElementById('cmap-scope').addEventListener('change', function () {
    var v = this.value.split(':');
    reload({ standard_id: v[0], subject_id: v[1] });
  });

  document.getElementById('cmap-learner').addEventListener('change', function () {
    reload({
      standard_id: state.scope.standard_id,
      subject_id: state.scope.subject_id,
      learner_id: this.value || ''
    });
  });

  var searchTimer;
  document.getElementById('cmap-search').addEventListener('input', function () {
    var v = this.value.trim();
    clearTimeout(searchTimer);
    searchTimer = setTimeout(function () { state.query = v; applyEmphasis(); }, 140);
  });

  document.getElementById('cmap-fit').addEventListener('click', function () {
    cy.animate({ fit: { padding: 40 }, duration: 220 });
  });

  Array.prototype.forEach.call(document.querySelectorAll('input[name="cmap-view"]'), function (r) {
    r.addEventListener('change', function () {
      if (!this.checked) { return; }
      state.view = this.value;
      render();
      pushUrl();
    });
  });

  document.getElementById('cmap-show-requires').addEventListener('change', function () {
    state.showRequires = this.checked; render();
  });
  document.getElementById('cmap-show-cross').addEventListener('change', function () {
    state.showCross = this.checked; render();
  });
  ['content', 'questions', 'linked'].forEach(function (k) {
    document.getElementById('cmap-f-' + k).addEventListener('change', function () {
      state.filters[k] = this.checked; render();
    });
  });

  document.addEventListener('keydown', function (e) {
    if (e.target.matches('input, select, textarea')) {
      if (e.key === 'Escape') { e.target.blur(); }
      return;
    }
    if (e.key === 'Escape') { clearSelection(); }
    if (e.key === 'f' || e.key === 'F') { cy.animate({ fit: { padding: 40 }, duration: 220 }); }
  });

  // ── go ──
  document.getElementById('cmap-legend-state').hidden = !state.learnerId;
  render();

  var initial = new URLSearchParams(location.search);
  var wantView = initial.get('view');
  if (wantView) {
    var radio = document.querySelector('input[name="cmap-view"][value="' + wantView + '"]');
    if (radio) { radio.checked = true; state.view = wantView; render(); }
  }
  var wantConcept = Number(initial.get('concept_id'));
  if (wantConcept && byId[wantConcept]) { select(wantConcept, true); }
})();
</script>
@endsection
