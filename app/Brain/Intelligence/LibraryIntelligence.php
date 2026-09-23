<?php

namespace App\Brain\Intelligence;

use App\Brain\Support\AcademicYear;
use App\Brain\Support\SchemaCache;
use Illuminate\Support\Facades\DB;

/**
 * Library Intelligence — one institute, one academic year.
 *
 * READS REAL BOOK CIRCULATION & CATALOGUE DATA FROM vivek_erp.
 *
 * Primary tables:
 * - `library_book_circulations` (book issue/return transactions per year)
 * - `library_books` (catalogue metadata, titles, authors)
 * - `library_items` (physical accessions and copy statuses)
 */
final class LibraryIntelligence
{
    private const CIRCULATION_TABLE = 'library_book_circulations';
    private const BOOKS_TABLE = 'library_books';
    private const ITEMS_TABLE = 'library_items';

    private readonly string $tenantId;
    private readonly ?string $syear;

    private array $memo = [];

    public function __construct(string $tenantId, ?string $syear)
    {
        $this->tenantId = (string) $tenantId;
        $this->syear = $syear === null || $syear === '' ? null : (string) $syear;
    }

    public function syear(): ?string
    {
        return $this->syear;
    }

    public function tenantId(): string
    {
        return $this->tenantId;
    }

    public function coverage(): array
    {
        return $this->memo['coverage'] ??= $this->computeCoverage();
    }

    private function computeCoverage(): array
    {
        if ($this->syear === null) {
            return [
                'available' => false,
                'reason' => 'No academic year was specified or resolved for this institute.',
                'sourceTable' => self::CIRCULATION_TABLE,
                'totalRows' => 0,
                'usableRows' => 0,
            ];
        }

        if (!SchemaCache::hasTable(self::CIRCULATION_TABLE)) {
            return [
                'available' => false,
                'reason' => "Table '" . self::CIRCULATION_TABLE . "' does not exist.",
                'sourceTable' => self::CIRCULATION_TABLE,
                'totalRows' => 0,
                'usableRows' => 0,
            ];
        }

        $totalRows = $this->scopedCirculations()
            ->count();

        if ($totalRows === 0) {
            return [
                'available' => false,
                'reason' => $this->yearWindow() === null
                    ? "No library loans carry academic year {$this->syear}, and this institute has no term dates on "
                        .'file for it, so loans that carry no year of their own cannot be scoped to it either.'
                    : "No library loans were issued for academic year {$this->syear}.",
                'sourceTable' => self::CIRCULATION_TABLE,
                'totalRows' => 0,
                'usableRows' => 0,
            ];
        }

        return [
            'available' => true,
            'reason' => null,
            'sourceTable' => self::CIRCULATION_TABLE . ' · ' . self::BOOKS_TABLE,
            'totalRows' => $totalRows,
            'usableRows' => $totalRows,
        ];
    }

    public function position(): ?array
    {
        return $this->memo['position'] ??= $this->computePosition();
    }

