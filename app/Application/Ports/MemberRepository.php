<?php

namespace App\Application\Ports;

use App\Application\Page;
use App\Domain\Book;
use App\Domain\Loan;
use App\Domain\Member;
use App\Domain\User;
use DateTimeImmutable;

/** Puerto de salida (artículo II): lo define la aplicación, lo implementa un adaptador. */
interface MemberRepository
{
    public function get(string $id): ?Member;

    /** Bloquea la fila del socio dentro de la transacción para serializar sus préstamos. */
    public function getForUpdate(string $id): ?Member;

    /** Lanza DOCUMENT_TAKEN si el motor rechaza el duplicado. */
    public function add(Member $member): void;

    public function search(?string $q, int $page, int $size): Page;

    /** Conteo de líneas sin devolver del socio. */
    public function activeLoansCount(string $memberId): int;
}
