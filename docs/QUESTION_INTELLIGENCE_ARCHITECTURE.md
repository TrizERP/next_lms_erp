# Question Intelligence Architecture Verification

**Date:** 2026-09-07  
**Status:** Verification complete — team decision required

## Executive Summary

**There is NO single shared Question Intelligence Engine.** The codebase contains three distinct generations of question infrastructure layered on top of each other, all reading from the same base table (`lms_question_master`) but with separate intelligence overlays and duplicated logic.

## Current Architecture

### Generation 1: Legacy LMS (LIVE)

| Component | Tables | Controllers | Question Selection |
|-----------|--------|-------------|-------------------|
| Question Bank | `lms_question_master`, `answer_master`, `lms_question_mapping` | `questionmasterController`, `questionpaperController` | Score-band based (<40 easy / 40-70 medium / >=70 hard) |
| Examination | `question_paper`, `lms_online_exam`, `lms_online_exam_answer` | `onlineExamController`, `questionpaperController` | Paper assembly from question bank |
| Concept Intelligence | `lms_concept`, `lms_concept_mastery`, `lms_concept_mastery_log` | `questionmasterController` (mastery matrix) | Manual calculation, no per-concept mastery |

### Generation 2: PAL V4 Intelligence (ARCHITECTED BUT UNFED)

| Component | Tables | Controllers | Status |
|-----------|--------|-------------|--------|
| Learner State | `pal_competencies`, `pal_learning_sessions`, `pal_session_events` | Various PAL services | **No confirmed writer in production** |
| Intelligence Engines | Various PAL tables | `LearnerStateEngine`, `PredictiveInterventionEngine`, `LearningVelocityEngine`, `MisconceptionIntelligenceEngine`, `RecommendationEngine` | **Reads tables that nothing writes to** |

### Generation 3: PAL Content Intelligence (ACTIVE)

| Component | Tables | Controllers | Status |
|-----------|--------|-------------|--------|
| Question Sidecar | `pal_question_metadata` | `ContentMetadataService`, `PalContentIntelligenceController` | 50/220 questions tagged |
| Concept Overlay | `pal_concept_metadata`, `pal_concept_relations`, `pal_concept_nodes` | `ConceptTagger`, `CoherenceMap` | Active |
| Misconception Library | `pal_misconception_library`, `pal_misconception_corrective` | `MisconceptionIntelligenceEngine` | Active |
| BKT Engine | In-memory (no persistent table) | `BktEngine` | Used by `palController` and `assessmentQuestionController` |

### Shared Schema

| Layer | Table | Role |
|-------|-------|------|
| Base | `lms_question_master` | All three generations read from this |
| Overlay | `pal_question_metadata` | Bloom level, practice level, difficulty, misconception tags, quality_status, node_id, item_type |
| Overlay | `pal_content_metadata` | Content intelligence sidecar over `content_master` |
| Overlay | `lms_concept` + `pal_concept_metadata` | Concept catalogue + mastery gate |

## Dependency Graph

```
lms_question_master (base)
├── questionmasterController (Legacy CRUD)
├── questionpaperController (Paper assembly)
├── onlineExamController (Exam submission)
├── ContentMetadataService (PAL V4 write gateway)
├── ConceptTagger (Auto-tagging)
├── BktEngine (Bayesian Knowledge Tracing)
├── MisconceptionIntelligenceEngine (Wrong-answer analysis)
└── pal_question_metadata (sidecar)
    ├── PalContentIntelligenceController (API)
    ├── PalContentController (Blade)
    └── PalVocabulary (shared vocabulary)
```

## Key Architectural Gaps

1. **No shared Question Intelligence Engine exists** — Item 6 in the backlog is "Needs Team Decision"
2. **Question Bank + Concept Intelligence share the base table** (`lms_question_master`) but the intelligence overlay (`pal_question_metadata`) is a separate sidecar that only 50/220 questions have been tagged for
3. **Dual fitness-for-purpose metadata** is required — `pal_calibration` (for PAL) and `board_compliance` (for exams) are different criteria over the same question
4. **The `lmsexamController` is a stub** — the examination module has no real implementation yet
5. **PAL V4 engines are unfed** — the intelligence layer reads `pal_competencies`/`pal_learning_sessions` but nothing writes to them
6. **Legacy PAL quiz flow** (`palController`) still uses score-bands, not per-concept mastery — conflicts with V4's Bloom ladder
7. **The `palController` directly instantiates `onlineExamController`** — tight coupling between legacy PAL and exam submission

## Options for Team Decision

### Option A: Consolidate

**Establish one shared Question Intelligence Engine and migrate consumers to it.**

- Create a unified `QuestionIntelligenceEngine` service
- Migrate `questionmasterController`, `questionpaperController`, `onlineExamController`, `palController`, and all PAL services to use it
- Retire `pal_question_metadata` sidecar in favor of a unified schema
- **Impact:** High — requires rewriting all question consumers
- **Risk:** High — breaking changes to live exam and PAL flows
- **Benefit:** Single source of truth, no duplication

### Option B: Keep Separate

**Retain separate engines and document the boundary and reason.**

- Keep Legacy LMS, PAL V4 Intelligence, and PAL Content Intelligence as separate systems
- Document why each exists and which scenarios use which
- **Impact:** Low — no code changes required
- **Risk:** Low — no breaking changes
- **Benefit:** Preserves working systems, clear ownership

### Option C: Hybrid

**Share the schema and intelligence services while keeping product-specific workflows separate.**

- Unify the base schema (`lms_question_master` + `pal_question_metadata` → single question table with all metadata)
- Share `ContentMetadataService`, `BktEngine`, `ConceptTagger` across all consumers
- Keep product-specific workflows (exam paper assembly, PAL quiz flow, content authoring) separate
- **Impact:** Medium — requires schema migration and service refactoring
- **Risk:** Medium — careful migration needed to avoid breaking live flows
- **Benefit:** Shared intelligence layer, product-specific flexibility

## Recommendation

Given the current state:
1. Legacy LMS is live and serving students
2. PAL V4 Intelligence is largely unfed
3. PAL Content Intelligence is active but only covers 50/220 questions

**Recommended path: Option C (Hybrid)** with the following sequence:
1. First, feed PAL V4 engines with real data (write paths are missing)
2. Then, unify the metadata schema
3. Finally, share intelligence services while keeping workflows separate

This avoids the high risk of Option A while achieving the benefits of shared intelligence.

## Decision Gate

**Do not build the Lesson Kit or change assessment architecture until this decision is made.**

---
**END OF VERIFICATION**
