<?php

namespace Tests\Unit;

use App\Domain\Book;
use App\Domain\DomainError;
use App\Domain\Isbn;
use App\Domain\Quantity;
use PHPUnit\Framework\TestCase;

/** T-04 — Book: withdraw, restock y total editable (RN-1, CA-03.3). */
final class BookTest extends TestCase
{
    private function book(int $total = 2): Book
    {
        return Book::create('b1', 'Cien años de soledad', 'García Márquez', Isbn::of('9780307474728'), 'c1', 'Novela', Quantity::of($total));
    }

    public function test_available_starts_equal_to_total(): void
    {
        $this->assertSame(3, $this->book(3)->availableCopies());
    }

    public function test_withdraw_never_goes_below_zero(): void
    {
        $book = $this->book(1);
        $book->withdraw();
        $this->assertSame(0, $book->availableCopies());

        try {
            $book->withdraw();
            $this->fail('Debió lanzar BOOK_NOT_AVAILABLE.');
        } catch (DomainError $e) {
            $this->assertSame(DomainError::BOOK_NOT_AVAILABLE, $e->errorCode);
        }
        $this->assertSame(0, $book->availableCopies());
    }

    public function test_restock_never_exceeds_total(): void
    {
        $book = $this->book(1);
        $this->expectException(\LogicException::class);
        $book->restock();
    }

    public function test_changing_total_shifts_available_by_the_same_difference(): void
    {
        $book = $this->book(3);
        $book->withdraw();
        $book->replace('Título', 'Autor', 'c1', 'Novela', Quantity::of(5));
        $this->assertSame(4, $book->availableCopies());
    }

    public function test_total_cannot_leave_available_negative(): void
    {
        $book = $this->book(3);
        $book->withdraw();
        $book->withdraw();
        $this->expectException(DomainError::class);
        $book->replace('Título', 'Autor', 'c1', 'Novela', Quantity::of(1));
    }

    public function test_cover_key_is_null_or_non_empty(): void
    {
        $book = $this->book();
        $this->assertNull($book->coverKey());
        $this->expectException(\LogicException::class);
        $book->setCoverKey('');
    }
}
