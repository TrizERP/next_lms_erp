# Plan: Simplified Question Generation Workflow

## Goal
Simplify question generation so user only selects:
1. Question Type (e.g., Narrative, MCQ)
2. Total Questions (e.g., 10)

System automatically:
- Detects available mappings from database
- Distributes questions using weighted difficulty (Easy 30%, Medium 40%, Hard 30%)
- Auto-calculates marks based on mapping type & value

---

## Database Structure

### lms_mapping_type Table
- `id` - Primary key
- `name` - Mapping name (e.g., "Bloom's Taxonomy")
- `parent_id` - 0 for parent types, >0 for values
- `globally` - Global (1) or chapter-specific
- `chapter_id` - Chapter-specific mapping

### question_type_master Table
- `id` - Primary key
- `question_type` - Type name (MCQ, Narrative, etc.)

---

## Implementation Plan

### 1. Controller Updates (assessmentQuestionController.php)

#### Add Methods:
1. `getAvailableMappings()` - Auto-detect from database
2. `distributeQuestionsByDifficulty()` - Weighted distribution
3. `calculateMarks()` - Auto-calculate marks

#### Update `chat()` Method:
- Accept: question_type_id, total_questions
- Auto-detect mappings → Apply distribution → Calculate marks → Generate prompt

### 2. UI Updates (assessment_preview.blade.php)

#### Simplified Form:
- Remove manual mapping rows
- Add single "Total Questions" input
- Display auto-calculated distribution preview

### 3. Distribution Algorithm

| Difficulty | % | Bloom's Taxonomy | Marks |
|------------|---|------------------|-------|
| Easy | 30% | Remember, Understand | 1 |
| Medium | 40% | Apply, Analyze | 2 |
| Hard | 30% | Evaluate, Create | 3 |

### Example: 10 Questions
- Easy: 3 questions (2 Remember + 1 Understand) = 3 marks
- Medium: 4 questions (2 Apply + 2 Analyze) = 8 marks
- Hard: 3 questions (1 Evaluate + 2 Create) = 9 marks
- **Total**: 20 marks

---

## Files to Modify
1. `app/Http/Controllers/lms/assessmentQuestionController.php`
2. `resources/views/lms/assessment_preview.blade.php`

---

## Data Flow
```mermaid
graph TD
    A[User: Question Type + Total] --> B[Controller]
    B --> C[Auto-detect Mappings]
    C --> D[Calculate Distribution]
    D --> E[Calculate Marks]
    E --> F[Generate AI Prompt]
    F --> G[AI Service]
    G --> H[Save with Mappings]