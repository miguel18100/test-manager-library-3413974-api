<?php

namespace App\Application\Services;

use App\Application\Ports\ReportReader;
use App\Domain\DateRange;

/**
 * HU-10: Reporte de libros más prestados (admin).
 */
final class ReportService
{
    public function __construct(
        private readonly ReportReader $reports,
    ) {}

    public function mostBorrowed(string $fromStr, string $toStr): array
    {
        $range = DateRange::ofDates($fromStr, $toStr);

        $rows = $this->reports->mostBorrowed($range->startInclusive(), $range->endExclusive());

        return [
            'from' => $fromStr,
            'to'   => $toStr,
            'rows' => $rows,
        ];
    }
}

