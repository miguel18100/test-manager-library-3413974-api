<?php

namespace App\Application\Ports;

use App\Application\Page;
use App\Domain\Book;
use App\Domain\Loan;
use App\Domain\Member;
use App\Domain\User;
use DateTimeImmutable;

/** Puerto de salida (artículo II): lo define la aplicación, lo implementa un adaptador. */
interface BookRepository
{
    /** Solo activos (deleted_at IS NULL). */
    public function getActive(string $id): ?Book;

    /** Incluye dados de baja (para devoluciones de libros históricos). */
    public function getAny(string $id): ?Book;

    /**
     * Q3: libros activos por lote.
     * @param list<string> $ids
     * @return array<string, Book>
     */
    public function getManyActive(array $ids): array;

    /** Lanza ISBN_TAKEN si el índice parcial rechaza el duplicado. */
    public function add(Book $book): void;

    /** Reemplaza datos editables; ajusta disponibles en la misma diferencia, de forma atómica. */
    public function replace(Book $book): void;

    public function markDeleted(Book $book): void;

    public function setCover(string $bookId, ?string $coverKey): void;

    public function hasOpenLoans(string $bookId): bool;

    /** Q1: texto parcial en título/autor, categoría, solo activos, orden por título. */
    public function search(?string $q, ?string $categoryId, int $page, int $size): Page;
}
