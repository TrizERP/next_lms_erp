<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Writes the mapping sheet the subject teacher reviews and signs: one CSV row per simulation-to-concept link, with the concept's own rating, so a
 * teacher can see at a glance which links stand on a concept the pipeline itself flagged.
 *
 *   php artisan lms:simulations-mapping-sheet                          (CSV to the screen)
 *   php artisan lms:simulations-mapping-sheet --out=storage/app/sim-mapping.csv
 *
 * READ ONLY. Columns: simulation, concept id, concept, chapter, topic ("(no topic)" for a concept that has none), standard, subject, concept
 * review_status (legacy, auto_accepted, flagged, low_confidence, verified, authored ...), citation, reason, link review_status, sign-off (blank).
 * A file written with --out starts with a UTF-8 byte-order mark so a spreadsheet reads curly apostrophes and accents correctly.
 */
class SimulationsMappingSheetCommand extends Command
{
    public const HEADER = ['simulation', 'concept_id', 'concept', 'chapter', 'topic', 'standard', 'subject', 'concept_review_status', 'citation', 'reason', 'link_review_status', 'sign_off'];

    protected $signature = 'lms:simulations-mapping-sheet
        {--out= : Write the CSV to this file instead of the screen}';

    protected $description = 'Writes the CSV mapping sheet (simulation, concept, chapter, topic, ratings, citation, reason, sign-off) for the subject teacher.';

    public function handle(): int
    {
        $rows = DB::table('lms_simulation_concept as l')
            ->join('lms_simulation as s', 's.id', '=', 'l.simulation_id')
            ->leftJoin('lms_concept as c', 'c.id', '=', 'l.concept_id')
            ->leftJoin('chapter_master as ch', 'ch.id', '=', 'l.chapter_id')
            ->leftJoin('topic_master as t', 't.id', '=', 'l.topic_id')
            ->leftJoin('standard as st', 'st.id', '=', 'l.standard_id')
            ->leftJoin('subject as su', 'su.id', '=', 'l.subject_id')
            ->whereNull('s.deleted_at')
            ->orderBy('s.title')->orderBy('l.sort_order')->orderBy('l.id')
            ->select('s.title as simulation', 'l.concept_id', 'c.name as concept', 'ch.chapter_name as chapter', 'l.topic_id', 't.name as topic', 'st.name as standard',
                'su.subject_name as subject', 'c.review_status as concept_review_status', 'l.citation', 'l.reason', 'l.review_status as link_review_status')
            ->get();

        $buffer = fopen('php://memory', 'w+b');
        fputcsv($buffer, self::HEADER);
        foreach ($rows as $r) {
            fputcsv($buffer, [
                $r->simulation, $r->concept_id, $r->concept, $r->chapter,
                $r->topic_id === null ? '(no topic)' : $r->topic,
                $r->standard, $r->subject, $r->concept_review_status, $r->citation, $r->reason, $r->link_review_status, '',
            ]);
        }
        rewind($buffer);
        $csv = (string) stream_get_contents($buffer);
        fclose($buffer);

        $out = $this->option('out');
        if ($out) {
            if (@file_put_contents((string) $out, "\xEF\xBB\xBF" . $csv) === false) {
                $this->error("Could not write {$out}.");

                return self::FAILURE;
            }
            $this->info($rows->count() . " link(s) written to {$out}.");
        } else {
            $this->output->write($csv);
        }

        return self::SUCCESS;
    }
}
