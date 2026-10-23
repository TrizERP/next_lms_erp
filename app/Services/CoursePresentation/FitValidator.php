<?php

namespace App\Services\CoursePresentation;

/**
 * Method-fit validation for one chapter: is the chosen teaching method right for THIS chapter, can the chosen format
 * deliver it, are the required sources there, and can the existing pipeline and viewers carry the combination?
 *
 * Educational fit and technical support are kept apart: `method_fit` and `format_fit` say whether the combination
 * is good teaching; `generation_supported`, `generation_blockers` and `viewer_type` say whether it can be built and
 * shown today. fit_status is "unsupported" only when the format has no generator at all.
 *
 * Evidence, nothing else:
 *   - method vs chapter: the chapter's own analysis (MethodSuitability)
 *   - method vs format: the CEDS fit matrix (config); a choice with no CEDS counterpart is "not evaluated"
 *   - sources: SourceRegistry statuses
 *   - generator / viewer: config format_capability and pedagogy_support, verified against the code base
 */
class FitValidator
{
    public function __construct(private Options $options)
    {
    }

    /**
     * @param array<int,array<string,mixed>> $sources SourceRegistry sources
     * @param array<int,array<string,mixed>> $existing SourceRegistry existing_resources
     * @param array<string,array<string,mixed>> $methodFit MethodSuitability::evaluate() for every method
     * @param array{model_available?:bool,active_run?:?array<string,mixed>,busy_run?:?array<string,mixed>} $context what only the caller can know: is a model reachable, is a run of THIS teaching method and format already in progress (active_run), is a run of another choice being made right now (busy_run)
     * @return array<string,mixed>
     */
    public function validate(string $pedagogy, string $format, array $sources, array $existing, array $methodFit, array $context = []): array
    {
        $labels = $this->options->pedagogies();
        $formats = $this->options->formats();
        $methodLabel = $labels[$pedagogy];
        $formatLabel = $formats[$format]['label'];
        $hasGenerator = $formats[$format]['generator'] !== null;
        $cap = (array) config("course_presentation_pilot.format_capability.$format", []);

        $mf = $methodFit[$pedagogy];
        [$ff, $ffNote] = $this->formatFit($pedagogy, $format);

        $required = (array) ($cap['required_sources'] ?? []);
        $byKey = [];
        foreach ($sources as $s) {
            $byKey[$s['key']] = $s;
        }
        $missing = [];
        $incomplete = [];
        foreach ($required as $key) {
            $s = $byKey[$key] ?? null;
            if ($s === null || in_array($s['status'], ['missing', 'conflicting'], true)) {
                $missing[] = ['key' => $key, 'label' => $s['label'] ?? $key, 'status' => $s['status'] ?? 'missing'];
            } elseif ($s['status'] === 'incomplete') {
                $incomplete[] = ['key' => $key, 'label' => $s['label'], 'status' => 'incomplete'];
            }
        }

        // A duplicate is a stored item of THIS format AND THIS teaching method: a presentation stored for another method (or for
        // none) does not make this combination a duplicate, nor does a story lesson make a presentation one.
        $duplicate = in_array($format, ['interactive_slides', 'branching_scenario'], true)
            && array_filter($existing, fn ($e) => ($e['format'] ?? 'interactive_slides') === $format && ($e['method'] ?? null) === $pedagogy) !== [];
        $template = $this->template($format, $byKey, (int) ($context['concept_count'] ?? 0), $context['story_template'] ?? null);

        // ---- fit_status
        if (!$hasGenerator) {
            $status = 'unsupported';
        } elseif ($mf['verdict'] === 'not_suitable' || $ff === 'weak') {
            $status = 'unsuitable';
        } elseif ($mf['verdict'] === 'suitable' && in_array($ff, ['strong', 'possible'], true)) {
            $status = 'suitable';
        } else {
            $status = 'partially_suitable';
        }

        $reason = $this->reason($status, $methodLabel, $formatLabel, $mf, $ff, $ffNote, $formats[$format]['message'], $pedagogy, $format);

        // ---- technical support (separate from fit)
        $pedagogyApplied = $hasGenerator && !empty($cap['pedagogy_applied']);
        $trigger = $hasGenerator && !empty($cap['trigger']) && (bool) config('course_presentation_pilot.generation.enabled', false);
        $activeRun = $context['active_run'] ?? null;
        $blockers = [];
        if (!$hasGenerator) {
            $blockers[] = "No generator exists for {$formatLabel}.";
        }
        if ($hasGenerator && !$pedagogyApplied) {
            $blockers[] = 'The generator takes no teaching method as an input, so the selected method would not shape the content.';
        }
        if ($hasGenerator && !$trigger) {
            $blockers[] = (string) ($cap['pending_note'] ?? 'No application endpoint starts this generator; it is run from the command line by an administrator.');
        }
        if ($hasGenerator && $trigger && !($context['model_available'] ?? false)) {
            $blockers[] = 'No model access is configured for the generator. An administrator needs to add it before a presentation can be generated.';
        }
        if ($activeRun !== null) {
            $blockers[] = 'A draft for this teaching method and format is already ' . ($activeRun['state'] === 'ready' ? 'waiting for review' : $activeRun['state']) . '.';
        }
        // A draft of ANOTHER choice does not block this one while it waits for review; only while it is being made, because the
        // background job is unique per chapter and a second one started meanwhile would never run.
        $busyRun = $context['busy_run'] ?? null;
        if ($activeRun === null && $busyRun !== null) {
            $blockers[] = 'Another draft for this chapter (' . ($busyRun['label'] ?? 'a different choice') . ') is still being made. Start this one when it has finished.';
        }
        if ($missing) {
            $blockers[] = 'Required source material is missing: ' . implode(', ', array_column($missing, 'label')) . '.';
        }
        if ($duplicate) {
            $blockers[] = $format === 'branching_scenario'
                ? 'A story lesson already exists for this chapter and teaching method; a second one would be a duplicate.'
                : 'A presentation already exists for this chapter and teaching method; a second one would be a duplicate.';
        }

        return [
            'fit_status' => $status,
            'reason' => $reason,
            'method_fit' => ['verdict' => $mf['verdict'], 'message' => $mf['message'], 'concepts_recommending' => $mf['concepts_recommending'], 'concepts_analysed' => $mf['concepts_analysed']],
            'format_fit' => ['status' => $ff, 'note' => $ffNote],
            'recommendations' => in_array($status, ['suitable'], true) ? [] : $this->recommendations($pedagogy, $format, $status, $methodFit),
            'missing_sources' => $missing,
            'incomplete_sources' => $incomplete,
            'duplicate' => $duplicate,
            'generation_supported' => $hasGenerator && $trigger && $pedagogyApplied && $blockers === [],
            'active_run' => $activeRun === null ? null : ['run' => $activeRun['run'], 'state' => $activeRun['state']],
            'generation_blockers' => $blockers,
            'viewer_type' => $cap['viewer'] ?? null,
            'viewer_label' => $cap['viewer_label'] ?? null,
            'admin_commands' => (array) ($cap['admin_commands'] ?? []),
            'template' => $template,
            // Where no template exists the screen offers Create; it is possible only where a generator can really be started.
            'create' => ['label' => 'Create', 'available' => $hasGenerator && $trigger && $pedagogyApplied && $blockers === [], 'reasons' => $blockers],
            // Only a combination that is educationally unsuitable stops here. An unsupported format continues, so the screen can say what is missing and offer Create.
            'continue_allowed' => $status !== 'unsuitable',
        ];
    }

