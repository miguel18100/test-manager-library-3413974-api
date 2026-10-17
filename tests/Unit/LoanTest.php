<?php

namespace Tests\Unit;

use App\Domain\Book;
use App\Domain\DomainError;
use App\Domain\Isbn;
use App\Domain\Loan;
use App\Domain\Quantity;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;

/** T-05 — Loan: 14 días, máximo 3 líneas, estado calculado con reloj fijo. */
final class LoanTest extends TestCase
{
    private function at(string $iso): DateTimeImmutable
    {
        return new DateTimeImmutable($iso, new DateTimeZone('UTC'));
    }

    private function book(string $id, string $isbn, int $total = 2): Book
    {
        return Book::create($id, "Libro {$id}", 'Autor', Isbn::of($isbn), 'c1', 'Novela', Quantity::of($total));
    }

    public function test_due_date_is_loaned_at_plus_fourteen_days(): void
    {
        $loan = Loan::open('l1', 'm1', 'u1', $this->at('2026-10-14T14:15:00Z'));
        $item = $loan->addItem('i1', $this->book('b1', '9780000000001'));
        $this->assertSame('2026-10-28T14:15:00Z', $item->dueAt->format('Y-m-d\TH:i:s\Z'));
    }

    public function test_line_freezes_title_isbn_and_category(): void
    {
        $book = $this->book('b1', '9780000000001');
        $loan = Loan::open('l1', 'm1', 'u1', $this->at('2026-10-14T14:15:00Z'));
        $item = $loan->addItem('i1', $book);
        $book->replace('Otro título', 'Autor', 'c2', 'Ciencia', Quantity::of(2));

        $this->assertSame('Libro b1', $item->bookTitle);
        $this->assertSame('9780000000001', $item->isbn);
        $this->assertSame('Novela', $item->categoryName);
    }

    public function test_adding_a_line_withdraws_one_copy(): void
    {
        $book = $this->book('b1', '9780000000001', 2);
        Loan::open('l1', 'm1', 'u1', $this->at('2026-10-14T14:15:00Z'))->addItem('i1', $book);
        $this->assertSame(1, $book->availableCopies());
    }

    public function test_same_book_twice_is_rejected(): void
    {
        $loan = Loan::open('l1', 'm1', 'u1', $this->at('2026-10-14T14:15:00Z'));
        $book = $this->book('b1', '9780000000001');
        $loan->addItem('i1', $book);

        try {
            $loan->addItem('i2', $book);
            $this->fail('Debió lanzar DUPLICATE_BOOK_IN_LOAN.');
        } catch (DomainError $e) {
            $this->assertSame(DomainError::DUPLICATE_BOOK_IN_LOAN, $e->errorCode);
        }
    }

    public function test_a_fourth_line_is_rejected(): void
    {
        $loan = Loan::open('l1', 'm1', 'u1', $this->at('2026-10-14T14:15:00Z'));
        foreach ([1, 2, 3] as $n) {
            $loan->addItem("i{$n}", $this->book("b{$n}", '978000000000'.$n));
        }
        $this->expectException(DomainError::class);
        $loan->addItem('i4', $this->book('b4', '9780000000004'));
    }

    public function test_status_is_calculated_open_overdue_closed(): void
    {
        $loan = Loan::open('l1', 'm1', 'u1', $this->at('2026-10-14T14:15:00Z'));
        $first = $this->book('b1', '9780000000001');
        $loan->addItem('i1', $first);
        $second = $this->book('b2', '9780000000002');
        $loan->addItem('i2', $second);

        $this->assertSame('open', $loan->status($this->at('2026-10-20T00:00:00Z')));
        $this->assertSame('overdue', $loan->status($this->at('2026-10-29T00:00:00Z')));

        $loan->returnItem('i1', $first, $this->at('2026-10-30T00:00:00Z'));
        $this->assertSame('overdue', $loan->status($this->at('2026-10-31T00:00:00Z')));

        $loan->returnItem('i2', $second, $this->at('2026-10-30T00:00:00Z'));
        $this->assertSame('closed', $loan->status($this->at('2026-11-30T00:00:00Z')));
    }

    public function test_returning_a_line_twice_is_item_already_returned(): void
    {
        $book = $this->book('b1', '9780000000001');
        $loan = Loan::open('l1', 'm1', 'u1', $this->at('2026-10-14T14:15:00Z'));
        $loan->addItem('i1', $book);
        $loan->returnItem('i1', $book, $this->at('2026-10-15T00:00:00Z'));

        try {
            $loan->returnItem('i1', $book, $this->at('2026-10-16T00:00:00Z'));
            $this->fail('Debió lanzar ITEM_ALREADY_RETURNED.');
        } catch (DomainError $e) {
            $this->assertSame(DomainError::ITEM_ALREADY_RETURNED, $e->errorCode);
        }
        $this->assertSame(2, $book->availableCopies(), 'No debe sumar dos veces.');
    }
}
