<?php

namespace App\Console\Commands\LMS;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Write everything needed to author a chapter's content into one folder.
 *
 * WHY THIS EXISTS
 * Content generation needs four things that live in four different places:
 * the chapter's ground-truth textbook text (document_extractions.md_content),
 * its per-concept semantic intelligence (semantic_intelligence), its concept
 * list (lms_concept), and the chapter's own identity. Assembling those by hand
 * per chapter does not survive 166 chapters.
 *
 * A bundle makes one generation reproducible, reviewable and resumable: the
 * exact inputs are on disk next to the outputs, so a run can be inspected,
 * re-run, or handed to a different generator without re-querying anything.
 *
 * LAYOUT
 *   content/std-9/science/ch-03-tissues-in-action/
 *     chapter.md         ground-truth text - the only source of facts
 *     intelligence.json  per-concept semantic intelligence
 *     concepts.json      concept names, in order
 *     manifest.json      ids, counts, and what is already stored
 *     out/               generated HTML lands here, one file per content type
 *
 * THE UTF-8 TRAP
 * semantic_intelligence.full_intelegance_json contains malformed UTF-8 on at
 * least some rows and fails json_decode outright ("Malformed UTF-8 characters").
 * Every read of it must pass through mb_convert_encoding($s, 'UTF-8', 'UTF-8')
 * first, which drops the invalid bytes. Same for md_content.
 */
class ExportChapterBundleCommand extends Command
{
    protected $signature = 'lms:export-chapter-bundle
        {--chapter=* : chapter_master.id to export (repeatable)}
        {--standard= : export every chapter of this standard_id}
        {--subject= : with --standard, restrict to this subject name}
        {--tenant=1 : sub_institute_id}
        {--root= : directory the bundles are written under (default: LMS_CONTENT_PACKAGE_ROOT, else ./content)}
        {--force : overwrite an existing bundle}';

    protected $description = 'Export a chapter\'s ground truth and intelligence into a generation bundle folder';

    /** Mirrors ApiLmsCourseController::GENERATED_CONTENT_SOURCES. */
    private const GENERATED_SOURCES = ['Gamma AI', 'aiGenerated', 'Claude AI'];

    public function handle(): int
    {
        $chapters = $this->resolveChapters();

        if ($chapters->isEmpty()) {
            $this->error('No chapters matched.');

            return self::FAILURE;
        }

        // Packages are working material for a human designer, not repo code, so
        // they live outside the project. The path is an env var rather than a
        // hardcoded default because it is machine-specific.
        $root = rtrim(
            (string) ($this->option('root') ?: env('LMS_CONTENT_PACKAGE_ROOT', 'content')),
            '/\\'
        );
        $written = 0;
        $skipped = 0;

        foreach ($chapters as $chapter) {
            $dir = $root . '/' . $this->bundlePath($chapter);

            if (is_dir($dir) && !$this->option('force')) {
                $this->warn('Skipped (exists): ' . $dir);
                $skipped++;
                continue;
            }

            if (!$this->writeBundle($chapter, $dir)) {
                continue;
            }

            $written++;
        }

        $this->newLine();
        $this->info(sprintf('%d bundle(s) written, %d skipped.', $written, $skipped));

        return self::SUCCESS;
    }

    /** @return \Illuminate\Support\Collection<int, object> */
    private function resolveChapters()
    {
        $tenant = (int) $this->option('tenant');

        $query = DB::table('chapter_master as cm')
            ->join('standard as s', 's.id', '=', 'cm.standard_id')
            ->join('subject as sub', 'sub.id', '=', 'cm.subject_id')
            ->where('cm.sub_institute_id', $tenant)
            // show_hide is NULL on chapters that predate the flag and are live
            // content, so the test is "not 0" rather than "= 1".
            ->where(function ($q) {
                $q->whereNull('cm.show_hide')->orWhere('cm.show_hide', '<>', 0);
            })
            ->select([
                'cm.id', 'cm.chapter_name', 'cm.standard_id', 'cm.subject_id',
                'cm.sub_institute_id',
                's.name as standard_name', 'sub.subject_name',
            ])
            ->orderBy('cm.id');

        $ids = array_filter(array_map('intval', (array) $this->option('chapter')));
        if ($ids) {
            return $query->whereIn('cm.id', $ids)->get();
        }

        if ($this->option('standard')) {
            $query->where('cm.standard_id', (int) $this->option('standard'));
            if ($this->option('subject')) {
                $query->where('sub.subject_name', (string) $this->option('subject'));
            }

            return $query->get();
        }

        return collect();
    }