    private function computePosition(): ?array
    {
        $cov = $this->coverage();
        if (!$cov['available']) {
            return null;
        }

        $summary = $this->scopedCirculations()
            ->selectRaw('
                count(*) as total_circulations,
                count(distinct student_id) as active_borrowers,
                count(distinct book_id) as distinct_titles,
                count(case when return_date is not null and return_date != "" then 1 end) as returned_count,
                count(case when return_date is null or return_date = "" then 1 end) as active_loans,
                count(case when (return_date is null or return_date = "") and due_date < CURDATE() then 1 end) as overdue_count
            ')
            ->first();

        $total = (int) ($summary->total_circulations ?? 0);
        if ($total === 0) {
            return null;
        }

        $borrowers = (int) ($summary->active_borrowers ?? 0);
        $titles = (int) ($summary->distinct_titles ?? 0);
        $returned = (int) ($summary->returned_count ?? 0);
        $activeLoans = (int) ($summary->active_loans ?? 0);
        $overdue = (int) ($summary->overdue_count ?? 0);

        $returnRate = round(($returned / $total) * 100, 1);
        $avgIssuesPerBorrower = $borrowers > 0 ? round($total / $borrowers, 1) : 0.0;

        return [
            'totalCirculations' => $total,
            'activeBorrowers' => $borrowers,
            'distinctTitles' => $titles,
            'returnedCount' => $returned,
            'returnRate' => $returnRate,
            'activeLoans' => $activeLoans,
            'overdueCount' => $overdue,
            'avgIssuesPerBorrower' => $avgIssuesPerBorrower,
        ];
    }

    public function byTitle(): array
    {
        return $this->memo['byTitle'] ??= $this->computeByTitle();
    }

    private function computeByTitle(): array
    {
        $cov = $this->coverage();
        if (!$cov['available']) {
            return [];
        }

        $rows = $this->scopedCirculations('c')
            ->leftJoin(self::BOOKS_TABLE . ' as b', 'b.id', '=', 'c.book_id')
            ->groupBy('c.book_id', 'b.title', 'b.author_name')
            ->select(
                'c.book_id',
                DB::raw('COALESCE(b.title, CONCAT("Book #", c.book_id)) as title'),
                DB::raw('COALESCE(b.author_name, "—") as author'),
                DB::raw('count(*) as issues'),
                DB::raw('count(case when c.return_date is null or c.return_date = "" then 1 end) as on_loan')
            )
            ->orderByDesc('issues')
            ->limit(20)
            ->get();

        $result = [];
        foreach ($rows as $r) {
            $result[] = [
                'key' => (string) $r->book_id,
                'label' => (string) $r->title,
                'author' => (string) $r->author,
                'issues' => (int) $r->issues,
                'onLoan' => (int) $r->on_loan,
            ];
        }

        return $result;
    }

    public function byStatus(): array
    {
        $pos = $this->position();
        if ($pos === null) {
            return [];
        }

        $total = $pos['totalCirculations'];
        $cleanActive = max(0, $pos['activeLoans'] - $pos['overdueCount']);

        return [
            [
                'key' => 'returned',
                'label' => 'Successfully returned',
                'count' => $pos['returnedCount'],
                'share' => round(($pos['returnedCount'] / max(1, $total)) * 100, 1),
            ],
            [
                'key' => 'active_current',
                'label' => 'Active loan (within due date)',
                'count' => $cleanActive,
                'share' => round(($cleanActive / max(1, $total)) * 100, 1),
            ],
            [
                'key' => 'overdue',
                'label' => 'Overdue (past due date)',
                'count' => $pos['overdueCount'],
                'share' => round(($pos['overdueCount'] / max(1, $total)) * 100, 1),
            ],
        ];
    }

    public function dataQuality(): array
    {
        $cov = $this->coverage();
        if (!$cov['available']) {
            return [];
        }

        $unlinkedBook = $this->scopedCirculations('c')
            ->leftJoin(self::BOOKS_TABLE . ' as b', 'b.id', '=', 'c.book_id')
            ->whereNull('b.id')
            ->count();

        $longOverdue = $this->scopedCirculations()
            ->where(function ($q) {
                $q->whereNull('return_date')->orWhere('return_date', '');
            })
            ->where('due_date', '<', now()->subDays(180)->toDateString())
            ->count();

        return [
            [
                'key' => 'unlinked_books',
                'label' => 'Catalogue reference mapping',
                'count' => $unlinkedBook,
                'status' => $unlinkedBook > 0 ? 'warning' : 'clean',
                'description' => $unlinkedBook > 0
                    ? "{$unlinkedBook} circulation logs reference unindexed book IDs."
                    : 'All circulation logs link to valid title master records.',
            ],
            [
                'key' => 'dormant_unreturned',
                'label' => 'Unreconciled loans (>180 days)',
                'count' => $longOverdue,
                'status' => $longOverdue > 0 ? 'warning' : 'clean',
                'description' => $longOverdue > 0
                    ? "{$longOverdue} books unreturned for more than 180 days require stock write-off audit."
                    : 'No dormant unreturned book anomalies detected.',
            ],
        ];
    }

    /**
     * Loans belonging to this academic year.
     *
     * ── WHY THIS IS NOT JUST `WHERE syear = ?` ──────────────────────────────
     *
     * `library_book_circulations.syear` IS OFTEN NULL. At the largest institute
     * 36,851 of its 36,977 loans carry no `syear` at all, every one of them with
     * a real `issued_date` between 2014 and 2025. Filtering on `syear` alone
     * showed that institute 126 loans for the year — three tenths of one per
     * cent of its circulation — and every figure on the screen, from the
     * borrower count to the overdue rate, was computed over that fraction.
     *
     * A loan therefore belongs to the year when it SAYS so, or — where it says
     * nothing — when it was issued inside the year's own dates, read from this
     * institute's `academic_year` rows. The two are kept separate deliberately:
     * rows that carry a `syear` are trusted over the calendar, because at one
     * institute the two conventions disagree and the stored value is the one
     * the library itself assigned. Verified against both: 254/2025 goes from
     * 126 to 663 loans, while 47/2024 and 47/2025 are unchanged at 10,426 and
     * 1,064.
     */
    private function scopedCirculations(string $alias = ''): \Illuminate\Database\Query\Builder
    {
        $table = $alias === '' ? self::CIRCULATION_TABLE : self::CIRCULATION_TABLE.' as '.$alias;
        $prefix = $alias === '' ? '' : $alias.'.';
        $window = $this->yearWindow();

        return DB::table($table)
            ->where($prefix.'sub_institute_id', $this->tenantId)
            ->whereNull($prefix.'deleted_at')
            ->where(function ($q) use ($prefix, $window) {
                $q->where($prefix.'syear', $this->syear);

                if ($window !== null) {
                    $q->orWhere(function ($inner) use ($prefix, $window) {
                        $inner->whereNull($prefix.'syear')
                            ->whereBetween($prefix.'issued_date', [$window['start'], $window['end']]);
                    });
                }
            });
    }

    /** @return array{start:string,end:string}|null */
    public function yearWindow(): ?array
    {
        return $this->memo['window'] ??= AcademicYear::window($this->tenantId, $this->syear);
    }


    /**
     * The catalogue against what has ever left the shelf.
     *
     * Deliberately NOT year-scoped: "this title has never been borrowed" is a
     * statement about its whole life, and scoping it to one year would call
     * every book nobody happened to take this term dormant.
     *
     * @return array{titles:?int,everBorrowed:?int,neverBorrowed:?int,dormantShare:?float}
     */
    public function catalogueReach(): array
    {
        return $this->memo['catalogue'] ??= (function (): array {
            if (! SchemaCache::hasTable(self::BOOKS_TABLE)) {
                return ['titles' => null, 'everBorrowed' => null, 'neverBorrowed' => null, 'dormantShare' => null];
            }

            $titles = (int) DB::table(self::BOOKS_TABLE)
                ->where('sub_institute_id', $this->tenantId)
                ->count();

            if ($titles === 0) {
                return ['titles' => null, 'everBorrowed' => null, 'neverBorrowed' => null, 'dormantShare' => null];
            }

            $everBorrowed = (int) DB::table(self::CIRCULATION_TABLE)
                ->where('sub_institute_id', $this->tenantId)
                ->whereNull('deleted_at')
                ->whereNotNull('book_id')
                ->where('book_id', '>', 0)
                ->distinct()
                ->count('book_id');

            // A loan can outlive the title it points at, so the count of titles
            // ever borrowed is capped at the catalogue rather than allowed to
            // produce a negative dormant figure.
            $everBorrowed = min($everBorrowed, $titles);

            return [
                'titles' => $titles,
                'everBorrowed' => $everBorrowed,
                'neverBorrowed' => $titles - $everBorrowed,
                'dormantShare' => round(($titles - $everBorrowed) / $titles * 100, 1),
            ];
        })();
    }

    /**
     * How far past their due date the open loans are.
     *
     * This is the figure that decides what an overdue loan MEANS. A book a week
     * late is a reminder; a book a year late is a book the library no longer
     * has, and the same count covers both.
     *
     * @return array{overdue:int,medianDaysOver:?int,worstDaysOver:?int,borrowers:int}
     */
    public function overdueProfile(): array
    {
        return $this->memo['overdue'] ??= (function (): array {
            $rows = $this->scopedCirculations()
                ->whereNull('return_date')
                ->whereNotNull('due_date')
                ->whereRaw('due_date < CURDATE()')
                ->orderByRaw('DATEDIFF(CURDATE(), due_date)')
                ->get(['student_id', DB::raw('DATEDIFF(CURDATE(), due_date) as days_over')]);

            if ($rows->isEmpty()) {
                return ['overdue' => 0, 'medianDaysOver' => null, 'worstDaysOver' => null, 'borrowers' => 0];
            }

            $days = $rows->pluck('days_over')->map(static fn ($d) => (int) $d)->all();

            return [
                'overdue' => count($days),
                'medianDaysOver' => $days[(int) floor(count($days) / 2)],
                'worstDaysOver' => $days[count($days) - 1],
                'borrowers' => $rows->pluck('student_id')->unique()->count(),
            ];
        })();
    }

}

