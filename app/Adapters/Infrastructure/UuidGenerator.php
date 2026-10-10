<?php

namespace App\Adapters\Infrastructure;

use App\Application\Ports\IdGenerator;
use Illuminate\Support\Str;

final class UuidGenerator implements IdGenerator
{
    public function next(): string
    {
        return (string) Str::uuid();
    }
}

