<?php

namespace App\Application\Services;

use App\Application\Page;
use App\Application\Ports\BookRepository;
use App\Application\Ports\Clock;
use App\Application\Ports\IdGenerator;
use App\Application\Ports\LoanReader;
use App\Application\Ports\LoanRepository;
use App\Application\Ports\MemberRepository;
use App\Application\Ports\TransactionRunner;
use App\Domain\DomainError;
use App\Domain\Loan;
use App\Domain\LoanItem;
use DateTimeImmutable;

/**
 * HU-06, HU-07, HU-08, HU-09: Préstamos y Devoluciones.
 */
final class LoanService
{
    public function __construct(
        private readonly LoanRepository $loans,
        private readonly LoanReader $loanReader,
        private readonly BookRepository $books,
        private readonly MemberRepository $members,
        private readonly IdGenerator $ids,
        private readonly Clock $clock,
        private readonly TransactionRunner $tx,
    ) {}

    /**
     * @param list<array{book_id:string}> $items
     */
    public function createLoan(string $memberId, array $items, string $authenticatedUserId): array
    {
        if (count($items) < 1 || count($items) > 3) {
            throw DomainError::validation('Un préstamo debe tener entre 1 y 3 libros.', 'items', 'de 1 a 3 elementos');
        }

        $bookIds = array_map(fn ($i) => $i['book_id'] ?? '', $items);
        if (count($bookIds) !== count(array_unique($bookIds))) {
            throw new DomainError(
                DomainError::DUPLICATE_BOOK_IN_LOAN,
                'No se puede prestar el mismo libro dos veces en la misma solicitud.',
            );
        }

        return $this->tx->run(function () use ($memberId, $bookIds, $authenticatedUserId) {
            $now = $this->clock->now();

            $member = $this->members->getForUpdate($memberId);
            if ($member === null) {
                throw DomainError::notFound('El socio no existe.');
            }

            $summary = $this->loans->openItemsSummary($memberId, $now);

            if ($summary['overdue'] > 0) {
                throw new DomainError(
                    DomainError::MEMBER_HAS_OVERDUE,
                    'El socio tiene libros vencidos pendientes de devolución.',
                );
            }

            if (($summary['open'] + count($bookIds)) > Loan::MAX_OPEN_PER_MEMBER) {
                throw new DomainError(
                    DomainError::MEMBER_LIMIT_EXCEEDED,
                    "El socio no puede tener más de 3 libros sin devolver en total (actualmente tiene {$summary['open']}).",
                );
            }

            $booksMap = $this->books->getManyActive($bookIds);
            foreach ($bookIds as $bid) {
                if (! isset($booksMap[$bid])) {
                    throw DomainError::notFound("El libro {$bid} no existe o no está activo.");
                }
            }

            $loan = Loan::open($this->ids->next(), $memberId, $authenticatedUserId, $now);

            foreach ($bookIds as $bid) {
                $book = $booksMap[$bid];
                $loan->addItem($this->ids->next(), $book);
            }

            $this->loans->add($loan);

            return $this->presentLoan($loan, $now);
        });
    }

    public function returnItem(string $loanId, string $itemId): array
    {
        return $this->tx->run(function () use ($loanId, $itemId) {
            $now = $this->clock->now();

            $loan = $this->loans->get($loanId);
            if ($loan === null) {
                throw DomainError::notFound('El préstamo no existe.');
            }

            $item = $loan->item($itemId);
            if ($item->isReturned()) {
                throw new DomainError(
                    DomainError::ITEM_ALREADY_RETURNED,
                    'Esta línea ya fue devuelta.',
                );
            }

            $book = $this->books->getAny($item->bookId);
            if ($book === null) {
                throw DomainError::notFound('El libro asociado a la línea no existe.');
            }

            $returnedItem = $loan->returnItem($itemId, $book, $now);

            $this->loans->returnItem($loanId, $itemId, $item->bookId, $now);

            return [
                'id'            => $returnedItem->id,
                'book_id'       => $returnedItem->bookId,
                'book_title'    => $returnedItem->bookTitle,
                'isbn'          => $returnedItem->isbn,
                'category_name' => $returnedItem->categoryName,
                'due_at'        => $returnedItem->dueAt->format('Y-m-d\TH:i:s\Z'),
                'returned_at'   => $returnedItem->returnedAt()->format('Y-m-d\TH:i:s\Z'),
            ];
        });
    }

    public function getLoan(string $id): array
    {
        $now = $this->clock->now();
        $loan = $this->loans->get($id);
        if ($loan === null) {
            throw DomainError::notFound('El préstamo no existe.');
        }

        return $this->presentLoan($loan, $now);
    }

    public function listLoans(?string $fromStr, ?string $toStr, ?string $status, ?string $memberId, int $page, int $size): Page
    {
        $now = $this->clock->now();
        $from = null;
        $toExclusive = null;

        if ($fromStr) {
            $from = new DateTimeImmutable($fromStr);
        }
        if ($toStr) {
            $toExclusive = (new DateTimeImmutable($toStr))->modify('+1 day');
        }

        $page = max(1, $page);
        $size = min(50, max(1, $size));

        return $this->loanReader->list($from, $toExclusive, $status, $memberId, $now, $page, $size);
    }

    public function overdue(int $page, int $size): Page
    {
        $now = $this->clock->now();
        $page = max(1, $page);
        $size = min(50, max(1, $size));

        return $this->loanReader->overdue($now, $page, $size);
    }

    private function presentLoan(Loan $loan, DateTimeImmutable $now): array
    {
        return [
            'id'        => $loan->id,
            'member_id' => $loan->memberId,
            'loaned_at' => $loan->loanedAt->format('Y-m-d\TH:i:s\Z'),
            'status'    => $loan->status($now),
            'items'     => array_map(function (LoanItem $i) {
                return [
                    'id'            => $i->id,
                    'book_id'       => $i->bookId,
                    'book_title'    => $i->bookTitle,
                    'isbn'          => $i->isbn,
                    'category_name' => $i->categoryName,
                    'due_at'        => $i->dueAt->format('Y-m-d\TH:i:s\Z'),
                    'returned_at'   => $i->returnedAt() ? $i->returnedAt()->format('Y-m-d\TH:i:s\Z') : null,
                ];
            }, $loan->items()),
        ];
    }
}

