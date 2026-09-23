<?php

namespace App\Services\Mcp;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The library, as `library_books`, `library_items` and `library_book_circulations` record it.
 *
 * THREE TABLES, THREE DIFFERENT THINGS
 *
 *   `library_books`              the TITLE — one row per work, 35,663 across this estate
 *   `library_items`              the COPY — one row per physical item, with its item code
 *   `library_book_circulations`  the LOAN — one row per issue, 67,487 of them
 *
 * Confusing the first two is the easy mistake: a library with 35,663 titles does not have
 * 35,663 books on the shelf, and a title with four copies is one row here and four there.
 * So `catalogue()` reports `copies` from `library_items` beside each title and says which
 * figure is which.
 *
 * OVERDUE IS REAL, AND IT IS THE ONE JUDGEMENT THIS MODULE MAKES
 *
 * A loan is out when `return_date` is null, and overdue when `due_date` has also passed.
 * Both are recorded columns and the derivation is exact — 328 loans are currently out
 * across this estate. That makes it the one thing this module can conclude rather than
 * merely report.
 *
 * WHAT IS NOT RECORDED
 *
 * No fine, no penalty, no reservation queue, no renewal count and no reading history
 * beyond the loans themselves. So nothing here may state what anybody owes, whether a
 * book is reserved, or that a borrower is a poor one. A child with an overdue book has an
 * overdue book.
 *
 * SOFT DELETES ARE RESPECTED
 *
 * All three tables carry `deleted_at`, and a withdrawn title or a deleted loan is excluded
 * everywhere. A count that included them would disagree with every screen in the module.
 *
 * SCOPING
 *
 * `sub_institute_id` from the caller's token on every read, and `syear` on circulations,
 * which carry one. Every join carries the institute.
 */
