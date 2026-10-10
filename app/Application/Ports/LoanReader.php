<?php

namespace App\Application\Ports;

use App\Application\Page;
use App\Domain\Book;
use App\Domain\Loan;
use App\Domain\Member;
use App\Domain\User;
use DateTimeImmutable;

/** Puerto de salida (artículo II): lo define la aplicación, lo implementa un adaptador. */
interface LoanReader
{
    /** Q6: préstamos por rango, estado y socio, orden por fecha descendente. */
    public function list(?DateTimeImmutable $from, ?DateTimeImmutable $toExclusive, ?string $status, ?string $memberId, DateTimeImmutable $now, int $page, int $size): Page;

    /** Q8: líneas vencidas, más días de retraso primero. */
    public function overdue(DateTimeImmutable $now, int $page, int $size): Page;
}
