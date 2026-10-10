<?php

namespace App\Application\Ports;

use App\Application\Page;
use App\Domain\Book;
use App\Domain\Loan;
use App\Domain\Member;
use App\Domain\User;
use DateTimeImmutable;

/** Puerto de salida (artículo II): lo define la aplicación, lo implementa un adaptador. */
interface LoanRepository
{
    /**
     * Guarda préstamo + líneas y descuenta ejemplares en la MISMA transacción.
     * Si otro préstamo se llevó el último ejemplar, lanza BOOK_NOT_AVAILABLE (RNF-2).
     */
    public function add(Loan $loan): void;

    public function get(string $id): ?Loan;

    /**
     * Q7: líneas sin devolver del socio.
     * @return array{open:int, overdue:int}
     */
    public function openItemsSummary(string $memberId, DateTimeImmutable $now): array;

    /** Marca la devolución y repone 1 ejemplar; si ya estaba devuelta lanza ITEM_ALREADY_RETURNED. */
    public function returnItem(string $loanId, string $itemId, string $bookId, DateTimeImmutable $returnedAt): void;
}
