# Future Modules: Scope and Boundaries

**Date:** 2026-09-07  
**Status:** Deferred — scope exercises required before implementation

## 6.1 Lesson Kit

**Status:** Deferred until Learning Experience split and Question Intelligence decision are complete.

### Current State
- `lms_lesson_plan` table exists with basic lesson planning functionality
- `lms_lesson_plan_periods` and `lms_lesson_plan_concepts` exist for scheduling
- `lms_intelligence_lesson_plans` exists for AI-generated variants
- No dedicated "Lesson Kit" module exists

### When Approved, Define the Kit Around:
1. **Before-class preparation materials** — resources teachers need before the lesson
2. **During-class activities and teacher guidance** — interactive elements and facilitation notes
3. **After-class practice, reinforcement, and evidence of learning** — homework and assessment
4. **Links to Learning Experience resources** — connections to the broader learning ecosystem
5. **Links to the shared Question Intelligence Engine** — if approved in Section 5
6. **A clear distinction between a reusable kit and a single lesson plan** — kit = reusable components; plan = dated instance

### Dependencies
- Learning Experience split (Section 2) must be complete
- Question Intelligence architecture decision (Section 5) must be made
- Content Learning Resource Metadata model (Section 2.4) must be in place

### Done When
- Module has approved scope, owner, data model, and integration points

---

## 6.2 Academic Operations

**Status:** Independent use ready — keep as separate top-level area.

### Current State
- No "Academic Operations" module exists in the codebase
- Related functionality is scattered across:
  - `school_setup` (academic setup, standards, divisions, terms, calendar)
  - `timetable` (class and teacher timetables)
  - `lessonplan` (lesson planning)
  - `assignment` (assignments and submissions)

### Scope Definition
When implemented, Academic Operations should include:

1. **Calendars and scheduling** — academic year calendar, term dates, holidays
2. **Lesson and teaching management** — lesson planning, resource allocation, teacher assignments
3. **Assignments and submissions** — creation, distribution, collection, grading
4. **Related operational workflows** — proxy allocation, attendance, class management

### Boundaries
- **Keep operational workflows separate from content authoring and learning resources**
- Academic Operations = how teaching is organized and managed
- Learning Experience = what learners experience and consume
- Content Management = how content is created, reviewed, and governed

### Navigation Placement
- Align with ScholarClone (existing HRMS/People & Competency structure)
- Top-level area, distinct from LMS/Learning Experience

### Done When
- Users can locate operational work without confusing it with content or learning experiences

---

## 6.3 Examination

**Status:** Future module — run separate scope exercise.

### Current State
- `lmsexamController` — stub (empty CRUD methods)
- `onlineExamController` — live (exam attempt, submission, results)
- `questionpaperController` — live (paper creation, search, PDF)
- `assessmentQuestionController` — live (adaptive practice, BKT mastery)

### Examination Lifecycle (to be defined)
1. **Setup** — exam creation, question selection, scheduling
2. **Question selection** — from Question Intelligence Engine (if approved)
3. **Delivery** — online exam interface, timing, security
4. **Marking** — automatic and manual grading
5. **Moderation** — review and adjustment
6. **Results** — calculation, publishing, reports
7. **Reporting** — analytics, insights, comparisons

### Dependencies
- Question Intelligence architecture decision (Section 5) — Examination consumes but does not own the shared engine
- Must NOT be bundled into the Learning Experience terminology cleanup
- Must NOT be bundled into the content governance workflow

### Scope Exercise Required
Before implementation:
1. Define the examination module boundary
2. Identify the module owner
3. Map integration points with:
   - Question Intelligence Engine
   - Content Learning Resource Metadata
   - Learning Experience
   - Academic Operations
4. Define the examination data model
5. Specify user roles and permissions

### Done When
- Examination has approved module boundary, roadmap, owner, and dependency list

---
**END OF FUTURE MODULES**
