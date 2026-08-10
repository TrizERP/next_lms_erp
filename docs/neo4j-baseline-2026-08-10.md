# Neo4j Baseline — 2026-08-10 (pre-migration)

> **Phase 0 artifact.** Measured live against `bolt://dev.triz.co.in:7688`, database `neo4j`, on
> 2026-08-10. This is the restore reference if the rebuild is ever rolled back. Nothing in this file
> is estimated — every number is the output of a query run against the live instance.
>
> Platform, verified live this session: `Neo4j Kernel 4.4.40, edition: community`.
>
> Companion CSV backups of everything that is *not* re-derivable from MariaDB:
> [`neo4j-backup-2026-08-10/`](./neo4j-backup-2026-08-10/).

---

## A. Totals

```
nodes             : 261828
relationships     : 618991
labels            : 19
relationship types: 24
```

## B. Node counts by label

| Label | Nodes | With sub_institute_id | Orphaned |
|---|---:|---:|---:|
| Result | 143360 | 0 | 31781 |
| Question | 94052 | 94052 | 7653 |
| Student | 12801 | 11611 | 7208 |
| Chapter | 5536 | 5534 | 1760 |
| StuDetail | 4609 | 4608 | 389 |
| Lesson | 719 | 719 | 3 |
| Assessment | 166 | 166 | 7 |
| Subject | 150 | 150 | 0 |
| Teacher | 118 | 118 | 118 |
| Standard | 110 | 110 | 29 |
| ChapterStandardMap | 96 | 0 | 96 |
| CompetencyStandards | 40 | 0 | 40 |
| Curriculum | 31 | 31 | 0 |
| Concept | 11 | 11 | 0 |
| LearningContent | 11 | 11 | 10 |
| LearningObjects | 8 | 0 | 8 |
| Unit | 6 | 0 | 0 |
| AssessmentTypology | 3 | 0 | 3 |
| Misconception | 1 | 1 | 0 |

## C. Relationship counts by type

| Type | Count |
|---|---:|
| HAS_RESULT | 501034 |
| BELONGS_TO | 86265 |
| ATTENDED | 16350 |
| HAS_STUDENT | 5590 |
| ENROLLED_IN | 5472 |
| HAS_CHAPTER | 2016 |
| HAS_SUBJECT | 679 |
| HAS_LESSON | 557 |
| HAS_QUESTION | 545 |
| FOR_ASSESSMENT | 112 |
| HAS_ASSESSMENT | 105 |
| ASSESSES_CHAPTER | 90 |
| ATTEMPTED | 40 |
| BELONGS_TO_CURRICULUM | 31 |
| INCLUDES | 31 |
| PREREQUISITE_OF | 28 |
| MASTERS | 21 |
| COVERS | 11 |
| HAS_UNIT | 6 |
| HAS_MISCONCEPTION | 3 |
| ASSESSES | 2 |
| OCCURS_IN | 1 |
| REMEDIATES | 1 |
| TEACHES | 1 |

## D. Edge patterns (source label -> type -> target label)

```
Student                  -[HAS_RESULT            ]-> Result                   501034
Question                 -[BELONGS_TO            ]-> Chapter                  86265
Student                  -[ATTENDED              ]-> Lesson                   16350
StuDetail                -[HAS_STUDENT           ]-> Student                  5590
Student                  -[ENROLLED_IN           ]-> Standard                 5472
Subject                  -[HAS_CHAPTER           ]-> Chapter                  2001
Standard                 -[HAS_SUBJECT           ]-> Subject                  679
Chapter                  -[HAS_LESSON            ]-> Lesson                   557
Assessment               -[HAS_QUESTION          ]-> Question                 545
Result                   -[FOR_ASSESSMENT        ]-> Assessment               112
Subject                  -[HAS_ASSESSMENT        ]-> Assessment               105
Assessment               -[ASSESSES_CHAPTER      ]-> Chapter                  90
Student                  -[ATTEMPTED             ]-> Assessment               40
Curriculum               -[INCLUDES              ]-> Subject                  31
Subject                  -[BELONGS_TO_CURRICULUM ]-> Curriculum               31
Concept                  -[PREREQUISITE_OF       ]-> Concept                  28
Student                  -[MASTERS               ]-> Chapter                  18
Unit                     -[HAS_CHAPTER           ]-> Chapter                  15
Lesson                   -[COVERS                ]-> Concept                  11
Curriculum               -[HAS_UNIT              ]-> Unit                     6
Student                  -[MASTERS               ]-> Concept                  3
Student                  -[HAS_MISCONCEPTION     ]-> Misconception            3
Assessment               -[ASSESSES              ]-> Concept                  2
Misconception            -[OCCURS_IN             ]-> Concept                  1
LearningContent          -[TEACHES               ]-> Concept                  1
LearningContent          -[REMEDIATES            ]-> Misconception            1
```

## E. Constraints (SHOW CONSTRAINTS)

