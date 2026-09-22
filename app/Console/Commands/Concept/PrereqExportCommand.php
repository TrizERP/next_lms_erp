<?php

namespace App\Console\Commands\Concept;

use App\Services\Concept\GradeMap;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Dump one subject's concepts for classes 6-10 as a single ordered list.
 *
 * WHY THIS COMES FIRST
 * Prerequisites run vertically - a Class 10 concept needing a Class 7 one that needs
 * a Class 6 one - but every existing view of `lms_concept` shows one class and one
 * chapter at a time. Nobody deciding the links can see the chain they are deciding.
 * This produces the one artefact that makes the decision possible: every concept for
 * a subject, all five classes, in curriculum reading order, with its real id.
 *
 * IT IS READ-ONLY
 * Nothing here writes. Safe to run against the shared estate, which is the only
 * place the concept catalogue exists.
 *
 * IDS, NOT NAMES
 * The authored links reference `lms_concept.id` directly, so the id is the first
 * column and the name is there for the human. Concept names on this estate are
 * bespoke to the extraction run ("Why Five Handspans Disagree"), so keying the
 * authored data on them would not survive a re-extraction.
 */
class PrereqExportCommand extends Command
{
    protected $signature = 'concept:prereq-export
        {--subject= : subject_name as stored, e.g. Science}
        {--subject-id= : subject id, when the name is ambiguous}
        {--tenant=1 : sub_institute_id holding the concept catalogue}
        {--standards=6,7,8,9,10 : class numbers to include}
        {--format=txt : txt|json|csv}
        {--out= : output path; default storage/app/concept-prereq/}';

    protected $description = 'Export a subject\'s concepts for classes 6-10, for authoring prerequisites';

    public function handle(GradeMap $grades): int
    {
        $tenant = (int) $this->option('tenant');
        $subjectName = $this->option('subject');
        $subjectId = $this->option('subject-id') !== null ? (int) $this->option('subject-id') : null;

        if (! $subjectName && ! $subjectId) {
            $this->error('Give --subject=Science or --subject-id=N.');

            return self::FAILURE;
        }

        if ($subjectId === null) {
            $subjectId = $this->resolveSubject($subjectName, $tenant);

            if ($subjectId === null) {
                return self::FAILURE;
            }
        }

        $wanted = array_filter(array_map('intval', explode(',', (string) $this->option('standards'))));
        $standardToGrade = array_filter(
            $grades->forTenant($tenant),
            fn ($g) => in_array($g, $wanted, true)
        );

        if ($standardToGrade === []) {
            $this->error("No standards on tenant {$tenant} resolve to classes ".implode(', ', $wanted).'.');

            return self::FAILURE;
        }

        $rows = DB::table('lms_concept as c')
            ->leftJoin('chapter_master as ch', 'ch.id', '=', 'c.chapter_id')
            ->where('c.subject_id', $subjectId)
            ->where('c.sub_institute_id', $tenant)
            ->whereIn('c.standard_id', array_keys($standardToGrade))
            // Named explicitly: lms_concept has drifted from its migrations and
            // carries no difficulty_level, bloom_level, pedagogy_tag or lesson_id.
            ->select('c.id', 'c.name', 'c.description', 'c.standard_id', 'c.chapter_id',
                'ch.chapter_name', 'ch.sort_order')
            ->get();

        if ($rows->isEmpty()) {
            $this->warn('No concepts found for that scope.');

            return self::FAILURE;
        }

        $corpus = [];

        foreach ($rows as $row) {
            $corpus[] = [
                'id' => (int) $row->id,
                'grade' => $standardToGrade[(int) $row->standard_id] ?? null,
                'chapter_id' => $row->chapter_id !== null ? (int) $row->chapter_id : null,
                'chapter' => $row->chapter_name,
                'chapter_order' => $row->sort_order !== null ? (int) $row->sort_order : null,
                'concept' => $row->name,
            ];
        }

        // Curriculum reading order. `lms_concept` has no ordering column at all, so
        // within a chapter the only sequence available is insertion id - which
        // reflects when the extraction ran, and is the closest thing to book order.
        usort($corpus, fn ($a, $b) => [$a['grade'] ?? 99, $a['chapter_order'] ?? 999, $a['chapter_id'] ?? 0, $a['id']]
            <=> [$b['grade'] ?? 99, $b['chapter_order'] ?? 999, $b['chapter_id'] ?? 0, $b['id']]);

        $path = $this->write($corpus, $subjectName ?: "subject-{$subjectId}");

        $byGrade = [];

        foreach ($corpus as $row) {
            $byGrade[$row['grade'] ?? '?'] = ($byGrade[$row['grade'] ?? '?'] ?? 0) + 1;
        }

        ksort($byGrade);

        $this->table(['class', 'concepts'], array_map(
            fn ($g, $n) => [$g, $n],
            array_keys($byGrade),
            array_values($byGrade)
        ));

        $this->info('total '.count($corpus).' concepts  ->  '.$path);

        return self::SUCCESS;
    }

    /** Refuse to guess when a name matches several subjects - half a subject would export. */
    private function resolveSubject(string $name, int $tenant): ?int
    {
        $matches = DB::table('subject')
            ->where('sub_institute_id', $tenant)
            ->where('subject_name', $name)
            ->select('id', 'subject_name')
            ->get();

        if ($matches->isEmpty()) {
            $this->error("No subject named '{$name}' on tenant {$tenant}.");

            return null;
        }

        if ($matches->count() > 1) {
            $this->error("'{$name}' matches ".$matches->count().' subjects. Pass --subject-id:');

            foreach ($matches as $m) {
                $this->line("  {$m->id}  {$m->subject_name}");
            }

            return null;
        }

        return (int) $matches->first()->id;
    }

    private function write(array $corpus, string $label): string
    {
        $slug = strtolower(preg_replace('/[^A-Za-z0-9]+/', '-', $label));
        $dir = $this->option('out') ?: storage_path('app/concept-prereq');

        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $format = $this->option('format');
        $path = rtrim($dir, '/\\')."/concepts-{$slug}.{$format}";

        if ($format === 'json') {
            file_put_contents($path, json_encode($corpus, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return $path;
        }

        if ($format === 'csv') {
            $handle = fopen($path, 'w');
            fputcsv($handle, array_keys($corpus[0]));

            foreach ($corpus as $row) {
                fputcsv($handle, $row);
            }

            fclose($handle);

            return $path;
        }

        // Plain text, grouped by class and chapter. This is the format a person
        // actually reads while deciding links, which is the whole point of the file.
        $out = '';
        $lastChapter = null;

        foreach ($corpus as $row) {
            $key = $row['grade'].'|'.$row['chapter_id'];

            if ($key !== $lastChapter) {
                $out .= "\n=== CLASS {$row['grade']}  |  {$row['chapter']}  (chapter {$row['chapter_id']})\n";
                $lastChapter = $key;
            }

            $out .= str_pad((string) $row['id'], 8).$row['concept']."\n";
        }

        file_put_contents($path, ltrim($out));

        return $path;
    }
}
