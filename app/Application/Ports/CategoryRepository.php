<?php

namespace App\Application\Ports;

use App\Application\Page;
use App\Domain\Book;
use App\Domain\Loan;
use App\Domain\Member;
use App\Domain\User;
use DateTimeImmutable;

/** Puerto de salida (artículo II): lo define la aplicación, lo implementa un adaptador. */
interface CategoryRepository
{
    /** @return array{id:string, name:string}|null */
    public function find(string $id): ?array;

    /** @return list<array{id:string, name:string}> orden por nombre */
    public function all(): array;
}