    /**
     * Can this format open straight from the stored data, with no AI call?
     *
     * @param array<string,array<string,mixed>> $sources by key
     * @return array{available:bool,kind:?string,requirements:array<int,string>,blocked_by:array<int,string>}
     */
    private function template(string $format, array $sources, int $concepts, ?array $story = null): array
    {
        $t = (array) config("course_presentation_pilot.templates.$format", []);
        // A story template is a reusable design registered in Template Management and bound to the study-deck player; the story itself
        // is written per chapter, so there is nothing to open from stored data and the screen offers Create when that step is connected.
        if (($t['kind'] ?? null) === 'story_template') {
            $c = (array) ($story['config'] ?? []);

            return [
                'available' => false, 'kind' => 'story_template', 'requirements' => [(string) $t['requires']], 'blocked_by' => [],
                'registered' => ['key' => (string) ($c['key'] ?? $t['template_key']), 'name' => (string) ($c['name'] ?? ''), 'version' => (int) ($c['version'] ?? 0), 'source' => (string) ($story['source'] ?? 'built_in'), 'template_id' => $story['template_id'] ?? null, 'player' => (string) ($c['player'] ?? '')],
            ];
        }
        if (empty($t['kind'])) {
            return ['available' => false, 'kind' => null, 'requirements' => [(string) ($t['requires'] ?? 'No template exists for this format.')], 'blocked_by' => []];
        }
        $blocked = [];
        if (!in_array($sources['textbook']['status'] ?? 'missing', ['present', 'incomplete'], true)) {
            $blocked[] = 'The chapter\'s source material is ' . (($sources['textbook']['status'] ?? 'missing') === 'conflicting' ? 'in conflict' : 'missing') . '.';
        }
        if ($concepts < 1) {
            $blocked[] = 'The chapter has no concepts.';
        }

        return ['available' => $blocked === [], 'kind' => $t['kind'], 'requirements' => (array) $t['requires'], 'blocked_by' => $blocked];
    }

