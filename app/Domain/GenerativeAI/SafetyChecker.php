<?php

namespace App\Domain\GenerativeAI;

/**
 * Safety checks on both sides of a generation call.
 *
 * Two distinct jobs:
 *
 *  - `inspectPrompt()` runs before the call. It looks for instruction-injection
 *    patterns in *interpolated data* — a student's free-text answer, a comment, an
 *    imported field. This is the guard the estate declared as an interface
 *    (PromptSecurityGuard in the conversational package) and never implemented.
 *
 *  - `inspectOutput()` runs after. In a K-12 product the audience includes children,
 *    so output that leaks personal identifiers or reads as harmful is blocked rather
 *    than merely flagged.
 *
 * Neither is a substitute for the architecture: generated text still cannot become
 * evidence, whatever these checks say.
 */
class SafetyChecker
{
    /**
     * Phrases that only appear in data when someone is trying to talk to the model.
     */
    private const INJECTION_PATTERNS = [
        '/ignore\s+(all\s+)?(previous|prior|above)\s+instructions?/i',
        '/disregard\s+(the\s+)?(system|previous)\s+(prompt|instructions?)/i',
        '/you\s+are\s+now\s+(a|an)\s+/i',
        '/\bsystem\s*:\s*/i',
        '/<\|im_start\|>|<\|im_end\|>/i',
        '/reveal\s+(your\s+)?(system\s+)?prompt/i',
        '/print\s+(your\s+)?(instructions|system\s+prompt)/i',
    ];

    /**
     * Identifier shapes that must not be echoed back into generated content.
     */
    private const PII_PATTERNS = [
        'aadhaar' => '/\b\d{4}\s?\d{4}\s?\d{4}\b/',
        'email' => '/[\w.+-]+@[\w-]+\.[\w.]{2,}/',
        'phone' => '/\b(?:\+91[\-\s]?)?[6-9]\d{9}\b/',
        'ifsc' => '/\b[A-Z]{4}0[A-Z0-9]{6}\b/',
    ];

    private const HARMFUL_PATTERNS = [
        '/\b(kill|harm|hurt)\s+(yourself|himself|herself|themselves)\b/i',
        '/\b(worthless|stupid|useless)\s+(student|child|kid)\b/i',
    ];

    /**
     * @return array{passed:bool, findings:array<int, array{rule:string, detail:string}>}
     */
    public function inspectPrompt(array $variables, array $extraRules = []): array
    {
        $findings = [];

        foreach ($this->flatten($variables) as $path => $value) {
            foreach (self::INJECTION_PATTERNS as $pattern) {
                if (preg_match($pattern, $value)) {
                    $findings[] = [
                        'rule' => 'prompt.injection',
                        'detail' => sprintf('Variable "%s" contains instruction-like text.', $path),
                    ];

                    break;
                }
            }

            foreach ($extraRules as $rule) {
                $needle = is_array($rule) ? ($rule['contains'] ?? null) : $rule;

                if (is_string($needle) && $needle !== '' && stripos($value, $needle) !== false) {
                    $findings[] = [
                        'rule' => 'prompt.blocked_term',
                        'detail' => sprintf('Variable "%s" contains a blocked term.', $path),
                    ];
                }
            }
        }

        return ['passed' => $findings === [], 'findings' => $findings];
    }

    /**
     * @return array{passed:bool, findings:array<int, array{rule:string, detail:string}>}
     */
    public function inspectOutput(string $content, array $extraRules = []): array
    {
        $findings = [];

        foreach (self::PII_PATTERNS as $label => $pattern) {
            if (preg_match($pattern, $content)) {
                $findings[] = [
                    'rule' => 'output.pii',
                    'detail' => sprintf('Generated content appears to contain %s data.', $label),
                ];
            }
        }

        foreach (self::HARMFUL_PATTERNS as $pattern) {
            if (preg_match($pattern, $content)) {
                $findings[] = [
                    'rule' => 'output.harmful',
                    'detail' => 'Generated content contains language unsuitable for a school audience.',
                ];

                break;
            }
        }

        foreach ($extraRules as $rule) {
            $needle = is_array($rule) ? ($rule['contains'] ?? null) : $rule;

            if (is_string($needle) && $needle !== '' && stripos($content, $needle) !== false) {
                $findings[] = [
                    'rule' => 'output.blocked_term',
                    'detail' => 'Generated content contains a blocked term.',
                ];
            }
        }

        return ['passed' => $findings === [], 'findings' => $findings];
    }

    /**
     * @return array<string, string>
     */
    private function flatten(array $variables, string $prefix = ''): array
    {
        $flat = [];

        foreach ($variables as $key => $value) {
            $path = $prefix === '' ? (string) $key : $prefix . '.' . $key;

            if (is_array($value)) {
                $flat += $this->flatten($value, $path);

                continue;
            }

            if (is_scalar($value)) {
                $flat[$path] = (string) $value;
            }
        }

        return $flat;
    }
}
