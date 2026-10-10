<?php

namespace App\Domain;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Objeto de valor: rango de fechas, ambos extremos inclusive (CA-10.2, api-contract §3.7).
 * Fin anterior al inicio => INVALID_DATE_RANGE.
 */
final class DateRange
{
    private function __construct(
        public readonly DateTimeImmutable $from,
        public readonly DateTimeImmutable $to,
    ) {}

    public static function ofDates(string $from, string $to): self
    {
        $utc = new DateTimeZone('UTC');
        $f = DateTimeImmutable::createFromFormat('!Y-m-d', $from, $utc);
        $t = DateTimeImmutable::createFromFormat('!Y-m-d', $to, $utc);
        if ($f === false || $f->format('Y-m-d') !== $from) {
            throw DomainError::validation('Fecha inicial inválida (formato AAAA-MM-DD).', 'from', 'formato AAAA-MM-DD');
        }
        if ($t === false || $t->format('Y-m-d') !== $to) {
            throw DomainError::validation('Fecha final inválida (formato AAAA-MM-DD).', 'to', 'formato AAAA-MM-DD');
        }
        if ($t < $f) {
            throw new DomainError(
                DomainError::INVALID_DATE_RANGE,
                'La fecha final no puede ser anterior a la inicial.',
                [['field' => 'to', 'issue' => 'anterior a from']],
            );
        }

        return new self($f, $t);
    }

    /** Inicio del día `from` (inclusive). */
    public function startInclusive(): DateTimeImmutable
    {
        return $this->from;
    }

    /** Inicio del día siguiente a `to` (exclusivo): así `to` queda incluido completo. */
    public function endExclusive(): DateTimeImmutable
    {
        return $this->to->modify('+1 day');
    }
}

