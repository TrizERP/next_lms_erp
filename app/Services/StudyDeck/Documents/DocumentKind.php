<?php

namespace App\Services\StudyDeck\Documents;

/**
 * The three kinds of STUDY DOCUMENT the study-deck pipeline can make besides the lesson deck itself.
 *
 * A study document is the same kind of thing as a study deck: it is built from the chapter's own data (chapter text,
 * concept intelligence, prerequisite graph, question bank), it is written by the same Completer, it is checked
 * before it is stored, and it is stored as ONE `content_master` row with its structured source and its PDF beside
 * it. What differs is the shape of the teaching:
 *
 *   revision_notes  concise, exam-oriented notes: one card per concept, key terms, important questions and answers,
 *                   a revision checklist
 *   remedial        a class for learners who have not mastered a concept: prerequisite check, small steps, a worked
 *                   example, common mistakes, guided practice that gets harder, targeted follow-up
 *   activities      classroom activities a teacher can run: objectives, materials, teacher and student steps,
 *                   expected outcomes, discussion, assessment criteria, answer keys
 *
 * The category each one is filed under is the one the content library already uses for it ("Revision Notes",
 * "Remedial Class", "Classroom Activity"), so the existing Classroom Resource tabs find it without a new column.
 * Its identity is its file name: `study_<kind>_<chapter>_<scope>_<hash>.pdf`. Nothing else in the estate is named
 * like that, so, like a study deck, no extra column or marker is needed to tell it from an upload.
 */
enum DocumentKind: string
{
    case RevisionNotes = 'revision_notes';
    case Remedial = 'remedial';
    case Activities = 'activities';

    /** Bump when the stored document's shape changes. */
    public const VERSION = 1;

    /** `content_master.content_category`: the name the content library, the drawer and the student tabs already use. */
    public function category(): string
    {
        return match ($this) {
            self::RevisionNotes => 'Revision Notes',
            self::Remedial => 'Remedial Class',
            self::Activities => 'Classroom Activity',
        };
    }

    /** What a learner or teacher reads on a card or a cover. */
    public function label(): string
    {
        return match ($this) {
            self::RevisionNotes => 'Revision notes',
            self::Remedial => 'Remedial class',
            self::Activities => 'Classroom activities',
        };
    }

    /** The title of the content row: "<chapter> Revision Notes". */
    public function title(string $chapterName): string
    {
        return mb_substr(trim($chapterName) . ' ' . match ($this) {
            self::RevisionNotes => 'Revision Notes',
            self::Remedial => 'Remedial Class',
            self::Activities => 'Classroom Activities',
        }, 0, 250);
    }

    /** What the contents page and the cross-references call one numbered part ("Note 3"). */
    public function unitName(): string
    {
        return match ($this) {
            self::RevisionNotes => 'Note',
            self::Remedial => 'Unit',
            self::Activities => 'Activity',
        };
    }

    /** The start of the stored file name; the part after it is the chapter, the scope and a hash of the content. */
    public function filePrefix(): string
    {
        return match ($this) {
            self::RevisionNotes => 'study_revision_notes_',
            self::Remedial => 'study_remedial_class_',
            self::Activities => 'study_classroom_activity_',
        };
    }

    /**
     * The two copies of the PDF. `revision` is the one that is stored; `practice` is drawn on request and not kept.
     * They are the deck's own two variants (answers shown, answers hidden), so the one request parameter, the one
     * renderer switch and the one viewer toggle serve both.
     *
     * @return array{revision:string, practice:string}
     */
    public function variantLabels(): array
    {
        return match ($this) {
            self::RevisionNotes => ['revision' => 'Answers shown', 'practice' => 'Answers hidden'],
            self::Remedial => ['revision' => 'Answers shown', 'practice' => 'Answers hidden'],
            self::Activities => ['revision' => 'Teacher edition', 'practice' => 'Student handout'],
        };
    }

    /** The kind a content category names, or null for anything else. Tolerant of case, spaces and underscores. */
    public static function fromCategory(?string $category): ?self
    {
        $key = trim((string) preg_replace('/[\s_\-]+/', ' ', strtolower((string) $category)));

        return match ($key) {
            'revision notes', 'revision note' => self::RevisionNotes,
            'remedial class', 'remedial classes', 'remedial' => self::Remedial,
            'classroom activity', 'classroom activities' => self::Activities,
            default => null,
        };
    }

    /** The kind named by the machine value (`revision_notes`), also with dashes or spaces. */
    public static function fromValue(?string $value): ?self
    {
        $key = trim((string) preg_replace('/[\s\-]+/', '_', strtolower((string) $value)));

        return self::tryFrom($key) ?? match ($key) {
            'revision', 'notes' => self::RevisionNotes,
            'remedial_class', 'remedial_classes' => self::Remedial,
            'classroom_activity', 'classroom_activities', 'activity' => self::Activities,
            default => null,
        };
    }

    /**
     * Is this stored file name a study document, and which kind? Strict on purpose: it decides whether a row's
     * structured source and PDF are served, so nothing but the exact name the publisher writes is accepted.
     */
    public static function fromFilename(?string $filename): ?self
    {
        $name = (string) $filename;
        if (!preg_match('/^study_(revision_notes|remedial_class|classroom_activity)_[a-z0-9_]*?_(all|p[0-9a-f]{6})_[0-9a-f]{12}\.pdf$/', $name, $m)) {
            return null;
        }

        return match ($m[1]) {
            'revision_notes' => self::RevisionNotes,
            'remedial_class' => self::Remedial,
            'classroom_activity' => self::Activities,
        };
    }

    /**
     * The stored file name: stable for a given document (so storing it again finds its row), different for a
     * changed one. The scope is part of the name so a document for some concepts never replaces the whole-chapter one.
     *
     * @param array<string,mixed> $document the document as it will be stored
     */
    public function filenameFor(string $chapterName, array $document): string
    {
        $slug = trim(strtolower((string) preg_replace('/[^a-z0-9]+/i', '_', $chapterName)), '_');
        $slug = substr($slug !== '' ? $slug : 'chapter', 0, 80);
        $hash = substr(sha1((string) json_encode($document, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)), 0, 12);

        return $this->filePrefix() . $slug . '_' . self::scopeToken($document) . '_' . $hash . '.pdf';
    }

    /**
     * `all` for a document that covers every concept of the chapter, otherwise `p` and a short hash of the concepts it
     * does cover, so two documents for the same concepts are the same scope and a different set is a different one.
     *
     * @param array<string,mixed> $document
     */
    public static function scopeToken(array $document): string
    {
        if (!empty($document['scope']['all'])) {
            return 'all';
        }
        $ids = array_map('intval', (array) ($document['scope']['concept_ids'] ?? []));
        sort($ids);

        return 'p' . substr(sha1(implode(',', $ids)), 0, 6);
    }

    /** The `LIKE` pattern (escaped for `\`) of the other documents of this kind and scope that a new one replaces. */
    public function replaceablePattern(string $chapterName, string $scope): string
    {
        $slug = trim(strtolower((string) preg_replace('/[^a-z0-9]+/i', '_', $chapterName)), '_');
        $slug = substr($slug !== '' ? $slug : 'chapter', 0, 80);

        return str_replace('_', '\\_', $this->filePrefix() . $slug . '_' . $scope . '_') . '%';
    }
}