    /** `std-9/science/ch-03-tissues-in-action` */
    private function bundlePath(object $chapter): string
    {
        return sprintf(
            'std-%s/%s/%s',
            Str::slug((string) $chapter->standard_name),
            Str::slug((string) $chapter->subject_name),
            Str::slug((string) $chapter->chapter_name)
        );
    }

    private function writeBundle(object $chapter, string $dir): bool
    {
        $row = DB::table('semantic_intelligence as si')
            ->leftJoin('document_extractions as de', 'de.id', '=', 'si.extraction_id')
            ->where('si.chapter_id', $chapter->id)
            ->select(['si.full_intelegance_json', 'si.learning_objective', 'de.md_content'])
            ->first();

        $ground = $this->utf8((string) ($row->md_content ?? ''));

        if (trim($ground) === '') {
            // Generating without ground truth produces a hallucinated chapter at
            // full price. Refuse rather than write a bundle that looks complete.
            $this->error(sprintf('Chapter %d "%s" has no ground-truth text - skipped.', $chapter->id, $chapter->chapter_name));

            return false;
        }

        $intelligence = json_decode($this->utf8((string) ($row->full_intelegance_json ?? '')), true);
        if (!is_array($intelligence)) {
            $this->error(sprintf('Chapter %d "%s": intelligence JSON unreadable - skipped.', $chapter->id, $chapter->chapter_name));

            return false;
        }

        $entries = $intelligence['concepts'] ?? $intelligence['intelligence']['concepts'] ?? [];
        $conceptNames = array_values(array_filter(array_map(
            static fn ($e) => $e['concept']['concept_name'] ?? null,
            is_array($entries) ? $entries : []
        )));

        $existing = DB::table('content_master')
            ->where('chapter_id', $chapter->id)
            ->whereIn('source', self::GENERATED_SOURCES)
            ->where(function ($q) {
                $q->whereNull('show_hide')->orWhere('show_hide', '<>', 0);
            })
            ->pluck('content_category')
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (!is_dir($dir . '/out') && !mkdir($dir . '/out', 0775, true) && !is_dir($dir . '/out')) {
            $this->error('Could not create ' . $dir . '/out');

            return false;
        }

        [$boardKey, $boardProfile, $framework] = $this->resolveBoard($chapter);

        file_put_contents($dir . '/chapter.md', $ground);
        file_put_contents($dir . '/intelligence.json', json_encode($entries, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        file_put_contents($dir . '/concepts.json', json_encode($conceptNames, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        file_put_contents($dir . '/manifest.json', json_encode([
            'chapter_id' => (int) $chapter->id,
            'chapter_name' => $chapter->chapter_name,
            'standard_id' => (int) $chapter->standard_id,
            'standard_name' => $chapter->standard_name,
            'subject_id' => (int) $chapter->subject_id,
            'subject_name' => $chapter->subject_name,
            'sub_institute_id' => (int) $this->option('tenant'),
            'concept_count' => count($conceptNames),
            'ground_truth_chars' => strlen($ground),
            // What the generator must write to. A board whose profile is
            // incomplete is named rather than silently replaced by CBSE.
            'board' => $boardKey,
            'board_label' => $boardProfile['label'] ?? $boardKey,
            'board_profile_complete' => (bool) ($boardProfile['profile_complete'] ?? false),
            'framework' => $framework,
            'question_types' => $boardProfile['question_types'] ?? [],
            'cognitive_bands' => $boardProfile['cognitive_bands'] ?? [],
            'difficulty_labels' => $boardProfile['difficulty_labels'] ?? [],
            'learning_objective' => $row->learning_objective ?? null,
            'already_generated' => $existing,
            'exported_at' => now()->toIso8601String(),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        // The chapter -> topic -> concept hierarchy. A designer needs it to
        // section the deck; the concept list alone is flat.
        $topics = $this->chapterTopics((int) $chapter->id);
        file_put_contents($dir . '/topics.json', json_encode($topics, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        // The paste-ready brief. This is what makes a bundle usable by a human
        // working in a design tool, not just by this repo's own renderer.
        file_put_contents($dir . '/PROMPT.md', $this->designPrompt($chapter, $conceptNames, $topics, $boardProfile, $existing, strlen($ground)));

        $this->line(sprintf(
            '%-58s %2d concepts | %s chars%s',
            $dir,
            count($conceptNames),
            number_format(strlen($ground)),
            $existing ? ' | has: ' . implode(', ', $existing) : ''
        ));

        return true;
    }

    /**
     * Which board this chapter is taught under, and that board's profile.
     *
     * `lms_curriculum.board` is the authority - it is the only place a board
     * is actually declared per standard/subject. Falls back to a board prefix
     * on the standard's name ("CBSE-6"), the same trick storeGammaContent
     * uses. Note that standards 39/40/42 are named plainly "6"/"7"/"9", so the
     * prefix yields nothing for the current target chapters and the curriculum
     * row is the only real source.
     *
     * Resolving to the 'generic' profile rather than CBSE is deliberate: a
     * chapter with no declared board must not silently acquire CBSE question
     * forms. See config/lms_content_boards.php.
     *
     * @return array{0:string, 1:array<string,mixed>, 2:?string}
     */
    private function resolveBoard(object $chapter): array
    {
        $curriculum = DB::table('lms_curriculum')
            ->where('standard_id', $chapter->standard_id)
            ->where('subject_id', $chapter->subject_id)
            ->where('sub_institute_id', $chapter->sub_institute_id ?? 1)
            ->orderByDesc('id')
            ->first(['board', 'framework']);

        $raw = trim((string) ($curriculum->board ?? ''));

        if ($raw === '' && preg_match('/^([A-Za-z]+)[-\s]+(.+)$/', (string) $chapter->standard_name, $m)) {
            $raw = $m[1];
        }

        $boards = (array) config('lms_content_boards.boards', []);
        $aliases = (array) config('lms_content_boards.aliases', []);
        $key = $aliases[strtolower($raw)] ?? (isset($boards[strtolower($raw)]) ? strtolower($raw) : null);

        if ($key === null && $raw !== '') {
            $this->warn(sprintf('  Board "%s" is not in lms_content_boards.', $raw));
        }

        // A content-owning tenant is a board: 1 is the CBSE library, 341 is
        // Cambridge. Last resort before board-neutral, because it is a
        // whole-tenant default and any per-subject declaration should beat it.
        if ($key === null) {
            $tenantBoards = (array) config('lms_content_boards.tenant_boards', []);
            $tenant = (int) ($chapter->sub_institute_id ?? $this->option('tenant'));
            $key = $tenantBoards[$tenant] ?? null;
        }

        if ($key === null) {
            $key = (string) config('lms_content_boards.default', 'generic');
        }

        $profile = $boards[$key] ?? [];

        if (!($profile['profile_complete'] ?? false)) {
            $this->warn(sprintf(
                '  Board "%s" has no completed profile. Generation must not assume CBSE question forms.',
                $profile['label'] ?? $key
            ));
        }

        return [$key, $profile, $curriculum->framework ?? null];
    }

    /**
     * The chapter's topic -> concept hierarchy.
     *
     * Concepts alone are a flat list; a designer sectioning a deck needs to
     * know which concepts belong together. topic_master carries a sort order
     * and estimated minutes, both of which shape pacing.
     *
     * @return array<int, array<string,mixed>>
     */
    private function chapterTopics(int $chapterId): array
    {
        $topics = DB::table('topic_master')
            ->where('chapter_id', $chapterId)
            ->where(function ($q) {
                $q->whereNull('topic_show_hide')->orWhere('topic_show_hide', '<>', 0);
            })
            ->orderBy('topic_sort_order')
            ->get(['id', 'name', 'description', 'estimated_minutes']);

        return $topics->map(function ($topic) {
            return [
                'topic' => $topic->name,
                'description' => $topic->description,
                'estimated_minutes' => $topic->estimated_minutes,
                'concepts' => DB::table('lms_concept')
                    ->where('topic_id', $topic->id)
                    ->orderBy('id')
                    ->pluck('name')
                    ->values()
                    ->all(),
            ];
        })->all();
    }

    /**
     * The paste-ready design brief.
     *
     * WHY THIS FILE EXISTS
     * Our own PHP renderer positions every shape by arithmetic, and PHP cannot
     * measure text - PHPPresentation exposes no font metrics. So box heights
     * are guesses, and on a real chapter that produced 33 overlapping pairs and
     * 22 shapes running off the bottom of the canvas. A tool that can actually
     * lay out text does better, so this file hands one everything it needs in
     * one paste: what to build, from what, in whose design language, and the
     * rules that make the content teach rather than merely look finished.
     *
     * @param array<int,string> $concepts
     * @param array<int,array<string,mixed>> $topics
     * @param array<string,mixed> $board
     * @param array<int,string> $existing
     */
    private function designPrompt(
        object $chapter,
        array $concepts,
        array $topics,
        array $board,
        array $existing,
        int $groundChars
    ): string {
        $questionTypes = implode(', ', array_values((array) ($board['question_types'] ?? []))) ?: 'standard question forms';
        $topicLines = $topics
            ? implode("\n", array_map(
                static fn ($t) => '- **' . $t['topic'] . '** (' . count($t['concepts']) . ' concepts'
                    . ($t['estimated_minutes'] ? ', ~' . $t['estimated_minutes'] . ' min' : '') . '): '
                    . implode(', ', array_slice($t['concepts'], 0, 6))
                    . (count($t['concepts']) > 6 ? ', …' : ''),
                $topics
            ))
            : '- (no topic breakdown recorded for this chapter)';

        $conceptLines = implode("\n", array_map(static fn ($c) => '- ' . $c, $concepts));
        $already = $existing ? implode(', ', $existing) : 'nothing yet';

        return <<<MD
# Build a classroom presentation — {$chapter->chapter_name}

**Class {$chapter->standard_name} · {$chapter->subject_name} · {$board['label']} board**

Attached in this folder:

| File | What it is |
|---|---|
| `chapter.md` | The **exact textbook text** ({$groundChars} characters). Every fact must come from here. |
| `concepts.json` | The concepts this chapter teaches — cover **all** of them |
| `topics.json` | How those concepts group, with suggested timings |
| `intelligence.json` | Per concept: misconceptions, real-world uses, suggested pedagogy, Bloom/DOK, and ready-made assessment questions |
| `manifest.json` | Board profile and counts |

Already generated for this chapter: {$already}.

---

## What to make

A **classroom presentation** a teacher projects while teaching this chapter. Deliver it as an editable **.pptx** file, 16:9.

Roughly 1–2 slides per concept, plus a cover and a closing summary.

## The one rule that matters most

**Every slide ends with a question, and the answer must NOT be visible until the teacher clicks.**

Put the answer on its own animation step. A slide that shows the question and answer together is useless in a classroom — the class reads the answer before being asked anything. This is the single most important requirement here.

## Content rules

- **Ground truth only.** Facts, definitions, examples, numbers and names come from `chapter.md`. Use the textbook's exact wording for definitions. Do not invent examples.
- **Cover every concept** in `concepts.json`. Use `topics.json` to group and section them.
- **Use the misconceptions** in `intelligence.json` — they are real, per concept, and they are where students actually fail. Give the important ones their own slide.
- **Use the assessment questions** already in `intelligence.json` rather than writing new ones where they fit.
- Question forms for this board: {$questionTypes}.
- Indian context and examples where the textbook offers them.

## Design language

- **Brand indigo `#4F46E5`**, deep indigo `#1E1B4B` for dark slides, slate neutrals (`#1E293B` text, `#475569` secondary).
- Semantic colour, used consistently: **amber `#B45309`** = misconception · **emerald `#047857`** = worked example · **blue `#0369A1`** = try this / check · **indigo** = key idea.
- **Inter** (or a close sans) throughout. Strong size contrast — the biggest and second-biggest text on a slide should differ by at least 2×.
- **Vary the layout.** Do not repeat one template. Use a mix: hero cover, section dividers, big-number stat tiles, split text/diagram, two-column comparison, full-bleed diagram, quote, question card, summary grid.
- **Sentence case** everywhere. Not Title Case.
- **No emoji, no decorative borders, no clip-art flourishes.** Visual interest comes from real diagrams, layout variety, colour blocking and type contrast — not ornament. Decoration that carries no meaning measurably reduces learning.
- Generous whitespace. Aim for **under 60 words** of body text per slide.
- Accessible contrast (4.5:1 body text), and never carry meaning by colour alone — always label a coloured box.

## Diagrams

Draw diagrams where they help — cell structures, force arrows, process cycles, comparison tables, labelled parts. Clean vector style matching the palette above. If you cannot draw something accurately, use a labelled layout instead rather than a decorative stock picture.

---

## Chapter structure

{$topicLines}

## Concepts to cover ({$chapter->chapter_name})

{$conceptLines}

---

*Generated by `lms:export-chapter-bundle`. When the .pptx is ready, download it and import with:*
`php artisan lms:import-deck {$chapter->id} "Presentation" <file.pptx>`
MD;
    }

    /**
     * Drop invalid UTF-8 bytes.
     *
     * Not cosmetic: full_intelegance_json fails json_decode with "Malformed
     * UTF-8 characters" on real rows in this database, and md_content carries
     * the same corruption from PDF extraction.
     */
    private function utf8(string $value): string
    {
        return mb_convert_encoding($value, 'UTF-8', 'UTF-8');
    }
}
