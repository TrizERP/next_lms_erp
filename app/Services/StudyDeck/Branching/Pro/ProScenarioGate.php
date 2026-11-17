<?php

namespace App\Services\StudyDeck\Branching\Pro;

/**
 * Decides whether a chapter uses the pro Branching Scenario format (see config/study_deck_pro_branching.php).
 *
 * Pure: it reads the chapter row it is given and the configuration, and nothing else. A chapter that is not named by ALL three lists
 * (standard, subject, chapter number; `*` names everything) is never sent down the new path, and a subject on the exclusion list never is.
 */
final class ProScenarioGate
{
    /**
     * @param array<string,mixed> $chapter standard_name, subject_name, sort_order (or number)
     * @param array<string,mixed>|null $config the configuration (default: config('study_deck_pro_branching'))
     */
    public static function applies(array $chapter, ?array $config = null): bool
    {
        $config ??= function_exists('config') ? (array) config('study_deck_pro_branching', []) : [];
        if (empty($config['enabled'])) {
            return false;
        }

        $standard = self::norm((string) ($chapter['standard_name'] ?? ''));
        $subject = self::norm((string) ($chapter['subject_name'] ?? $chapter['subject'] ?? ''));
        $number = (int) ($chapter['sort_order'] ?? $chapter['number'] ?? 0);
        if ($standard === '' || $subject === '') {
            return false;
        }
        if (in_array($subject, array_map(self::norm(...), array_map('strval', (array) ($config['exclude_subjects'] ?? []))), true)) {
            return false;
        }

        $chapters = array_map('strval', (array) ($config['chapter_numbers'] ?? []));

        return self::listed($standard, (array) ($config['standards'] ?? []))
            && self::listed($subject, (array) ($config['subjects'] ?? []))
            && (in_array('*', $chapters, true) || ($number > 0 && in_array((string) $number, $chapters, true)));
    }

    /** True when stored story-format scenarios of this chapter are shown in the new format too. */
    public static function convertsExisting(array $chapter, ?array $config = null): bool
    {
        $config ??= function_exists('config') ? (array) config('study_deck_pro_branching', []) : [];

        return !empty($config['convert_existing']) && self::applies($chapter, $config);
    }

    /** @param array<int,mixed> $list */
    private static function listed(string $value, array $list): bool
    {
        $list = array_map(fn ($v) => self::norm((string) $v), $list);

        return in_array('*', $list, true) || in_array($value, $list, true);
    }

    private static function norm(string $s): string
    {
        // "8", "Std 8" and "Standard 8" are the same standard; "Science" and "science" the same subject.
        $s = mb_strtolower(trim($s));
        $s = (string) preg_replace('/^(standard|std\.?|class|grade)\s*/u', '', $s);

        return trim($s);
    }
}
