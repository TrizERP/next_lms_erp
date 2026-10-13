<?php

namespace App\Services\QuestionGeneration\H5p;

/**
 * Which catalogue formats are generated from an H5P content type, and by what.
 *
 * The map is code, like QuestionFormatRegistry::FORMATS: a prompt cannot live in a
 * catalogue row. What is switched on is config (config/question_formats.php, `h5p`):
 * a type is ACTIVE when the layer is enabled, the type is registered here, and its
 * code is listed in `types`. An inactive format is generated exactly as it was before
 * this layer existed.
 */
class H5pContentTypeRegistry
{
    /**
     * Phase 1 content types. Fill blank, drag text, matching, numerical, assertion &
     * reason, case study and the image types are added here, one class each.
     *
     * @var list<class-string<H5pContentType>>
     */
    public const TYPES = [
        H5pMultipleChoice::class,
        H5pTrueFalse::class,
    ];

    /** @var array<string, H5pContentType>|null */
    private ?array $types = null;

    /** @return array<string, H5pContentType> every implemented content type, by catalogue code */
    public function all(): array
    {
        if ($this->types === null) {
            $this->types = [];
            foreach (static::TYPES as $class) {
                $type = new $class();
                $this->types[$type->formatCode()] = $type;
            }
        }

        return $this->types;
    }

    public function get(string $code): ?H5pContentType
    {
        return $this->all()[$code] ?? null;
    }

    public function enabled(): bool
    {
        return (bool) config('question_formats.h5p.enabled', true);
    }

    /** The content type to generate $code from, or null when it is generated the old way. */
    public function activeFor(string $code): ?H5pContentType
    {
        return in_array($code, $this->activeCodes() ?? [], true) ? $this->get($code) : null;
    }

    /**
     * The catalogue codes offered and generated through H5P types, in registry order.
     * Null when the layer is off, meaning "no restriction".
     *
     * @return list<string>|null
     */
    public function activeCodes(): ?array
    {
        if (!$this->enabled()) {
            return null;
        }

        $configured = (array) config('question_formats.h5p.types', []);

        return array_values(array_filter(
            array_keys($this->all()),
            fn (string $code) => in_array($code, $configured, true)
        ));
    }

    /** Exactly this many questions are written per selected type; null when the layer is off. */
    public function questionsPerType(): ?int
    {
        return $this->enabled() ? max(1, (int) config('question_formats.h5p.questions_per_type', 2)) : null;
    }

    /**
     * The generatable-formats list as the picker should show it: EVERY implemented,
     * catalogued format, with the ones generated through an active H5P content type
     * tagged with the H5P type they play as. A format with no active H5P type is still
     * offered and is written by its own format class, exactly as before this layer.
     *
     * @param  list<array<string, mixed>>  $generatable
     * @return list<array<string, mixed>>
     */
    public function decorate(array $generatable): array
    {
        $active = $this->activeCodes();
        if ($active === null) {
            return $generatable;
        }

        foreach ($generatable as &$entry) {
            $type = in_array($entry['code'] ?? null, $active, true) ? $this->get($entry['code']) : null;
            if ($type !== null) {
                $entry['h5p_content_type'] = ['type' => $type->h5pType(), 'label' => $type->h5pLabel()];
            }
        }
        unset($entry);

        return $generatable;
    }
}
