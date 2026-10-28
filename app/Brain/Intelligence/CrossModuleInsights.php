<?php

namespace App\Brain\Intelligence;

/**
 * Pure, deterministic arithmetic behind the cross-module insights.
 *
 * NO DATABASE, NO MODEL OUTPUT. Everything here takes figures the module services have already
 * computed (attendance by class, results by class) and relates them. That keeps the definitions in
 * one place - a class's attendance rate is AttendanceIntelligence's, a class's result percentage is
 * ResultIntelligence's - and makes every claim here testable with plain arrays.
 *
 * WHAT A CROSS-MODULE INSIGHT IS ALLOWED TO SAY. Two measures moving together across classes is an
 * ASSOCIATION. Nothing here states or implies a cause, and every insight carries the limits of the
 * evidence it stands on: how many classes were compared, how many could not be matched, and that the
 * two modules record different things for different students.
 */
final class CrossModuleInsights
{
    /** Fewer matched classes than this and a correlation is noise; the insight declines instead. */
    public const MIN_CLASSES = 6;

    /** |r| below this is reported as "no clear relationship", not as a weak one. */
    public const WEAK = 0.3;

    /** |r| at or above this is called strong. */
    public const STRONG = 0.6;

    /** The label two modules must agree on to be the same class: trimmed, case-folded, single-spaced. */
    public static function classKey(mixed $label): string
    {
        $text = preg_replace('/\s+/u', ' ', trim((string) $label)) ?? '';

        return mb_strtolower($text);
    }

    /**
     * Pair each class that appears in BOTH modules by its label. No fuzzy matching: a class one module
     * calls "6" and the other "Std 6" is reported as unmatched rather than guessed to be the same.
     *
     * @param  array<int,array<string,mixed>>  $attendance  rows with label, attendanceRate, students
     * @param  array<int,array<string,mixed>>  $results  rows with label, percentage, students
     * @return array{matched: array<int,array<string,mixed>>, attendanceOnly: array<int,string>, resultsOnly: array<int,string>}
     */
    public static function joinClasses(array $attendance, array $results): array
    {
        $resultsByKey = [];
        foreach ($results as $row) {
            if (($row['percentage'] ?? null) === null) {
                continue;
            }
            $resultsByKey[self::classKey($row['label'] ?? '')] = $row;
        }

        $matched = [];
        $attendanceOnly = [];
        $seen = [];
        foreach ($attendance as $row) {
            $key = self::classKey($row['label'] ?? '');
            if (($row['attendanceRate'] ?? null) === null) {
                continue;
            }
            if (! isset($resultsByKey[$key])) {
                $attendanceOnly[] = (string) $row['label'];
                continue;
            }
            $seen[$key] = true;
            $matched[] = [
                'class' => (string) $row['label'],
                'attendanceRate' => (float) $row['attendanceRate'],
                'attendanceStudents' => (int) ($row['students'] ?? 0),
                'resultPercentage' => (float) $resultsByKey[$key]['percentage'],
                'resultStudents' => (int) ($resultsByKey[$key]['students'] ?? 0),
            ];
        }

        $resultsOnly = [];
        foreach ($resultsByKey as $key => $row) {
            if (! isset($seen[$key])) {
                $resultsOnly[] = (string) $row['label'];
            }
        }

        return ['matched' => $matched, 'attendanceOnly' => $attendanceOnly, 'resultsOnly' => $resultsOnly];
    }

    /** Pearson correlation, or null when it is undefined (fewer than two points, or no variance). */
    public static function pearson(array $x, array $y): ?float
    {
        $n = count($x);
        if ($n < 2 || $n !== count($y)) {
            return null;
        }
        $mx = array_sum($x) / $n;
        $my = array_sum($y) / $n;
        $sxy = $sxx = $syy = 0.0;
        foreach ($x as $i => $xi) {
            $dx = $xi - $mx;
            $dy = $y[$i] - $my;
            $sxy += $dx * $dy;
            $sxx += $dx * $dx;
            $syy += $dy * $dy;
        }
        if ($sxx <= 0.0 || $syy <= 0.0) {
            return null;
        }

        return max(-1.0, min(1.0, $sxy / sqrt($sxx * $syy)));
    }

    /** Spearman rank correlation (Pearson on average ranks), robust to one extreme class. */
    public static function spearman(array $x, array $y): ?float
    {
        return self::pearson(self::ranks($x), self::ranks($y));
    }

    /** @return array<int,float> average ranks, ties share the mean of their positions */
    public static function ranks(array $values): array
    {
        $indexed = array_values($values);
        $order = array_keys($indexed);
        usort($order, static fn ($a, $b) => $indexed[$a] <=> $indexed[$b]);

        $ranks = [];
        $i = 0;
        $n = count($order);
        while ($i < $n) {
            $j = $i;
            while ($j + 1 < $n && $indexed[$order[$j + 1]] == $indexed[$order[$i]]) {
                $j++;
            }
            $avg = ($i + $j) / 2 + 1;
            for ($k = $i; $k <= $j; $k++) {
                $ranks[$order[$k]] = $avg;
            }
            $i = $j + 1;
        }
        ksort($ranks);

        return array_values($ranks);
    }

    /** "strong" / "moderate" / "none", from the size of the coefficient alone. */
    public static function strength(float $r): string
    {
        $abs = abs($r);

        return $abs >= self::STRONG ? 'strong' : ($abs >= self::WEAK ? 'moderate' : 'none');
    }

