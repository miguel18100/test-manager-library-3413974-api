<?php

namespace App\Adapters\Persistence;

use App\Application\Ports\LoanRepository;
use App\Domain\DomainError;
use App\Domain\Loan;
use App\Domain\LoanItem;
use DateTimeImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

final class PostgresLoanRepository implements LoanRepository
{
    public function add(Loan $loan): void
    {
        try {
            DB::table('library.loan')->insert([
                'id'                => $loan->id,
                'member_id'         => $loan->memberId,
                'loaned_by_user_id' => $loan->loanedByUserId,
                'loaned_at'         => $loan->loanedAt->format('Y-m-d H:i:sO'),
            ]);

            foreach ($loan->items() as $item) {
                DB::table('library.loan_item')->insert([
                    'id'            => $item->id,
                    'loan_id'       => $loan->id,
                    'book_id'       => $item->bookId,
                    'book_title'    => $item->bookTitle,
                    'isbn'          => $item->isbn,
                    'category_name' => $item->categoryName,
                    'due_at'        => $item->dueAt->format('Y-m-d H:i:sO'),
                    'returned_at'   => null,
                ]);

                // Descontar ejemplar en la base de datos (invariante atómica)
                $updated = DB::table('library.book')
                    ->where('id', $item->bookId)
                    ->where('available_copies', '>=', 1)
                    ->decrement('available_copies', 1);

                if ($updated === 0) {
                    throw new DomainError(
                        DomainError::BOOK_NOT_AVAILABLE,
                        "No quedan ejemplares disponibles de «{$item->bookTitle}».",
                        [['field' => 'book_id', 'issue' => 'sin ejemplares']],
                    );
                }
            }
        } catch (QueryException $e) {
            if (str_contains($e->getMessage(), 'ck_book_available_range')) {
                throw new DomainError(
                    DomainError::BOOK_NOT_AVAILABLE,
                    'No quedan ejemplares disponibles del libro solicitado.',
                );
            }
            throw $e;
        }
    }

    public function get(string $id): ?Loan
    {
        $loanRow = DB::table('library.loan')->where('id', $id)->first();
        if (! $loanRow) {
            return null;
        }

        $itemsRows = DB::table('library.loan_item')->where('loan_id', $id)->get();

        $items = [];
        foreach ($itemsRows as $r) {
            $dueAt = new DateTimeImmutable($r->due_at);
            $returnedAt = $r->returned_at ? new DateTimeImmutable($r->returned_at) : null;
            $items[] = new LoanItem(
                $r->id,
                $r->book_id,
                $r->book_title,
                $r->isbn,
                $r->category_name,
                $dueAt,
                $returnedAt,
            );
        }

        return new Loan(
            $loanRow->id,
            $loanRow->member_id,
            $loanRow->loaned_by_user_id,
            new DateTimeImmutable($loanRow->loaned_at),
            $items,
        );
    }

    public function openItemsSummary(string $memberId, DateTimeImmutable $now): array
    {
        $rows = DB::table('library.loan_item as i')
            ->join('library.loan as l', 'l.id', '=', 'i.loan_id')
            ->where('l.member_id', $memberId)
            ->whereNull('i.returned_at')
            ->select('i.due_at')
            ->get();

        $openCount = count($rows);
        $overdueCount = 0;
        $nowStr = $now->format('Y-m-d H:i:sO');

        foreach ($rows as $r) {
            if ($r->due_at < $nowStr) {
                $overdueCount++;
            }
        }

        return [
            'open'    => $openCount,
            'overdue' => $overdueCount,
        ];
    }

    public function returnItem(string $loanId, string $itemId, string $bookId, DateTimeImmutable $returnedAt): void
    {
        $updated = DB::table('library.loan_item')
            ->where('id', $itemId)
            ->where('loan_id', $loanId)
            ->whereNull('returned_at')
            ->update([
                'returned_at' => $returnedAt->format('Y-m-d H:i:sO'),
            ]);

        if ($updated === 0) {
            throw new DomainError(
                DomainError::ITEM_ALREADY_RETURNED,
                'Esta línea ya fue devuelta.',
            );
        }

        // Reponer ejemplar en book
        DB::table('library.book')
            ->where('id', $bookId)
            ->increment('available_copies', 1);
    }
}

