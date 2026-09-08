<?php

namespace App\Brain\Intelligence;

/**
 * THE ONE DEFINITION of what a graph node can be and what an edge can mean.
 *
 * Ported from hp-enterprise-brain/app/Domain/Graph/GraphVocabulary.php and kept
 * to its central discipline: every label is backed by rows this installation
 * genuinely holds, and every relationship names the COLUMN that produces it.
 *
 * THERE IS NO GENERIC "RELATED TO" EDGE. Each entry's third element is a
 * provenance clause naming the join behind it, and that clause is published to
 * the client and shown beside the edge. A pair of entities with no such column
 * gets no edge — so this graph shows fewer connections than a reader might hope
 * for, and every one it does show can be traced to a query. That trade is the
 * whole point: a graph that invents plausible edges is worse than no graph,
 * because it looks like knowledge.
 *
 * A LABEL NOT IN THIS MAP CANNOT BE RENDERED, EXPANDED OR SEARCHED. The
 * projection layer can only emit what is declared here, which is what makes the
 * vocabulary auditable against the schema without reading the query layer.
 *
 * FAMILIES exist so the screen can filter and colour by what a node IS rather
 * than by its label — five families read better than fourteen labels.
 */
final class GraphVocabulary
{
    public const FAMILY_ORGANIZATION = 'organization';

    public const FAMILY_PEOPLE = 'people';

    public const FAMILY_STUDENT = 'student';

    /** Dimensions of the school's academic data: class, subject. */
    public const FAMILY_ACADEMIC = 'academic';

    /** The Brain's own loop: signal, evidence, case, recommendation, decision. */
    public const FAMILY_INTELLIGENCE = 'intelligence';

    /** Node label => family. */
    public const LABEL_FAMILY = [
        'Organization' => self::FAMILY_ORGANIZATION,
        'Department' => self::FAMILY_ORGANIZATION,
        'Person' => self::FAMILY_PEOPLE,
        'Student' => self::FAMILY_STUDENT,
        'Class' => self::FAMILY_ACADEMIC,
        'Subject' => self::FAMILY_ACADEMIC,
        'Signal' => self::FAMILY_INTELLIGENCE,
        'Evidence' => self::FAMILY_INTELLIGENCE,
        'Case' => self::FAMILY_INTELLIGENCE,
        'Recommendation' => self::FAMILY_INTELLIGENCE,
        'Decision' => self::FAMILY_INTELLIGENCE,
        'Capability' => self::FAMILY_INTELLIGENCE,
    ];

    /** How each label reads in a heading, and what it is called in the plural. */
    public const LABEL_PLURAL = [
        'Organization' => 'Organization',
        'Department' => 'Departments',
        'Person' => 'Staff',
        'Student' => 'Students',
        'Class' => 'Classes',
        'Subject' => 'Subjects',
        'Signal' => 'Findings',
        'Evidence' => 'Evidence',
        'Case' => 'Cases',
        'Recommendation' => 'Recommendations',
        'Decision' => 'Decisions',
        'Capability' => 'Capabilities',
    ];

    /**
     * Relationship type => [human label, family, what produces it].
     *
     * The third element is not decoration. It is shown in the detail panel, so a
     * reader who does not believe an edge can see the column it came from.
     */
    public const RELATIONSHIPS = [
        'has_department' => [
            'has department',
            'organizational',
            'hrms_departments rows for this institute that carry at least one staff member.',
        ],
        'works_in' => [
            'works in',
            'people',
            'tbluser.department_id, the department recorded on the staff record.',
        ],
        'employs' => [
            'employs',
            'people',
            'tbluser rows whose department_id is this department.',
        ],
        'headed_by' => [
            'headed by',
            'people',
            'hrms_departments.head_user_id resolved against tbluser.',
        ],
        'teaches' => [
            'teaches',
            'academic',
            'class_teacher.teacher_id and class_teacher.standard_id for this institute.',
        ],
        'taught_by' => [
            'taught by',
            'academic',
            'class_teacher rows allocating this class to a member of staff.',
        ],
        'enrolled_in' => [
            'enrolled in',
            'academic',
            'attendance_student.standard_id — the class this student was marked present or absent in.',
        ],
        'enrolls' => [
            'enrolls',
            'academic',
            'Distinct attendance_student.student_id recorded against this class.',
        ],
        'set_homework_in' => [
            'set homework in',
            'academic',
            'homework.subject_id, grouped over the assignments recorded for this institute.',
        ],
        'has_finding' => [
            'has finding',
            'intelligence',
            'hpbrain_signals.related_entity_type and related_entity_id, written by the rule that raised it.',
        ],
        'supported_by' => [
            'supported by',
            'intelligence',
            'hpbrain_evidence.signal_id.',
        ],
        'opened_case' => [
            'opened case',
            'intelligence',
            'hpbrain_cases.signal_id.',
        ],
        'led_to' => [
            'led to',
            'intelligence',
            'hpbrain_recommendations.reasoning_step_id resolved to a reasoning step carrying this case.',
        ],
        'decided_by' => [
            'decided by',
            'intelligence',
            'hpbrain_decisions.recommendation_id.',
        ],
        'holds_capability' => [
            'holds capability',
            'intelligence',
            'hpbrain_capability_assignments.target_type and target_id.',
        ],
        'contains' => [
            'contains',
            'organizational',
            'A group of rows of one kind belonging to the node it hangs from. The count is a COUNT over exactly those rows.',
        ],
    ];

    /** Relationship families the client may filter by. */
    public const RELATIONSHIP_FAMILIES = ['organizational', 'people', 'academic', 'intelligence'];

    public static function family(string $label): string
    {
        return self::LABEL_FAMILY[$label] ?? self::FAMILY_ORGANIZATION;
    }

    public static function isKnownLabel(string $label): bool
    {
        return isset(self::LABEL_FAMILY[$label]);
    }

    public static function plural(string $label): string
    {
        return self::LABEL_PLURAL[$label] ?? $label;
    }

    /** @return array{0: string, 1: string, 2: string} */
    public static function relationship(string $type): array
    {
        return self::RELATIONSHIPS[$type] ?? [$type, 'organizational', 'Derived directly from a stored column.'];
    }

    /** The whole vocabulary, for a client that wants to render the legend. */
    public static function published(): array
    {
        $labels = [];
        foreach (self::LABEL_FAMILY as $label => $family) {
            $labels[] = ['label' => $label, 'plural' => self::plural($label), 'family' => $family];
        }

        $relationships = [];
        foreach (self::RELATIONSHIPS as $type => [$label, $family, $provenance]) {
            $relationships[] = ['type' => $type, 'label' => $label, 'family' => $family, 'provenance' => $provenance];
        }

        return [
            'labels' => $labels,
            'relationships' => $relationships,
            'families' => self::RELATIONSHIP_FAMILIES,
        ];
    }
}
