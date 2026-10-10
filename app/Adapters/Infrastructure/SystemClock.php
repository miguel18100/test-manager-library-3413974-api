<?php

namespace App\Adapters\Infrastructure;

use App\Application\Ports\Clock;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\Request;

final class SystemClock implements Clock
{
    public function now(): DateTimeImmutable
    {
        $header = Request::header('X-Simulated-Time');
        if ($header) {
            $parsed = DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s\Z', $header, new DateTimeZone('UTC'));
            if ($parsed !== false) {
                return $parsed;
            }
        }

        return new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }
}

