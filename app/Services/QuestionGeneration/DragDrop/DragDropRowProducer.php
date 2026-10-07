<?php

namespace App\Services\QuestionGeneration\DragDrop;

use App\Services\QuestionGeneration\DragDrop\Contracts\DiagramImageFinder;
use App\Services\QuestionGeneration\DragDrop\Contracts\DiagramImageStore;
use App\Services\QuestionGeneration\DragDrop\Contracts\DiagramVisionAnalyzer;

/**
 * Turns "N image-based drag and drop questions for this concept" into question rows.
 *
 *   find a picture -> check it -> ask a vision model where its parts are
 *   -> turn those boxes into zones -> validate -> keep a copy of the picture -> row
 *
 * The language model is never asked for a coordinate. The vision model reports where
 * things are in the picture it was shown; this class converts and validates that, and
 * drops anything that does not hold up. When nothing holds up it returns nothing for
 * that question and says why. It does not fall back to guessed positions.
 *
 * One picture makes one question: every question has its own picture.
 */
class DragDropRowProducer
{
    public function __construct(
        private readonly DiagramImageFinder $finder,
        private readonly DiagramImageStore $store,
        private readonly DiagramVisionAnalyzer $analyzer,
        private readonly DragDropGeometry $geometry,
        /** @var callable(array<string, mixed>): string builds the vision prompt from the context */
        private readonly mixed $promptFor,
        private readonly int $maxImages = 3,
        private readonly int $attemptsPerQuestion = 3,
    ) {
    }

    /**
     * @param array<int, array<string, mixed>> $batchQuota
     * @param array<string, mixed>             $slice
     * @param array<string, mixed>             $context
     *
     * @return array{ok: bool, error?: string, rows?: array<int, array<string, mixed>>, underfilled?: bool, reason?: string|null}
     */
    public function produce(array $batchQuota, array $slice, array $context): array
    {
        $wanted = [];
        foreach ($batchQuota as $quotaRow) {
            for ($n = 0; $n < (int) ($quotaRow['count'] ?? 0); $n++) {
                $wanted[] = $quotaRow;
            }
        }
        $capped = count($wanted) > $this->maxImages;
        $wanted = array_slice($wanted, 0, max(1, $this->maxImages));

        $rows = [];
        $tried = [];
        $notes = [];

        foreach ($wanted as $index => $quotaRow) {
            $built = $this->buildOne($quotaRow, $slice, $context, $tried);
            if ($built['row'] !== null) {
                $rows[] = $built['row'];
            } else {
                $notes[] = 'Question ' . ($index + 1) . ': ' . $built['why'];
            }
        }

        if ($rows === []) {
            return [
                'ok' => false,
                'error' => 'No suitable diagram could be found for this concept. ' . implode(' ', array_slice($notes, 0, 2)),
            ];
        }

        $reason = $notes;
        if ($capped) {
            $reason[] = "At most {$this->maxImages} image questions are made per request.";
        }

        return [
            'ok' => true,
            'rows' => $rows,
            'underfilled' => $notes !== [] || $capped,
            'reason' => $reason === [] ? null : implode(' ', $reason),
        ];
    }

