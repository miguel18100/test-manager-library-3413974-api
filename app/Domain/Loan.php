<?php

namespace App\Domain;

use DateTimeImmutable;

/**
 * Agregado Loan (architecture.md §3).
 * 1 a 3 líneas; sin libro repetido; vencimiento a 14 días; inmutable salvo devolución.
 * El estado se CALCULA (artículo VII): open | overdue | closed.
 */
final class Loan
{
    public const MAX_ITEMS = 3;
    public const LOAN_DAYS = 14;
    /** RN-2: máximo de libros sin devolver por socio. */
    public const MAX_OPEN_PER_MEMBER = 3;

    /** @param list<LoanItem> $items */
    public function __construct(
        public readonly string $id,
        public readonly string $memberId,
        public readonly string $loanedByUserId,
        public readonly DateTimeImmutable $loanedAt,
        private array $items = [],
    ) {}

    public static function open(string $id, string $memberId, string $userId, DateTimeImmutable $now): self
    {
        return new self($id, $memberId, $userId, $now);
    }

    /**
     * Loan.AddItem -> Book.Withdraw: descontar el ejemplar y crear la línea son UNA operación (CA-06.3, CA-06.6).
     */
    public function addItem(string $itemId, Book $book): LoanItem
    {
        foreach ($this->items as $item) {
            if ($item->bookId === $book->id) {
                throw new DomainError(
                    DomainError::DUPLICATE_BOOK_IN_LOAN,
                    'No se puede repetir el mismo libro en un préstamo.',
                    [['field' => 'items', 'issue' => 'libro repetido']],
                );
            }
        }
        if (count($this->items) >= self::MAX_ITEMS) {
            throw DomainError::validation('Un préstamo admite de 1 a 3 libros.', 'items', 'máximo 3');
        }

        $book->withdraw();

        $item = new LoanItem(
            $itemId, $book->id, $book->title(), $book->isbn->value, $book->categoryName(),
            $this->loanedAt->modify('+'.self::LOAN_DAYS.' days'),
        );
        $this->items[] = $item;

        return $item;
    }

    /** Loan.ReturnItem -> Book.Restock una sola vez por línea (CA-07.1, CA-07.2). */
    public function returnItem(string $itemId, Book $book, DateTimeImmutable $now): LoanItem
    {
        $item = $this->item($itemId);
        $item->markReturned($now);
        $book->restock();

        return $item;
    }

    public function item(string $itemId): LoanItem
    {
        foreach ($this->items as $item) {
            if ($item->id === $itemId) {
                return $item;
            }
        }
        throw DomainError::notFound('La línea de préstamo no existe.');
    }

    /** @return list<LoanItem> */
    public function items(): array
    {
        return $this->items;
    }

    public function assertComplete(): void
    {
        $n = count($this->items);
        if ($n < 1 || $n > self::MAX_ITEMS) {
            throw DomainError::validation('Un préstamo admite de 1 a 3 libros.', 'items', 'entre 1 y 3');
        }
    }

    public function status(DateTimeImmutable $now): string
    {
        $pending = array_filter($this->items, fn (LoanItem $i) => ! $i->isReturned());
        if ($pending === []) {
            return 'closed';
        }
        foreach ($pending as $i) {
            if ($i->isOverdue($now)) {
                return 'overdue';
            }
        }

        return 'open';
    }
}

