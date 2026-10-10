<?php

namespace App\Domain;

use DateTimeImmutable;

/** Línea de préstamo: congela título, ISBN y categoría en el instante del préstamo (RN-4). */
final class LoanItem
{
    public function __construct(
        public readonly string $id,
        public readonly string $bookId,
        public readonly string $bookTitle,
        public readonly string $isbn,
        public readonly string $categoryName,
        public readonly DateTimeImmutable $dueAt,
        private ?DateTimeImmutable $returnedAt = null,
    ) {}

    public function returnedAt(): ?DateTimeImmutable { return $this->returnedAt; }
    public function isReturned(): bool { return $this->returnedAt !== null; }
    public function isOverdue(DateTimeImmutable $now): bool { return ! $this->isReturned() && $this->dueAt < $now; }

    /** CA-07.2: devolver una línea ya devuelta es 409 y nunca suma dos veces. */
    public function markReturned(DateTimeImmutable $now): void
    {
        if ($this->isReturned()) {
            throw new DomainError(DomainError::ITEM_ALREADY_RETURNED, 'Esta línea ya fue devuelta.');
        }
        $this->returnedAt = $now;
    }
}

