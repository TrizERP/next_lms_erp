# Current LMS Navigation Baseline (Pre-Demo Freeze)

**Captured:** 2026-09-07  
**Purpose:** Record current navigation state before demo week per Action Plan Section 1.1

## G2G LMS Module (from 2026_09_05_210000_add_g2g_lms_menu.php)

### Level-2 Menu
- **Name:** LMS
- **Link:** g2g_lms.index
- **Parent:** People & Competency (level-1)
- **Icon:** mdi mdi-school-outline
- **Menu Type:** ENTRY
- **Menu Path:** LMS

### Level-3 Screens (nested under LMS)

| Sort | Name | Link | Description |
|------|------|------|-------------|
| 1 | Learning Dashboard | g2g_lms.learning_dashboard | LMS: learning overview, progress and highlights |
| 2 | Learning Catalog | g2g_lms.learning_catalog | LMS: browse and enrol into courses |
| 3 | My Learning | g2g_lms.my_learning | LMS: a learner's own enrolled and completed courses |
| 4 | Assignments | g2g_lms.assignments | LMS: assignments, submissions and grading |
| 5 | Sessions & Calendar | g2g_lms.sessions_calendar | LMS: scheduled sessions and the learning calendar |
| 6 | Certifications & Records | g2g_lms.certifications_records | LMS: certificates earned and learning records |
| 7 | Course Builder | g2g_lms.course_builder | LMS: author and manage course content |
| 8 | Administration & Governance | g2g_lms.administration_governance | LMS: module administration, policies and governance |
| 9 | Assessments | g2g_lms.assessments | LMS: quizzes, tests and assessment results |

## AI Workspace Module (from 2026_08_21_000001_seed_ai_module_coverage.php)

| Module Key | Label | Description | Route Patterns | Icon |
|------------|-------|-------------|----------------|------|
| lms | Learning | Lessons, homework and learning activity. | ['/lms', '/lms/**'] | graduation-cap |
| pal | Personalised learning | Adaptive learning paths and learner state. | ['/pal', '/pal/**', '/new-pal', '/new-pal/**'] | brain |
| subjects | Subjects | Subject catalogue and allocation. | ['/subjects', '/subjects/**'] | book |
| chapters | Chapters | Chapter and topic structure. | ['/chapters', '/chapters/**'] | book-open |
| course-master | Courses | Course master and lesson plans. | ['/course-master', '/course-master/**'] | library |
| learning-outcome | Learning outcomes | Outcome definitions and mapping. | ['/learning-outcome', '/learning-outcome/**'] | target |
| quiz | Quizzes | Quiz banks and attempts. | ['/quiz', '/quiz/**'] | help-circle |
| h5p | Interactive content | H5P interactive learning content. | ['/h5p', '/h5p/**'] | play-circle |

## Teacher Resource Module

### Route
- `lms_teacherResource.index` → `lms/teacher_resource/show_teacher_resource`
- `lms_teacherResource.create` → `lms/teacher_resource/edit`
- `lms_teacherResource.store` → Teacher Resource Added Successfully
- `lms_teacherResource.update` → Teacher Resource Updated Successfully
- `lms_teacherResource.destroy` → Teacher Resource Deleted Successfully

### Blade Views
- `resources/views/lms/teacher_resource/show_teacher_resource.blade.php` - "Add Teacher Resource", "Teacher Resource" breadcrumb
- `resources/views/lms/teacher_resource/edit.blade.php` - "Edit Teacher Resource", "Teacher Resource" breadcrumb
- `resources/views/lms/show_chapter.blade.php` - "Teacher Resource" button/link
- `resources/views/lms/show_topic.blade.php` - "Teacher Resource" button

## H5P / Interactive Content Module

### AI Module Label
- **Module Key:** h5p
- **Label:** Interactive content
- **Description:** H5P interactive learning content.
- **Route Patterns:** ['/h5p', '/h5p/**']
- **Icon:** play-circle

### Routes (from routes/lms.php)
- `h5p` → `H5PController` (resource)
- `h5p/html_contents` → `H5PIndexController`
- `h5p/scenario_based` → `H5PScenarioController`
- `h5p/h5p_mcq` → `H5PMCQController`
- `h5p/h5p_interactive_video` → `H5PInteractiveVideoController`
- `h5p/h5p_flashacard` → `H5pFlashcardController`

## PAL Content Intelligence Module (from 2026_08_14_140000_add_new_pal_submodule_menu.php)

- **New PAL** (level-2 under LMS/Homework)
  - **Unified Learning Units** (level-3) → `new_pal.ulu`
  - **Pedagogy Engine** (level-3) → `new_pal.pedagogy_engine`

## Content Governance Workflow (PAL Content Model)

### Quality Status States (from config/pal_content.php)
| Status | Stage | Human-Only | Servable |
|--------|-------|------------|----------|
| draft | 1 | No | No |
| reviewed | 2 | Yes | No |
| pedagogy_reviewed | 3 | Yes | No |
| piloted | 4 | No | No |
| approved | 5 | Yes | **Yes** |
| deprecated | 6 | Yes | No |

### Legal Transitions
```
draft → reviewed, deprecated
reviewed → pedagogy_reviewed, draft, deprecated
pedagogy_reviewed → piloted, approved, reviewed, deprecated
piloted → approved, pedagogy_reviewed, deprecated
approved → deprecated, reviewed
deprecated → draft
```

### Tagged By Values
- human
- ai
- imported
- derived

## Enrichment Naming Collision

### Meaning A: Per-Concept Extension Activity (Code - DOMINANT)
- `app/Services/PAL/ContentModel/ContentModelEnrichmentService.php` - class `ContentModelEnrichmentService`
- `app/Services/PAL/Pedagogy/PedagogySuggestedContentService.php:881` - `'title' => 'Ready for Enrichment'`
- `app/Services/ESO/EsoEnrichmentResolver.php:38` - class `EsoEnrichmentResolver`
- `resources/views/lms/pal/show.blade.php:1245` - `<h5>Enrichment Content</h5>`
- `resources/views/lms/pal/show.blade.php:1466` - `'enrichment': '🚀 Enrichment'`

### Meaning B: Subject-Category Tier (Documentation Only)
- `docs/LMS_CONTENT_ARCHITECTURE_BACKLOG.md` - References collision but no code implementation

## Student Resources
**Status:** No production code uses "Student Resources" or "Student Resource" as a label. Only referenced in backlog doc as terminology to correct.

## Question Intelligence Architecture

### Current State: MULTIPLE separate engines sharing one base table (`lms_question_master`)

| Generation | Tables | Controllers | Status |
|------------|--------|-------------|--------|
| Legacy LMS | lms_question_master, answer_master, question_paper, lms_online_exam | questionmasterController, questionpaperController, onlineExamController, lmsexamController (stub) | LIVE |
| PAL V4 Intelligence | pal_competencies, pal_learning_sessions, pal_session_events | PalContentIntelligenceController, various PAL services | ARCHITECTED BUT UNFED |
| PAL Content Intelligence | pal_question_metadata, pal_content_metadata, pal_concept_metadata, pal_concept_relations, pal_misconception_library | ContentMetadataService, ConceptTagger, PalContentIntelligenceController | ACTIVE (50/220 questions tagged) |

### Shared Schema
- Base: `lms_question_master` (all three generations read from it)
- Overlay: `pal_question_metadata` sidecar with concept_ref_id, node_id, item_type, bloom_level, practice_level, difficulty_1_to_5, misconception_tags, quality_status

---
**END OF BASELINE - Do not modify live navigation during demo week per Section 1.1**