    /**
     * @param array<string, mixed> $quotaRow
     * @param array<string, mixed> $slice
     * @param array<string, mixed> $context
     * @param array<int, string>   $tried  source image URLs already attempted, updated in place
     *
     * @return array{row: array<string, mixed>|null, why: string}
     */
    private function buildOne(array $quotaRow, array $slice, array $context, array &$tried): array
    {
        $why = 'no picture was found';

        for ($attempt = 0; $attempt < $this->attemptsPerQuestion; $attempt++) {
            $image = $this->finder->find($context, $tried);
            if ($image === null) {
                break;
            }
            $tried[] = $image['image_url'];

            $imageWhy = $this->geometry->imageReason($image['width'], $image['height'], $image['mime'], strlen($image['bytes']));
            if ($imageWhy !== null) {
                $why = ucfirst($imageWhy) . '.';
                continue;
            }

            $analysis = $this->analyzer->analyze(($this->promptFor)($context), $image['bytes'], $image['mime']);
            if (!($analysis['ok'] ?? false)) {
                $why = 'the picture could not be analysed (' . ($analysis['error'] ?? 'unknown') . ').';
                continue;
            }
            if (empty($analysis['usable'])) {
                $why = 'the picture has no distinct parts to label.';
                continue;
            }
            if (!empty($analysis['has_printed_labels'])) {
                $why = 'the picture already shows its labels.';
                continue;
            }

            $zones = $this->zonesFrom($analysis['parts'] ?? []);
            if (count($zones) < $this->geometry->limits()['min_zones']) {
                $why = 'fewer than ' . $this->geometry->limits()['min_zones'] . ' parts could be located reliably.';
                continue;
            }

            $payload = $this->payload($image, $zones, $analysis['alt'] ?? null, $context);
            $problem = $this->geometry->reason($payload);
            if ($problem !== null) {
                $why = $problem . '.';
                continue;
            }

            // Only now, with a question certain to be written, keep a copy of the picture.
            try {
                $payload['image']['url'] = $this->store->store($image['bytes'], $image['mime']);
            } catch (\Throwable $e) {
                $why = 'the picture could not be saved.';
                continue;
            }

            return ['row' => $this->row($quotaRow, $slice, $context, $payload), 'why' => ''];
        }

        return ['row' => null, 'why' => $why];
    }

    /**
     * Boxes on the model's 0-1000 scale -> zones in percent of the image.
     * A part that is malformed, outside the picture, the wrong size or on top of one
     * already accepted is dropped on its own; the rest are kept.
     *
     * @param array<int, mixed> $parts
     * @return array<int, array<string, mixed>>
     */
    private function zonesFrom(array $parts): array
    {
        $max = $this->geometry->limits()['max_zones'];
        $zones = [];
        $seen = [];

        foreach ($parts as $part) {
            if (!is_array($part) || !is_string($part['label'] ?? null)) {
                continue;
            }
            $label = trim(preg_replace('/\s+/u', ' ', $part['label']) ?? '');
            $key = mb_strtolower($label);
            $box = $part['box_2d'] ?? null;
            if ($label === '' || isset($seen[$key]) || !is_array($box) || count($box) !== 4) {
                continue;
            }
            foreach ($box as $value) {
                if (!is_int($value) && !is_float($value)) {
                    continue 2;
                }
            }
            [$ymin, $xmin, $ymax, $xmax] = array_map('floatval', array_values($box));
            if ($ymin < 0 || $xmin < 0 || $ymax > 1000 || $xmax > 1000 || $ymax <= $ymin || $xmax <= $xmin) {
                continue;
            }

            $zone = [
                'id'     => 'z' . (count($zones) + 1),
                'label'  => $label,
                'x'      => round($xmin / 10, 1),
                'y'      => round($ymin / 10, 1),
                'width'  => round(($xmax - $xmin) / 10, 1),
                'height' => round(($ymax - $ymin) / 10, 1),
            ];
            if ($this->geometry->zoneReason($zone, count($zones)) !== null) {
                continue;
            }
            foreach ($zones as $accepted) {
                if ($this->geometry->overlap($accepted, $zone) > $this->geometry->limits()['max_overlap']) {
                    continue 2;
                }
            }

            $seen[$key] = true;
            $zones[] = $zone;
            if (count($zones) >= $max) {
                break;
            }
        }

        return $zones;
    }

