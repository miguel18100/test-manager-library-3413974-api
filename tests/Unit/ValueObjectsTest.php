<?php

namespace Tests\Unit;

use App\Domain\DateRange;
use App\Domain\DomainError;
use App\Domain\Isbn;
use App\Domain\Quantity;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** T-03 — objetos de valor: ISBN de 13 dígitos, cantidad > 0, fin >= inicio. */
final class ValueObjectsTest extends TestCase
{
    public function test_isbn_accepts_thirteen_digits(): void
    {
        $this->assertSame('9780307474728', Isbn::of(' 9780307474728 ')->value);
    }

    #[DataProvider('invalidIsbns')]
    public function test_isbn_rejects_anything_else(string $raw): void
    {
        $this->expectException(DomainError::class);
        Isbn::of($raw);
    }

    public static function invalidIsbns(): array
    {
        return [['123'], ['97803074747289'], ['978030747472X'], ['']];
    }

    public function test_quantity_must_be_at_least_one(): void
    {
        $this->assertSame(1, Quantity::of(1)->value);
        $this->expectException(DomainError::class);
        Quantity::of(0);
    }

    public function test_date_range_allows_equal_ends(): void
    {
        $range = DateRange::ofDates('2026-10-01', '2026-10-01');
        $this->assertSame('2026-10-02', $range->endExclusive()->format('Y-m-d'));
    }

    public function test_inverted_date_range_is_invalid_date_range(): void
    {
        try {
            DateRange::ofDates('2026-10-31', '2026-10-01');
            $this->fail('Debió lanzar INVALID_DATE_RANGE.');
        } catch (DomainError $e) {
            $this->assertSame(DomainError::INVALID_DATE_RANGE, $e->errorCode);
        }
    }
}
