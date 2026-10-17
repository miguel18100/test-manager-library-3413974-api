<?php

namespace Tests\Support;

use App\Application\Page;
use App\Application\Ports\BookRepository;
use App\Application\Ports\Clock;
use App\Application\Ports\IdGenerator;
use App\Application\Ports\LoanReader;
use App\Application\Ports\LoanRepository;
use App\Application\Ports\MemberRepository;
use App\Application\Ports\TransactionRunner;
use App\Domain\Book;
use App\Domain\Loan;
use App\Domain\Member;
use DateTimeImmutable;

/** Fakes en memoria de los puertos de salida: los casos de uso se prueban sin base de datos. */
final class FixedClock implements Clock
{
    public function __construct(public DateTimeImmutable $now) {}

    public function now(): DateTimeImmutable
    {
        return $this->now;
    }
}

final class SequentialIds implements IdGenerator
{
    private int $n = 0;

    public function next(): string
    {
        return 'id-'.(++$this->n);
    }
}

final class DirectTransaction implements TransactionRunner
{
    public function run(callable $work): mixed
    {
        return $work();
    }
}

final class InMemoryBooks implements BookRepository
{
    /** @var array<string, Book> */
    public array $books = [];

    public function getActive(string $id): ?Book { return $this->books[$id] ?? null; }
    public function getAny(string $id): ?Book { return $this->books[$id] ?? null; }

    public function getManyActive(array $ids): array
    {
        return array_intersect_key($this->books, array_flip($ids));
    }

    public function add(Book $book): void { $this->books[$book->id] = $book; }
    public function replace(Book $book): void {}
    public function markDeleted(Book $book): void {}
    public function setCover(string $bookId, ?string $coverKey): void {}
    public function hasOpenLoans(string $bookId): bool { return false; }
    public function search(?string $q, ?string $categoryId, int $page, int $size): Page { return new Page([], $page, $size, 0); }
}

final class InMemoryMembers implements MemberRepository
{
    /** @var array<string, Member> */
    public array $members = [];

    public function get(string $id): ?Member { return $this->members[$id] ?? null; }
    public function getForUpdate(string $id): ?Member { return $this->members[$id] ?? null; }
    public function add(Member $member): void { $this->members[$member->id] = $member; }
    public function search(?string $q, int $page, int $size): Page { return new Page([], $page, $size, 0); }
    public function activeLoansCount(string $memberId): int { return 0; }
}

final class InMemoryLoans implements LoanRepository
{
    /** @var array<string, Loan> */
    public array $loans = [];

    public function add(Loan $loan): void { $this->loans[$loan->id] = $loan; }
    public function get(string $id): ?Loan { return $this->loans[$id] ?? null; }

    public function openItemsSummary(string $memberId, DateTimeImmutable $now): array
    {
        $open = 0;
        $overdue = 0;
        foreach ($this->loans as $loan) {
            if ($loan->memberId !== $memberId) {
                continue;
            }
            foreach ($loan->items() as $item) {
                if (! $item->isReturned()) {
                    $open++;
                    $overdue += $item->isOverdue($now) ? 1 : 0;
                }
            }
        }

        return ['open' => $open, 'overdue' => $overdue];
    }

    public function returnItem(string $loanId, string $itemId, string $bookId, DateTimeImmutable $returnedAt): void {}
}

final class EmptyLoanReader implements LoanReader
{
    public function list(?DateTimeImmutable $from, ?DateTimeImmutable $toExclusive, ?string $status, ?string $memberId, DateTimeImmutable $now, int $page, int $size): Page
    {
        return new Page([], $page, $size, 0);
    }

    public function overdue(DateTimeImmutable $now, int $page, int $size): Page
    {
        return new Page([], $page, $size, 0);
    }
}