    /**
     * @param array<string, mixed> $image
     * @param array<int, array<string, mixed>> $zones
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function payload(array $image, array $zones, ?string $alt, array $context): array
    {
        $elements = [];
        foreach ($zones as $i => $zone) {
            $elements[] = ['id' => 'e' . ($i + 1), 'text' => $zone['label'], 'zone_ids' => [$zone['id']]];
        }

        $alt = trim((string) ($alt ?? '')) !== '' ? trim((string) $alt) : (trim((string) ($image['title'] ?? '')) ?: (string) ($context['concept_name'] ?? 'the diagram'));

        return [
            'v'         => 1,
            'image'     => [
                // Filled with the stored copy's URL once the question is certain.
                'url'         => $image['image_url'],
                'width_px'    => $image['width'],
                'height_px'   => $image['height'],
                'mime'        => $image['mime'],
                'alt'         => mb_substr($alt, 0, 200),
                'provider'    => $image['provider'] ?? null,
                'licence'     => $image['licence'] ?? null,
                'creator'     => $image['creator'] ?? null,
                'attribution' => $image['attribution'] ?? null,
                'source_url'  => $image['source_url'] ?? null,
                'sha1'        => sha1($image['bytes']),
            ],
            'image_fit' => 'contain',
            'zones'     => $zones,
            'elements'  => $elements,
        ];
    }

    /**
     * @param array<string, mixed> $quotaRow
     * @param array<string, mixed> $slice
     * @param array<string, mixed> $context
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function row(array $quotaRow, array $slice, array $context, array $payload): array
    {
        $concept = (string) ($context['concept_name'] ?? 'this concept');
        $alt = $payload['image']['alt'];
        $count = count($payload['zones']);
        $knowledge = $this->strings($slice['knowledge_items'] ?? []);
        $outcomes = $this->strings($slice['learning_outcomes'] ?? []);
        $misconceptions = $this->strings($slice['misconceptions'] ?? []);

        $key = array_column($payload['zones'], 'label');

        return [
            'question_title'   => mb_substr("Label the diagram: {$alt}. Drag each label onto the matching part of the picture.", 0, 400),
            'description'      => mb_substr("Tests identification of {$count} parts in a diagram of {$alt} at the {$quotaRow['level']} level.", 0, 240),
            'subconcept'       => mb_substr($knowledge[0] ?? $concept, 0, 240),
            'points'           => $count,
            'multiple_answer'  => 0,
            'hint_text'        => null,
            'learning_outcome' => $outcomes !== [] ? array_slice($outcomes, 0, 3) : [$concept],
            'answer'           => [
                'v'                      => 'ans-2.0',
                'question_type'          => 'drag_drop',
                'sub_type'               => 'Drag and Drop',
                'bloom_level'            => $quotaRow['level'],
                'dok_level'              => (int) $quotaRow['dok'],
                'difficulty'             => $quotaRow['difficulty'],
                'stimulus'               => null,
                'estimated_time_seconds' => 30 + 20 * $count,
                'drag_drop'              => $payload,
                'model_answer'           => implode('; ', $key),
                'explanation'            => 'Each label names the part of the diagram it is placed on.',
                'remediation'            => "Show the diagram of {$alt} again and name each part as it is pointed to.",
                'knowledge_refs'         => [$knowledge[0] ?? $concept],
                'ability_ref'            => null,
                'misconception_refs'     => $misconceptions !== [] ? [$misconceptions[0]] : [],
            ],
        ];
    }

    /**
     * The plain strings in a slice list, whatever the list's items look like.
     *
     * @param mixed $items
     * @return array<int, string>
     */
    private function strings($items): array
    {
        $out = [];
        foreach (is_array($items) ? $items : [] as $item) {
            if (is_array($item)) {
                foreach (['knowledge', 'outcome', 'misconception', 'name', 'text', 'title'] as $key) {
                    if (is_string($item[$key] ?? null) && trim($item[$key]) !== '') {
                        $item = $item[$key];
                        break;
                    }
                }
            }
            if (is_string($item) && trim($item) !== '') {
                $out[] = trim($item);
            }
        }

        return $out;
    }
}
