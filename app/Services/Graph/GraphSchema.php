<?php

namespace App\Services\Graph;

use InvalidArgumentException;

/**
 * The whitelist of node labels, their unique key properties, and the
 * relationship types the live-sync pipeline is allowed to write.
 *
 * WHY A WHITELIST AND NOT A LOOKUP. Neo4j cannot parameterise a label or a
 * relationship type — they have to be interpolated into the Cypher string. Any
 * value reaching that interpolation from `sync_log.table_name` or
 * `neo4j_sync_queue.rel_type` is therefore an injection vector. Sanitising with
 * a regex (as the migration report's skeleton does) stops syntax breakage but
 * still lets an attacker who can write an outbox row pick ANY label in the
 * graph. Matching against this fixed map is what actually closes it.
 *
 * KEYS COME FROM THE LIVE CONSTRAINTS, NOT FROM GUESSWORK. Every entry below
 * mirrors a `REQUIRE ... IS UNIQUE` constraint in the k12 ingest script and was
 * re-verified against the running database on 2026-08-17.
 *
 * NOTE: the migration report's drain skeleton MERGEs on `{id: $id}`. That is
 * WRONG for this graph — no node uses `id` as its key — and running it would
 * mint a parallel set of nodes that the 5,409 existing :Student and every
 * ENROLLED_IN edge would not match. Hence this map.
 */
class GraphSchema
{
    /**
     * label => [
     *   'key'        => unique property the label is MERGEd on,
     *   'uid_syear'  => uid fallback: the syear segment, or null for no fallback,
     *   'uid_tenant' => 'node'   -> take tenant from the related node
     *                   'global' -> tenant segment is always 0
     * ]
     *
     * The uid fallback exists because the graph carries TWO key conventions.
     * Measured 2026-08-17 — legacy / uid node counts:
     *   Standard 48/826 · Subject 830/1961 · Chapter 7533/5625 · Concept 9/1372
     *   Curriculum 31/10 · Unit 6/60 · Misconception 1/2 · Lesson 719/1807
     * Labels with no uid column (Student, StuDetail, Result, Question,
     * Assessment, Teacher, ...) are legacy-only and need no fallback.
     *
     * Lesson is deliberately fallback-less: its uid is YEAR-scoped
     * (`Lesson:195:2023:1`), and neither outbox table carries a syear, so a
     * fallback could only be built by guessing the year. Better to create no
     * edge than a wrong one.
     */
    private const LABELS = [
        'StuDetail'           => ['key' => 'sdId',                 'uid_syear' => null, 'uid_tenant' => null],
        'Student'             => ['key' => 'stuId',                'uid_syear' => null, 'uid_tenant' => null],
        'Standard'            => ['key' => 'stId',                 'uid_syear' => 0,    'uid_tenant' => 'node'],
        'Subject'             => ['key' => 'subId',                'uid_syear' => 0,    'uid_tenant' => 'node'],
        'Chapter'             => ['key' => 'chId',                 'uid_syear' => 0,    'uid_tenant' => 'node'],
        'Concept'             => ['key' => 'conceptId',            'uid_syear' => 0,    'uid_tenant' => 'node'],
        'Curriculum'          => ['key' => 'curriculumId',         'uid_syear' => 0,    'uid_tenant' => 'node'],
        'Unit'                => ['key' => 'unitId',               'uid_syear' => 0,    'uid_tenant' => 'node'],
        'Misconception'       => ['key' => 'misconceptionId',      'uid_syear' => 0,    'uid_tenant' => 'global'],
        'Lesson'              => ['key' => 'lessonId',             'uid_syear' => null, 'uid_tenant' => null],
        'Assessment'          => ['key' => 'assId',                'uid_syear' => null, 'uid_tenant' => null],
        'Result'              => ['key' => 'resultId',             'uid_syear' => null, 'uid_tenant' => null],
        'Question'            => ['key' => 'qId',                  'uid_syear' => null, 'uid_tenant' => null],
        'Teacher'             => ['key' => 'teacherId',            'uid_syear' => null, 'uid_tenant' => null],
        'LearningContent'     => ['key' => 'contentId',            'uid_syear' => null, 'uid_tenant' => null],
        'LearningObjects'     => ['key' => 'learningobjectId',     'uid_syear' => null, 'uid_tenant' => null],
        'CompetencyStandards' => ['key' => 'competencystandardsId','uid_syear' => null, 'uid_tenant' => null],
        'ChapterStandardMap'  => ['key' => 'chapterstandardmapId', 'uid_syear' => null, 'uid_tenant' => null],
        'AssessmentTypology'  => ['key' => 'assessmenttypologyId', 'uid_syear' => null, 'uid_tenant' => null],
    ];

