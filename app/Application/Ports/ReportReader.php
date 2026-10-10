<?php

namespace App\Application\Ports;

use App\Application\Page;
use App\Domain\Book;
use App\Domain\Loan;
use App\Domain\Member;
use App\Domain\User;
use DateTimeImmutable;

/** Puerto de salida (artículo II): lo define la aplicación, lo implementa un adaptador. */
interface ReportReader
{
    /**
     * Q9 / ADR-004: una fila por book_id + book_title + category_name congelados.
     * @return list<array{book_id:string, book_title:string, category_name:string, times_borrowed:int}>
     */
    public function mostBorrowed(DateTimeImmutable $fromInclusive, DateTimeImmutable $toExclusive): array;
}
