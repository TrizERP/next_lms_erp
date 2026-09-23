<?php

namespace App\Services\PAL\Support;

use InvalidArgumentException;

/**
 * Coerce and bounds-check one administrator-supplied setting against its
 * declared field descriptor.
 *
 * Lifted verbatim from App\Services\PAL\Administration\ArchitectureRegistry,
 * where it was a private method, so that the flow validator and the
 * architecture registry cannot drift apart. Two subsystems that both accept
 * "a number between 1 and 5 from an admin screen" must agree on what that
 * means, and the way to guarantee that is one implementation rather than two
 * careful ones.
 *
 * ArchitectureRegistry keeps its private coerce() and delegates here, so no
 * call site of its own changes and its existing tests cover the extraction.
 *
 * Every rejection names the offending field, because the message is surfaced
 * verbatim by the admin UI — see the contract at ArchitectureRegistry.php:102-105.
 */
class SettingCoercer
{
    /** Longest free-text value accepted, matching the original. */
    private const MAX_TEXT_LENGTH = 1000;

    /** Longest single tag, matching the original. */
    private const MAX_TAG_LENGTH = 64;

    /**
     * @param  array<string,mixed>  $descriptor  the field descriptor: type, min, max, step, options
     * @param  mixed  $raw  what the client submitted
     * @param  mixed  $fallback  the shipped default, used only to disambiguate an empty string
     * @param  string  $label  human-readable field name, quoted back in every error
     *
     * @throws InvalidArgumentException when the value is not of the declared
     *         type or falls outside the declared bounds.
     */
    public static function coerce(array $descriptor, mixed $raw, mixed $fallback, string $label): mixed
    {
        $type = (string) ($descriptor['type'] ?? 'text');

        switch ($type) {
            case 'toggle':
                return $raw === true || $raw === 1 || $raw === '1' || $raw === 'true';

            case 'number':
                if (! is_numeric($raw)) {
                    throw new InvalidArgumentException("{$label} must be a number.");
                }
                $number = (float) $raw;

                if (isset($descriptor['min']) && $number < (float) $descriptor['min']) {
                    throw new InvalidArgumentException("{$label} cannot be below {$descriptor['min']}.");
                }
                if (isset($descriptor['max']) && $number > (float) $descriptor['max']) {
                    throw new InvalidArgumentException("{$label} cannot be above {$descriptor['max']}.");
                }

                // Keep integers integral so a round-trip does not turn 3 into 3.0.
                $isIntegral = ! isset($descriptor['step']) || fmod((float) $descriptor['step'], 1.0) === 0.0;

                return $isIntegral && fmod($number, 1.0) === 0.0 ? (int) $number : $number;

            case 'select':
                $options = array_map('strval', (array) ($descriptor['options'] ?? []));
                $choice = (string) $raw;
                if (! in_array($choice, $options, true)) {
                    throw new InvalidArgumentException("{$label} must be one of: " . implode(', ', $options) . '.');
                }

                return $choice;

            case 'tags':
                if (! is_array($raw)) {
                    throw new InvalidArgumentException("{$label} must be a list.");
                }
                $tags = [];
                foreach ($raw as $tag) {
                    $tag = trim((string) $tag);
                    if ($tag !== '') {
                        $tags[] = mb_substr($tag, 0, self::MAX_TAG_LENGTH);
                    }
                }

                return array_values(array_unique($tags));

            case 'code':
            case 'text':
            default:
                $text = trim((string) $raw);
                if (mb_strlen($text) > self::MAX_TEXT_LENGTH) {
                    throw new InvalidArgumentException("{$label} is too long (" . self::MAX_TEXT_LENGTH . ' characters maximum).');
                }

                // An empty string is a legitimate "inherit the default" for the
                // optional per-agent model pin, so it is kept rather than
                // replaced by the fallback.
                return $text === '' && $fallback === null ? '' : $text;
        }
    }
}