    /**
     * Relationship types the sync may write. Everything the k12 ingest script
     * authors, plus the four already present in neo4j_sync_queue's history.
     *
     * HAS_CONCEPT is not in the ingest script but is the edge the data actually
     * supports: `lms_concept` has `chapter_id` and no `lesson_id`, so the
     * script's `(:Lesson)-[:COVERS]->(:Concept)` could never be built here. The
     * live graph shows the result — 1,372 `(:Chapter)-[:HAS_CONCEPT]->(:Concept)`
     * against 9 COVERS. Kept alongside COVERS rather than replacing it, so the
     * handful of genuine COVERS edges stay writable.
     */
    private const RELATIONSHIPS = [
        'HAS_STUDENT', 'ENROLLED_IN', 'HAS_SUBJECT', 'HAS_ASSESSMENT',
        'HAS_RESULT', 'FOR_ASSESSMENT', 'HAS_QUESTION', 'BELONGS_TO',
        'HAS_CHAPTER', 'HAS_LESSON', 'HAS_UNIT', 'COVERS', 'ASSESSES',
        'ASSESSES_CHAPTER', 'OCCURS_IN', 'TEACHES', 'REMEDIATES',
        'ATTEMPTED', 'ATTENDED', 'MASTERS', 'HAS_MISCONCEPTION',
        'INCLUDES', 'BELONGS_TO_CURRICULUM', 'PREREQUISITE_OF',
        'HAS_CONCEPT',
    ];

    public static function knowsLabel(string $label): bool
    {
        return isset(self::LABELS[$label]);
    }

    public static function knowsRelationship(string $type): bool
    {
        return in_array($type, self::RELATIONSHIPS, true);
    }

    /** The unique property this label is MERGEd on. */
    public static function key(string $label): string
    {
        self::assertLabel($label);

        return self::LABELS[$label]['key'];
    }

    /** True when this label also has uid-keyed nodes worth falling back to. */
    public static function hasUidFallback(string $label): bool
    {
        self::assertLabel($label);

        return self::LABELS[$label]['uid_syear'] !== null;
    }

    /**
     * Cypher expression that builds the uid for this label, given a Cypher
     * variable holding the tenant. Returns null when there is no fallback.
     *
     * e.g. Standard, tenant from `s`  ->
     *   'Standard:' + toString(s.sub_institute_id) + ':0:' + toString($tid)
     */
    public static function uidExpression(string $label, string $tenantVar, string $idParam): ?string
    {
        self::assertLabel($label);
        $spec = self::LABELS[$label];

        if ($spec['uid_syear'] === null) {
            return null;
        }

        $tenant = $spec['uid_tenant'] === 'global'
            ? "'0'"
            : "toString({$tenantVar}.sub_institute_id)";

        return "'{$label}:' + {$tenant} + ':{$spec['uid_syear']}:' + toString(\${$idParam})";
    }

    /**
     * Guard before a label is interpolated into Cypher.
     *
     * @throws InvalidArgumentException
     */
    public static function assertLabel(string $label): void
    {
        if (! isset(self::LABELS[$label])) {
            throw new InvalidArgumentException("Unknown graph label '{$label}' — refusing to interpolate it into Cypher");
        }
    }

    /**
     * Guard before a relationship type is interpolated into Cypher.
     *
     * @throws InvalidArgumentException
     */
    public static function assertRelationship(string $type): void
    {
        if (! in_array($type, self::RELATIONSHIPS, true)) {
            throw new InvalidArgumentException("Unknown relationship type '{$type}' — refusing to interpolate it into Cypher");
        }
    }
}
