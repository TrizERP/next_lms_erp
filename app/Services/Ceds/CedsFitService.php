<?php

namespace App\Services\Ceds;

use InvalidArgumentException;

/**
 * Teaching method (P1-P8) x resource format (D1-D6) fit, read from config/ceds_fit_matrix.php.
 *
 * Pure: no database, no framework state. The config array is injectable so tests
 * (and a future reviewed matrix) never need the application to boot. A weak pairing
 * is reported with a reason and the closest stronger alternative; it is never blocked.
 */
class CedsFitService
{
    public const STRONG = 'S';
    public const POSSIBLE = 'P';
    public const WEAK = 'W';

    private const RANK = [self::STRONG => 0, self::POSSIBLE => 1, self::WEAK => 2];

    /** @var array<string,mixed> */
    private array $config;

    /** @param array<string,mixed>|null $config defaults to config('ceds_fit_matrix') */
    public function __construct(?array $config = null)
    {
        $this->config = $config ?? (array) config('ceds_fit_matrix', []);
    }

    /** @return array<string,string> code => label */
    public function pedagogies(): array
    {
        return array_map(fn (array $p) => (string) $p['label'], $this->config['pedagogies']);
    }

    /** @return array<string,string> code => label */
    public function formats(): array
    {
        return array_map(fn (array $f) => (string) $f['label'], $this->config['formats']);
    }

    /** S, P or W for one pairing. */
    public function fit(string $pedagogy, string $format): string
    {
        $this->assertKnown($pedagogy, $format);

        return $this->config['matrix'][$pedagogy][$format];
    }

    /**
     * One pairing with everything a teacher needs to see: fit, qualifier note, and,
     * for a weak pairing, a one-line reason plus the nearest stronger alternatives.
     *
     * @return array<string,mixed>
     */
    public function assess(string $pedagogy, string $format): array
    {
        $fit = $this->fit($pedagogy, $format);
        $key = $pedagogy . '.' . $format;
        $out = [
            'pedagogy' => $pedagogy,
            'format' => $format,
            'fit' => $fit,
            'note' => $this->config['notes'][$key] ?? null,
            'warning' => null,
            'alternative_formats' => [],
            'alternative_pedagogies' => [],
        ];
        if ($fit === self::WEAK) {
            $out['warning'] = $this->config['weak_reasons'][$key] ?? 'This pairing is a weak fit.';
            $out['alternative_formats'] = $this->codesWithFit($this->config['matrix'][$pedagogy], self::STRONG, $format);
            $out['alternative_pedagogies'] = array_values(array_filter(
                array_keys($this->config['pedagogies']),
                fn (string $p) => $p !== $pedagogy && $this->config['matrix'][$p][$format] === self::STRONG
            ));
        }

        return $out;
    }

    /**
     * Every format for a method: strong first, then possible, then weak, each in
     * the D1..D6 order within its band.
     *
     * @return list<array<string,mixed>>
     */
    public function formatsForPedagogy(string $pedagogy): array
    {
        $this->assertKnown($pedagogy, null);

        return $this->ordered(array_keys($this->config['formats']), fn (string $d) => $this->assess($pedagogy, $d), 'format');
    }

    /**
     * Every method for a format, strong first.
     *
     * @return list<array<string,mixed>>
     */
    public function pedagogiesForFormat(string $format): array
    {
        $this->assertKnown(null, $format);

        return $this->ordered(array_keys($this->config['pedagogies']), fn (string $p) => $this->assess($p, $format), 'pedagogy');
    }

    /** The phases of a method as [name, weight%] pairs (the lesson-flow stage uses these). */
    public function phases(string $pedagogy): array
    {
        $this->assertKnown($pedagogy, null);

        return $this->config['pedagogies'][$pedagogy]['phases'];
    }

    public function defaultPeriodMinutes(): int
    {
        return (int) ($this->config['default_period_minutes'] ?? 40);
    }

    /**
     * @param list<string> $codes
     * @param callable(string):array<string,mixed> $assess
     * @param 'format'|'pedagogy' $key which field of a row holds the code
     * @return list<array<string,mixed>>
     */
    private function ordered(array $codes, callable $assess, string $key): array
    {
        $rows = array_map($assess, $codes);
        // usort is not guaranteed stable on older PHP, so the original position breaks ties.
        $position = array_flip($codes);
        usort($rows, function (array $a, array $b) use ($position, $key) {
            return [self::RANK[$a['fit']], $position[$a[$key]]] <=> [self::RANK[$b['fit']], $position[$b[$key]]];
        });

        return $rows;
    }

    /**
     * @param array<string,string> $row
     * @return list<string>
     */
    private function codesWithFit(array $row, string $fit, string $except): array
    {
        return array_values(array_filter(array_keys($row), fn (string $c) => $c !== $except && $row[$c] === $fit));
    }

    private function assertKnown(?string $pedagogy, ?string $format): void
    {
        if ($pedagogy !== null && !isset($this->config['pedagogies'][$pedagogy])) {
            throw new InvalidArgumentException("Unknown teaching method '$pedagogy'.");
        }
        if ($format !== null && !isset($this->config['formats'][$format])) {
            throw new InvalidArgumentException("Unknown resource format '$format'.");
        }
    }
}