```
CompetencyStandards_competencystandardsId_unique UNIQUENESS   ["CompetencyStandards"] ["competencystandardsId"]
LearningObject_learningobjectId_unique UNIQUENESS   ["LearningObjects"]  ["learningobjectId"]
assessment_record_id_unique    UNIQUENESS   ["Assessment"]       ["record_id"]
assessmenttypology_assessmenttypologyId_unique UNIQUENESS   ["AssessmentTypology"] ["assessmenttypologyId"]
chapter_chId_unique            UNIQUENESS   ["Chapter"]          ["chId"]
chapter_record_id_unique       UNIQUENESS   ["Chapter"]          ["record_id"]
chapterstandardmap_chapterstandardmapId_unique UNIQUENESS   ["ChapterStandardMap"] ["chapterstandardmapId"]
concept_conceptId_unique       UNIQUENESS   ["Concept"]          ["conceptId"]
content_contentId_unique       UNIQUENESS   ["LearningContent"]  ["contentId"]
curriculum_curriculumId_unique UNIQUENESS   ["Curriculum"]       ["curriculumId"]
lesson_lessonId_unique         UNIQUENESS   ["Lesson"]           ["lessonId"]
misconception_misconceptionId_unique UNIQUENESS   ["Misconception"]    ["misconceptionId"]
question_qId_unique            UNIQUENESS   ["Question"]         ["qId"]
question_record_id_unique      UNIQUENESS   ["Question"]         ["record_id"]
result_record_id_unique        UNIQUENESS   ["Result"]           ["record_id"]
standard_record_id_unique      UNIQUENESS   ["Standard"]         ["record_id"]
standard_stId_unique           UNIQUENESS   ["Standard"]         ["stId"]
stu_details_sdId_unique        UNIQUENESS   ["StuDetail"]        ["sdId"]
student_record_id_unique       UNIQUENESS   ["Student"]          ["record_id"]
student_stuId_unique           UNIQUENESS   ["Student"]          ["stuId"]
subject_record_id_unique       UNIQUENESS   ["Subject"]          ["record_id"]
subject_subId_unique           UNIQUENESS   ["Subject"]          ["subId"]
teacher_teacherId_unique       UNIQUENESS   ["Teacher"]          ["teacherId"]
unit_unitId_unique             UNIQUENESS   ["Unit"]             ["unitId"]
-- total constraints: 24
```

## F. Indexes (SHOW INDEXES)

```
CompetencyStandards_competencystandardsId_unique BTREE      NODE         ["CompetencyStandards"] ["competencystandardsId"]
LearningObject_learningobjectId_unique BTREE      NODE         ["LearningObjects"]  ["learningobjectId"]
assessment_record_id_unique    BTREE      NODE         ["Assessment"]       ["record_id"]
assessmenttypology_assessmenttypologyId_unique BTREE      NODE         ["AssessmentTypology"] ["assessmenttypologyId"]
chapter_chId_unique            BTREE      NODE         ["Chapter"]          ["chId"]
chapter_record_id_unique       BTREE      NODE         ["Chapter"]          ["record_id"]
chapterstandardmap_chapterstandardmapId_unique BTREE      NODE         ["ChapterStandardMap"] ["chapterstandardmapId"]
concept_conceptId_unique       BTREE      NODE         ["Concept"]          ["conceptId"]
content_contentId_unique       BTREE      NODE         ["LearningContent"]  ["contentId"]
curriculum_curriculumId_unique BTREE      NODE         ["Curriculum"]       ["curriculumId"]
index_343aff4e                 LOOKUP     NODE         null                 null
index_f7700477                 LOOKUP     RELATIONSHIP null                 null
lesson_lessonId_unique         BTREE      NODE         ["Lesson"]           ["lessonId"]
misconception_misconceptionId_unique BTREE      NODE         ["Misconception"]    ["misconceptionId"]
question_qId_unique            BTREE      NODE         ["Question"]         ["qId"]
question_record_id_unique      BTREE      NODE         ["Question"]         ["record_id"]
result_record_id_unique        BTREE      NODE         ["Result"]           ["record_id"]
standard_record_id_unique      BTREE      NODE         ["Standard"]         ["record_id"]
standard_stId_unique           BTREE      NODE         ["Standard"]         ["stId"]
stu_details_sdId_unique        BTREE      NODE         ["StuDetail"]        ["sdId"]
student_record_id_unique       BTREE      NODE         ["Student"]          ["record_id"]
student_stuId_unique           BTREE      NODE         ["Student"]          ["stuId"]
subject_record_id_unique       BTREE      NODE         ["Subject"]          ["record_id"]
subject_subId_unique           BTREE      NODE         ["Subject"]          ["subId"]
teacher_teacherId_unique       BTREE      NODE         ["Teacher"]          ["teacherId"]
unit_unitId_unique             BTREE      NODE         ["Unit"]             ["unitId"]
-- total indexes: 26
```

## G. Tenant distribution (sub_institute_id)

```
<NULL>         144706
195            68704
1              41927
76             3197
47             2661
341            218
203            137
201            78
254            74
202            36
61             22
334            15
335            15
0              10
327            8
246            7
326            4
253            3
324            3
244            2
338            1
```
