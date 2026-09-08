# Content Governance Workflow Registration

**Registered:** 2026-09-07  
**Source:** Action Plan Section 4.1, existing PAL Content Model state machine

## Existing Workflow (Canonical)

The system already enforces a 6-stage quality pipeline through `ContentMetadataService` and `PalVocabulary`. This is the canonical content governance flow.

### States

| Stage | Status | Human-Only | Servable to Learners | Description |
|-------|--------|------------|---------------------|-------------|
| 1 | `draft` | No | No | Auto-generated or initial authoring state |
| 2 | `reviewed` | Yes | No | Reviewed by teacher/author |
| 3 | `pedagogy_reviewed` | Yes | No | Pedagogy/school review completed |
| 4 | `piloted` | No | No | Piloted/tested in classroom |
| 5 | `approved` | Yes | **Yes** | Approved and ready for learners |
| 6 | `deprecated` | Yes | No | Retired from service |

### Legal Transitions

```
draft → reviewed, deprecated
reviewed → pedagogy_reviewed, draft, deprecated
pedagogy_reviewed → piloted, approved, reviewed, deprecated
piloted → approved, pedagogy_reviewed, deprecated
approved → deprecated, reviewed
deprecated → draft
```

### Actor Permissions

| Transition | Who Can Perform | Notes |
|------------|----------------|-------|
| Any → `draft` | Human (author/teacher) | Reset to draft |
| `draft` → `reviewed` | Human (teacher) | Teacher preview |
| `reviewed` → `pedagogy_reviewed` | Human (pedagogy lead/school) | School review |
| `pedagogy_reviewed` → `approved` | Human (master reviewer) | School master review |
| `pedagogy_reviewed` → `piloted` | Machine or Human | Pilot launch |
| `piloted` → `approved` | Human (master reviewer) | Final approval after pilot |
| Any → `deprecated` | Human (master reviewer) | Retire content |
| Machine → any | `draft` or `piloted` only | CONTENT LAW C5 |

### Content Visibility by State

| State | Visible to Teachers | Visible to Students | Visible to School Admin |
|-------|-------------------|---------------------|------------------------|
| `draft` | Yes (author only) | No | No |
| `reviewed` | Yes (reviewers) | No | Yes (review queue) |
| `pedagogy_reviewed` | Yes (pedagogy team) | No | Yes |
| `piloted` | Yes (pilot classes) | Yes (pilot classes) | Yes |
| `approved` | Yes | **Yes** | Yes |
| `deprecated` | Yes (audit only) | No | Yes (audit only) |

### Audit Information

Every state change is recorded with:
- Actor ID (`reviewed_by`)
- Actor name/role
- Timestamp (`reviewed_at`, `last_reviewed`)
- Previous state
- Next state
- Change payload

Enforced by `ContentMetadataService::upsert()` and `ContentMetadataService::transition()`.

## Mapping to Action Plan Target Flow

The action plan describes a 5-stage target flow:
`Draft → Teacher Preview → Publish → School Review → School Master Review`

### Mapping Table

| Target Flow Stage | Existing State | Mapping Notes |
|-------------------|----------------|---------------|
| Draft | `draft` | Direct match |
| Teacher Preview | `reviewed` | Teacher reviews content before school review |
| Publish | `piloted` | Content is published/piloted for classroom use |
| School Review | `pedagogy_reviewed` | Pedagogy/school review |
| School Master Review | `approved` | Final approval; content becomes servable |

### Discrepancies

1. The target flow omits `deprecated` — this is a terminal state in the existing workflow and should be retained for content retirement.
2. The target flow order differs slightly from the existing workflow's happy path:
   - Target: Draft → Teacher Preview → Publish → School Review → School Master Review
   - Existing: Draft → Reviewed → Pedagogy Reviewed → Piloted → Approved
3. The existing workflow allows `approved` → `reviewed` (re-review after approval), which the target flow does not explicitly call out.

### Recommendation

Retain the existing 6-stage workflow as the canonical governance flow. The 5-stage target flow is a simplified user-facing view of the same pipeline. Do not build a bespoke parallel approval mechanism.

---
**END OF WORKFLOW REGISTRATION**