class LibraryService
{
    /**
     * Titles in the catalogue, with how many copies each has.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function catalogue(McpRequestContext $context, array $filters): array
    {
        if (! Schema::hasTable('library_books')) {
            return ['count' => 0, 'titles' => [], 'note' => 'A library catalogue is not kept in this estate.'];
        }

        $limit = min(max((int) ($filters['limit'] ?? 50), 1), 200);

        $query = DB::table('library_books as b')
            ->where('b.sub_institute_id', $context->selectedInstituteId)
            ->whereNull('b.deleted_at');

        foreach (['language' => 'b.language', 'classification' => 'b.classification'] as $filter => $column) {
            $value = trim((string) ($filters[$filter] ?? ''));

            if ($value !== '') {
                $query->where($column, 'like', '%'.$value.'%');
            }
        }

        $search = trim((string) ($filters['search_text'] ?? ''));

        if ($search !== '') {
            $needle = '%'.$search.'%';
            $query->where(static function ($inner) use ($needle): void {
                $inner->where('b.title', 'like', $needle)
                    ->orWhere('b.author_name', 'like', $needle)
                    ->orWhere('b.isbn_issn', 'like', $needle)
                    ->orWhere('b.publisher_name', 'like', $needle);
            });
        }

        $total = (clone $query)->count();

        $rows = $query
            ->selectRaw('b.id, b.title, b.sub_title, b.author_name, b.publisher_name, b.isbn_issn,
                b.edition, b.publish_year, b.language, b.classification, b.call_number,
                b.material_resource_type, b.subject, b.standard, b.pages')
            ->orderBy('b.title')
            ->limit($limit)
            ->get();

        $copies = $this->copiesByBook($context, $rows->pluck('id')->map(static fn ($id) => (int) $id)->all());
        $onLoan = $this->onLoanByBook($context, $rows->pluck('id')->map(static fn ($id) => (int) $id)->all());

        // The estate-wide copy count, so a reader never mistakes titles for books.
        $itemTotal = Schema::hasTable('library_items')
            ? DB::table('library_items')->where('sub_institute_id', $context->selectedInstituteId)->whereNull('deleted_at')->count()
            : null;

        return [
            'count' => $total,
            'row_count' => $rows->count(),
            'titles_matched' => $total,
            'copies_held_in_this_institute' => $itemTotal,
            'figures_cover' => 'every title matching these filters, not only the rows listed',
            'titles' => $rows->map(static function ($row) use ($copies, $onLoan) {
                $id = (int) $row->id;

                return [
                    'book_id' => $id,
                    'title' => $row->title,
                    'sub_title' => $row->sub_title ?: null,
                    'author' => $row->author_name ?: null,
                    'publisher' => $row->publisher_name ?: null,
                    'isbn_issn' => $row->isbn_issn ?: null,
                    'edition' => $row->edition ?: null,
                    'published' => $row->publish_year ?: null,
                    'language' => $row->language ?: null,
                    'classification' => $row->classification ?: null,
                    'call_number' => $row->call_number ?: null,
                    'material_type' => $row->material_resource_type ?: null,
                    'subject' => $row->subject ?: null,
                    // One row here is one TITLE; these are the physical copies of it.
                    'copies' => $copies[$id] ?? 0,
                    'copies_currently_on_loan' => $onLoan[$id] ?? 0,
                ];
            })->all(),
            'rule' => 'One row is one TITLE, not one book on the shelf. `copies` is how many physical items '
                .'this institute holds of it and `copies_currently_on_loan` how many are out — never '
                .'report a count of titles as a count of books. Withdrawn titles are excluded. This '
                .'system records no fine, no reservation and no renewal, so never state what anybody owes '
                .'or that a title is reserved.',
        ];
    }

    /**
     * Loans, with the ones that are out and overdue.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function circulation(McpRequestContext $context, array $filters): array
    {
        if (! Schema::hasTable('library_book_circulations')) {
            return ['count' => 0, 'loans' => [], 'note' => 'Library circulation is not recorded in this estate.'];
        }

        $limit = min(max((int) ($filters['limit'] ?? 50), 1), 200);
        $query = $this->circulationQuery($context);

        foreach (['student_id' => 'c.student_id', 'book_id' => 'c.book_id'] as $filter => $column) {
            if (! empty($filters[$filter])) {
                $query->where($column, (int) $filters[$filter]);
            }
        }

        foreach (['from_date' => '>=', 'to_date' => '<='] as $filter => $operator) {
            $date = trim((string) ($filters[$filter] ?? ''));

            if ($date !== '') {
                $query->whereDate('c.issued_date', $operator, $date);
            }
        }

        $state = trim((string) ($filters['state'] ?? 'any'));

        if ($state === 'out') {
            $query->whereNull('c.return_date');
        } elseif ($state === 'overdue') {
            $this->onlyOverdue($query);
        } elseif ($state === 'returned') {
            $query->whereNotNull('c.return_date');
        }

        // Every figure over the whole filtered set.
        $total = (clone $query)->count();
        $out = (clone $query)->whereNull('c.return_date')->count();
        $overdue = (clone $query)->where(fn ($inner) => $this->onlyOverdue($inner))->count();

        $rows = $query
            ->selectRaw("c.id, c.book_id, c.item_code, c.student_id, c.issued_date, c.due_date, c.return_date,
                b.title AS book_title, b.author_name,
                CONCAT_WS(' ', s.first_name, s.middle_name, s.last_name) AS student_name,
                s.enrollment_no,
                DATEDIFF(CURDATE(), c.due_date) AS days_past_due")
            ->orderByDesc('c.issued_date')
            ->orderByDesc('c.id')
            ->limit($limit)
            ->get();

        return [
            'count' => $total,
            'row_count' => $rows->count(),
            'academic_year' => $context->academicYear,
            'currently_out' => $out,
            'overdue' => $overdue,
            'figures_cover' => 'every loan matching these filters, not only the rows listed',
            'loans' => $rows->map(static function ($row) {
                $returned = trim((string) ($row->return_date ?? '')) !== '';
                $daysPastDue = $row->days_past_due === null ? null : (int) $row->days_past_due;
                $due = trim((string) ($row->due_date ?? ''));

                return [
                    'loan_id' => (int) $row->id,
                    'book_id' => $row->book_id === null ? null : (int) $row->book_id,
                    // Null when the title is not in this institute's catalogue — a record
                    // to correct, not a title to borrow from elsewhere.
                    'book_title' => $row->book_title,
                    'author' => $row->author_name ?: null,
                    'item_code' => $row->item_code ?: null,
                    'student_id' => $row->student_id === null ? null : (int) $row->student_id,
                    'student_name' => trim((string) ($row->student_name ?? '')) ?: null,
                    'enrollment_no' => $row->enrollment_no ?: null,
                    'issued_on' => $row->issued_date,
                    'due_on' => $due !== '' ? $due : null,
                    'returned_on' => $returned ? $row->return_date : null,
                    'returned' => $returned,
                    // Overdue only where a due date exists and the book is still out.
                    'overdue' => ! $returned && $due !== '' && $daysPastDue !== null && $daysPastDue > 0,
                    'days_past_due' => $returned || $due === '' ? null : $daysPastDue,
                ];
            })->all(),
            'rule' => 'One row is one loan. A loan is OUT when no return date is recorded and OVERDUE when '
                .'the due date has also passed — both are recorded columns and the derivation is exact. A '
                .'loan with no due date is not overdue, it is undated. Deleted loans are excluded. This '
                .'system records NO fine, penalty, reservation or renewal, so never state what a borrower '
                .'owes, that a book is reserved, or that somebody is an unreliable borrower: an overdue '
                .'book is an overdue book.',
        ];
    }

    /** Out and past its due date. A loan with no due date is undated, not overdue. */
    private function onlyOverdue(Builder $query): Builder
    {
        return $query
            ->whereNull('c.return_date')
            ->whereNotNull('c.due_date')
            ->whereRaw("TRIM(c.due_date) <> ''")
            ->whereDate('c.due_date', '<', now()->toDateString());
    }