    /** @return array{0:string,1:?string} strong|possible|weak|not_evaluated and the matrix's note */
    private function formatFit(string $pedagogy, string $format): array
    {
        $p = config("course_presentation_pilot.ceds_method_code.$pedagogy");
        $d = config("course_presentation_pilot.ceds_format_code.$format");
        $cell = ($p && $d) ? config("course_presentation_pilot.ceds_matrix.$p.$d") : null;
        if ($cell === null) {
            return ['not_evaluated', null];
        }
        // Keys like "P2.D2" contain a dot, so these two maps are read as arrays, not with config()'s dot path.
        $note = config('course_presentation_pilot.ceds_notes')["$p.$d"] ?? null;
        if ($cell === 'W') {
            $note = (config('course_presentation_pilot.ceds_weak_reasons')["$p.$d"] ?? null) ?: $note;
        }

        return [['S' => 'strong', 'P' => 'possible', 'W' => 'weak'][$cell], $note];
    }

    private function reason(string $status, string $method, string $format, array $mf, string $ff, ?string $ffNote, string $formatMessage, string $pedagogy, string $formatKey): string
    {
        $parts = [];
        $k = $mf['concepts_recommending'];
        $n = $mf['concepts_analysed'];

        // Teaching method vs this chapter
        $parts[] = match ($mf['verdict']) {
            'suitable' => "{$method} suits this chapter: its analysis recommends it for {$k} of {$n} analysed concepts.",
            'not_suitable' => "{$method} does not suit this chapter: its analysis recommends it for " . ($k === 0 ? "none of {$n}" : "only {$k} of {$n}") . ' analysed concepts.',
            'unconfirmed' => "The chapter's analysis does not evaluate {$method}, so it cannot be confirmed as suitable.",
            default => "{$method} cannot be checked: this chapter has no readable teaching analysis.",
        };

        // Teaching method vs format
        $parts[] = match ($ff) {
            'strong' => "The CEDS fit matrix rates {$method} with {$format} a strong fit." . ($ffNote ? " {$ffNote}" : ''),
            'possible' => "The CEDS fit matrix rates {$method} with {$format} possible but not ideal." . ($ffNote ? " {$ffNote}" : ''),
            'weak' => "The CEDS fit matrix rates {$method} with {$format} a weak fit." . ($ffNote ? " {$ffNote}" : ''),
            default => "The CEDS fit matrix does not cover {$method} with {$format}, so that pairing is not evaluated.",
        };

        if ($status === 'unsupported') {
            $parts[] = $formatMessage;
        }

        return implode(' ', $parts);
    }

    /**
     * Alternatives that the evidence justifies: other methods that suit the chapter (and are not weak with the
     * format), and other formats that have a generator (and are not weak with the method).
     *
     * @param array<string,array<string,mixed>> $methodFit
     * @return array<int,array<string,mixed>>
     */
    private function recommendations(string $pedagogy, string $format, string $status, array $methodFit): array
    {
        $out = [];
        $methodUnfit = $methodFit[$pedagogy]['verdict'] !== 'suitable';
        [$ff] = $this->formatFit($pedagogy, $format);
        $hasGenerator = $this->options->formats()[$format]['generator'] !== null;

        if ($methodUnfit || $ff === 'weak') {
            $candidates = [];
            foreach ($this->options->pedagogies() as $key => $label) {
                if ($key === $pedagogy || $methodFit[$key]['verdict'] !== 'suitable') {
                    continue;
                }
                [$fit] = $this->formatFit($key, $format);
                if ($fit === 'weak') {
                    continue;
                }
                $candidates[] = ['type' => 'method', 'key' => $key, 'label' => $label, 'concepts_recommending' => $methodFit[$key]['concepts_recommending'],
                    'reason' => "The chapter's analysis recommends it for {$methodFit[$key]['concepts_recommending']} of {$methodFit[$key]['concepts_analysed']} analysed concepts."
                        . ($fit === 'not_evaluated' ? '' : " It is a {$fit} fit with this format in the CEDS matrix.")];
            }
            usort($candidates, fn ($a, $b) => $b['concepts_recommending'] <=> $a['concepts_recommending']);
            $out = array_merge($out, array_slice($candidates, 0, 2));
        }

        if (!$hasGenerator || $ff === 'weak') {
            $formats = [];
            foreach ($this->options->formats() as $key => $f) {
                if ($key === $format || $f['generator'] === null) {
                    continue;
                }
                [$fit] = $this->formatFit($pedagogy, $key);
                if ($fit === 'weak') {
                    continue;
                }
                $formats[] = ['type' => 'format', 'key' => $key, 'label' => $f['label'], 'concepts_recommending' => null,
                    'reason' => 'A generator exists for it.' . ($fit === 'not_evaluated' ? '' : " It is a {$fit} fit with this teaching method in the CEDS matrix.")];
            }
            $out = array_merge($out, array_slice($formats, 0, 2));
        }

        return $out;
    }
}
