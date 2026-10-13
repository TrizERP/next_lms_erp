<?php

namespace App\Services\QuestionGeneration\DragDrop;

/**
 * The rules a stored image-based Drag & Drop payload must satisfy, in one place.
 *
 * Pure: no framework, no I/O. The generator runs it on what the vision step produced,
 * and the format's validateRow() runs it again on every row, so a payload that reaches
 * lms_question_master has passed the same checks whichever way it was built.
 *
 * Geometry is percentages (0-100) of the image: the convention the manual editor
 * already stores in h5p_drag_drop_zones (position_x / position_y / width / height).
 * With image_fit "contain" the canvas takes the image's aspect ratio, so 1% of the
 * canvas is 1% of the image and nothing here depends on a browser pixel.
 */
final class DragDropGeometry
{
    public const DEFAULTS = [
        'min_zones'         => 3,
        'max_zones'         => 8,
        'min_zone_pct'      => 4.0,
        'max_zone_pct'      => 60.0,
        // Largest share of the SMALLER zone another zone may cover. Overlap that heavy
        // makes "which part is this?" unanswerable for the learner.
        'max_overlap'       => 0.2,
        'min_image_width'   => 400,
        'min_image_height'  => 300,
        'max_image_bytes'   => 5242880,
        'mime_types'        => ['image/jpeg', 'image/png', 'image/webp'],
        'max_label_chars'   => 40,
    ];

    /** @var array<string, mixed> */
    private array $limits;

    /** @param array<string, mixed> $limits overrides for DEFAULTS */
    public function __construct(array $limits = [])
    {
        $this->limits = array_replace(self::DEFAULTS, $limits);
    }

    /** @return array<string, mixed> */
    public function limits(): array
    {
        return $this->limits;
    }

    /** Why an image cannot be used, or null. */
    public function imageReason(int $width, int $height, string $mime, int $bytes): ?string
    {
        if (!in_array(strtolower($mime), $this->limits['mime_types'], true)) {
            return "image type {$mime} is not supported";
        }
        if ($width < $this->limits['min_image_width'] || $height < $this->limits['min_image_height']) {
            return "image is {$width}x{$height}; at least {$this->limits['min_image_width']}x{$this->limits['min_image_height']} is needed";
        }
        if ($bytes <= 0 || $bytes > $this->limits['max_image_bytes']) {
            return 'image file size is outside the allowed range';
        }

        return null;
    }