    /** The circulation join, scoped at every hop that carries an institute. */
    private function circulationQuery(McpRequestContext $context): Builder
    {
        $institute = $context->selectedInstituteId;

        $query = DB::table('library_book_circulations as c')
            ->leftJoin('library_books as b', function ($join) use ($institute) {
                $join->on('b.id', '=', 'c.book_id')->where('b.sub_institute_id', '=', $institute);
            })
            ->leftJoin('tblstudent as s', function ($join) use ($institute) {
                $join->on('s.id', '=', 'c.student_id')->where('s.sub_institute_id', '=', $institute);
            })
            ->where('c.sub_institute_id', $institute)
            ->whereNull('c.deleted_at');

        if ($context->academicYear !== null) {
            $query->where('c.syear', $context->academicYear);
        }

        return $query;
    }

    /**
     * Physical copies held of each title.
     *
     * @param  array<int, int>  $bookIds
     * @return array<int, int>
     */
    private function copiesByBook(McpRequestContext $context, array $bookIds): array
    {
        if ($bookIds === [] || ! Schema::hasTable('library_items')) {
            return [];
        }

        $counts = [];

        foreach (DB::table('library_items')
            ->where('sub_institute_id', $context->selectedInstituteId)
            ->whereNull('deleted_at')
            ->whereIn('book_id', $bookIds)
            ->selectRaw('book_id, COUNT(*) AS copies')
            ->groupBy('book_id')
            ->get() as $row) {
            $counts[(int) $row->book_id] = (int) $row->copies;
        }

        return $counts;
    }

    /**
     * Copies of each title currently out on loan.
     *
     * @param  array<int, int>  $bookIds
     * @return array<int, int>
     */
    private function onLoanByBook(McpRequestContext $context, array $bookIds): array
    {
        if ($bookIds === [] || ! Schema::hasTable('library_book_circulations')) {
            return [];
        }

        $counts = [];

        foreach (DB::table('library_book_circulations')
            ->where('sub_institute_id', $context->selectedInstituteId)
            ->whereNull('deleted_at')
            ->whereNull('return_date')
            ->whereIn('book_id', $bookIds)
            ->selectRaw('book_id, COUNT(*) AS loans')
            ->groupBy('book_id')
            ->get() as $row) {
            $counts[(int) $row->book_id] = (int) $row->loans;
        }

        return $counts;
    }
}
