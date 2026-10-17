<?php

namespace Tests\Unit;

use App\Application\Services\LoanService;
use App\Domain\Book;
use App\Domain\DomainError;
use App\Domain\Isbn;
use App\Domain\Member;
use App\Domain\Quantity;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use Tests\Support\DirectTransaction;
use Tests\Support\EmptyLoanReader;
use Tests\Support\FixedClock;
use Tests\Support\InMemoryBooks;
use Tests\Support\InMemoryLoans;
use Tests\Support\InMemoryMembers;
use Tests\Support\SequentialIds;

/** T-09 y T-10 — casos de uso de préstamo y devolución contra fakes (HU-06, HU-07). */
final class LoanServiceTest extends TestCase
{
    private InMemoryBooks $books;
    private InMemoryMembers $members;
    private InMemoryLoans $loans;
    private FixedClock $clock;
    private LoanService $service;

    protected function setUp(): void
    {
        $this->books = new InMemoryBooks();
        $this->members = new InMemoryMembers();
        $this->loans = new InMemoryLoans();
        $this->clock = new FixedClock(new DateTimeImmutable('2026-10-14T14:15:00Z', new DateTimeZone('UTC')));
        $this->service = new LoanService(
            $this->loans, new EmptyLoanReader(), $this->books, $this->members,
            new SequentialIds(), $this->clock, new DirectTransaction(),
        );
        $this->members->add(Member::create('m1', 'Ana Pérez', '1000'));
        for ($n = 1; $n <= 5; $n++) {
            $this->books->add(Book::create("b{$n}", "Libro {$n}", 'Autor', Isbn::of('978000000000'.$n), 'c1', 'Novela', Quantity::of(1)));
        }
    }

    private function assertCode(string $code, callable $work): void
    {
        try {
            $work();
            $this->fail("Debió lanzar {$code}.");
        } catch (DomainError $e) {
            $this->assertSame($code, $e->errorCode);
        }
    }

    public function test_registers_a_loan_with_due_date_and_open_status(): void
    {
        $result = $this->service->createLoan('m1', [['book_id' => 'b1'], ['book_id' => 'b2']], 'u1');

        $this->assertSame('open', $result['status']);
        $this->assertCount(2, $result['items']);
        $this->assertSame('2026-10-28T14:15:00Z', $result['items'][0]['due_at']);
        $this->assertSame(0, $this->books->books['b1']->availableCopies());
    }

    public function test_rejects_zero_or_more_than_three_lines(): void
    {
        $this->assertCode(DomainError::VALIDATION_ERROR, fn () => $this->service->createLoan('m1', [], 'u1'));
        $four = array_map(fn ($n) => ['book_id' => "b{$n}"], [1, 2, 3, 4]);
        $this->assertCode(DomainError::VALIDATION_ERROR, fn () => $this->service->createLoan('m1', $four, 'u1'));
    }

    public function test_rejects_a_repeated_book(): void
    {
        $this->assertCode(DomainError::DUPLICATE_BOOK_IN_LOAN, fn () => $this->service->createLoan('m1', [['book_id' => 'b1'], ['book_id' => 'b1']], 'u1'));
    }

    public function test_member_cannot_exceed_three_open_books(): void
    {
        $this->service->createLoan('m1', [['book_id' => 'b1'], ['book_id' => 'b2']], 'u1');

        $this->assertCode(DomainError::MEMBER_LIMIT_EXCEEDED, fn () => $this->service->createLoan('m1', [['book_id' => 'b3'], ['book_id' => 'b4']], 'u1'));
    }

    public function test_member_with_overdue_books_cannot_borrow(): void
    {
        $this->service->createLoan('m1', [['book_id' => 'b1']], 'u1');
        $this->clock->now = new DateTimeImmutable('2026-11-05T00:00:00Z', new DateTimeZone('UTC'));

        $this->assertCode(DomainError::MEMBER_HAS_OVERDUE, fn () => $this->service->createLoan('m1', [['book_id' => 'b2']], 'u1'));
    }

    public function test_no_copies_left_registers_nothing(): void
    {
        $this->books->books['b1']->withdraw();

        $this->assertCode(DomainError::BOOK_NOT_AVAILABLE, fn () => $this->service->createLoan('m1', [['book_id' => 'b1']], 'u1'));
        $this->assertSame([], $this->loans->loans);
    }

    public function test_unknown_member_is_not_found(): void
    {
        $this->assertCode(DomainError::NOT_FOUND, fn () => $this->service->createLoan('nadie', [['book_id' => 'b1']], 'u1'));
    }

    public function test_malformed_items_are_validation_errors_not_crashes(): void
    {
        $this->assertCode(DomainError::VALIDATION_ERROR, fn () => $this->service->createLoan('m1', ['b1'], 'u1'));
        $this->assertCode(DomainError::VALIDATION_ERROR, fn () => $this->service->createLoan('', [['book_id' => 'b1']], 'u1'));
    }

    public function test_returning_a_line_twice_is_item_already_returned(): void
    {
        $loan = $this->service->createLoan('m1', [['book_id' => 'b1']], 'u1');
        $itemId = $loan['items'][0]['id'];

        $returned = $this->service->returnItem($loan['id'], $itemId);
        $this->assertNotNull($returned['returned_at']);

        $this->assertCode(DomainError::ITEM_ALREADY_RETURNED, fn () => $this->service->returnItem($loan['id'], $itemId));
    }
}