    /**
     * Why a payload is not a playable Drag & Drop, or null when it is.
     *
     * @param array<string, mixed> $dd the object stored at answer.drag_drop
     */
    public function reason(array $dd): ?string
    {
        $image = $dd['image'] ?? null;
        if (!is_array($image)) {
            return 'drag_drop.image is missing';
        }
        $url = $image['url'] ?? null;
        if (!is_string($url) || !preg_match('#^https?://#i', $url)) {
            return 'drag_drop.image.url must be an http(s) URL';
        }
        $w = $image['width_px'] ?? null;
        $h = $image['height_px'] ?? null;
        if (!is_int($w) || !is_int($h)) {
            return 'drag_drop.image needs integer width_px and height_px';
        }
        $imageReason = $this->imageReason($w, $h, (string) ($image['mime'] ?? 'image/jpeg'), 1);
        if ($imageReason !== null) {
            return 'drag_drop.' . $imageReason;
        }

        $zones = $dd['zones'] ?? null;
        $elements = $dd['elements'] ?? null;
        if (!is_array($zones) || !array_is_list($zones) || !is_array($elements) || !array_is_list($elements)) {
            return 'drag_drop.zones and drag_drop.elements must be lists';
        }
        $count = count($zones);
        if ($count < $this->limits['min_zones'] || $count > $this->limits['max_zones']) {
            return "drag_drop needs {$this->limits['min_zones']} to {$this->limits['max_zones']} zones, got {$count}";
        }

        $zoneIds = [];
        $labels = [];
        foreach ($zones as $i => $zone) {
            $reason = $this->zoneReason($zone, $i);
            if ($reason !== null) {
                return $reason;
            }
            if (isset($zoneIds[$zone['id']])) {
                return "drag_drop zone id \"{$zone['id']}\" is used twice";
            }
            $zoneIds[$zone['id']] = true;
            $key = mb_strtolower(trim($zone['label']));
            if (isset($labels[$key])) {
                return "drag_drop zone label \"{$zone['label']}\" is used twice";
            }
            $labels[$key] = true;
        }

        for ($a = 0; $a < $count; $a++) {
            for ($b = $a + 1; $b < $count; $b++) {
                if ($this->overlap($zones[$a], $zones[$b]) > $this->limits['max_overlap']) {
                    return "drag_drop zones \"{$zones[$a]['label']}\" and \"{$zones[$b]['label']}\" overlap too much";
                }
            }
        }

        $elementIds = [];
        $covered = [];
        foreach ($elements as $i => $element) {
            if (!is_array($element) || !is_string($element['id'] ?? null) || $element['id'] === '') {
                return "drag_drop element {$i} needs a string id";
            }
            if (isset($elementIds[$element['id']])) {
                return "drag_drop element id \"{$element['id']}\" is used twice";
            }
            $elementIds[$element['id']] = true;
            $text = $element['text'] ?? null;
            if (!is_string($text) || trim($text) === '' || mb_strlen($text) > $this->limits['max_label_chars']) {
                return "drag_drop element \"{$element['id']}\" needs text of 1 to {$this->limits['max_label_chars']} characters";
            }
            $targets = $element['zone_ids'] ?? null;
            if (!is_array($targets) || $targets === []) {
                return "drag_drop element \"{$element['id']}\" maps to no zone";
            }
            foreach ($targets as $zoneId) {
                if (!is_string($zoneId) || !isset($zoneIds[$zoneId])) {
                    return "drag_drop element \"{$element['id']}\" maps to unknown zone";
                }
                $covered[$zoneId] = true;
            }
        }
        foreach (array_keys($zoneIds) as $zoneId) {
            if (!isset($covered[$zoneId])) {
                return "drag_drop zone \"{$zoneId}\" has no correct element";
            }
        }

        return null;
    }

    /** @param mixed $zone */
    public function zoneReason($zone, int $i): ?string
    {
        if (!is_array($zone) || !is_string($zone['id'] ?? null) || $zone['id'] === '') {
            return "drag_drop zone {$i} needs a string id";
        }
        $label = $zone['label'] ?? null;
        if (!is_string($label) || trim($label) === '' || mb_strlen($label) > $this->limits['max_label_chars']) {
            return "drag_drop zone \"{$zone['id']}\" needs a label of 1 to {$this->limits['max_label_chars']} characters";
        }
        foreach (['x', 'y', 'width', 'height'] as $key) {
            if (!isset($zone[$key]) || !is_int($zone[$key]) && !is_float($zone[$key]) || is_nan((float) $zone[$key])) {
                return "drag_drop zone \"{$zone['id']}\" needs a numeric {$key}";
            }
        }
        [$x, $y, $w, $h] = [(float) $zone['x'], (float) $zone['y'], (float) $zone['width'], (float) $zone['height']];
        if ($x < 0 || $y < 0 || $x + $w > 100.0001 || $y + $h > 100.0001) {
            return "drag_drop zone \"{$zone['id']}\" lies outside the image";
        }
        $min = $this->limits['min_zone_pct'];
        $max = $this->limits['max_zone_pct'];
        if ($w < $min || $h < $min || $w > $max || $h > $max) {
            return "drag_drop zone \"{$zone['id']}\" must be between {$min}% and {$max}% of the image in each direction";
        }

        return null;
    }

    /** Intersection area as a share of the smaller zone's area, 0 to 1. */
    public function overlap(array $a, array $b): float
    {
        $w = min($a['x'] + $a['width'], $b['x'] + $b['width']) - max($a['x'], $b['x']);
        $h = min($a['y'] + $a['height'], $b['y'] + $b['height']) - max($a['y'], $b['y']);
        if ($w <= 0 || $h <= 0) {
            return 0.0;
        }
        $smaller = min($a['width'] * $a['height'], $b['width'] * $b['height']);

        return $smaller > 0 ? ($w * $h) / $smaller : 1.0;
    }
}
