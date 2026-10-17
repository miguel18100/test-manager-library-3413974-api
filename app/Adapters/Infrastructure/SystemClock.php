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
        // La hora simulada solo existe para el sembrador de demostración y está apagada por defecto:
        // si cualquier cliente pudiera fijar la hora, eludiría los vencimientos (RN-2, RN-3).
        $header = config('library.allow_simulated_time') ? Request::header('X-Simulated-Time') : null;
        if ($header) {
            $parsed = DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s\Z', $header, new DateTimeZone('UTC'));
            if ($parsed !== false) {
                return $parsed;
            }
        }

        return new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }
}

