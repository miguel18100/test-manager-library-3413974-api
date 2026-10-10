<?php

namespace App\Adapters\Persistence;

use App\Application\Page;
use App\Application\Ports\LoanReader;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

final class PostgresLoanReader implements LoanReader
{
    public function list(?DateTimeImmutable $from, ?DateTimeImmutable $toExclusive, ?string $status, ?string $memberId, DateTimeImmutable $now, int $page, int $size): Page
    {
        $query = DB::table('library.loan as l');

        if ($from) {
            $query->where('l.loaned_at', '>=', $from->format('Y-m-d H:i:sO'));
        }
        if ($toExclusive) {
            $query->where('l.loaned_at', '<', $toExclusive->format('Y-m-d H:i:sO'));
        }
        if ($memberId) {
            $query->where('l.member_id', $memberId);
        }

        $total = $query->count();
        $offset = ($page - 1) * $size;

        $loans = $query->orderBy('l.loaned_at', 'desc')
            ->offset($offset)
            ->limit($size)
            ->get();

        $loanIds = $loans->pluck('id')->all();
        $items = DB::table('library.loan_item')
            ->whereIn('loan_id', $loanIds)
            ->get()
            ->groupBy('loan_id');

        $nowStr = $now->format('Y-m-d H:i:sO');

        $result = [];
        foreach ($loans as $l) {
            $loanItems = $items->get($l->id, collect());
            $hasUnreturned = false;
            $hasOverdue = false;

            $itemsFormatted = [];
            foreach ($loanItems as $item) {
                if ($item->returned_at === null) {
                    $hasUnreturned = true;
                    if ($item->due_at < $nowStr) {
                        $hasOverdue = true;
                    }
                }
                $itemsFormatted[] = [
                    'id'            => $item->id,
                    'book_id'       => $item->book_id,
                    'book_title'    => $item->book_title,
                    'isbn'          => $item->isbn,
                    'category_name' => $item->category_name,
                    'due_at'        => (new DateTimeImmutable($item->due_at))->format('Y-m-d\TH:i:s\Z'),
                    'returned_at'   => $item->returned_at ? (new DateTimeImmutable($item->returned_at))->format('Y-m-d\TH:i:s\Z') : null,
                ];
            }

            $computedStatus = 'closed';
            if ($hasUnreturned) {
                $computedStatus = $hasOverdue ? 'overdue' : 'open';
            }

            if ($status && $status !== $computedStatus) {
                continue;
            }

            $result[] = [
                'id'        => $l->id,
                'member_id' => $l->member_id,
                'loaned_at' => (new DateTimeImmutable($l->loaned_at))->format('Y-m-d\TH:i:s\Z'),
                'status'    => $computedStatus,
                'items'     => $itemsFormatted,
            ];
        }

        return new Page($result, $page, $size, $total);
    }

    public function overdue(DateTimeImmutable $now, int $page, int $size): Page
    {
        $nowStr = $now->format('Y-m-d H:i:sO');

        $query = DB::table('library.loan_item as i')
            ->join('library.loan as l', 'l.id', '=', 'i.loan_id')
            ->join('library.member as m', 'm.id', '=', 'l.member_id')
            ->whereNull('i.returned_at')
            ->where('i.due_at', '<', $nowStr);

        $total = $query->count();
        $offset = ($page - 1) * $size;

        $rows = $query->select(
            'l.id as loan_id',
            'i.id as item_id',
            'i.book_title',
            'm.full_name as member_name',
            'i.due_at',
            DB::raw("EXTRACT(DAY FROM ('{$nowStr}'::timestamptz - i.due_at))::int as days_late")
        )
            ->orderBy('i.due_at', 'asc') // más días de retraso primero
            ->offset($offset)
            ->limit($size)
            ->get();

        $items = $rows->map(function ($r) {
            return [
                'loan_id'     => $r->loan_id,
                'item_id'     => $r->item_id,
                'book_title'  => $r->book_title,
                'member_name' => $r->member_name,
                'due_at'      => (new DateTimeImmutable($r->due_at))->format('Y-m-d\TH:i:s\Z'),
                'days_late'   => (int) $r->days_late,
            ];
        })->all();

        return new Page($items, $page, $size, $total);
    }
}

