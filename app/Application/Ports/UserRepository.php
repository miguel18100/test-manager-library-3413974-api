<?php

namespace App\Application\Ports;

use App\Application\Page;
use App\Domain\Book;
use App\Domain\Loan;
use App\Domain\Member;
use App\Domain\User;
use DateTimeImmutable;

/** Puerto de salida (artículo II): lo define la aplicación, lo implementa un adaptador. */
interface UserRepository
{
    public function findByUsername(string $normalizedUsername): ?User;

    /** Lanza USERNAME_TAKEN si el motor rechaza el duplicado. */
    public function add(User $user): void;
}
