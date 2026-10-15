# Live Demo Script — Knowledge Graph (KG) vs Context Graph (CG), K-12

**Purpose:** prove to a reviewer, live, that KG and CG are real and running in production code — not
slides. Everything below runs against the real dev database using real data. Takes about 5 minutes.

**The one-sentence framing to open with:**
> "The Knowledge Graph is the curriculum's prerequisite structure, sitting in Neo4j. The Context Graph
> is the logic that reads that structure for one specific student, right now, and leaves behind a record
> of what it used and why. I'll show you both, live."

**Real data used** (confirmed present on this dev database, not fabricated):
- Student id `97926`, sub-institute `1`
- Chapter 1012 (Chemistry — chemical reactions), standard `43`, subject `3975`
- Concept `2733` — "Combination reaction" — this student has attempted it 3 times and scored 0%

---

## Part 1 — Show the Knowledge Graph (the "what's connected to what")

Open a terminal in `next_lms_erp` and run:

```bash
php artisan tinker --execute="
\$neo4j = app(\App\Services\Neo4jService::class);
\$result = \$neo4j->run(
    'MATCH (c:Concept {conceptId: 2733})
     OPTIONAL MATCH p = (c)-[:REQUIRES*1..6]->(blocker:Concept)
     RETURN c.name AS concept, [n IN nodes(p) | n.name] AS chain
     LIMIT 10'
);
foreach (\$result as \$r) {
    \$chain = \$r->get('chain');
    \$names = is_object(\$chain) && method_exists(\$chain, 'toArray') ? \$chain->toArray() : (array) \$chain;
    echo \$r->get('concept') . ' requires: ' . implode(' -> ', \$names) . PHP_EOL;
}
"
```

Tested, real output (confirmed before writing this script — expect similar, up to 7 hops deep):
```
Combination reaction requires: Combination reaction -> Hit-and-trial balancing method -> Balancing
starts with maximum atoms -> Skeletal chemical equations -> Chemical equations using formulae ->
Chemical formula uses symbols and valencies -> Valency as electrons lost/gained/shared
```

**What to say while it runs:** *"This is pulling directly from Neo4j — the Knowledge Graph. It's not
asking 'what did this student get wrong', it's asking 'what does this concept structurally depend on,
and how many steps deep.' That relationship exists whether or not any student has ever touched it — it's
curriculum structure, not a student's history. Seven concepts deep, for one chapter — this is real
curriculum data, not a toy example."*

If you have Neo4j Browser access, the more visual version of the same thing:

```cypher
MATCH (c:Concept {conceptId: 2733})-[:REQUIRES*1..6]->(blocker:Concept)
RETURN c, blocker
```

— this renders as an actual graph diagram on screen, which lands better live than JSON.

---

## Part 2 — Show the Context Graph assembling evidence for ONE student, right now

Same terminal:

```bash
php artisan tinker --execute="
\$repo = app(\App\Services\PAL\Coherence\CoherenceMapRepository::class);
\$blockers = \$repo->rootBlockers(2733, 97926);
echo json_encode(\$blockers, JSON_PRETTY_PRINT) . PHP_EOL;
"
```

Expected output (real, already confirmed):
```json
[
    { "id": 2725, "name": "Reactants and products", "mastery": 0, "gate": 0.92, "depth": 5 },
    { "id": 2728, "name": "Law of conservation of mass", "mastery": 0, "gate": 0.94, "depth": 3 }
]
```

**What to say:** *"Same graph, but now it's asking a context-specific question: 'for THIS student,
which of this concept's prerequisites are they actually below the mastery gate on, and how far back does
the problem go?' Depth 5 means this student's real gap isn't 'Combination reactions' at all — it's
something five steps upstream they never mastered. That's the difference between KG and CG: the graph
doesn't change, but what you pull from it and why is specific to this moment, this student, this
decision."*

Now run the actual production method — the exact one the live app calls:

