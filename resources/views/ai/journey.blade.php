<!doctype html>
<html lang="en" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>AI Journey — Student Profiles</title>
    <style>
        :root {
            --bg: #f5f6f8;
            --panel: #ffffff;
            --ink: #16181d;
            --muted: #6b7280;
            --line: #e3e6ea;
            --accent: #2563eb;
            --ran: #15803d;
            --pending: #b45309;
            --blocked: #b91c1c;
            --skipped: #6b7280;
            --idle: #c2c7ce;
            --code-bg: #f3f4f6;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font: 14px/1.5 -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background: var(--bg);
            color: var(--ink);
        }
        header {
            background: var(--panel);
            border-bottom: 1px solid var(--line);
            padding: 12px 20px;
            display: flex;
            align-items: center;
            gap: 16px;
            flex-wrap: wrap;
        }
        header h1 { font-size: 15px; margin: 0; font-weight: 650; }
        header .scope { font-size: 12px; color: var(--muted); }
        header .scope b { color: var(--ink); font-weight: 600; }
        .layout {
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
            gap: 16px;
            padding: 16px;
            align-items: start;
        }
        @media (max-width: 1100px) { .layout { grid-template-columns: minmax(0, 1fr); } }

        .panel {
            background: var(--panel);
            border: 1px solid var(--line);
            border-radius: 10px;
            overflow: hidden;
        }
        .panel > h2 {
            margin: 0;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: .06em;
            color: var(--muted);
            padding: 10px 14px;
            border-bottom: 1px solid var(--line);
            background: #fafbfc;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        /* ---------------- chat ---------------- */
        #thread { padding: 14px; max-height: 68vh; overflow-y: auto; }
        .msg { margin-bottom: 18px; }
        .msg.user .bubble {
            background: var(--accent);
            color: #fff;
            display: inline-block;
            padding: 8px 12px;
            border-radius: 12px 12px 2px 12px;
            max-width: 85%;
        }
        .msg.user { text-align: right; }
        .msg.ai .headline { font-weight: 650; margin-bottom: 8px; }
        .section { border-top: 1px solid var(--line); padding-top: 10px; margin-top: 10px; }
        .section > .stitle {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: .05em;
            color: var(--muted);
            margin-bottom: 6px;
        }
        .rec { border: 1px solid var(--line); border-radius: 8px; padding: 8px 10px; margin-bottom: 6px; }
        .rec .rtitle { font-weight: 600; }
        .rec .rline { color: #374151; font-size: 13px; }
        .rec .rmeta { font-size: 11px; color: var(--muted); margin-top: 4px; }
        .badge {
            display: inline-block; font-size: 11px; font-weight: 600;
            padding: 1px 7px; border-radius: 999px; margin-left: 6px;
            background: #eef2ff; color: #3730a3;
        }
        .badge.danger { background: #fee2e2; color: #991b1b; }
        .badge.warning { background: #fef3c7; color: #92400e; }
        table.kv, table.cmp { width: 100%; border-collapse: collapse; font-size: 13px; }
        table.kv td, table.cmp td, table.cmp th {
            padding: 4px 6px; border-bottom: 1px solid var(--line); vertical-align: top;
        }
        table.kv td:first-child { color: var(--muted); width: 40%; }
        table.cmp th { text-align: left; font-size: 11px; color: var(--muted); text-transform: uppercase; }
        .ev { font-size: 13px; border-left: 3px solid var(--line); padding: 4px 0 4px 8px; margin-bottom: 6px; }
        .ev .src { font-size: 11px; color: var(--muted); font-family: ui-monospace, Menlo, Consolas, monospace; }
        .ev .vtag { font-size: 10px; padding: 0 5px; border-radius: 3px; background: #dcfce7; color: #166534; }
        .ev .vtag.gen { background: #fef3c7; color: #92400e; }
        .stepline { display: flex; gap: 8px; align-items: baseline; padding: 3px 0; font-size: 13px; }
        .stepline .dot { width: 14px; flex: 0 0 14px; text-align: center; font-weight: 700; }
        .stepline.completed .dot { color: var(--ran); }
        .stepline.pending .dot, .stepline.awaiting_approval .dot { color: var(--pending); }
        .stepline.rejected .dot, .stepline.failed .dot { color: var(--blocked); }
        .stepline .skey { color: var(--muted); font-family: ui-monospace, Menlo, Consolas, monospace; font-size: 11px; }

        .actions { margin-top: 12px; display: flex; gap: 8px; flex-wrap: wrap; }
        button {
            font: inherit; cursor: pointer; border-radius: 7px;
            border: 1px solid var(--line); background: #fff; padding: 6px 12px;
        }
        button:hover { border-color: #b6bcc4; }
        button.primary { background: var(--accent); border-color: var(--accent); color: #fff; }
        button.danger { background: #fff; border-color: #fca5a5; color: #b91c1c; }
        button:disabled { opacity: .5; cursor: not-allowed; }
        .chips { margin-top: 10px; display: flex; gap: 6px; flex-wrap: wrap; }
        .chip {
            font-size: 12px; padding: 4px 10px; border-radius: 999px;
            border: 1px dashed #c7ccd3; color: #374151; background: #fff; cursor: pointer;
        }
        .chip:hover { border-style: solid; border-color: var(--accent); color: var(--accent); }

        #composer { display: flex; gap: 8px; padding: 12px 14px; border-top: 1px solid var(--line); background: #fafbfc; }
        #q { flex: 1; padding: 9px 12px; border: 1px solid var(--line); border-radius: 8px; font: inherit; }
        #q:focus { outline: 2px solid #bfdbfe; border-color: var(--accent); }

        /* ---------------- trace ---------------- */
        #trace { padding: 6px 0; max-height: 68vh; overflow-y: auto; }
        .stage { border-bottom: 1px solid var(--line); }
        .stage:last-child { border-bottom: 0; }
        .stage .head {
            display: grid; grid-template-columns: 26px 20px 1fr; gap: 8px;
            padding: 9px 14px; cursor: pointer; align-items: start;
        }
        .stage .head:hover { background: #fafbfc; }
        .stage .ord { color: var(--idle); font-size: 11px; font-variant-numeric: tabular-nums; padding-top: 2px; }
        .stage .mark { font-weight: 700; line-height: 1.3; }
        .stage .layer { font-weight: 600; font-size: 13px; }
        .stage .sum { color: var(--muted); font-size: 12px; }
        .stage.ran .mark { color: var(--ran); }
        .stage.pending .mark { color: var(--pending); }
        .stage.blocked .mark { color: var(--blocked); }
        .stage.skipped .mark { color: var(--skipped); }
        .stage.not_reached .mark { color: var(--idle); }
        .stage.not_reached .layer { color: #98a0aa; font-weight: 500; }
        .stage .body { display: none; padding: 0 14px 12px 48px; font-size: 12px; }
        .stage.open .body { display: block; }
        .stage .body dt { color: var(--muted); text-transform: uppercase; font-size: 10px; letter-spacing: .05em; margin-top: 8px; }
        .stage .body dd { margin: 2px 0 0; }
        code, pre {
            font-family: ui-monospace, Menlo, Consolas, monospace;
            background: var(--code-bg); border-radius: 4px;
        }
        code { padding: 1px 5px; font-size: 11px; }
        pre { padding: 8px 10px; overflow-x: auto; max-height: 260px; font-size: 11px; margin: 4px 0 0; }
        .legend { display: flex; gap: 12px; font-size: 11px; color: var(--muted); flex-wrap: wrap; }
        .legend b { font-weight: 700; }
        .err { color: var(--blocked); padding: 10px 14px; }
        .thinking { color: var(--muted); font-style: italic; padding: 4px 0; }
    </style>
</head>
<body>
<header>
    <h1>AI Journey — Student Profiles</h1>
    <div class="scope">
        {{ $schoolName ?: 'Institute' }} #<b>{{ $instituteId }}</b>
        · year <b>{{ $academicYear ?: '—' }}</b>
        · term <b>{{ $termId ?: '—' }}</b>
        · role <b>{{ $role }}</b>
    </div>
    <div class="scope" style="margin-left:auto">
        Conversation <b id="convref">—</b>
    </div>
</header>

<div class="layout">
    <div class="panel">
        <h2>
            <span>Conversation</span>
            <span class="legend"><span id="timing"></span></span>
        </h2>
        <div id="thread">
            <div class="msg ai">
                <div class="headline">Ask about academic risk in this school.</div>
                <div class="section">
                    <div class="stitle">Start with one of these</div>
                    <div class="chips" id="starters">
                        <span class="chip">Which students are at academic risk?</span>
                        <span class="chip">What has the system learned?</span>
                    </div>
                </div>
                <div class="section">
                    <div class="stitle">What the panel on the right shows</div>
                    <div>Every question is answered by the same fifteen-stage pipeline. Each stage reports
                        whether it ran, what it did, which class did it, which rows it wrote, and the exact
                        call you can make to check it yourself. Stages that did not run say why.</div>
                </div>
            </div>
        </div>
        <form id="composer" onsubmit="return false;">
            <input id="q" placeholder="Ask a question…" autocomplete="off">
            <button class="primary" id="send">Ask</button>
        </form>
    </div>

    <div class="panel">
        <h2>
            <span>Pipeline trace</span>
            <span class="legend">
                <span><b style="color:var(--ran)">OK</b> ran</span>
                <span><b style="color:var(--pending)">..</b> waiting</span>
                <span><b style="color:var(--blocked)">XX</b> refused</span>
                <span><b style="color:var(--skipped)">--</b> skipped</span>
                <span><b style="color:var(--idle)">·</b> not reached</span>
            </span>
        </h2>
        <div id="trace">
            <div class="thinking" style="padding:14px">No question asked yet.</div>
        </div>
    </div>
</div>

<script>
(function () {
    const BASE = @json($aiBase);
    const TOKEN = @json($aiToken);
    const MARKS = { ran: 'OK', pending: '..', blocked: 'XX', skipped: '--', not_reached: '·' };

    let conversationId = null;
    let busy = false;

    const thread = document.getElementById('thread');
    const traceEl = document.getElementById('trace');
    const input = document.getElementById('q');

    const esc = (s) => String(s == null ? '' : s)
        .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');

    function el(html) {
        const d = document.createElement('div');
        d.innerHTML = html.trim();
        return d.firstElementChild;
    }

    function scroll() { thread.scrollTop = thread.scrollHeight; }

    // ---- asking -----------------------------------------------------------
    async function ask(question, payload) {
        if (busy || !question.trim()) return;
        busy = true;
        document.getElementById('send').disabled = true;

        thread.appendChild(el('<div class="msg user"><span class="bubble">' + esc(question) + '</span></div>'));
        const waiting = el('<div class="msg ai thinking">Running the pipeline…</div>');
        thread.appendChild(waiting);
        scroll();

        try {
            const res = await fetch(BASE + '/ask', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'Authorization': 'Bearer ' + TOKEN
                },
                body: JSON.stringify({
                    question: question,
                    conversation_id: conversationId,
                    payload: payload || {}
                })
            });

            const body = await res.json();
            waiting.remove();

            if (!body.success || !body.data) {
                thread.appendChild(el('<div class="msg ai err">' + esc(body.message || 'Request failed.') + '</div>'));
                scroll();
                return;
            }

            const data = body.data;
            conversationId = data.conversation.id || conversationId;
            document.getElementById('convref').textContent = data.conversation.reference || '—';
            document.getElementById('timing').textContent =
                'intent: ' + data.intent.key + ' (' + Math.round(data.intent.confidence * 100) + '%) · ' + data.duration_ms + 'ms';

            thread.appendChild(renderAnswer(data));
            renderTrace(data.trace);
            scroll();
        } catch (e) {
            waiting.remove();
            thread.appendChild(el('<div class="msg ai err">' + esc(e.message) + '</div>'));
        } finally {
            busy = false;
            document.getElementById('send').disabled = false;
        }
    }

    // ---- the answer -------------------------------------------------------
    function renderAnswer(data) {
        const a = data.answer;
        const wrap = document.createElement('div');
        wrap.className = 'msg ai';
        wrap.appendChild(el('<div class="headline">' + esc(a.headline) + '</div>'));

        (a.sections || []).forEach(s => {
            const sec = document.createElement('div');
            sec.className = 'section';
            sec.appendChild(el('<div class="stitle">' + esc(s.title) + '</div>'));
            sec.appendChild(renderSection(s));
            wrap.appendChild(sec);
        });

        if ((a.actions || []).length) {
            const bar = document.createElement('div');
            bar.className = 'actions';
            a.actions.forEach(act => {
                const b = document.createElement('button');
                b.className = act.style === 'primary' ? 'primary' : (act.style === 'danger' ? 'danger' : '');
                b.textContent = act.label;
                b.onclick = () => ask(act.utterance, act.payload);
                bar.appendChild(b);
            });
            wrap.appendChild(bar);
        }

        if ((a.follow_ups || []).length) {
            const chips = document.createElement('div');
            chips.className = 'chips';
            a.follow_ups.forEach(f => {
                const c = document.createElement('span');
                c.className = 'chip';
                c.textContent = f;
                c.onclick = () => ask(f, {});
                chips.appendChild(c);
            });
            wrap.appendChild(chips);
        }

        return wrap;
    }

    function renderSection(s) {
        const box = document.createElement('div');

        if (s.type === 'text') {
            box.innerHTML = '<div>' + esc(s.body) + '</div>';
        } else if (s.type === 'records') {
            box.innerHTML = (s.items || []).map(i =>
                '<div class="rec">' +
                    '<div class="rtitle">' + esc(i.title) +
                        (i.badge ? '<span class="badge ' + esc(i.badge_tone || '') + '">' + esc(i.badge) + '</span>' : '') +
                    '</div>' +
                    (i.lines || []).map(l => '<div class="rline">' + esc(l) + '</div>').join('') +
                    (i.meta && Object.keys(i.meta).length
                        ? '<div class="rmeta">' + Object.entries(i.meta).map(([k, v]) => esc(k) + ': ' + esc(v)).join(' · ') + '</div>'
                        : '') +
                '</div>'
            ).join('');
        } else if (s.type === 'key_values') {
            box.innerHTML = '<table class="kv">' + (s.items || []).map(i =>
                '<tr><td>' + esc(i.label) + '</td><td>' + esc(i.value) + '</td></tr>'
            ).join('') + '</table>';
        } else if (s.type === 'evidence') {
            box.innerHTML = (s.items || []).map(i =>
                '<div class="ev">' +
                    '<div>' + esc(i.summary) + (i.value ? ' <b>' + esc(i.value) + '</b>' : '') +
                        ' <span class="vtag' + (i.is_generated ? ' gen' : '') + '">' +
                        (i.is_generated ? 'generated' : (i.verified ? 'verified' : 'unverified')) + '</span></div>' +
                    '<div class="src">#' + esc(i.id) + ' · ' + esc(i.kind) + ' · ' + esc(i.source) +
                        (i.observed_at ? ' · ' + esc(i.observed_at) : '') + '</div>' +
                '</div>'
            ).join('');
        } else if (s.type === 'steps') {
            box.innerHTML = (s.items || []).map(i =>
                '<div class="stepline ' + esc(i.status) + '">' +
                    '<span class="dot">' + (i.status === 'completed' ? '✓'
                        : (i.status === 'rejected' || i.status === 'failed') ? '✗'
                        : i.is_current ? '→' : '·') + '</span>' +
                    '<span>' + esc(i.label) + ' <span class="skey">' + esc(i.key) + '</span></span>' +
                    '<span style="margin-left:auto;color:var(--muted)">' + esc(i.status) + '</span>' +
                '</div>'
            ).join('');
        } else if (s.type === 'comparison') {
            box.innerHTML = '<table class="cmp"><tr><th>Metric</th><th>Before</th><th>After</th><th>Change</th><th>Status</th></tr>' +
                (s.items || []).map(i =>
                    '<tr><td>' + esc(i.label) + '</td><td>' + esc(i.before ?? '—') + '</td><td>' +
                    esc(i.after ?? 'not yet measured') + '</td><td>' + esc(i.delta ?? '—') + '</td><td>' +
                    esc(i.status) + '</td></tr>'
                ).join('') + '</table>';
        }

        return box;
    }

    // ---- the trace --------------------------------------------------------
    function renderTrace(stages) {
        traceEl.innerHTML = '';

        stages.forEach(st => {
            const node = document.createElement('div');
            node.className = 'stage ' + st.status;

            const summary = st.summary || st.note || 'not reached in this turn';

            node.appendChild(el(
                '<div class="head">' +
                    '<span class="ord">' + st.order + '</span>' +
                    '<span class="mark">' + (MARKS[st.status] || '·') + '</span>' +
                    '<span><span class="layer">' + esc(st.layer) + '</span>' +
                        (st.duration_ms != null ? ' <span class="skey">' + st.duration_ms + 'ms</span>' : '') +
                        '<div class="sum">' + esc(summary) + '</div></span>' +
                '</div>'
            ));

            const body = document.createElement('div');
            body.className = 'body';
            let html = '<dl>';
            html += '<dt>Where it is implemented</dt><dd><code>' + esc(st.component) + '</code></dd>';
            html += '<dt>Where the user sees it</dt><dd>' + esc(st.surface) + '</dd>';

            if (st.records && st.records.table) {
                html += '<dt>Rows written or read</dt><dd><code>' + esc(st.records.table) + '</code>' +
                    (st.records.ids && st.records.ids.length
                        ? ' → id ' + st.records.ids.slice(0, 20).map(esc).join(', ')
                        : ' (no ids on this turn)') + '</dd>';
            }

            if (st.verify && (st.verify.api || st.verify.sql)) {
                html += '<dt>How to verify it yourself</dt><dd>';
                if (st.verify.api) html += '<pre>' + esc(st.verify.api) + '</pre>';
                if (st.verify.sql) html += '<pre>' + esc(st.verify.sql) + '</pre>';
                html += '</dd>';
            }

            if (st.data && Object.keys(st.data).length) {
                html += '<dt>What this stage produced</dt><dd><pre>' +
                    esc(JSON.stringify(st.data, null, 2)) + '</pre></dd>';
            }

            if (st.note && st.summary) {
                html += '<dt>Note</dt><dd>' + esc(st.note) + '</dd>';
            }

            html += '</dl>';
            body.innerHTML = html;
            node.appendChild(body);

            node.querySelector('.head').onclick = () => node.classList.toggle('open');
            traceEl.appendChild(node);
        });
    }

    // ---- wiring -----------------------------------------------------------
    document.getElementById('send').onclick = () => {
        const v = input.value;
        input.value = '';
        ask(v, {});
    };

    input.addEventListener('keydown', e => {
        if (e.key === 'Enter') {
            e.preventDefault();
            document.getElementById('send').click();
        }
    });

    document.querySelectorAll('#starters .chip').forEach(c => {
        c.onclick = () => ask(c.textContent, {});
    });
})();
</script>
</body>
</html>
