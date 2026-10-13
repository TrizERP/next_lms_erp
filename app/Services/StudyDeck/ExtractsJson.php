<?php

namespace App\Services\StudyDeck;

use RuntimeException;

/** Model replies are JSON wrapped in prose or code fences often enough to need one tolerant reader. */
trait ExtractsJson
{
    /** @return array<string,mixed> */
    protected function extractJson(string $reply): array
    {
        $text = trim($reply);
        $text = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', $text);

        $decoded = json_decode((string) $text, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        $start = strpos($text, '{');
        $end = strrpos($text, '}');
        if ($start !== false && $end !== false && $end > $start) {
            $decoded = json_decode(substr($text, $start, $end - $start + 1), true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        throw new RuntimeException('The model reply was not valid JSON: ' . mb_substr($text, 0, 200));
    }
}