```bash
php artisan tinker --execute="
\$service = app(\App\Services\PAL\Integration\PedagogySuggestedContentService::class);
\$result = \$service->getSuggestions([
    'learner_id' => 97926,
    'standard_id' => 43,
    'subject_id' => 3975,
    'chapter_id' => 1012,
    'sub_institute_id' => 1,
]);
echo 'message: ' . \$result['message'] . PHP_EOL;
echo 'weak concepts found: ' . count(\$result['weak_concepts']) . PHP_EOL;
echo 'example root_cause for Combination reaction: ' . json_encode(
    collect(\$result['weak_concepts'])->firstWhere('concept_id', 2733)['root_cause']
) . PHP_EOL;
"
```

**What to say:** *"This is not a demo-only function — this is `PedagogySuggestedContentService::getSuggestions()`,
called from `app/Http/Controllers/lms/pal/palController.php::getPedagogySuggestedContent()`, which is
the exact backend route the Suggested Content screen in the student app calls. I'm running the real
production code against a real student."*

---

## Part 3 — Show the Context Graph leaving a record behind

This is the part that didn't exist before this work — the suggestion above now gets recorded, not just
computed and forgotten.

```bash
php artisan tinker --execute="
echo 'Rows before:' . DB::table('ai_audit_logs')->where('subject_id', 97926)->count() . PHP_EOL;
"
```

Then re-run the exact `getSuggestions()` call from Part 2 (it records on every call), and immediately:

```bash
php artisan tinker --execute="
\$row = DB::table('ai_audit_logs')->where('subject_id', 97926)->orderByDesc('id')->first();
echo 'event_type: ' . \$row->event_type . PHP_EOL;
echo 'message: ' . \$row->message . PHP_EOL;
echo 'knowledge_graph_used: ' . var_export((bool) \$row->knowledge_graph_used, true) . PHP_EOL;
echo 'payload: ' . \$row->payload . PHP_EOL;
"
```

**What to say:** *"That row didn't exist before this suggestion was computed. It's a permanent, queryable
record: which concepts were weak, which prerequisite ids the graph identified as the real cause, with a
flag confirming the graph was actually used — not copying the student's data, just the ids. Six months
from now, someone can answer 'why did the system suggest this to this student' by reading this row,
without re-running anything."*

**Optional — the "would this scale" question, answered honestly:** *"This exact pattern — assemble from
the graph, let existing logic decide, record the decision — is already how G2G's recommendation approvals
and Enterprise Brain's signal explanations work. I didn't invent it for K-12; I found it missing here and
added it using the same existing `AiAuditLogger` mechanism everything else already uses."*

---

## If your senior asks "why isn't this just in the UI already?"

Honest answer: it is, as of this work — reload the actual Suggested Content screen for this student/chapter
in the K-12 app and the same `ai_audit_logs` row gets written on every real page load, not just this
tinker script. The script exists so you can show it without needing a logged-in student session open.

## If your senior asks "what stops this leaking another student's data?"

Run this live too — it's the same safety property verified for Enterprise Brain earlier in this work:

```bash
php artisan tinker --execute="
\$repo = app(\App\Services\PAL\Coherence\CoherenceMapRepository::class);
// Student 97926, standard 43, subject 3975 — real sub_institute_id is 1.
echo 'correct tenant, concepts returned: ' . count(\$repo->readiness(43, 3975, 97926, 1)) . PHP_EOL;
echo 'wrong tenant, concepts returned: ' . count(\$repo->readiness(43, 3975, 97926, 99999999)) . PHP_EOL;
"
```

Expected: the correct tenant returns ~300 real concept-mastery entries; the wrong tenant returns `0` —
confirmed, verified before writing this script. `CoherenceMapRepository`'s own class docblock documents
why this check exists: an earlier version trusted `(standard_id, subject_id)` alone to imply one
institute, which the authors themselves flagged as "how a cross-tenant leak gets shipped." This is that
fix, live.

---

## Cleanup

Everything above is read-only except the `ai_audit_logs` rows the demo creates for student `97926`.
These are harmless (append-only audit rows, same shape real traffic creates) but if you want to remove
them after the meeting:

```bash
php artisan tinker --execute="
DB::table('ai_audit_logs')->where('subject_id', 97926)->where('related_type', 'pal_suggested_content')->delete();
"
```
