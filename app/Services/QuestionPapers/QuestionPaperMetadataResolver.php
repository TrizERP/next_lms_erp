<?php

namespace App\Services\QuestionPapers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Decides the standard / subject / chapter a paper belongs to without guessing.
 *
 * Order of authority: what the uploader chose (checked against the school's own rows) beats what
 * the paper's heading says. A heading counts only when it names exactly ONE row of this school
 * after case/space folding. When several rows share the name (the school has both "Science" and
 * "SCIENCE"), capitalisation never decides: the school's own standard->subject mapping
 * (sub_std_map) does, and only when it leaves exactly one candidate. Otherwise the field stays
 * null, the candidates are recorded, and the owner chooses.
 */
class QuestionPaperMetadataResolver
{
    /**
     * @param  array<string, string|null>  $header  heading text the parser found
     * @return array{standard_id: ?int, subject_id: ?int, chapter_id: ?int, academic_year: ?string, notes: list<string>, candidates: array<string, list<array{id: int, name: string, mapped: bool}>>}
     */
    public function resolve(object $job, array $header): array
    {
        $tenant = (int) $job->sub_institute_id;
        $notes = [];
        $candidates = [];

        $standard = $job->standard_id !== null ? (int) $job->standard_id : null;
        if ($standard === null) {
            $found = $this->namedStandard($tenant, $header['standard'] ?? null);
            if (count($found) === 1) {
                $standard = $found[0]['id'];
            } elseif ($found !== []) {
                $candidates['standard'] = $found;
                $notes[] = "The paper's standard \"{$header['standard']}\" matches more than one standard of this school; choose it and retry.";
            } else {
                $notes[] = empty($header['standard'])
                    ? 'The standard is not stated in the paper; choose it and retry.'
                    : "The paper's standard \"{$header['standard']}\" matches no standard of this school; choose it and retry.";
            }
        }

        $subject = $job->subject_id !== null ? (int) $job->subject_id : null;
        if ($subject === null) {
            // A subject is never matched against the school's global subject catalogue. Its
            // identity is meaningful only inside the standard's active subject mapping.
            $found = $standard !== null ? $this->mappedSubjectsNamed($tenant, $standard, $header['subject'] ?? null) : [];
            if (count($found) === 1) {
                $subject = $found[0]['id'];
            } elseif ($found !== []) {
                $candidates['subject'] = $found;
                $notes[] = "The paper's subject \"{$header['subject']}\" matches more than one subject mapped to this standard; choose it and retry.";
            } else {
                $notes[] = empty($header['subject'])
                    ? ($standard === null ? 'Resolve the standard before matching its subject.' : "The subject is not stated in the paper; choose it from this standard's subjects and retry.")
                    : ($standard === null ? 'Resolve the standard before matching its subject.' : "The paper's subject \"{$header['subject']}\" matches no subject mapped to this standard; choose it from this standard's subjects and retry.");
                // Offer the VALID options: only the subjects this standard really uses.
                if ($standard !== null) {
                    $options = $this->mappedSubjects($tenant, $standard);
                    if ($options !== []) {
                        $candidates['subject'] = $options;
                    }
                }
            }
        }

        $chapter = $job->chapter_id !== null ? (int) $job->chapter_id : null;
        if ($chapter === null) {
            if ($standard !== null && $subject !== null && !empty($header['chapter'])) {
                $found = $this->chapters($tenant, $standard, $subject, (string) $header['chapter']);
                if (count($found) === 1) {
                    $chapter = $found[0]['id'];
                } else {
                    if ($found !== []) {
                        $candidates['chapter'] = $found;
                    }
                    $notes[] = $found === []
                        ? "The paper's chapter \"{$header['chapter']}\" matches no chapter of this standard and subject; choose one or leave it unset."
                        : "The paper's chapter \"{$header['chapter']}\" matches more than one chapter; choose it.";
                }
            } elseif ($standard !== null && $subject !== null) {
                $notes[] = 'The paper does not state a chapter. Each question is matched to a chapter from its own wording; any that cannot be matched with confidence wait for a teacher to confirm.';
            }
        }

        return [
            'standard_id' => $standard,
            'subject_id' => $subject,
            'chapter_id' => $chapter,
            'academic_year' => $job->academic_year ?: (isset($header['academic_year']) ? mb_substr((string) $header['academic_year'], 0, 20) : null),
            'notes' => $notes,
            'candidates' => $candidates,
        ];
    }

    /**
     * Validate ids the uploader supplied against this school's rows. Null for any that do not belong.
     *
     * @return array{standard_id: ?int, subject_id: ?int, chapter_id: ?int}
     */
    public function validateChosen(int $tenant, ?int $standardId, ?int $subjectId, ?int $chapterId): array
    {
        $standard = $standardId && DB::table('standard')->where('id', $standardId)->where('sub_institute_id', $tenant)->exists() ? $standardId : null;
        $subject = $subjectId && DB::table('subject')->where('id', $subjectId)->where('sub_institute_id', $tenant)->exists() ? $subjectId : null;

        $chapter = null;
        if ($chapterId) {
            $row = DB::table('chapter_master')->where('id', $chapterId)->where('sub_institute_id', $tenant)->first(['standard_id', 'subject_id']);
            // A chapter must agree with the standard and subject chosen with it; otherwise drop it.
            if ($row && ($standard === null || (int) $row->standard_id === $standard) && ($subject === null || (int) $row->subject_id === $subject)) {
                $chapter = $chapterId;
            }
        }

        return ['standard_id' => $standard, 'subject_id' => $subject, 'chapter_id' => $chapter];
    }

    public function validChapter(int $tenant, int $standard, int $subject, ?int $chapter): ?int
    {
        return $chapter && DB::table('chapter_master')->where('id', $chapter)->where('sub_institute_id', $tenant)
            ->where('standard_id', $standard)->where('subject_id', $subject)->exists() ? $chapter : null;
    }

    public function resolveChapterTitle(object $job, string $title): ?int
    {
        if ($job->standard_id === null || $job->subject_id === null) return null;
        $found = $this->chapters((int) $job->sub_institute_id, (int) $job->standard_id, (int) $job->subject_id, $title);
        return count($found) === 1 ? $found[0]['id'] : null;
    }

    /**
     * The subjects of the school flagged with whether the school maps them to $standardId, so the
     * picker can show which of two same-named subjects the standard actually uses.
     *
     * @return list<array{id: int, name: string, mapped: bool}>
     */
    public function subjectsFor(int $tenant, ?int $standardId): array
    {
        if ($standardId && Schema::hasTable('sub_std_map')) {
            return DB::table('sub_std_map as m')->join('subject as s', 's.id', '=', 'm.subject_id')
                ->where('m.standard_id', $standardId)->where('s.sub_institute_id', $tenant)
                ->where('m.status', 1)->whereNull('m.deleted_at')->orderBy('s.subject_name')->limit(500)
                ->select('s.id', 's.subject_name as name')->distinct()->get()->map(fn ($r) => ['id' => (int) $r->id, 'name' => (string) $r->name, 'mapped' => true])->all();
        }
        $subjects = DB::table('subject')->where('sub_institute_id', $tenant)->orderBy('subject_name')->limit(500)
            ->get(['id', 'subject_name'])
            ->map(fn ($r) => ['id' => (int) $r->id, 'name' => (string) $r->subject_name, 'mapped' => false])->all();

        return $standardId ? $this->markMapped($subjects, $standardId) : $subjects;
    }

    /**
     * The subjects a standard really uses: live sub_std_map rows of this school, joined to an active subject of this school.
     * A subject that merely shares a name with one of these, but is not mapped to the standard, is not in this list.
     *
     * @return list<array{id: int, name: string, display: string, mapped: bool}>
     */
    public function mappedSubjects(int $tenant, int $standard): array
    {
        if (!Schema::hasTable('sub_std_map')) {
            return [];
        }
        $query = DB::table('sub_std_map as m')->join('subject as s', 's.id', '=', 'm.subject_id')
            ->where('m.standard_id', $standard)->where('s.sub_institute_id', $tenant)
            ->where('m.status', 1)->whereNull('m.deleted_at');
        if (Schema::hasColumn('subject', 'status')) {
            $query->where(fn ($q) => $q->where('s.status', 1)->orWhereNull('s.status'));
        }
        $hasDisplay = Schema::hasColumn('sub_std_map', 'display_name');
        $out = [];
        foreach ($query->orderBy('s.subject_name')->get(['s.id', 's.subject_name'] + ($hasDisplay ? [2 => 'm.display_name'] : [])) as $row) {
            $out[(int) $row->id] ??= ['id' => (int) $row->id, 'name' => (string) $row->subject_name, 'display' => (string) ($row->display_name ?? ''), 'mapped' => true];
        }

        return array_values($out);
    }

    /** @return list<int> */
    public function mappedSubjectIds(int $tenant, int $standard): array
    {
        return array_column($this->mappedSubjects($tenant, $standard), 'id');
    }

    /**
     * Mapped subjects of the standard whose name (or the mapping's display name) equals the heading after folding.
     *
     * @return list<array{id: int, name: string, mapped: bool}>
     */
    public function mappedSubjectsNamed(int $tenant, int $standard, ?string $name): array
    {
        if (!$name || trim($name) === '') {
            return [];
        }
        $folded = $this->fold($this->cleanSubjectHeading($name));
        $out = [];
        foreach ($this->mappedSubjects($tenant, $standard) as $subject) {
            if ($folded !== '' && ($this->fold($subject['name']) === $folded || ($subject['display'] !== '' && $this->fold($subject['display']) === $folded))) {
                $out[] = ['id' => $subject['id'], 'name' => $subject['name'], 'mapped' => true];
            }
        }

        return $out;
    }

    /** "Class 9 - Science", "Std. IX: SCIENCE" and "Subject: Science" all mean Science. */
    private function cleanSubjectHeading(string $heading): string
    {
        $text = mb_strtolower($heading);
        // "class 9", "std. ix", "standard 10", "grade 8" ...
        $text = preg_replace('~\b(?:class|std|standard|grade)\.?\s*(?:[0-9]{1,2}|[ivx]{1,4})\b~u', ' ', $text) ?? $text;
        // a leading standard number: "9 - science", "9: science"
        $text = preg_replace('~^\s*[0-9]{1,2}\s*[-:/|.]?\s*~u', ' ', $text) ?? $text;
        // the word "subject" ("Subject: Science")
        $text = preg_replace('~\bsubject\s*[:-]?~u', ' ', $text) ?? $text;

        return trim($text);
    }

    /** @return list<array{id: int, name: string, mapped: bool}> */
    private function named(string $table, string $column, int $tenant, ?string $name): array
    {
        if ($name === null || trim($name) === '') {
            return [];
        }
        $folded = $this->fold($name);
        $out = [];
        foreach (DB::table($table)->where('sub_institute_id', $tenant)->get(['id', $column]) as $row) {
            if ($this->fold((string) $row->{$column}) === $folded) {
                $out[] = ['id' => (int) $row->id, 'name' => (string) $row->{$column}, 'mapped' => false];
            }
        }

        return $out;
    }

    /** Standard headings commonly say Class 9, Grade 9, Standard 9 or simply 9. */
    private function namedStandard(int $tenant, ?string $name): array
    {
        if (!$name || trim($name) === '') return [];
        $folded = $this->fold($name);
        $classNumber = preg_match('/(?:class|grade|standard|std)?\s*(\d{1,2})/iu', trim($name), $match) ? (int) $match[1] : null;
        $out = [];
        foreach (DB::table('standard')->where('sub_institute_id', $tenant)->get(['id', 'name']) as $row) {
            $rowName = (string) $row->name;
            $rowNumber = preg_match('/(?:class|grade|standard|std)?\s*(\d{1,2})/iu', trim($rowName), $rowMatch) ? (int) $rowMatch[1] : null;
            if ($this->fold($rowName) === $folded || ($classNumber !== null && $rowNumber === $classNumber)) {
                $out[] = ['id' => (int) $row->id, 'name' => $rowName, 'mapped' => false];
            }
        }
        return $out;
    }

    /** @return list<array{id: int, name: string}> */
    private function chapters(int $tenant, int $standard, int $subject, string $name): array
    {
        $folded = $this->fold($name);
        $out = [];
        $rows = DB::table('chapter_master')
            ->where('sub_institute_id', $tenant)->where('standard_id', $standard)->where('subject_id', $subject)
            ->get(['id', 'chapter_name']);
        foreach ($rows as $row) {
            if ($this->fold((string) $row->chapter_name) === $folded) {
                $out[] = ['id' => (int) $row->id, 'name' => (string) $row->chapter_name];
            }
        }

        return $out;
    }

    /**
     * Flag candidates the school actually uses for the standard: a live sub_std_map row.
     *
     * @param  list<array{id: int, name: string, mapped: bool}>  $subjects
     * @return list<array{id: int, name: string, mapped: bool}>
     */
    private function markMapped(array $subjects, int $standard): array
    {
        if ($subjects === [] || !Schema::hasTable('sub_std_map')) {
            return $subjects;
        }

        $mapped = DB::table('sub_std_map')
            ->where('standard_id', $standard)->whereIn('subject_id', array_column($subjects, 'id'))
            ->where('status', 1)->whereNull('deleted_at')
            ->pluck('subject_id')->map(fn ($id) => (int) $id)->all();

        return array_map(fn ($s) => ['mapped' => in_array($s['id'], $mapped, true)] + $s, $subjects);
    }

    private function fold(string $value): string
    {
        return preg_replace('/[^\p{L}\p{N}]+/u', '', mb_strtolower($value)) ?? '';
    }
}
