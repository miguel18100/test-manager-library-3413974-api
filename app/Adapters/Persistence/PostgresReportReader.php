<?php

namespace App\Adapters\Persistence;

use App\Application\Ports\ReportReader;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

final class PostgresReportReader implements ReportReader
{
    /**
     * Q9 / ADR-004:
     * Una sola consulta agregada. Agrupa por book_id + book_title + category_name congelados.
     */
    public function mostBorrowed(DateTimeImmutable $fromInclusive, DateTimeImmutable $toExclusive): array
    {
        $rows = DB::table('library.loan_item as i')
            ->join('library.loan as l', 'l.id', '=', 'i.loan_id')
            ->where('l.loaned_at', '>=', $fromInclusive->format('Y-m-d H:i:sO'))
            ->where('l.loaned_at', '<', $toExclusive->format('Y-m-d H:i:sO'))
            ->groupBy('i.book_id', 'i.book_title', 'i.category_name')
            ->select(
                'i.book_id',
                'i.book_title',
                'i.category_name',
                DB::raw('COUNT(*)::int as times_borrowed')
            )
            ->orderBy('times_borrowed', 'desc')
            ->orderBy('i.book_title', 'asc')
            ->get();

        return $rows->map(function ($r) {
            return [
                'book_id'        => $r->book_id,
                'book_title'     => $r->book_title,
                'category_name'  => $r->category_name,
                'times_borrowed' => (int) $r->times_borrowed,
            ];
        })->all();
    }
}