    /**
     * Attendance against results, class by class.
     *
     * @param  array<int,array<string,mixed>>  $attendanceByClass  AttendanceIntelligence::byStandard()
     * @param  array<int,array<string,mixed>>  $resultsByClass  ResultIntelligence::byClass()
     * @param  float|null  $institutionAttendance  AttendanceIntelligence::position()['attendanceRate']
     * @param  float|null  $institutionMean  ResultIntelligence::position()['meanPercentage']
     * @return array<string,mixed> an insight in the shape the Enterprise Brain renders
     */
    public static function attendanceVsResults(
        array $attendanceByClass,
        array $resultsByClass,
        ?float $institutionAttendance,
        ?float $institutionMean,
        ?string $syear
    ): array {
        $base = [
            'key' => 'attendance-vs-results',
            'title' => 'Attendance and results, class by class',
            'kind' => 'association',
            'period' => $syear === null ? null : "Academic year {$syear}",
            'sources' => [
                ['module' => 'attendance', 'label' => 'Attendance Intelligence', 'route' => '/attendance/intelligence',
                    'tables' => ['result_student_attendance_master', 'result_working_day_master']],
                ['module' => 'result', 'label' => 'Result Intelligence', 'route' => '/result/intelligence',
                    'tables' => ['result_personalize_marks']],
            ],
            'calculation' => 'Each class\'s attendance rate (days present ÷ working days, from Attendance Intelligence) is set beside '
                .'its mean result percentage (marks obtained ÷ maximum marks, from Result Intelligence). Classes are paired only when '
                .'both modules use the same class label. Spearman rank correlation is computed across the paired classes.',
            'limitations' => [
                'This is an association between classes, not a cause: it does not show that attendance drives results, or the reverse.',
                'Each point is a whole class; it says nothing about any individual student.',
                'Attendance and marks are recorded separately and may cover different students and different periods of the year.',
            ],
        ];

        $joined = self::joinClasses($attendanceByClass, $resultsByClass);
        $matched = $joined['matched'];
        $coverage = [
            'classesCompared' => count($matched),
            'attendanceOnly' => $joined['attendanceOnly'],
            'resultsOnly' => $joined['resultsOnly'],
        ];

        if ($attendanceByClass === [] || $resultsByClass === []) {
            $missing = $attendanceByClass === [] ? 'attendance' : 'result';

            return $base + [
                'status' => 'unavailable',
                'explanation' => "No class-wise {$missing} data was available for this year, so attendance and results cannot be compared.",
                'metrics' => [],
                'rows' => [],
                'coverage' => $coverage,
                'nextStep' => "Record {$missing} data for this academic year, or select a year that has it.",
            ];
        }

        if (count($matched) < self::MIN_CLASSES) {
            return $base + [
                'status' => 'insufficient_data',
                'explanation' => sprintf(
                    'Only %d class%s appear in both Attendance and Results under the same name; at least %d are needed before a pattern means anything.',
                    count($matched),
                    count($matched) === 1 ? '' : 'es',
                    self::MIN_CLASSES
                ),
                'metrics' => [],
                'rows' => $matched,
                'coverage' => $coverage,
                'nextStep' => 'Check that classes are named the same way in both modules. '
                    .($joined['attendanceOnly'] || $joined['resultsOnly'] ? 'Unmatched names are listed under coverage.' : ''),
            ];
        }

        $att = array_column($matched, 'attendanceRate');
        $res = array_column($matched, 'resultPercentage');
        $rho = self::spearman($att, $res);
        $r = self::pearson($att, $res);

        // Classes below the institute on BOTH measures: where to look first, with no claim about why.
        $both = [];
        foreach ($matched as $row) {
            if ($institutionAttendance !== null && $institutionMean !== null
                && $row['attendanceRate'] < $institutionAttendance && $row['resultPercentage'] < $institutionMean) {
                $both[] = $row;
            }
        }
        usort($both, static fn ($a, $b) => ($a['attendanceRate'] + $a['resultPercentage']) <=> ($b['attendanceRate'] + $b['resultPercentage']));

        if ($rho === null) {
            $explanation = 'The classes do not differ enough on one of the two measures to compare them.';
            $strength = null;
        } else {
            $strength = self::strength($rho);
            $explanation = match (true) {
                $strength === 'none' => sprintf('Across %d classes there is no clear relationship between attendance and results (rank correlation %.2f).', count($matched), $rho),
                $rho > 0 => sprintf('Across %d classes, those with higher attendance also tend to have higher results - a %s association (rank correlation %.2f). This does not show that one causes the other.', count($matched), $strength, $rho),
                default => sprintf('Across %d classes, those with higher attendance tend to have LOWER results - a %s association (rank correlation %.2f). That is unusual and is more likely a sign of how the data is recorded than of anything real; check the sources before acting on it.', count($matched), $strength, $rho),
            };
        }

        $nextStep = $both !== []
            ? sprintf('Look first at %s: below the institute on both attendance and results. Review each class in its own module before drawing a conclusion.',
                implode(', ', array_map(static fn ($row) => $row['class'], array_slice($both, 0, 3))))
            : 'No class is below the institute on both measures. Continue to review each module on its own.';

        return $base + [
            'status' => 'supported',
            'explanation' => $explanation,
            'metrics' => [
                'spearman' => $rho === null ? null : round($rho, 2),
                'pearson' => $r === null ? null : round($r, 2),
                'strength' => $strength,
                'classesCompared' => count($matched),
                'institutionAttendanceRate' => $institutionAttendance,
                'institutionMeanPercentage' => $institutionMean,
                'classesBelowOnBoth' => count($both),
            ],
            'rows' => $matched,
            'belowOnBoth' => array_map(static fn ($row) => $row['class'], $both),
            'coverage' => $coverage,
            'nextStep' => $nextStep,
        ];
    }
}
